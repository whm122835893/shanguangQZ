<?php
declare(strict_types=1);

namespace app\service;

use app\common\Msg;

/**
 * 空网关：本机未接入任何真实支付通道时的默认实现
 *
 * 行为恒定 —— 拒绝，并给出明确原因。绝不返回成功。
 * 这是整套支付链路的安全底线：宁可让用户看到「支付通道未接入」，
 * 也不能让他以为付成功了。
 */
final class NullGateway implements PaymentGateway
{
    public function charge(array $order, string $payWay): PayResult
    {
        return PayResult::rejected(Msg::PAY_CHANNEL_NOT_READY);
    }
}
