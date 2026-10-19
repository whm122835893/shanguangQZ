<?php
declare(strict_types=1);

namespace app\controller\api;

use app\model\ArtelOrder;
use app\service\Gateways;
use app\service\SettlementService;
use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\service\TradeFormatter;
use app\controller\api\Concerns\HasAuth;
use think\facade\Db;

/**
 * 订单域控制器（v2.3 从 Core 拆分）
 *
 * 下单、支付、订单列表与详情、寄售、我的求购单
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
class OrderController
{
    use HasAuth;

    /**
     * POST /order/pay/fastOrderNew 快捷下单【DB，v2.8 切】
     *
     * 原站实测（MarketGoodsList.vue 抓包 2026-10-16）：
     *   payload {goods_id, key, pay_way}（pay_way=钱包名 huifu/yibao/sandpay）
     *   data = {order_sn, order_id:"18554", time:"300"} —— order_id 是 **string**，
     *   time 为收银台倒计时秒数（string），msg=「下单成功」
     *
     * key 与 MarketController::marketGoodsDetail 的 detail.key 同源
     * （'mock-key-' . goods_id）；goods_id 即 collection_id。
     * 事务落 artel_order（order_type='2' 市场购买单，待付款），
     * 不锁库存 —— 库存占用发生在支付成功（doPay/网关回调），与原站一致。
     */
    public function fastOrderNew()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $goodsId = Request::int('goods_id');
        $key     = Request::str('key');
        $payWay  = Request::str('pay_way');

        if ($goodsId <= 0 || $key === '') {
            return Response::fail('未找到合适商品');
        }
        if ($payWay === '') {
            return Response::fail('请选择支付方式');
        }
        if ($key !== 'mock-key-' . $goodsId) {
            return Response::fail('未找到合适商品');
        }

        // 取该藏品最低价在售挂单
        $mg = Db::name('market_goods')->alias('mg')
            ->join('collection c', 'c.id = mg.collection_id')
            ->join('series s', 's.id = c.series_id', 'LEFT')
            ->where('mg.collection_id', $goodsId)
            ->where('mg.status', 1)
            ->whereRaw('mg.sold_num < mg.num')
            ->field('mg.price, c.name, c.listimg, IFNULL(s.name, "") AS series_name')
            ->order('mg.price', 'asc')->order('mg.id', 'asc')
            ->find();
        if (!$mg) {
            return Response::fail('未找到合适商品');
        }

        $ts      = time();
        $orderSn = date('YmdHis', $ts) . '57' . random_int(100000000, 999999999);
        Db::startTrans();
        try {
            $orderId = Db::name('artel_order')->insertGetId([
                'order_sn'          => $orderSn,
                'user_id'           => (int) $user['id'],
                'artel_order'       => 'mh_secondary_market_order',
                'order_type'        => '2',
                'order_status'      => 1,
                'total_price'       => $mg['price'],
                'num'               => '1',
                'goods_id'          => $goodsId,
                'goods_type'        => 2,
                'goods_name'        => (string) $mg['name'],
                'goods_cover_image' => (string) $mg['listimg'],
                'series_name'       => (string) $mg['series_name'],
                'crystal_type'      => 'crystal_no',
                'crystal_number'    => '0',
                'uc_ids'            => null,
                'collection_code'   => '',
                'createtime'        => $ts,
                'updatetime'        => $ts,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return Response::fail('下单失败，请稍后重试');
        }

        return Response::ok([
            'order_id' => (string) $orderId,   // ⚠️ 实测 string
            'order_sn' => $orderSn,
            'time'     => '300',
        ], '下单成功');
    }

    /** POST /order/pay/doPay 发起支付 */

    public function doPay()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $orderSn = Request::str('order_sn');
        if ($orderSn === '') {
            return Response::fail('订单号不能为空');
        }
        // 原站实测文案（raw/order_pay_dopay.json）：支付方式不能为空
        $payWay = Request::str('pay_way');
        if ($payWay === '') {
            return Response::fail('支付方式不能为空');
        }

        // 🔐 IDOR 防护：订单必须属于当前用户。不匹配时返回「订单不存在」
        //    而非「无权限」——后者等于确认订单号存在，会泄露订单空间。
        $order = ArtelOrder::where('order_sn', $orderSn)
            ->where('user_id', $user['id'])
            ->find();
        if ($order === null) {
            return Response::fail('订单不存在');
        }

        // 幂等：已付过款的订单不允许再次发起支付。
        // 判定依据用 pay_time（字段语义明确：0 = 从未支付），
        // 而不是 order_status —— 状态枚举的实测值仅有 2/3 两个样本，
        // 语义归属未确认（见 ArtelOrder::STATUS 注释），拿它做幂等判断
        // 会把「已支付」误判成「待付款」从而重复收款。
        if ((int) $order['pay_time'] > 0) {
            return Response::fail('该订单已完成支付');
        }

        // 收款结果**只能**由网关决定。通道未接入时网关恒返回拒绝，
        // 本方法不存在任何「凭空构造成功」的路径（见 PayGateway 注释）。
        $result = Gateways::make($payWay)->charge($order->toArray(), $payWay);
        if (! $result->paid) {
            return Response::fail($result->msg);
        }

        // 🔴 v3.0 支付闭环：确认收款后立即结算（订单置付 + 挂单核销 + 卖家入账）。
        //    settleOrder 幂等：外部渠道若再发异步回调（同 pay_no）会被重放护栏静默放行，
        //    不会双入账；pay_no 不一致的伪造回调会被拒绝。
        SettlementService::settleOrder($orderSn, $result->payNo, $payWay);

        return Response::ok(['pay_no' => $result->payNo]);
    }

    // ==================== 订单 ====================

    /** POST /order/order/orderList 我的订单【DB】 */

    public function orderList()
    {
        [$user, $deny] = $this->currentUser();
        if ($deny !== null) {
            return $deny;
        }

        ['page' => $p, 'size' => $s] = Request::page();

        // 🔐 强制带 user_id —— IDOR 防护：不加此条件可枚举全站订单
        $query = ArtelOrder::where('user_id', $user['id']);

        // 原站支持按状态筛选（实测 status 参数名未确认，前端传的是 order_status 语义）
        // ⚠️ 这里只透传 status 一个入口；原站是否还支持 order_sn / 时间区间未实测，
        //    不做无依据的扩展。
        $status = Request::int('status', 0);
        if ($status > 0) {
            $query->where('order_status', $status);
        }

        $total = (int) $query->count();
        $rows  = $query->order('createtime', 'desc')
            ->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        // ⚠️ 此端点是标准五字段分页（与 newList 的 {count,list} 不同）
        return Response::page(
            array_map(static fn($r) => TradeFormatter::order($r), $rows),
            $total, $s, $p, Msg::ORDER_LIST
        );
    }

    /**
     * POST /order/order/orderDetail/ids/{id} 【DB，v2.7 切】
     * 🔐 IDOR 防护：订单必须属于当前用户，否则「订单不存在」（不泄露存在性）。
     */
    public function orderDetail($id)
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $order = ArtelOrder::where('id', (int) $id)
            ->where('user_id', $user['id'])->find();
        if (!$order) {
            return Response::fail('订单不存在');
        }
        return Response::ok(TradeFormatter::order($order->toArray()));
    }

    /** POST /order/order/MarketDetail/ids/{id} 市场订单详情【DB，v2.7 切】 */

    public function marketOrderDetail($id)
    {
        return $this->orderDetail($id);
    }

    /** POST /order/order/myConsignment 我的寄售单【DB，v2.7 切】（order_type=8） */

    public function myConsignment()
    {
        return $this->ordersByType('8');
    }

    /** POST /market/Prepayment/myOrderlist 我的预付订单【DB，v2.7 切】（order_type=4） */

    public function myOrderlist()
    {
        return $this->ordersByType('4');
    }

    /**
     * order_type 维度的个人订单分页（order_type 为 varchar，按字符串比较）
     *
     * status 透传（TradeList.vue 契约）：null/缺省=全部，0/1/2=order_status 精确匹配。
     * 🔐 强制 user_id 归属（IDOR）。
     */
    private function ordersByType(string $orderType)
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $query = ArtelOrder::where('user_id', $user['id'])
            ->where('order_type', $orderType);
        // Request::int 缺省哨兵 -1：0 是合法筛选值（进行中），不能用 0 当「未传」
        $status = Request::int('status', -1);
        if ($status >= 0) {
            $query->where('order_status', $status);
        }
        $total = (int) $query->count();
        $rows  = $query->order('createtime', 'desc')->order('id', 'desc')
            ->page($p, $s)->select()->toArray();

        return Response::page(
            array_map(static fn($r) => TradeFormatter::order($r), $rows),
            $total, $s, $p
        );
    }

    // ==================== 合成 /盲盒 ====================

    /** POST /synthesis/themeinfo 合成主题（🔴 必须 POST，GET 无效） */}
