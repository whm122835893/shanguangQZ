<?php
declare(strict_types=1);

namespace app\service\admin;

use app\service\Audit;
use think\facade\Db;

/**
 * Guard —— v1 管理端守卫：JWT 身份 + RBAC 权限码 + 高风险密码验证
 *
 * 三层防线（契约 §1.2 / §五 / §十）：
 *   1. JWT verify → 401
 *   2. 权限码校验 → 403（超级管理员 role=1 直通全量）
 *   3. 高风险操作二次密码验证 → 400（连续错 5 次锁定 15 分钟）
 *
 * 审计：所有 mutation 必须调用 audit() 落 audit_log 表（§3.9 审计全覆盖）
 *
 * @package app\service\admin
 */
final class Guard
{
    /** @var array<int,array<string,int>> 每请求权限码缓存 [adminId => [code => 1]] */
    private static array $permCache = [];

    /**
     * 从请求解析管理员（不做权限校验）
     * @return array|null 管理员行（无敏感列）或 null
     */
    public static function admin(): ?array
    {
        $jwt = self::bearerToken();
        if ($jwt === '') {
            return null;
        }
        $claims = JwtService::decode($jwt);
        if ($claims === null) {
            return null;
        }
        $admin = Db::name('admin_user')->where('id', (int) $claims['sub'])->find();
        if ($admin === null || (int) $admin['status'] !== 1) {
            return null;
        }
        // IP 白名单（§6.10）：空串不限
        $whitelist = trim((string) ($admin['ip_whitelist'] ?? ''));
        if ($whitelist !== '') {
            $ip = request()->ip();
            if (!in_array($ip, array_map('trim', explode(',', $whitelist)), true)) {
                return null;
            }
        }
        unset($admin['password_hash'], $admin['token_hash'], $admin['refresh_token_hash'], $admin['twofa_secret']);
        return $admin;
    }

    /**
     * 要求登录 + 指定权限码；不满足抛 AdminException（401/403）
     * @return array 管理员行
     */
    public static function require(string $permission): array
    {
        $admin = self::admin();
        if ($admin === null) {
            throw new AdminException('登录已失效，请重新登录', Resp::UNAUTHORIZED);
        }
        // 空权限码 = 仅要求登录（auth 自身端点）
        if ($permission !== '' && (int) $admin['role'] !== 1 && !self::hasPerm((int) $admin['id'], $permission)) {
            throw new AdminException('无权限执行该操作', Resp::FORBIDDEN);
        }
        Db::name('admin_user')->where('id', (int) $admin['id'])->update(['last_action_at' => time()]);
        return $admin;
    }

    public static function hasPerm(int $adminId, string $permission): bool
    {
        if (!isset(self::$permCache[$adminId])) {
            $rows = Db::name('admin_role_permission')
                ->alias('rp')
                ->join('admin_permission p', 'p.id = rp.permission_id')
                ->join('admin_user u', 'u.role = rp.role_id')
                ->where('u.id', $adminId)
                ->column('p.code');
            self::$permCache[$adminId] = array_fill_keys($rows, 1);
        }
        return isset(self::$permCache[$adminId][$permission]);
    }

    /** 当前管理员的权限码全集（/auth/me 与前端动态路由用） */
    public static function permissionsOf(array $admin): array
    {
        if ((int) $admin['role'] === 1) {
            return Db::name('admin_permission')->where('status', 1)->column('code');
        }
        return array_keys(self::$permCache[(int) $admin['id']] ?? []);
    }

    /**
     * 高风险操作：验证管理员登录密码（§十 高风险操作统一规范）
     * 连续失败 5 次锁定 15 分钟；失败写安全事件
     */
    public static function verifyPassword(array $admin, string $password): void
    {
        $password = trim($password);
        if ($password === '') {
            throw new AdminException('请输入管理员密码');
        }
        $row = Db::name('admin_user')->where('id', (int) $admin['id'])->find();
        if ($row === null) {
            throw new AdminException('登录已失效，请重新登录', Resp::UNAUTHORIZED);
        }
        $now = time();
        if ((int) $row['locked_until'] > $now) {
            throw new AdminException('密码错误次数过多，账号已临时锁定，请稍后再试', Resp::FORBIDDEN);
        }
        if (!password_verify($password, (string) $row['password_hash'])) {
            $fails = (int) $row['login_fail_count'] + 1;
            $update = ['login_fail_count' => $fails, 'updatetime' => $now];
            if ($fails >= 5) {
                $update['locked_until']     = $now + 15 * 60;
                $update['login_fail_count'] = 0;
            }
            Db::name('admin_user')->where('id', (int) $row['id'])->update($update);
            self::securityEvent(5, (int) $row['id'], '高风险操作密码验证失败');
            throw new AdminException('管理员密码错误');
        }
        Db::name('admin_user')->where('id', (int) $row['id'])->update(['login_fail_count' => 0, 'updatetime' => $now]);
    }

    /** 审计落库（audit_log 表，可查询可复核） */
    public static function audit(string $action, int $adminId, int $targetId, array $detail = []): void
    {
        Audit::db($action, $adminId, $targetId, $detail);
    }

    /** 安全事件落库 */
    public static function securityEvent(int $eventType, int $adminId, string $description, int $level = 2): void
    {
        Db::name('security_event')->insert([
            'event_type'   => $eventType,
            'event_level'  => $level,
            'admin_id'     => $adminId,
            'ip_address'   => request()->ip(),
            'user_agent'   => mb_substr((string) request()->header('user-agent', ''), 0, 500),
            'request_path' => substr((string) request()->pathinfo(), 0, 255),
            'description'  => $description,
            'status'       => 1,
            'createtime'   => time(),
        ]);
    }

    // --------------------------------------------------------

    private static function bearerToken(): string
    {
        $header = request()->header('authorization', '');
        if (is_string($header) && preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        // 兼容 query 参数（文件下载等无法带 header 的场景）
        $q = request()->param('admin_token', '');
        return is_string($q) ? trim($q) : '';
    }
}
