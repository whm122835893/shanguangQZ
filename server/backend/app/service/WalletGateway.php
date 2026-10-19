<?php
declare(strict_types=1);

namespace app\service;

/**
 * WalletGateway —— 钱包余额支付通道
 *
 * 🔴 这是本项目第一条**真实**支付通道：扣的是用户钱包里的真钱，
 *    资金流必须且只能走 WalletService 账本（biz_type=order_pay，
 *    biz 键 = order_sn，天然幂等 —— 同单二次 charge 会被账本拒绝）。
 *
 * fail-closed 口径不变：
 *   - 余额不足 / 账本幂等冲突 → PayResult::rejected（可读原因）
 *   - 绝不返回凭空构造的成功；paid 只代表「真金白银已扣」
 *
 * 异步回调：钱包支付同步扣款即结算（settleOrder 的卖家入账由
 * /order/pay/notify 回调驱动，或直接由 doPay 后续流程驱动）。
 */
final class WalletGateway implements PaymentGateway
{
    public function charge(array $order, string $payWay): PayResult
    {
        $userId  = (int) ($order['user_id'] ?? 0);
        $orderSn = (string) ($order['order_sn'] ?? '');
        $amount  = (string) ($order['total_price'] ?? '0.00');

        if ($userId <= 0 || $orderSn === '') {
            return PayResult::rejected('订单信息不完整');
        }

        try {
            WalletService::debit($userId, $amount, 'order_pay', $orderSn, '钱包余额支付');
        } catch (WalletException $e) {
            return PayResult::rejected($e->getMessage());
        }

        return PayResult::paid('WAL' . date('YmdHis') . random_int(100000, 999999));
    }
}
