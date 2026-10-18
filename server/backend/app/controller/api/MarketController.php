<?php
declare(strict_types=1);

namespace app\controller\api;

use app\model\Collection;
use app\service\MarketFormatter;
use app\service\MarketStats;
use app\service\MockData;
use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;

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

    public function marketGoodsListByGoodsId()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 4;
        $r = MockData::paginate(function ($i) {
            $g = MockData::marketGoods(155 + $i);
            $g['key'] = 'mock-key-' . (155 + $i);
            return $g;
        }, $total, $p, $s);

        return json([
            'code' => 1, 'msg' => '', 'time' => time(),
            'data' => ['list' => $r['list'], 'total' => $r['total']],
        ]);
    }

    /**
     * POST /market/market/marketGoodsDetail
     * 🔴 detail.key 是快捷下单必需（SPEC §4.2 检查清单第 1 项）
     */

    public function marketGoodsDetail()
    {
        $id   = Request::int('id', 155);
        $type = Request::int('type', 2);
        $g    = MockData::marketGoods($id);

        return Response::ok([
            'id'      => $g['id'],
            'name'    => $g['name'],
            'type'    => $type,
            'series_name' => $g['series_name'],
            'listimg' => $g['listimg'],
            'main_image' => $g['main_image'],
            'detail' => [
                'key'     => 'mock-key-' . $id,
                'price'   => '100.00',
                'num'     => 1,
                'goods_id'=> $id,
            ],
            'priceList' => [
                ['price' => '100.00', 'num' => 1],
            ],
        ]);
    }

    /** POST /market/market/getMarketList 行情主列表 */

    public function marketList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 10;
        $r = MockData::paginate(function ($i) {
            $c = MockData::collection($i + 1);
            return [
                'id'    => $c['id'],
                'name'  => $c['name'],
                'image' => $c['main_image'],
                'price' => (string)(100 + $i * 10) . '.00',
                'change_rate' => (string)round(($i - 2) * 1.5, 2) . '%',
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /market/market/marketList 幸运盒行情 */

    public function marketBoxList()
    {
        return $this->marketList();
    }

    /** POST /market/market/MarketDetail/ids/{id} */

    public function marketDetail($id)
    {
        $g = MockData::marketGoods((int) $id);
        return Response::ok($g);
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
     * 🔴 四层错误链 + 成功信封，全部来自 2026-10-18 复探原站实测
     *    （raw4/market_trade_begBuyList_161.json 及现场 curl 比对）：
     *      1. 无 token                    → 401「请登录后操作」
     *      2. collection_id 为空/0/缺参    → code=0「藏品ID不能为空」
     *      3. sort 不在 {asc, desc}        → code=0「排序方式错误」（缺参也算错）
     *      4. collection_id 非整数/不存在  → code=0「藏品不存在」
     *      5. 全部合法 → data = {current_page, data:[], last_page:0, per_page:0, total:0}
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
     * 🔴 分页：page 缺省或 0 → current_page=1（Request::page() 已 clamp）；
     *    每页条数空态不回显（per_page=0，请求带 list_rows=10 也一样）。
     *
     * ⚠️ data 行结构无样本（全语料无非空求购单样本，兄弟端点 begBuyOrderList
     *    同样 total=0），本地 beg_buy_order 表 0 行 → 恒 []，见 D-006。
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
        if ($collectionId <= 0 || Collection::where('id', $collectionId)->count() === 0) {
            return Response::fail('藏品不存在');
        }

        ['page' => $p, 'size' => $s] = Request::page();

        // total 走真实计数（与 tradingInformation 的 current_buying 同口径）；
        // data 行结构无样本 → 恒 []（D-005 先例，见 MarketFormatter::begBuyList）
        $total = MarketStats::begBuyOrderCount($collectionId);

        return Response::ok(
            MarketFormatter::begBuyList($total, $p, $s),
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
