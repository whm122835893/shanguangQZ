<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Msg;
use app\model\Affiche;
use app\model\AfficheType;
use app\model\Banner;
use app\model\Batch;
use app\model\BlindBox;
use app\model\Collection;
use app\model\Series;
use app\service\Formatter;
use app\model\Publisher;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;

/**
 * 内容域控制器（v2.3 从 Core 拆分）
 *
 * 公告、Banner、系列、盲盒、藏品目录、合成、抽奖、活动、水晶
 *
 * 🔴 数据来源分两类，勿混淆：
 *   【DB  】已按原站实测响应建表并灌入真实数据
 *   【MOCK】原站需登录或已下线的端点，暂用内存假数据
 *
 * 🔐 鉴权统一走 HasAuth::currentUser()，禁止只判断 token 非空。
 *
 * ⚠️ 改动本文件后必须跑契约回归：php tools/contract/run.php
 *    退出码非 0 表示与原站实测契约不一致，禁止提交。
 *
 * @package app\controller\api
 */
class ContentController
{
    use HasAuth;

    public function afficheList()
    {
        ['page' => $p, 'size' => $s] = Request::page();

        // 分类过滤：category 对应 affiche.type（实测 1寄售/2活动/3合成/4运营/5空投）
        $category = Request::int('category', 0);
        $type     = Request::str('type');

        $query = Affiche::where('is_show', 1);
        if ($category > 0) {
            $query->where('type', (string) $category);
        } elseif ($type !== '') {
            $query->where('type', $type);
        }

        $total = (clone $query)->count();
        $rows  = $query->order('diysort', 'desc')
            ->order('createtime', 'desc')
            ->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        return Response::page(
            Formatter::afficheList($rows),
            $total,
            $s,
            $p
        );
    }

    /**
     * POST /index/affichetypelist 公告分类【DB】
     *
     * ⚠️ 原站 data 是裸数组 [{id,name}]，不是分页信封 —— 已实测确认。
     */

    public function afficheTypeList()
    {
        $rows = AfficheType::order('id', 'asc')->select()->toArray();
        return Response::ok(array_map(static fn($r) => [
            'id'   => (int) $r['id'],
            'name' => (string) $r['name'],
        ], $rows));
    }

    /**
     * GET /index/getSeries 系列 Tab【DB，v2.7 切】
     *
     * 前端契约（Market.vue onMounted）：res.data 是数组，行用 series_id / name /
     * is_def（默认选中项）渲染，switchSeries 后把 series_id 传给 getMarketList。
     * 原站首个 Tab 是「全部区」（is_def=1，series_id=0 语义=不过滤）——
     * 本地 series 表是真实系列全集，全部区由前端 series_id=0 表达，
     * 因此这里把 sort 最小的系列标 is_def=1 作默认选中。
     * type 参数（10/20）在原站区分两个分类域；本地表无此维度，暂不过滤。
     */
    public function getSeries()
    {
        Request::int('type', 10);

        $rows = Series::where('is_show', 1)
            ->order('sort', 'asc')->order('id', 'asc')
            ->select()->toArray();

        $list = array_map(static function ($r, $i) {
            return [
                'series_id' => (int) $r['id'],
                'name'      => (string) $r['name'],
                'is_def'    => $i === 0 ? 1 : 0,
            ];
        }, $rows, array_keys($rows));

        return Response::ok($list);
    }

    /**
     * GET /banner/show/getBanner 首页轮播【DB】
     *
     * 实测信封：{total,per_page:15,current_page,last_page,data[]}，2 条记录 × 5 字段。
     *
     * 🔴 原站此接口**不接分页参数**（实测 per_page 恒为 15，current_page 恒为 1），
     *    但仍按分页信封返回，所以这里固定 15 / page 1，不透传 Request::page()。
     *
     * 🔴 只出 is_show=1 的记录；排序 sort desc, id desc（实测 id 2 在前 1 在后）。
     */

    public function getBanner()
    {
        $rows = Banner::where('is_show', 1)
            ->order('sort', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        // 实测全量返回不分页，这里只做安全截断（防运营误灌上万条拖垮首屏）
        $rows = array_slice($rows, 0, 15);

        return Response::page(
            Formatter::bannerList($rows),
            count($rows),
            15,
            1,
            Msg::BANNER_LIST
        );
    }

    /**
     * GET /index/afficheDetail/ids/{id} 公告详情【DB】
     *
     * 原站详情比列表多下发 content（HTML）与 publish_time。
     * 库中 content 为空的记录返回站点级通用公告文案，避免前端详情页空白。
     */

    public function afficheDetail($id)
    {
        $row = Affiche::where('id', (int) $id)
            ->where('is_show', 1)
            ->find();

        if (!$row) {
            return Response::fail('公告不存在或已下架');
        }

        $a = Formatter::affiche($row);
        if (($a['content'] ?? '') === '') {
            $a['content'] = '<p>为保障交易安全，平台将陆续上线藏品溯源功能。</p>';
        }
        $a['publish_time'] = (int) $a['publish_time'];

        return Response::ok($a);
    }

    // ==================== 藏品 / 盲盒 ====================

    /**
     * GET /box/blind_box/list 发售日历【DB】
     *
     * 原站实测契约：data 是「裸数组」而非分页信封，列表项 22 顶层字段
     * + 2 个嵌套对象（batch 4 字段 / publisher 2 字段）。
     *⚠️ 原站确实支持分页入参（实测 page/list_rows 生效），但返回仍是裸数组，
     *    故此处沿用裸数组语义，仅按入参切片。
     */

    public function blindBoxList()
    {
        ['page' => $p, 'size' => $s] = Request::page();

        $query = BlindBox::where('is_show', 1);

        // 可选过滤：type（实测 "collection"）、goods_type（实测 "2"）
        $type = Request::str('type');
        if ($type !== '') {
            $query->where('type', $type);
        }
        $goodsType = Request::str('goods_type');
        if ($goodsType !== '') {
            $query->where('goods_type', $goodsType);
        }

        $rows = $query->order('diysort', 'desc')
            ->order('release_time', 'desc')
            ->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        // 批量取关联的batch / publisher，避免 N+1 查询
        $batchIds = array_values(array_unique(array_filter(
            array_column($rows, 'batch_ref'))));
        $pubIds   = array_values(array_unique(array_filter(
            array_column($rows, 'publisher_ref'))));

        // 注意：column() 第一个参数必须是字段数组，不能传 null
        $batchMap = $batchIds
            ? \app\model\Batch::whereIn('id', $batchIds)
                ->column('*', 'id')
            : [];
        $pubMap = $pubIds
            ? \app\model\Publisher::whereIn('id', $pubIds)
                ->column('*', 'id')
            : [];

        $list = array_map(static fn($r) => Formatter::blindBox(
            $r,
            $batchMap[$r['batch_ref']] ?? null,
            $pubMap[$r['publisher_ref']] ?? null
        ), $rows);

        // 🔴 裸数组，不是 Response::page()
        return Response::ok($list);
    }

    /**
     * 藏品系列列表
     * 🔴 注意：原站前端误写为 /box/collection/CollectionList（404 死路由），
     *    正确端点是下划线的 collection_list。本 Mock 只提供正确版本，
     *    并保留旧名做重定向提示，避免前端再次踩坑。
     */

    /**
     * POST /box/collection/collection_list 藏品列表【DB，v2.7 切】
     * 数据源 collection 表（is_show=1），行对齐 MockData::collection 的 10 字段契约，
     * 附 series_name（前端列表页展示系列归属）。
     */
    public function collectionList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $query = Collection::alias('c')
            ->leftJoin('series s', 's.id = c.series_id')
            ->where('c.is_show', 1);
        $total = (int) (clone $query)->count();
        $rows  = $query->field('c.*, IFNULL(s.name, "") AS series_name')
            ->order('c.id', 'asc')->page($p, $s)->select()->toArray();

        $list = array_map(static fn($r) => [
            'id'                     => (int) $r['id'],
            'name'                   => (string) $r['name'],
            'main_image'             => (string) $r['main_image'],
            'listimg'                => (string) $r['listimg'],
            'collection_level'       => (string) $r['collection_level'],
            'total_num_show'         => (int) $r['total_num_show'],
            'total_num'              => (int) $r['total_num'],
            'is_img_diff'            => (int) $r['is_img_diff'],
            'unfreeze_collection_id' => (int) $r['unfreeze_collection_id'],
            'freeze_time'            => (int) $r['freeze_time'],
            'series_name'            => (string) $r['series_name'],
        ], $rows);

        return Response::page($list, $total, $s, $p);
    }
}
