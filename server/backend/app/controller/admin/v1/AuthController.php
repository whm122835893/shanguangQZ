<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\common\Request;
use app\service\AdminAuthException;
use app\service\Audit;
use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\JwtService;
use app\service\admin\Resp;
use app\service\admin\Totp;
use think\facade\Db;

/**
 * AuthController —— 认证模块 /auth（契约 §8.1，7 个接口）
 *
 * 安全红线：
 *   1. 用户枚举防护：账号不存在也做 Argon2id 校验，文案统一「账号或密码错误」
 *   2. 连续失败 5 次锁定 15 分钟（admin_user.locked_until）
 *   3. 2FA：管理员已绑定 twofa_secret 时登录必须携带有效 TOTP
 *   4. 登录日志 admin_login_log 全量落库（成功/失败）
 *
 * @package app\controller\admin\v1
 */
final class AuthController
{
    use V1;

    /** POST /auth/login */
    public function login()
    {
        $username = Request::str('username');
        $password = Request::str('password');
        if ($username === '' || $password === '') {
            throw new AdminException('账号或密码错误');
        }

        $admin = Db::name('admin_user')->where('username', $username)->find();
        $ip    = self::clientIp();
        $ua    = mb_substr((string) request()->header('user-agent', ''), 0, 500);

        $logFail = function (int $adminId, string $reason) use ($username, $ip, $ua) {
            Db::name('admin_login_log')->insert([
                'admin_id' => $adminId, 'username' => $username, 'ip' => $ip,
                'user_agent' => $ua, 'is_success' => 0, 'fail_reason' => $reason, 'createtime' => time(),
            ]);
        };

        if ($admin === null) {
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$dummy$dummyhashvaluepadding00');
            $logFail(0, '账号不存在');
            Audit::write('admin_login', 0, ['username' => $username, 'result' => 'no_user'], false);
            throw new AdminException('账号或密码错误');
        }

        $now = time();
        if ((int) $admin['locked_until'] > $now) {
            $logFail((int) $admin['id'], 'locked');
            throw new AdminException('密码错误次数过多，账号已临时锁定，请稍后再试', Resp::FORBIDDEN);
        }

        if (!password_verify($password, (string) $admin['password_hash'])) {
            $fails = (int) $admin['login_fail_count'] + 1;
            $update = ['login_fail_count' => $fails];
            if ($fails >= 5) {
                $update['locked_until']     = $now + 15 * 60;
                $update['login_fail_count'] = 0;
            }
            Db::name('admin_user')->where('id', (int) $admin['id'])->update($update);
            $logFail((int) $admin['id'], 'bad_password');
            Audit::write('admin_login', (int) $admin['id'], ['result' => 'bad_password'], false);
            throw new AdminException('账号或密码错误');
        }

        if ((int) $admin['status'] !== 1) {
            JwtService::revokeRefresh((int) $admin['id']);
            Db::name('admin_user')->where('id', (int) $admin['id'])->update(['token_hash' => null, 'token_expire' => 0]);
            $logFail((int) $admin['id'], 'disabled');
            throw new AdminException('账号已禁用，请联系超级管理员', Resp::FORBIDDEN);
        }

        // 2FA：已绑定则必须验证（TOTP）
        if ((string) ($admin['twofa_secret'] ?? '') !== '') {
            $code = Request::str('totp_code');
            if (!Totp::verify((string) $admin['twofa_secret'], $code)) {
                $logFail((int) $admin['id'], 'totp_failed');
                throw new AdminException('两步验证码错误');
            }
        }

        $tokens = JwtService::issue($admin);
        Db::name('admin_login_log')->insert([
            'admin_id' => (int) $admin['id'], 'username' => (string) $admin['username'], 'ip' => $ip,
            'user_agent' => $ua, 'is_success' => 1, 'fail_reason' => '', 'createtime' => time(),
        ]);
        Audit::write('admin_login', (int) $admin['id'], ['result' => 'ok', 'ip' => $ip], true);

        return Resp::ok([
            'token'         => $tokens['token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in'    => $tokens['expires_in'],
            'admin'         => [
                'id'       => (int) $admin['id'],
                'username' => (string) $admin['username'],
                'real_name' => (string) ($admin['real_name'] ?? ''),
                'role'     => (int) ($admin['role'] ?? 1),
                'avatar'   => (string) ($admin['avatar'] ?? ''),
            ],
        ]);
    }

    /** POST /auth/logout */
    public function logout()
    {
        $admin = self::guard('');
        JwtService::revokeRefresh((int) $admin['id']);
        // 旧会话 token 一并吊销
        Db::name('admin_user')->where('id', (int) $admin['id'])->update(['token_hash' => null, 'token_expire' => 0]);
        Guard::audit('admin_logout', (int) $admin['id'], (int) $admin['id']);
        return Resp::ok(['success' => true]);
    }

    /** POST /auth/refresh */
    public function refresh()
    {
        $refreshToken = Request::str('refresh_token');
        $result = JwtService::rotate($refreshToken);
        if ($result === null) {
            throw new AdminException('refresh_token 无效或已过期', Resp::UNAUTHORIZED);
        }
        $admin = $result['admin'];
        unset($admin['password_hash'], $admin['token_hash'], $admin['refresh_token_hash']);
        return Resp::ok([
            'token'         => $result['token'],
            'refresh_token' => $result['refresh_token'],
            'expires_in'    => $result['expires_in'],
        ]);
    }

    /** GET /auth/me */
    public function me()
    {
        $admin = self::guard('');
        return Resp::ok([
            'id'          => (int) $admin['id'],
            'username'    => (string) $admin['username'],
            'real_name'   => (string) ($admin['real_name'] ?? ''),
            'role'        => (int) ($admin['role'] ?? 1),
            'avatar'      => (string) ($admin['avatar'] ?? ''),
            'phone'       => self::maskPhone((string) ($admin['phone'] ?? '')),
            'email'       => (string) ($admin['email'] ?? ''),
            'twofa_enabled' => (string) (Db::name('admin_user')->where('id', (int) $admin['id'])->value('twofa_secret') ?? '') !== '',
            'permissions' => Guard::permissionsOf($admin),
        ]);
    }

    /** PUT /auth/password */
    public function password()
    {
        $admin   = self::guard('');
        $old     = Request::str('old_password');
        $new     = Request::str('new_password');
        if ($old === '' || $new === '') {
            throw new AdminException('新旧密码不能为空');
        }
        if (mb_strlen($new) < 8) {
            throw new AdminException('新密码长度至少 8 位');
        }
        $row = Db::name('admin_user')->where('id', (int) $admin['id'])->find();
        if ($row === null || !password_verify($old, (string) $row['password_hash'])) {
            throw new AdminException('原密码错误');
        }
        Db::name('admin_user')->where('id', (int) $admin['id'])->update([
            'password_hash' => password_hash($new, PASSWORD_ARGON2ID),
            'updatetime'    => time(),
        ]);
        Guard::audit('admin_password_change', (int) $admin['id'], (int) $admin['id']);
        return Resp::ok(['success' => true]);
    }

    /** POST /auth/2fa/setup —— 生成密钥（未确认前不落库生效） */
    public function twofaSetup()
    {
        $admin  = self::guard('');
        $secret = Totp::generateSecret();
        // 临时保存：verify 成功后启用（草稿存 updatetime 前先清空判断）
        Db::name('admin_user')->where('id', (int) $admin['id'])->update([
            'twofa_secret_draft' => $secret, 'updatetime' => time(),
        ]);
        return Resp::ok([
            'secret'      => $secret,
            'qr_code_url' => Totp::otpauthUrl($secret, (string) $admin['username']),
        ]);
    }

    /** POST /auth/2fa/verify */
    public function twofaVerify()
    {
        $admin = self::guard('');
        $row   = Db::name('admin_user')->where('id', (int) $admin['id'])->find();
        $draft = (string) ($row['twofa_secret_draft'] ?? '');
        if ($draft === '') {
            throw new AdminException('请先获取 2FA 密钥');
        }
        if (!Totp::verify($draft, Request::str('code'))) {
            throw new AdminException('两步验证码错误');
        }
        Db::name('admin_user')->where('id', (int) $admin['id'])->update([
            'twofa_secret' => $draft, 'twofa_secret_draft' => '', 'updatetime' => time(),
        ]);
        Guard::audit('admin_2fa_enable', (int) $admin['id'], (int) $admin['id']);
        return Resp::ok(['success' => true]);
    }

    // --------------------------------------------------------

    private static function clientIp(): string
    {
        $ip = request()->ip();
        return is_string($ip) ? $ip : '0.0.0.0';
    }
}
