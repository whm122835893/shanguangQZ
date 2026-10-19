<?php
declare(strict_types=1);

namespace app\service;

/**
 * 短信通道选择器
 *
 * 选择规则（按优先级）：
 *   1. env('SMS_CHANNEL') = 'local'  → 本地通道（写日志）
 *   2. env('SMS_CHANNEL') = 'none'  → 显式关闭，任何发送都失败
 *   3. env('SMS_CHANNEL') = 其他值   → 该通道尚未实现，**失败**而非静默回落
 *   4. 未配置                        → 按 'local' 处理，但若 APP_DEBUG=false 则拒绝
 *
 * 🔴 规则 4 的兜底拒绝是本类的安全底线：
 *    本地通道把验证码写进日志，日志在生产环境没人看，
 *    用户永远收不到验证码 —— 但接口却返回「发送成功」。
 *    这就是另一种形式的「假成功」，同样会误导用户。
 *    因此生产环境（APP_DEBUG=false）未显式配置通道时，
 *    一律明确失败，不假装成功。
 *
 * ⚠️ 接入真实服务商（如腾讯云 SMS）时：
 *    新建 implements SmsSender 的类，在此登记分发规则即可，
 *    其余代码（Service/Controller/契约用例）无需改动。
 *
 * @package app\service
 */
final class SmsChannels
{
    /**
     * 取当前应使用的通道
     */
    public static function for(): SmsSender
    {
        $channel = strtolower(trim((string) env('SMS_CHANNEL', '')));

        if ($channel === 'none') {
            return new NullSmsChannel();
        }
        if ($channel === 'local') {
            return new LocalSmsChannel();
        }
        if ($channel !== '') {
            // 配置了但没实现 —— 明确失败，绝不静默回落到 local
            return new NullSmsChannel();
        }

        // 未配置：开发环境用本地通道，生产环境拒绝
        $debug = (bool) env('APP_DEBUG', false);
        return $debug ? new LocalSmsChannel() : new NullSmsChannel();
    }

    /**
     * 当前环境是否为「可把明文码交给开发者」的模式
     *
     * 🔴 仅供测试与开发工具使用。HTTP 响应体在任何情况下
     *    都不得包含明文验证码 —— 本方法只控制「是否写日志/暴露给测试」，
     *    不控制响应内容。
     */
    public static function isLocalMode(): bool
    {
        return self::for() instanceof LocalSmsChannel;
    }
}
