<?php
declare(strict_types=1);

namespace app\service;

/**
 * 支付结果值对象
 *
 * 🔴 paid=true 只允许在真实通道确认收款后置位。
 *    全代码库不存在任何「凭空构造 paid=true」的路径：
 *    paid() 是唯一入口，且只应由真实网关实现调用。
 */
final class PayResult
{
    private function __construct(
        public readonly bool   $paid,
        public readonly string $payNo,
        public readonly string $msg
    ) {
    }

    /** 真实通道确认收款后调用 */
    public static function paid(string $payNo): self
    {
        return new self(true, $payNo, '');
    }

    /** 未收款（通道未接入 / 被拒 / 余额不足等），必须带可读原因 */
    public static function rejected(string $msg): self
    {
        return new self(false, '', $msg);
    }
}
