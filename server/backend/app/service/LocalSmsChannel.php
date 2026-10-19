<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * 本地短信通道 —— 验证码写入日志，不真正发短信
 *
 * 🔴 存在的意义：
 *    本机没有短信服务商账号（腾讯云/阿里云/汇付等），但账号体系
 *    （注册/改密）必须能跑通、能测试。真实通道接入前，
 *    本通道保证「验证码生成 → 存储 → 校验」的完整链路是真实的，
 *    只把「投递到手机」这一步换成了写日志。
 *
 * 🔴 与原站 MOCK 实现的本质区别：
 *    原站：验证码写死 '123456'，并**放进 HTTP 响应体回传给前端**。
 *          任何人调一次 sms/send 就能看到验证码，注册形同虚设。
 *    本实现：验证码是**每次随机生成**的，服务端存 HMAC 哈希，
 *          明文只写进服务端日志，**绝不出现在 HTTP 响应里**。
 *
 * ⚠️ 生产环境必须切换到真实通道：
 *    见 SmsChannels::for() 的选择逻辑 —— 当 env('SMS_CHANNEL')
 *    配置为具体服务商时，本通道不会被使用。
 *
 * @package app\service
 */
final class LocalSmsChannel implements SmsSender
{
    public const CHANNEL = 'local';

    public function send(string $mobile, string $code, string $scene): SmsResult
    {
        // 🔴 只写日志，绝不返回值给 HTTP 层。
        //    日志文件在服务端本地，不对外暴露；且用 notice 级便于检索。
        //    ⚠️ ThinkPHP 的 Log context 只做 {占位符} 替换，消息里没有占位符
        //       时 context 会被整个丢弃 —— 因此必须把数据序列化进消息本身，
        //       否则本地开发永远拿不到明文验证码（注册链路断死）。
        Log::notice('sms local channel ' . json_encode([
            'mobile' => $mobile,
            'scene'  => $scene,
            'code'   => $code,   // 本地开发通道专用，生产通道不得有此项
        ], JSON_UNESCAPED_UNICODE));

        return SmsResult::sentWithCode(self::CHANNEL, $code);
    }
}
