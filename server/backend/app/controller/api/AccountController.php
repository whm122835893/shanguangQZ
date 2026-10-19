<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Request;
use app\common\Response;
use app\service\AccountService;
use app\service\PasswordPolicy;
use app\service\Audit;

/**
 * 账号域控制器 —— 注册 / 找回密码
 *
 * 🔴 为什么从 TradeController 拆出来：
 *    这两个端点挂在 user/ 路径下（user/register、user/resetpwd），
 *    属于账号生命周期，与交易域无关。v2.3 大拆分时被顺手留在了
 *    TradeController，导致该文件涨到 354 行触发 ≤300 行 P0 红线。
 *    按域归位的同时解决行数问题。
 *
 * ✅ v2.7（2026-10-19）：两端点由【MOCK】升级为【DB】真实实现。
 *    原实现的问题（已修复）：
 *      1. register 不落库 —— 返回 MockData::loginResult() 假用户
 *      2. resetPwd 不改密 —— 只回显 mobile，密码原封不动
 *      3. 两者都用写死的 '123456' 校验验证码 —— 等于没有验证码
 *    现在的链路：验证码走 SmsCodeService（随机生成 + HMAC 存储 +
 *    一次性消费 + 频率限制），密码走 Argon2id，改密吊销旧 token。
 *
 * ⚠️ 改动后必须跑 php tools/contract/run.php，退出码非 0 禁止提交。
 *
 * @package app\controller\api
 */
class AccountController
{
    /**
     * POST /user/register 注册
     *
     * 入参：mobile / password / code / inviter_code（可空）
     *
     * ⚠️ 响应中的 userinfo 遵循 login 契约（26 字段），
     *    不含明文密码、不含密码哈希。
     */
    public function register()
    {
        $mobile   = Request::str('mobile');
        $password = Request::str('password');
        $code     = Request::str('code');
        $inviter  = Request::str('inviter_code');
        $ip       = (string) request()->ip();

        $result = AccountService::register($mobile, $password, $code, $inviter, $ip);

        if (!$result['ok']) {
            Audit::write('user_register', 0, [
                'mobile' => $mobile,
                'result' => 'failed',
                'reason' => $result['msg'],
            ], false);
            return Response::fail($result['msg']);
        }

        $user    = $result['user'];
        $payload = PasswordPolicy::issueLoginPayload($user, 'register', $ip);

        Audit::write('user_register', (int) $user['id'], [
            'mobile' => $mobile,
            'result' => 'success',
        ], true);

        return Response::ok(['userinfo' => $payload], $result['msg']);
    }

    /**
     * POST /user/resetpwd 重置密码
     *
     * 入参：mobile / code / password / repassword（可选）
     *
     * 🔴 成功后该用户的全部登录态失效（token 被吊销），
     *    需要重新登录 —— 这是改密的安全语义。
     */
    public function resetPwd()
    {
        $mobile     = Request::str('mobile');
        $code       = Request::str('code');
        $password   = Request::str('password');
        $repassword = Request::str('repassword');

        $result = AccountService::resetPassword($mobile, $code, $password, $repassword);

        if (!$result['ok']) {
            Audit::write('user_resetpwd', 0, [
                'mobile' => $mobile,
                'result' => 'failed',
                'reason' => $result['msg'],
            ], false);
            return Response::fail($result['msg']);
        }

        Audit::write('user_resetpwd', 0, [
            'mobile' => $mobile,
            'result' => 'success',
        ], true);

        return Response::ok(['mobile' => $mobile], $result['msg']);
    }
}
