<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 网关工厂：按支付方式取实现
 *
 * 分发规则：
 *   - payment_channel 表中 code=$payWay 且 is_open=1 的渠道才可用；
 *     目前唯一已实现通道是 wallet（钱包余额），其余一律 NullGateway。
 *   - NullGateway 不是降级，而是如实反映「没有收单能力」——fail-closed。
 *
 * 接入真实外部通道（huifu/sandpay…）时在此追加实现映射：
 *   if ($payWay === 'huifu') return new HuifuGateway($ch);
 *   回调落地走 /order/pay/notify/:code → SettlementService::dispatch。
 */
final class Gateways
{
    public static function make(string $payWay): PaymentGateway
    {
        if ($payWay === '') {
            return new NullGateway();
        }

        $open = Db::name('payment_channel')
            ->where('code', $payWay)
            ->where('is_open', 1)
            ->count();

        if ($open > 0) {
            return match ($payWay) {
                'wallet' => new WalletGateway(),
                default  => new NullGateway(), // 渠道已配置但实现未接入，明确拒绝
            };
        }

        return new NullGateway();
    }
}
