<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;
use app\service\OrderService;

/**
 * 下单域控制器（v2.7 从 PayController 拆出）
 *
 * 🔴 为什么拆：PayController 在接入真实下单后达 324 行，破 300 红线。
 *    按本项目既有做法（先删死代码、再按域拆），把「创建订单」这一类
 *    端点整体移出，PayController 只留「收单/支付」职责。
 *
 * 本类所有端点遵循同一条底线：**不产生假成功**。
 * 没有真实价格来源就不定价，没有真实收单通道就不返回支付成功。
 */
class PayOrderController
{
    use HasAuth;

    /**
     * 汇付开户手续费订单
     *
     * 🔴 原站实测（raw/order_pay_createhuifuaccountorder.json）：
     *    本账号已付过开户费，返回「您已经支付开户手续费，请勿重复操作」。
     *    开户涉及真实资金与实名，本机无通道，**不具备实现成功路径的条件**，
     *    因此如实返回该业务错误，不走 makeOrder() 的假成功。
     */
    public function createHuifuAccountOrder()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::fail('您已经支付开户手续费，请勿重复操作');
    }

    /**
     * 市场下单（CreateMarketOrder / CreateMarketOrderRush / CreateOrder 同源）
     *
     * 🔴 为什么不再用 trait 的 makeOrder()：
     *    makeOrder() 返回 `order_id => random_int(20000,29999)` 且不落库，
     *    即「下单成功但订单列表查不到」的假成功。现改走 OrderService，
     *    服务端算价 + 幂等防重 + 事务落库。
     *
     * ⚠️ 响应结构 `{order_sn, order_id, time}` 取自同族实测端点
     *    （createVipOrder / createBegBuyOrder 键序一致），属归纳依据——
     *    order/pay/* 整组在原站当前返回空响应，无法直接取证。
     *    前端当前**无页面调用**这三个端点（仅 api/index.js 导出），
     *    因此该结构调整不影响任何页面。
     */
    public function createMarketOrder()
    {
        return $this->createRealOrder();
    }

    public function createMarketOrderRush()
    {
        return $this->createRealOrder();
    }

    public function createOrder()
    {
        return $this->createRealOrder();
    }

    /** 三个下单端点的共同实现 */
    private function createRealOrder(): \think\response\Json
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $res = OrderService::createMarketOrder(
            (int) $user['id'],
            Request::int('goods_id') ?: Request::int('collection_id'),
            Request::int('num') ?: Request::int('number'),
            Request::str('pay_way')
        );
        if (! $res['ok']) {
            return Response::fail($res['msg']);
        }

        $order = $res['order'];

        return Response::ok([
            'order_sn' => (string) $order['order_sn'],
            'order_id' => (string) $order['id'],   // ⚠️ string，同族实测形态
            'time'     => (string) OrderService::PAY_WINDOW,
        ], Msg::CREATE_ORDER);
    }
}
