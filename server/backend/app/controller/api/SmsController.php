<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Request;
use app\common\Response;
use app\model\SmsCode;
use app\service\Audit;
use app\service\SmsCodeService;

/**
 * 短信验证码控制器 —— 发送验证码
 *
 * ✅ v2.7（2026-10-19）：由【MOCK】升级为【DB】真实实现。
 *
 * 🔴 原 MOCK 实现的安全漏洞（本类彻底修复）：
 *      return Response::ok([
 *          'mobile' => $mobile,
 *          'event'  => $event,
 *          'expire' => 60,
 *          'code'   => '123456',   // ← 验证码直接回传给前端！
 *      ]);
 *
 *    任何人调一次 sms/send 就能看到验证码，注册/改密的验证码形同虚设；
 *    更糟的是它写死 '123456'，全局一个码。
 *
 *    现在的实现：验证码随机生成 → HMAC 存库 → 走短信通道投递。
 *    **HTTP 响应体里没有任何明文验证码**。
 *
 * ⚠️ 改动后必须跑 php tools/contract/run.php，退出码非 0 禁止提交。
 *
 * @package app\controller\api
 */
class SmsController
{
    /**
     * POST /sms/send 发送验证码
     *
     * 入参：mobile / event（register | resetpwd）
     *
     * 🔴 响应只含 {mobile, event, expire}，绝不含 code。
     *    前端只需要知道「发出去了」和「60 秒后可重发」。
     */
    public function send()
    {
        $mobile = Request::str('mobile');
        $event  = Request::str('event');

        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return Response::fail('手机号格式错误');
        }
        if (!in_array($event, SmsCode::SCENES, true)) {
            return Response::fail('不支持的验证场景');
        }

        $ip     = (string) request()->ip();
        $result = SmsCodeService::send($mobile, $event, $ip);

        if (!$result['ok']) {
            Audit::write('sms_send', 0, [
                'mobile' => $mobile,
                'event'  => $event,
                'result' => 'failed',
                'reason' => $result['msg'],
            ], false);
            return Response::fail($result['msg']);
        }

        Audit::write('sms_send', 0, [
            'mobile' => $mobile,
            'event'  => $event,
            'result' => 'success',
        ], true);

        // 🔴 响应体绝不含明文验证码 —— 这是本端点最重要的安全约束
        return Response::ok([
            'mobile' => $mobile,
            'event'  => $event,
            'expire' => SmsCode::SEND_INTERVAL,
        ], $result['msg']);
    }
}
