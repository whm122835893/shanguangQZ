<?php
declare(strict_types=1);

namespace app\service;

/**
 * 短信发送结果
 *
 * 与 PayResult 同构：只允许两个出口，不留「半成功」中间态。
 *
 * ⚠️ 与 PayResult 的关键差异：
 *    短信结果**可以携带明文验证码**，但仅限本地开发通道 ——
 *    它只在服务端内部流转（日志/测试读取），绝不允许进入 HTTP 响应体。
 *    生产通道的实现必须返回不含明文的实例。
 *
 * @package app\service
 */
final class SmsResult
{
    private function __construct(
        private bool $ok,
        private string $message,
        private string $channel,
        private ?string $plainCode = null
    ) {
    }

    /** 发送成功（生产通道，无明文） */
    public static function sent(string $channel, string $message = '验证码已发送'): self
    {
        return new self(true, $message, $channel, null);
    }

    /** 发送成功（本地通道，带明文供开发/测试取用） */
    public static function sentWithCode(string $channel, string $plainCode, string $message = '验证码已发送'): self
    {
        return new self(true, $message, $channel, $plainCode);
    }

    /** 发送失败 */
    public static function failed(string $message, string $channel = ''): self
    {
        return new self(false, $message, $channel, null);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function channel(): string
    {
        return $this->channel;
    }

    /**
     * 明文验证码
     *
     * 🔴 只有本地开发通道的实现才允许返回非 null。
     *    调用方（Controller）**绝不能**把它放进响应体 ——
     *    那是原站 MOCK 实现最大的安全漏洞。
     */
    public function plainCode(): ?string
    {
        return $this->plainCode;
    }
}
