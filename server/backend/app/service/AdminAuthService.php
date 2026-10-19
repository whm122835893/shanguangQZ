<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * AdminAuthService —— 管理端鉴权唯一入口（P0 最小集）
 *
 * 四条红线（tools/contract/admin_test.php 逐条锁定）：
 *   1. Argon2id 校验；错误文案与「账号不存在」一致（防管理员枚举）
 *   2. token 明文只下发一次，库内只存 sha256；有效期 12 小时（短于 C 端 30 天）
 *   3. 禁用管理员：拒绝登录 + 存量 token 立即失效
 *   4. 登出立即吊销；垃圾/空 token verify 一律 null
 *
 * 单会话设计：token_hash 单列，新登录顶掉旧会话 —— 管理端不追求多设备，
 * 吊销成本最低。RBAC（多角色/权限码/审批流）属 P2，此处刻意不做。
 */
final class AdminAuthService
{
    public const TTL = 12 * 3600;
    public const GENERIC_ERROR = '账号或密码错误';

    /** @return array{token:string, expiretime:int, admin:array} */
    public static function login(string $username, string $password, string $ip): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new AdminAuthException(self::GENERIC_ERROR);
        }

        $admin = Db::name('admin_user')->where('username', $username)->find();

        // 用户枚举防护：与 C 端登录同口径 —— 不存在也做一次 Argon2id 校验
        if ($admin === null) {
            password_verify($password, '$argon2id$v=19$m=65536,t=4,p=1$dummy$dummyhashvaluepadding00');
            Audit::write('admin_login', 0, ['username' => $username, 'result' => 'no_user'], false);
            throw new AdminAuthException(self::GENERIC_ERROR);
        }

        if (!password_verify($password, (string) $admin['password_hash'])) {
            Audit::write('admin_login', (int) $admin['id'], ['result' => 'bad_password'], false);
            throw new AdminAuthException(self::GENERIC_ERROR);
        }

        if ((int) $admin['status'] !== 1) {
            // 禁用：拒绝登录且立刻吊销存量会话
            Db::name('admin_user')->where('id', $admin['id'])->update(['token_hash' => null, 'token_expire' => 0, 'updatetime' => time()]);
            Audit::write('admin_login', (int) $admin['id'], ['result' => 'disabled'], false);
            throw new AdminAuthException('账号已禁用，请联系超级管理员');
        }

        $plain = bin2hex(random_bytes(20));                       // 40 hex，明文只出现一次
        $expire = time() + self::TTL;
        Db::name('admin_user')->where('id', $admin['id'])->update([
            'token_hash'      => hash('sha256', $plain),          // 库内只存哈希
            'token_expire'    => $expire,
            'last_login_time' => time(),
            'last_login_ip'   => $ip,
            'updatetime'      => time(),
        ]);
        Audit::write('admin_login', (int) $admin['id'], ['result' => 'ok', 'ip' => $ip], true);

        return [
            'token'      => $plain,
            'expiretime' => $expire,
            'admin'      => ['id' => (int) $admin['id'], 'username' => (string) $admin['username']],
        ];
    }

    /** 有效返回管理员行（不含敏感列），无效/过期/禁用返回 null */
    public static function verify(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $admin = Db::name('admin_user')
            ->where('token_hash', hash('sha256', $token))
            ->find();
        if ($admin === null) {
            return null;
        }
        if ((int) $admin['token_expire'] < time() || (int) $admin['status'] !== 1) {
            return null;
        }

        unset($admin['password_hash'], $admin['token_hash']);
        return $admin;
    }

    /** 登出：立即吊销当前会话 */
    public static function logout(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        return Db::name('admin_user')
            ->where('token_hash', hash('sha256', $token))
            ->update(['token_hash' => null, 'token_expire' => 0, 'updatetime' => time()]) > 0;
    }
}
