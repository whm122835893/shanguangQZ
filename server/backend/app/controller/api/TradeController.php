<?php
declare(strict_types=1);

namespace app\controller\api;

use app\model\Collection;
use app\model\ArtelOrder;
use app\model\ConsignmentPriceRule;
use app\model\UserCollection;
use app\service\MarketFormatter;
use app\service\TradeFormatter;
use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;
use think\facade\Db;

/**
 * 交易域控制器（v2.3 从 Trade 拆分）—— 订单/市场单/寄售/藏品详情/求购单
 *
 * 已切 DB 的端点：tradeCollectionDetail / prepaymentCollectionDetail（market 域，v2.4）
 * 其余端点仍为【MOCK】。切 DB 时禁止凭端点名猜字段，必须以 recon/ 实测样本为准。
 * 🔐 鉴权走 HasAuth::currentUser()，禁用 Request::hasToken()（只判非空，是后门）。
 * ⚠️ 改动后必须跑：php tools/contract/run.php
 *
 * @package app\controller\api
 */
class TradeController
{
    use HasAuth;

    /**
     * POST /order/order/getMarketOrderDetails 市场订单详情【DB，v2.8 切】
     * 🔐 IDOR：order_id 必须属于当前用户；不存在返回 data=null（前端 redirect 首页）。
     */
    public function getMarketOrderDetails()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $order = ArtelOrder::where('id', Request::int('order_id'))
            ->where('user_id', $user['id'])->find();
        if (!$order) {
            return Response::ok(null);
        }
        return Response::ok(TradeFormatter::order($order->toArray()));
    }

    /**
     * POST /order/order/saleMarketOrderDetail 转售订单详情【DB，v2.8 切】
     * 前端 OrderDetail.vue（kind=regift）传 {order_id}，消费 18 字段 + 状态映射。
     * consign=1 寄售标记（布尔 = int）。
     */
    public function saleMarketOrderDetail()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $order = ArtelOrder::where('id', Request::int('order_id'))
            ->where('user_id', $user['id'])->find();
        if (!$order) {
            return Response::ok(null);
        }

        $o              = TradeFormatter::order($order->toArray());
        $o['consign']   = 1;   // 寄售标记，布尔 = int
        return Response::ok($o);
    }

    // ==================== 交易/ 寄售 ====================

    /**
     * POST /market/trade/entrust 发起委托【DB，v2.8 切】
     *
     * 委托 = 把持仓挂上市场：事务内写 market_goods（在售挂单，
     * entrustOrderList/市场列表即时可见）+ artel_order（order_type='4' 委托单，
     * TradeList「我的委托」tab 与 TradeInfo 详情可见）。
     * 校验：持仓归属（IDOR）、可售数量、consignment_price_rule(scene=trade) 价格下限。
     */
    public function entrust()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $ucId  = Request::int('user_collection_id');
        $price = (float) (string) Request::str('price');
        $num   = Request::int('num', 1);
        if ($ucId <= 0 || $price <= 0 || $num <= 0) {
            return Response::fail('请填写价格与数量');
        }

        // 🔐 只能寄售自己的持仓
        $uc = UserCollection::where('id', $ucId)
            ->where('user_id', $user['id'])->find();
        if (!$uc) {
            return Response::fail('未找到合适商品');
        }
        $sellable = (int) $uc['num'] - (int) $uc['sell_num'] - (int) $uc['lock_num'];
        if ($num > $sellable) {
            return Response::fail('可寄售数量不足');
        }

        $coll = Collection::find((int) $uc['collection_id']);
        if (!$coll) {
            return Response::fail('未找到合适商品');
        }

        // 🆕 寄售开关（规格 §3.4）：is_resaleable=0 → 禁止挂单
        if ((int) $coll['is_resaleable'] !== 1) {
            return Response::fail('该藏品暂不支持寄售');
        }

        // 🆕 藏品级限价（规格 §3.4.1）：mode=1 → 挂单价必须落在 [min, max] 闭区间
        if ((int) $coll['resale_price_mode'] === 1) {
            $rMin = $coll['resale_price_min'] !== null ? (float) $coll['resale_price_min'] : null;
            $rMax = $coll['resale_price_max'] !== null ? (float) $coll['resale_price_max'] : null;
            if ($rMin !== null && $rMin > 0 && $price < $rMin) {
                return Response::fail('寄售价格不能低于' . number_format($rMin, 2, '.', ''));
            }
            if ($rMax !== null && $rMax > 0 && $price > $rMax) {
                return Response::fail('寄售价格不能高于' . number_format($rMax, 2, '.', ''));
            }
        }

        // 价格下限（寄售限价规则，scene=trade）—— 规则表限价列是 minprice
        $rule = ConsignmentPriceRule::findForCollection((int) $uc['collection_id'], 'trade');
        $min  = $rule ? (float) $rule['minprice'] : 0.0;
        if ($min > 0 && $price < $min) {
            return Response::fail($rule['low_price_text'] !== ''
                ? (string) $rule['low_price_text']
                : '寄售价格不能低于' . number_format($min, 2, '.', ''));
        }

        $ts   = time();
        $code = sprintf('%d%04d', (int) $uc['collection_id'], (int) $uc['id']);
        $orderSn = date('YmdHis', $ts) . '57' . random_int(100000000, 999999999);
        $seriesName = (string) (Db::name('series')
            ->where('id', (int) $coll['series_id'])->value('name') ?? '');

        Db::startTrans();
        try {
            Db::name('market_goods')->insert([
                'user_id'         => (int) $user['id'],
                'collection_id'   => (int) $uc['collection_id'],
                'collection_code' => $code,
                'price'           => number_format($price, 2, '.', ''),
                'num'             => $num,
                'sold_num'        => 0,
                'status'          => 1,
                'createtime'      => $ts,
                'updatetime'      => $ts,
            ]);
            Db::name('artel_order')->insert([
                'order_sn'          => $orderSn,
                'user_id'           => (int) $user['id'],
                'artel_order'       => 'mh_secondary_market_order',
                'order_type'        => '4',
                'order_status'      => 0,
                'total_price'       => number_format($price * $num, 2, '.', ''),
                'num'               => (string) $num,
                'goods_id'          => (int) $uc['collection_id'],
                'goods_type'        => 2,
                'goods_name'        => (string) $coll['name'],
                'goods_cover_image' => (string) $coll['listimg'],
                'series_name'       => $seriesName,
                'crystal_type'      => 'crystal_no',
                'crystal_number'    => '0',
                'uc_ids'            => (string) $ucId,
                'collection_code'   => $code,
                'createtime'        => $ts,
                'updatetime'        => $ts,
            ]);
            // 占用可售数量（user_collection 时间戳字段是 utime，无 updatetime 列）
            $uc->save([
                'sell_num' => (int) $uc['sell_num'] + $num,
                'utime'    => $ts,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return Response::fail('委托失败，请稍后重试');
        }

        return Response::ok([
            'entrust_no' => $orderSn,
            'status'     => 1,
        ]);
    }

    public function entrustOrderList()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $query = Db::name('market_goods')->alias('mg')
            ->join('collection c', 'c.id = mg.collection_id')
            ->where('mg.user_id', $user['id']);
        $total = (int) (clone $query)->count();
        $rows  = $query->field('mg.*, c.name')
            ->order('mg.createtime', 'desc')->order('mg.id', 'desc')
            ->page($p, $s)->select()->toArray();

        // status_text：sold_num<num=寄售中，其余=已售罄（本地语义，原站枚举无样本）
        $list = array_map(static function ($r) {
            $soldOut = (int) $r['sold_num'] >= (int) $r['num'];
            return [
                'id'            => (int) $r['id'],
                'entrust_no'    => (string) $r['collection_code'],
                'collection_id' => (int) $r['collection_id'],
                'name'          => (string) $r['name'],
                'num'           => (int) $r['num'],
                'price'         => number_format((float) $r['price'], 2, '.', ''),
                'status'        => $soldOut ? 2 : 1,
                'status_text'   => $soldOut ? '已售罄' : '寄售中',
                'createtime'    => (int) $r['createtime'],
            ];
        }, $rows);

        return Response::page($list, $total, $s, $p);
    }

    /**
     * market/trade/collectionDetail —— 寄售藏品详情 + 地板价
     *
     * 实测结构：data = {id, name, listimg, reprice_limit, reprice_min_limit,
     *                  reprice_beg_max_limit, max_buy_price, floor_price:{11 字段}}
     * 信封 msg = 「藏品详情」
     *
     * 🔴 两个实测事实，改这里前必须知道：
     *   1. floor_price 是嵌套对象，字段来自 consignment_price_rule.scene='trade'
     *   2. 前端传参 collection_id 是**字符串**（"124"），Request::int 已兼容
     */
    public function tradeCollectionDetail()
    {
        $collectionId = Request::int('collection_id');

        $coll = $collectionId > 0 ? Collection::find($collectionId) : null;
        $rule = $collectionId > 0
            ? ConsignmentPriceRule::findForCollection($collectionId, 'trade')
            : null;

        // 原站无「藏品不存在」分支的样本。返回空数组（不是 null），
        // 前端 SubmitBegBuy.vue 有 r.data 判空保护，不会崩。
        if (!$coll || !$rule) {
            return Response::ok([], Msg::COLLECTION_DETAIL_TRADE);
        }

        return Response::ok(
            MarketFormatter::tradeCollectionDetail($coll->toArray(), $rule),
            Msg::COLLECTION_DETAIL_TRADE
        );
    }

    /**
     * market/Prepayment/collectionDetail —— 预付藏品的地板价/费率
     *
     * 实测结构：data = {11 字段}，**平铺无嵌套**（与 trade 端点最大的结构差异）
     * 信封 msg = 「相关金额」
     *
     * 🔴 前端 SubmitEntrust.vue 的说明文案取的就是这个端点的 tips
     *    （「出价下限为昨日平均成交价100%」），不是 trade 的「寄售出价下限」。
     *    所以 scene='prepay' 不能省、不能错。
     */
    public function prepaymentCollectionDetail()
    {
        $collectionId = Request::int('collection_id');

        $rule = $collectionId > 0
            ? ConsignmentPriceRule::findForCollection($collectionId, 'prepay')
            : null;

        if (!$rule) {
            return Response::ok([], Msg::COLLECTION_DETAIL_PREPAY);
        }

        return Response::ok(
            MarketFormatter::prepaymentCollectionDetail($rule),
            Msg::COLLECTION_DETAIL_PREPAY
        );
    }

    // begBuyOrderList / begBuyOrderInfo 已于 v2.6 迁至 BegBuyController
    // （原站真实端点 createBegBuyOrder/cancelBegBuyOrder 一并归入求购域，
    //   契约依据 recon/raw5/ 实测非空样本）

    /**
     * market/Prepayment/myOrderInfo 【DB，v2.7 切】
     * 前端 TradeInfo.vue：委托订单详情，取 order 数据 + num=number 兜底。
     * order_id 是 artel_order.id，强制 user_id 归属校验（IDOR）。
     */
    public function myOrderInfo()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $order = ArtelOrder::where('id', Request::int('order_id'))
            ->where('user_id', $user['id'])->find();
        if (!$order) {
            return Response::ok(null);
        }

        $o           = TradeFormatter::order($order->toArray());
        $o['prepay'] = 1;
        return Response::ok($o);
    }

    // ==================== 我的藏品 ====================

    /**
     * user_collection/user_collection/detail/ids/{ids} 【DB，v2.7 切】
     *
     * 前端 Consign.vue（寄售提交页）消费：
     *   detail.collection_code（藏品编号）/ detail.from_price（买入价，无则不展示）/
     *   detail.product{main_image, listimg, name, weither_regift}
     * ids 来自我的藏品（可逗号分隔），强制 user_id 归属校验（IDOR）。
     */
    public function collectionDetail($ids = null)
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $raw = (string) ($ids ?? Request::str('ids'));
        $firstId = (int) explode(',', trim($raw, ','))[0];
        if ($firstId <= 0) {
            return Response::fail('参数错误');
        }

        // 🔐 只能看自己的持仓
        $uc = UserCollection::where('id', $firstId)
            ->where('user_id', $user['id'])->find();
        $coll = $uc ? Collection::find($uc['collection_id']) : null;
        if (!$uc || !$coll) {
            return Response::fail('未找到合适商品');
        }

        return Response::ok([
            // 持仓无独立流通码列，按 藏品id+持仓id 生成确定性编号
            'collection_code' => sprintf('%d%04d', (int) $uc['collection_id'], (int) $uc['id']),
            'from_price'      => '',
            'product'         => [
                'name'           => (string) $coll['name'],
                'main_image'     => (string) $coll['main_image'],
                'listimg'        => (string) $coll['listimg'],
                // 转赠开关（规格 §3.5）：weither_regift === is_transferable（C 端按钮据此隐藏/置灰）
                'weither_regift' => (int) $coll['is_transferable'],
            ],
        ]);
    }

    /**
     * user_collection/user_collection/show_resale_money 【DB，v2.7 切】
     *
     * 前端 Consign.vue loadRate 消费 pay_platform_service_rate /
     * share_proportion_rate（寄售手续费与分成比例）——mock 版字段与前端
     * 消费不符，v2.7 一并修正。费率来自 consignment_price_rule（scene=trade）。
     */
    public function showResaleMoney()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $ucId = Request::int('user_collection_id');
        $price = (float) (string) Request::str('price', '0');

        $uc = $ucId > 0 ? UserCollection::where('id', $ucId)
            ->where('user_id', $user['id'])->find() : null;
        $rule = $uc ? ConsignmentPriceRule::findForCollection(
            (int) $uc['collection_id'], 'trade'
        ) : null;

        $serviceRate = (string) ($rule ? $rule['pay_platform_service_rate'] : '0');
        $shareRate   = (string) ($rule ? $rule['share_proportion_rate'] : '0');

        return Response::ok([
            'resale_money'            => number_format(max($price, 0), 2, '.', ''),
            'resale_num'              => $uc ? (int) $uc['num'] : 0,
            'settle_money'            => '0.00',
            'pay_platform_service_rate' => $serviceRate,
            'share_proportion_rate'     => $shareRate,
        ]);
    }

    // ==================== 其他 ====================
    // unionLogs 已移至 UserController（union/index/logs 属公会域，
    // 与 union/user/myunion 同域归位；v2.4 拆分，兼解本文件超 300 行）

    /**
     * GET|POST /user/newrank1 —— 新秀矿石榜【空态】
     *
     * 🔴 v3.1：不再编造 10 条假数据（假昵称/假矿石值）。ore 流水无数据载体，
     *    真实空榜——玩法接入后再切真实聚合。前端无页面调用。
     */
    public function newRank()
    {
        return Response::ok([]);
    }

    public function getConfig()
    {
        $key = Request::str('key');
        if ($key === '') {
            // 对齐原站：code=0 + 固定 msg，HTTP 仍 200
            return Response::fail('缺少key值');
        }

        // 防护：用参数化查询，不做字符串拼接，避免 SQL 注入
        $row = \think\facade\Db::name('site_config')
            ->where('key', $key)
            ->find();

        if (!$row) {
            return Response::fail('不合法的key值');
        }

        return Response::ok((string) $row['value']);
    }

    // ==================== 账号 ====================
    // register / resetPwd 已移至 AccountController（user/ 域，v2.4 归位）
    //
    // ==================== 内部 ====================
    // makeOrder 曾抽至 Concerns\MakesOrders（v2.4）——因留在本类变成
    // 私有方法，导致 PayController 的 5 个 Create*Order 端点调用时 fatal
    // （实测 500 HTML 错误页，静态检查发现不了，详见 trait 注释）。
}
