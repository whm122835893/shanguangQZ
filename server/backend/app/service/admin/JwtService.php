<?php
declare(strict_types=1);

namespace app\service\admin;

use think\facade\Db;

/**
 * AdminJwt —— 管理端 JWT（access）+ Refresh Token 双令牌
 *
 * 契约（司南后台提示词 v3.1 §1.2）：
 *   - Header Authorization: Bearer <admin_jwt_token>
 *   - 过期通过 refresh_token 续期
 *
 * 设计决策：
 *   1. HS256 纯 PHP 实现（hash_hmac），不引入 firebase/jwt 依赖 —— 沙箱无网
 *   2. 密钥 env ADMIN_JWT_SECRET，与 C 端 JWT（UserToken）完全隔离（§1.3 红线）
 *   3. access TTL 2 小时；refresh 随机 48 字节、库存 sha256、7 天有效、旋转使用
 *   4. 吊销：登出清 refresh_token_hash；access 短时效自然过期
 *      （管理端可接受 ≤2h 的吊销延迟，换取无状态校验）
 *
 * @package app\service\admin
 */
final class JwtService
{
    public const ACCESS_TTL  = 2 * 3600;
    public const REFRESH_TTL = 7 * 24 * 3600;

    private static function secret(): string
    {
        $secret = env('ADMIN_JWT_SECRET', '');
        if (!is_string($secret) || $secret === '') {
            // 开发环境兜底密钥；生产必须配置 32+ 字节随机值
            $secret = 'dev-admin-jwt-secret-2f8a1c3e9b7d4a6c8e0b2d4f';
        }
        return $secret;
    }

    // --------------------------------------------------------
    // JWT HS256 编解码
    // --------------------------------------------------------

    /** @param array<string,mixed> $claims */
    public static function encode(array $claims, int $ttl): string
    {
        $now    = time();
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $claims += ['iat' => $now, 'exp' => $now + $ttl, 'iss' => 'shanguang-admin'];

        $seg = [
            self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::b64url(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ];
        $sig = hash_hmac('sha256', $seg[0] . '.' . $seg[1], self::secret(), true);
        $seg[] = self::b64url($sig);
        return implode('.', $seg);
    }

    /** 校验失败返回 null；成功返回 claims */
    public static function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;

        $expected = self::b64url(hash_hmac('sha256', $h . '.' . $p, self::secret(), true));
        if (!hash_equals($expected, $s)) {
            return null;
        }
        $claims = json_decode(self::b64urlDecode($p), true);
        if (!is_array($claims) || (int) ($claims['exp'] ?? 0) < time()) {
            return null;
        }
        if (($claims['typ'] ?? '') !== 'access') {
            return null;
        }
        return $claims;
    }

    // --------------------------------------------------------
    // Refresh Token：DB 轮换存储
    // --------------------------------------------------------

    /**
     * 签发 access + refresh；refresh 写库（sha256），明文只出现一次
     * @return array{token:string, refresh_token:string, expires_in:int}
     */
    public static function issue(array $admin): array
    {
        $now = time();
        $access = self::encode([
            'typ'  => 'access',
            'sub'  => (int) $admin['id'],
            'role' => (int) ($admin['role'] ?? 1),
            'name' => (string) $admin['username'],
        ], self::ACCESS_TTL);

        $refresh = bin2hex(random_bytes(48));
        Db::name('admin_user')->where('id', (int) $admin['id'])->update([
            'refresh_token_hash' => hash('sha256', $refresh),
            'refresh_expire'     => $now + self::REFRESH_TTL,
            'last_login_time'    => $now,   // 沿用既有列
            'login_fail_count'   => 0,
            'locked_until'       => 0,
            'updatetime'         => $now,
        ]);

        return ['token' => $access, 'refresh_token' => $refresh, 'expires_in' => self::ACCESS_TTL];
    }

    /**
     * 用 refresh 换新对：旋转旧 refresh（一次性），返回 [admin, tokens]
     * 无效返回 null
     * @return array{admin:array, token:string, refresh_token:string, expires_in:int}|null
     */
    public static function rotate(string $refreshToken): ?array
    {
        if ($refreshToken === '') {
            return null;
        }
        $admin = Db::name('admin_user')
            ->where('refresh_token_hash', hash('sha256', $refreshToken))
            ->find();
        if ($admin === null) {
            return null;
        }
        if ((int) $admin['refresh_expire'] < time() || (int) $admin['status'] !== 1) {
            return null;
        }

        $tokens = self::issue($admin);
        return ['admin' => $admin] + $tokens;
    }

    /** 登出：立即吊销 refresh 会话 */
    public static function revokeRefresh(int $adminId): void
    {
        Db::name('admin_user')->where('id', $adminId)->update([
            'refresh_token_hash' => null,
            'refresh_expire'     => 0,
            'updatetime'         => time(),
        ]);
    }

    // --------------------------------------------------------

    private static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $str): string
    {
        return base64_decode(strtr($str, '-_', '+/') . str_repeat('=', (4 - strlen($str) % 4) % 4), true) ?: '';
    }
}
