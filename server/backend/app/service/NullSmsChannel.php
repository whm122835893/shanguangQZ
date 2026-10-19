<?php
declare(strict_types=1);

namespace app\service;

/**
 * 空短信通道 —— 恒定失败
 *
 * 与支付域的 NullGateway 同构：绝不假成功。
 *
 * 用途：
 *   1. 显式关闭短信（env SMS_CHANNEL=none）
 *   2. 配置了未实现的通道名
 *   3. 生产环境未配置通道时的兜底
 *
 * ⚠️ 这里的失败文案要让用户看得懂「为什么收不到验证码」，
 *    但**不能**让攻击者据此判断哪些手机号注册过本站 ——
 *    本通道不查库，不涉及用户存在性。
 *
 * @package app\service
 */
final class NullSmsChannel implements SmsSender
{
    public const CHANNEL = 'none';

    public function send(string $mobile, string $code, string $scene): SmsResult
    {
        return SmsResult::failed(
            '短信通道未接入，暂时无法发送验证码',
            self::CHANNEL
        );
    }
}
