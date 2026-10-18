<?php
declare(strict_types=1);

namespace app\controller\api;

use app\model\Affiche;
use app\model\AfficheType;
use app\model\Batch;
use app\model\BlindBox;
use app\service\Formatter;
use app\service\MockData;
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
     * GET /index/getSeries 藏品系列【MOCK】
     * 原站实测 data = [] （空数组，站点当前无系列数据）。保持空数组语义。
     */

    public function getSeries()
    {
        return Response::ok([]);
    }

    /** GET /banner/show/getBanner 首页轮播 */

    public function getBanner()
    {
        $total = 2;
        $list  = [MockData::banner(0), MockData::banner(1)];
        return Response::page($list, $total, 15, 1);
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

    public function collectionList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 6;
        $r = MockData::paginate(fn($i) => MockData::collection($i + 1), $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 用户 ====================

    /** POST /user/login 账号密码登录【DB + Argon2id】 */

    public function synthesisTheme()
    {
        $thm = Request::int('thm', 1);
        return Response::ok([
            'thm'    => $thm,
            'name'   => '合成主题 ' . $thm,
            'image'  => MockData::collection($thm)['main_image'],
            'material_num' => 3,
        ]);
    }

    /** POST /synthesis/material 合成材料 */

    public function synthesisMaterial()
    {
        $thm = Request::int('thm', 1);
        // 原站约定：4003=无资格3003=直接进方案详情
        if ($thm === 0) {
            return Response::ok(['code' => 4003, 'msg' => '暂无合成资格']);
        }
        return Response::ok([
            'code' => 3003,
            'thm'  => $thm,
            'list' => [
                ['id' => 1, 'name' => '材料A', 'num' => 2, 'image' => MockData::collection(1)['main_image']],
                ['id' => 2, 'name' => '材料B', 'num' => 1, 'image' => MockData::collection(2)['main_image']],
            ],
        ]);
    }

    /** GET /synthesis/recordList 合成记录 */

    public function synthesisRecord()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 4;
        $r = MockData::paginate(function ($i) {
            $o = MockData::order(18950 + $i, 2, 2);
            $o['theme_name'] = '主题 ' . ($i + 1);
            return $o;
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 抽签 / 抽奖 ====================

    /** POST /lottery/info */

    public function lotteryInfo()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok([
            'id'     => Request::int('id', 1),
            'name'   => '幸运抽签',
            'image'  => MockData::collection(1)['main_image'],
            'price'  => '10.00',
        ]);
    }

    /** POST /lottery/statistics （两种入参形态） */

    public function lotteryStatistics()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok([
            'total_users'  => 128,
            'today_users'  => 12,
            'total_prize'  => 5,
        ]);
    }

    /** POST /lottery/start （写操作，Mock 仅返回结构） */

    public function lotteryStart()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }
        return Response::ok(['prize_id' => 1, 'prize_name' => '藏品A']);
    }

    /** POST /lottery/record */

    public function lotteryRecord()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(function ($i) {
            $o = MockData::order(18970 + $i, 2, 2);
            $o['prize_name'] = '奖品' . ($i + 1);
            return $o;
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 活动 / 社区 ====================

    /** POST /activity/index 活动列表 */

    public function activityIndex()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 4;
        $r = MockData::paginate(function ($i) {
            return [
                'id'    => $i + 1,
                'name'  => '活动 ' . ($i + 1),
                'image' => MockData::collection($i)['main_image'],
                'type'  => 1,
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** GET /Activity/category 活动分类（⚠️ 首字母大写） */

    public function activityCategory()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok([
            ['id' => 1, 'name' => '全部'],
            ['id' => 2, 'name' => '抽签'],
            ['id' => 3, 'name' => '空投'],
        ]);
    }

    /** GET /crystal_goods/list 水晶列表 */

    public function crystalGoods()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(function ($i) {
            return [
                'id' => $i + 1,
                'name' => '水晶 ' . ($i + 1),
                'image' => MockData::collection($i)['main_image'],
                'crystal_type' => ['yellow', 'purple', 'blue'][$i % 3],
                'price' => '50.00',
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /user/ranking 排行榜 */}
