<?php
declare(strict_types=1);

namespace app\service;

/**
 * 支付领域异常（结算拒绝 / 单据不存在 / 金额不符等统一走本类）
 */
class PaymentException extends \RuntimeException
{
}
