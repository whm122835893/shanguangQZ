<?php
declare(strict_types=1);

namespace app\service;

/**
 * PayNotify —— 渠道异步回调签名（HMAC-SHA256）
 *
 * 签名口径（与渠道接入文档保持一致的唯一事实源）：
 *   sign = hash_hmac('sha256', order_sn|pay_no|amount, notify_secret)
 *   amount 用渠道回调原始字符串，不做数值归一（避免 "10" vs "10.00" 验签歧义）
 *
 * 验签必须用 hash_equals 恒定时间比较，防时序侧信道。
 */
final class PayNotify
{
    public const REQUIRED_FIELDS = ['order_sn', 'pay_no', 'amount'];

    public static function sign(array $payload, string $secret): string
    {
        return hash_hmac(
            'sha256',
            ($payload['order_sn'] ?? '') . '|' . ($payload['pay_no'] ?? '') . '|' . ($payload['amount'] ?? ''),
            $secret
        );
    }

    /** @return bool 签名是否有效（缺字段/缺签一律无效） */
    public static function verify(array $payload, string $secret): bool
    {
        foreach (self::REQUIRED_FIELDS as $f) {
            if (!isset($payload[$f]) || (string) $payload[$f] === '') {
                return false;
            }
        }
        if ($secret === '') {
            return false;
        }

        return hash_equals(self::sign($payload, $secret), (string) ($payload['sign'] ?? ''));
    }
}
