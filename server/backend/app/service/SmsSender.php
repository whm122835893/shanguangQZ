<?php
declare(strict_types=1);

namespace app\service;

/**
 * 短信发送通道接口
 *
 * 🔴 为什么支付与短信要用同一套抽象思路，但结论不同：
 *
 *    支付：没有真实通道就**拒绝**（fail-closed）。
 *      理由 —— 支付成功意味着钱扣了，不许有假成功。
 *
 *    短信：没有真实通道时**仍然可以发**，但必须让开发者能取到码。
 *      理由 —— 短信本身不涉及资金，失败的代价是「用户收不到验证码」，
 *      而不是「用户以为付了钱」。若这里也 fail-closed，
 *      本地开发与自动化测试将完全无法注册，整个账号体系无法推进。
 *
 *    但「能发」不等于「安全地发」：LocalChannel 把验证码写入日志，
 *    绝不能出现在 HTTP 响应体里 —— 原站的 MOCK 实现把 '123456'
 *    写死并回传给前端，等于把验证码公开给所有人。
 *
 * @package app\service
 */
interface SmsSender
{
    /**
     * 发送验证码
     *
     * @param string $mobile 手机号
     * @param string $code   6 位明文验证码（仅供发送，不得落库、不得回传前端）
     * @param string $scene  场景：register / resetpwd
     * @return SmsResult
     */
    public function send(string $mobile, string $code, string $scene): SmsResult;
}
