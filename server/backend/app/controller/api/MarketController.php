<?php
declare(strict_types=1);

namespace app\controller\api;

use app\model\Collection;
use app\model\BlindBox;
use app\service\MarketFormatter;
use app\service\MarketStats;
use app\service\MockData;
use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;
use think\facade\Db;

/**
 * 市场域控制器（v2.3 从 Core 拆分，v2.4 tradingInformation 切 DB）
 *
 * 挂单查询、藏品详情、行情、求购列表
 *
 * 🔴 数据来源分两类，勿混淆：
 *   【DB  】已按原站实测响应建表并灌入真实数据（tradingInformation / begBuyList）
 *   【MOCK】原站需登录或已下线的端点，暂用内存假数据
 *
 * 🔐 鉴权统一走 HasAuth::currentUser()，禁止只判断 token 非空。
 *
 * ⚠️ 改动本文件后必须跑契约回归：php tools/contract/run.php
 *    退出码非 0 表示与原站实测契约不一致，禁止提交。
 *
 * @package app\controller\api
 */
class MarketController
{
    use HasAuth;

    /**
     * POST /market/market/getMarketGoodsListByGoodsId 【DB，v2.7 切】
     *
     * 前端 MarketGoodsList.vue 请求 {page, sort:'price', order:'asc', type, keyword, id=goods_id}
     * 实测响应 data = {list:[{id, collection_code, price, status, ...}], last_page}
     * list 行是**挂单**（有 collection_code 流通码），不是藏品聚合。
     */
    public function marketGoodsListByGoodsId()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $collectionId = Request::int('id');
        if ($collectionId <= 0) {
            return json(['code' => 1, 'msg' => '', 'time' => time(),
                'data' => ['list' => [], 'last_page' => 0]]);
        }

        $query = Db::name('market_goods')->alias('mg')
            ->join('collection c', 'c.id = mg.collection_id')
            ->where('mg.collection_id', $collectionId)
            ->where('mg.status', 1)
            ->field('mg.id, mg.collection_code, mg.price, mg.num, mg.sold_num, mg.status, mg.createtime, c.name');
        $total = (clone $query)->count();
        $rows  = $query->order('mg.price', 'asc')->order('mg.id', 'asc')
            ->page($p, $s)->select()->toArray();

        $list = array_map(static fn($r) => [
            'id'              => (int) $r['id'],
            'name'            => (string) $r['name'],
            'collection_id'   => $collectionId,
            'collection_code' => (string) $r['collection_code'],
            'price'           => number_format((float) $r['price'], 2, '.', ''),
            'num'             => (int) $r['num'],
            'sale_number'     => (int) $r['sold_num'],
            'status'          => (int) $r['status'],
            // detail.key 快捷下单凭据，与 marketGoodsDetail 的 key 同源（SPEC §4.2）
            'key'             => 'mock-key-' . $collectionId,
        ], $rows);

        return json([
            'code' => 1, 'msg' => '', 'time' => time(),
            'data' => [
                'list'      => $list,
                'total'     => $total,
                'last_page' => (int) ceil($total / max($s, 1)),
            ],
        ]);
    }

    /**
     * POST /market/market/marketGoodsDetail 【DB，v2.7 切】
     * 🔴 detail.key 是快捷下单必需（SPEC §4.2 检查清单第 1 项），
     *    与 fastOrderNew 的 'mock-key-' . goods_id 校验规则同源。
     * priceList 从 market_goods 实时聚合 —— 真实价格阶梯，不再是写死值。
     */
    public function marketGoodsDetail()
    {
        $id   = Request::int('id');
        $type = Request::int('type', 2);

        $row = Db::name('market_goods')->alias('mg')
            ->join('collection c', 'c.id = mg.collection_id')
            ->join('series s', 's.id = c.series_id', 'LEFT')
            ->where('mg.id', $id)
            ->field('mg.id, mg.collection_id, mg.price, mg.num, mg.sold_num, mg.status, c.name, c.listimg, c.main_image, IFNULL(s.name, "") AS series_name')
            ->find();
        if (!$row) {
            return Response::fail('未找到合适商品');
        }

        // 该藏品全部在售价位（价格阶梯，低价在前）
        $priceList = Db::name('market_goods')
            ->where('collection_id', $row['collection_id'])
            ->where('status', 1)
            ->group('price')
            ->field('price, SUM(num - sold_num) AS num')
            ->order('price', 'asc')
            ->select()->toArray();

        $cid = (int) $row['collection_id'];
        return Response::ok([
            'id'          => (int) $row['id'],
            'name'        => (string) $row['name'],
            'type'        => $type,
            'series_name' => (string) $row['series_name'],
            'listimg'     => (string) $row['listimg'],
            'main_image'  => (string) $row['main_image'],
            'detail'      => [
                'key'      => 'mock-key-' . $cid,
                'price'    => number_format((float) $row['price'], 2, '.', ''),
                'num'      => (int) $row['num'],
                'goods_id' => $cid,
            ],
            'priceList'   => array_map(static fn($r) => [
                'price' => number_format((float) $r['price'], 2, '.', ''),
                'num'   => (int) $r['num'],
            ], $priceList),
        ]);
    }

    /**
     * POST /market/market/getMarketList 行情主列表【DB，v2.7 切】
     *
     * 🔴 前端契约（Market.vue loadList）：寄售分支 res.data 是**数组**（非分页信封）。
     * 每行是藏品维度的聚合行情：min_price=最低在售价，flux=在售流通量；
     * 无在售挂单的藏品不出现（is_out 语义只对单藏品详情有意义）。
     * series_id 过滤：0/缺省=「全部区」。
     */
    public function marketList()
    {
        $seriesId = Request::int('series_id', 0);

        $query = Db::name('market_goods')->alias('mg')
            ->join('collection c', 'c.id = mg.collection_id')
            ->where('mg.status', 1)
            ->where('c.is_show', 1);
        if ($seriesId > 0) {
            $query->where('c.series_id', $seriesId);
        }
        $rows = $query->group('mg.collection_id, c.id')
            ->field('c.id, c.name, c.listimg, c.main_image, c.total_num, c.reprice_limit, MIN(mg.price) AS min_price, SUM(mg.num - mg.sold_num) AS flux')
            ->order('min_price', 'asc')
            ->select()->toArray();

        $list = array_map(static fn($r) => [
            'id'            => (int) $r['id'],
            'goods_type'    => 2,
            'is_out'        => 0,
            'total_num'     => (int) $r['total_num'],
            'flux'          => (int) $r['flux'],
            'min_price'     => number_format((float) $r['min_price'], 2, '.', ''),
            'price'         => number_format((float) $r['min_price'], 2, '.', ''),
            'reprice_limit' => (int) $r['reprice_limit'] > 0
                ? number_format((float) $r['reprice_limit'], 2, '.', '')
                : null,
            'product'       => [
                'name'          => (string) $r['name'],
                'listimg'       => (string) $r['listimg'],
                'reprice_limit' => (int) $r['reprice_limit'] > 0
                    ? number_format((float) $r['reprice_limit'], 2, '.', '')
                    : null,
            ],
        ], $rows);

        return Response::ok($list);
    }

    /**
     * POST /market/market/marketList 幸运盒行情【DB，v2.7 切】
     * 前端契约：res.data 是分页信封（loadList 取 d.data），行结构同寄售卡。
     * 数据源 blind_box 表（is_show=1），is_out 对应 soldout。
     */
    public function marketBoxList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $query = BlindBox::where('is_show', 1);
        $total = (int) $query->count();
        $rows  = $query->order('release_time', 'desc')->order('id', 'desc')
            ->page($p, $s)->select()->toArray();

        $list = array_map(static fn($b) => [
            'id'            => (int) $b['id'],
            'goods_type'    => 1,
            'is_out'        => (int) $b['soldout'],
            'total_num'     => (int) $b['total_num'],
            'flux'          => (int) $b['flux'],
            'min_price'     => number_format((float) $b['price'], 2, '.', ''),
            'price'         => number_format((float) $b['price'], 2, '.', ''),
            'reprice_limit' => null,
            'product'       => [
                'name'          => (string) $b['name'],
                'listimg'       => (string) $b['listimg'],
                'reprice_limit' => null,
            ],
        ], $rows);

        return Response::page($list, $total, $s, $p);
    }

    /**
     * POST /market/market/MarketDetail/ids/{id} 【DB，v2.7 切】
     * 挂单详情。前端 Detail.vue / OrderSubmit.vue 消费：
     *   goods_name / price / product{main_image,listimg,total_num,flux,introduce_image,publisher.name} /
     *   user_collection{collection_code,user_hash_text,rz_code} / series_name
     * id 是挂单 id（market_goods.id）。
     */
    public function marketDetail($id)
    {
        $row = Db::name('market_goods')->alias('mg')
            ->join('collection c', 'c.id = mg.collection_id')
            ->join('series s', 's.id = c.series_id', 'LEFT')
            ->join('user u', 'u.id = mg.user_id', 'LEFT')
            ->join('publisher p', 'p.id = c.issuer_id', 'LEFT')
            ->where('mg.id', (int) $id)
            ->field('mg.id, mg.collection_id, mg.collection_code, mg.price, mg.num, mg.sold_num, mg.status, c.name, c.main_image, c.listimg, c.total_num, IFNULL(s.name, "") AS series_name, u.username AS seller, IFNULL(p.name, "") AS publisher_name')
            ->find();
        if (!$row) {
            return Response::fail('未找到合适商品');
        }

        // sum() 不接受表达式（实测 not support data:num - sold_num），用聚合查询
        $fluxRow = Db::name('market_goods')
            ->where('collection_id', $row['collection_id'])->where('status', 1)
            ->field('SUM(num - sold_num) AS flux')->find();
        $flux = (int) ($fluxRow['flux'] ?? 0);

        // 卖家脱敏：13800138000 → 138****8000（原站 user_hash_text 是打码用户名）
        $seller  = (string) ($row['seller'] ?? '');
        $masked  = mb_strlen($seller) === 11
            ? mb_substr($seller, 0, 3) . '****' . mb_substr($seller, -4)
            : $seller;

        return Response::ok([
            'id'          => (int) $row['id'],
            'goods_name'  => (string) $row['name'],
            'name'        => (string) $row['name'],
            'price'       => number_format((float) $row['price'], 2, '.', ''),
            'series_name' => (string) $row['series_name'],
            'product'     => [
                'name'            => (string) $row['name'],
                'main_image'      => (string) $row['main_image'],
                'listimg'         => (string) $row['listimg'],
                'total_num'       => (int) $row['total_num'],
                'flux'            => $flux,
                'introduce_image' => '',
                'publisher'       => ['name' => (string) $row['publisher_name']],
            ],
            'user_collection' => [
                'collection_code' => (string) $row['collection_code'],
                'user_hash_text'  => $masked,
                'rz_code'         => '',
            ],
        ]);
    }

    /**
     * POST /market/trade/tradingInformation —— 藏品三态行情【DB 驱动，v2.4 切】
     *
     * 实测（recon/raw2/market_trade_tradinginformation.json，样本账号无数据故全空）：
     *   入参 {status:1|2|3, page:1, goods_id:"124"}
     *     status: 1=成交记录 2=当前求购 3=当前寄售（依据前端 SellInfo.vue 复刻注释）
     *   出参 9 键：list[] + 6 个 int 统计 + today_highest_price/today_lowest_price = null
     *
     * 🔴 契约要点（改这里前必须知道）：
     *   1. today_highest_price / today_lowest_price 无成交时是 **null 不是 0**——
     *      前端据此走「无数据」分支，给 0 会显示「今日最高 ￥0」的假成交
     *   2. list 行结构**无样本**（实测 list=[]，myConsignment 样本同样空），
     *      本地三表对该藏品也无行。禁止拿 MockData::order 的行结构凑数——
     *      那是跨端点套用字段，正是明令禁止的「凭端点名猜字段」
     *   3. 统计语义（成交态判定、地板价算法）无非空样本可验证，
     *      详见 docs/decisions/OPEN-DECISIONS.md D-005；本地数据状态下恒等于样本值
     */
    public function tradingInformation()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        // status 暂不参与计算（list 行结构无样本，见方法注释第 2 条）；
        // 接收但不使用，避免前端传缺时报警
        Request::int('status', 1);
        $collectionId = Request::int('goods_id');

        // 统计走 MarketStats（契约用例共用同一入口，测的是真实查询而非副本）
        $stats = $collectionId > 0
            ? MarketStats::tradingStats($collectionId)
            : MarketStats::emptyStats();

        return Response::ok(
            MarketFormatter::tradingInformation($stats),
            Msg::TRADING_INFORMATION
        );
    }

    /**
     * POST /market/trade/begBuyList 求购列表【DB，v2.5 切】
     *
     * 🔴 四层错误链 + 成功信封，全部来自原站实测：
     *      1. 无 token                    → 401「请登录后操作」
     *      2. collection_id 为空/0/缺参    → code=0「藏品ID不能为空」
     *         （raw4/market_trade_begBuyList.json：缺参错误样本）
     *      3. sort 不在 {asc, desc}        → code=0「排序方式错误」（缺参也算错）
     *      4. collection_id 非整数/不存在  → code=0「藏品不存在」
     *      5. 全部合法 → 信封按**藏品可求购性**分两支（raw6 矩阵探测实证）：
     *         不可求购（reprice_beg_max_limit ≤ 0）→ {current_page,data,last_page:0,per_page:0,total}
     *         可求购  （reprice_beg_max_limit > 0）→ {total,per_page:50,current_page,last_page,data}
     *         msg="订单信息"
     *
     * 🔴 检查顺序是实测出来的，不是设计出来的：
     *    collection_id=-1（不存在）且无 sort → 原站返回「排序方式错误」
     *    而非「藏品不存在」→ 存在性检查必须排在 sort **之后**。
     *    按「先查存在再验排序」实现会静默返回不同的错误文案。
     *
     * 🔴 collection_id 判定细节（实测）：
     *    "0" / "" → 不能为空；"abc" / "1.5" / "-1" / "99999" → 藏品不存在
     *    即非整数字符串不走 int 强转（1.5 不会变成 1），直接判不存在。
     *
     * 🔴 双分支的判别列是 reprice_beg_max_limit（与 createBegBuyOrder 第 6 步
     *    「该藏品不可求购」同一个flag）：1/8/124/161/165（≤0）走旧信封，
     *    162（=20000.00）走新信封。判别机制是从 6 个数据点归纳的，
     *    原站代码不可见——证据链与残余不确定性见 MarketFormatter::begBuyList。
     *
     * 🔴 分页：page 缺省或 0 → current_page=1；空态下请求 page=2 也不回显
     *    （实测两个分支都回 1）。
     *
     * ⚠️ data 行结构无样本（非空求购单需要已支付，本账号 money=0，D-006），
     *    本地 beg_buy_order 表 2 行均为取消终态 → total 恒 0，data 恒 []。
     *    前端 MarketGoodsList.vue 只读 d.data，空态走「暂无」分支，不会崩。
     */
    public function begBuyList()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        // 第 2 层：collection_id 空判定（"0" 与原站一致按空处理）
        $rawCollectionId = Request::str('collection_id');
        if ($rawCollectionId === '' || $rawCollectionId === '0') {
            return Response::fail('藏品ID不能为空');
        }

        // 第 3 层：sort 必须显式为 asc/desc（缺参即错，实测）
        $sort = Request::str('sort');
        if (!in_array($sort, ['asc', 'desc'], true)) {
            return Response::fail('排序方式错误');
        }

        // 第 4 层：存在性。非整数字符串（"abc"/"1.5"）直接判不存在，
        // 不靠 MySQL 隐式转换（那等于把契约交给 DB 的cast 规则）
        $collectionId = preg_match('/^-?\d+$/', $rawCollectionId) ? (int) $rawCollectionId : -1;
        $collection = $collectionId > 0 ? Collection::find($collectionId) : null;
        if ($collection === null) {
            return Response::fail('藏品不存在');
        }

        ['page' => $p, 'size' => $s] = Request::page();

        // total 走真实计数（与 tradingInformation 的 current_buying 同口径）；
        // data 行结构无样本 → 恒 []（D-005 先例，见 MarketFormatter::begBuyList）
        $total = MarketStats::begBuyOrderCount($collectionId);

        // 信封分支：可求购藏品走新信封（total 开头 + per_page=50），
        // 判别列与建单第 6 步同一个 flag
        $begBuyable = (float) (string) $collection['reprice_beg_max_limit'] > 0;

        return Response::ok(
            MarketFormatter::begBuyList($total, $p, $s, $begBuyable),
            Msg::BEGIN_BUY_ORDER
        );
    }

    // ==================== 交易下单 ====================

    /**
     * POST /order/pay/fastOrderNew 快捷下单（核心交易接口）
     *
     * 🔴 三条硬规则（SPEC §4.2）：
     *   1. 端点名是 fastOrderNew，不是 fastCreateOrder
     *   2. key 必须来自 marketGoodsDetail 的 detail.key
     *   3. 同用户存在未支付订单时返回明确业务错误
     */}
