<?php
declare(strict_types=1);

namespace app\service;

/**
 * 支付网关接口 —— 收口「这笔钱到底有没有真的收上来」
 *
 * 🔴 为什么必须有这一层（2026-10-19 建立，起因是线上资损风险）：
 *    此前 `OrderController::doPay()` 与 `PayController::batchDoPay()`
 *    直接返回 `code=1` + `pay_no = 'MOCKPAY' . random_int(...)`，
 *    即**伪造支付成功**。而 `server.cjs` 的 `API_UPSTREAM` 默认指向本地
 *    Mock，按现状发布就是「线上用户付款看到成功，但没订单、没资金流」。
 *    假成功比报错危险得多：报错用户会重试，假成功用户会一直等货。
 *
 *    行业最优做法：**支付结果只能由真实通道的返回决定**。
 *    通道没接入时，唯一正确的响应是明确失败，而不是假成功。
 *
 * 【接入真实通道时怎么做】
 *    1. 实现本接口（如 HuifuGateway），charge() 内调真实收单接口
 *    2. 在 Gateways::make() 里按 pay_way 返回对应实现
 *    3. payment_channel 表灌入真实渠道（code / mer_cust_id 等）
 *    在此之前一律走 NullGateway，行为是「明确拒绝」。
 */
interface PaymentGateway
{
    /**
     * 对一笔订单发起收款
     *
     * @param array  $order  订单行（至少含 order_sn / total_price / user_id）
     * @param string $payWay 支付方式代码
     */
    public function charge(array $order, string $payWay): PayResult;
}
