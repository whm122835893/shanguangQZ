<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * PermissionController —— 权限审计（契约 §8.15，15 个接口 / 5 组）
 *
 * 数据载体：
 *   管理员   admin_user（密码只存 password_hash；删除自我保护：不能删自己）
 *   角色     admin_role + admin_role_permission（permissions 数组 → code 反查关联）；
 *            预设角色（is_preset=1）禁删 → 409
 *   权限树   admin_permission 按 module 分组
 *   操作日志 audit_log（分页/详情/CSV 导出）
 *   登录日志 admin_login_log
 *
 * @package app\controller\admin\v1
 */
final class PermissionController
{
    use V1;

    // ========================================================
    // 8.15.1 管理员（162~167）
    // ========================================================

    /** GET /permission/admins（162） */
    public function adminList()
    {
        self::guard('permission:admin:list');
        [$page, $size] = self::paging();
        $q = Db::name('admin_user');
        if (self::str('role') !== '') {
            $q->where('role', self::int('role'));
        }
        if (self::str('status') !== '') {
            $q->where('status', self::int('status') === 1 ? 1 : 0);
        }
        if ($kw = self::str('keyword')) {
            $q->whereLike('username', '%' . $kw . '%');
        }
        $q->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->adminBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /permission/admins（163） */
    public function adminCreate()
    {
        $admin = self::guard('permission:admin:create');
        self::requireFields(['username', 'password', 'real_name', 'role']);
        $username = self::str('username');
        if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
            throw new AdminException('用户名仅限字母数字下划线 3~32 位');
        }
        if (Db::name('admin_user')->where('username', $username)->find() !== null) {
            throw new AdminException('用户名已存在', Resp::CONFLICT);
        }
        $password = (string) request()->post('password');
        if (strlen($password) < 8) {
            throw new AdminException('密码长度至少 8 位');
        }
        $now = time();
        $id = (int) Db::name('admin_user')->insertGetId([
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
            'real_name' => mb_substr(self::str('real_name'), 0, 50),
            'role' => max(1, self::int('role')),
            'phone' => mb_substr(self::str('phone'), 0, 20),
            'email' => mb_substr(self::str('email'), 0, 100),
            'status' => self::int('status') === 0 ? 0 : 1,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('admin_create', (int) $admin['id'], $id, ['username' => $username]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /permission/admins/:id（164） */
    public function adminDetail()
    {
        self::guard('permission:admin:detail');
        $r = Db::name('admin_user')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('管理员不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->adminBrief($r) + [
            'last_login_ip'  => (string) $r['last_login_ip'],
            'ip_whitelist'   => (string) $r['ip_whitelist'],
        ]);
    }

    /** PUT /permission/admins/:id（165） */
    public function adminEdit()
    {
        $admin = self::guard('permission:admin:edit');
        $id = self::int('id');
        $r = Db::name('admin_user')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('管理员不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if (self::str('real_name') !== '') {
            $upd['real_name'] = mb_substr(self::str('real_name'), 0, 50);
        }
        if (self::str('role') !== '') {
            $upd['role'] = max(1, self::int('role'));
        }
        if (self::str('phone') !== '') {
            $upd['phone'] = mb_substr(self::str('phone'), 0, 20);
        }
        if (self::str('email') !== '') {
            $upd['email'] = mb_substr(self::str('email'), 0, 100);
        }
        if (self::str('avatar') !== '') {
            $upd['avatar'] = mb_substr(self::str('avatar'), 0, 255);
        }
        if (self::str('status') !== '') {
            $upd['status'] = self::int('status') === 1 ? 1 : 0;
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('admin_user')->where('id', $id)->update($upd);
        }
        Guard::audit('admin_edit', (int) $admin['id'], $id, array_keys($upd));
        return Resp::ok(['success' => true]);
    }

    /** DELETE /permission/admins/:id（166） */
    public function adminDelete()
    {
        $admin = self::guard('permission:admin:delete');
        $id = self::int('id');
        if ($id === (int) $admin['id']) {
            throw new AdminException('不能删除当前登录账号');
        }
        $r = Db::name('admin_user')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('管理员不存在', Resp::NOT_FOUND);
        }
        Db::name('admin_user')->where('id', $id)->delete();
        Guard::audit('admin_delete', (int) $admin['id'], $id, ['username' => (string) $r['username']]);
        return Resp::ok(['success' => true]);
    }

    /** PUT /permission/admins/:id/reset-password（167） */
    public function adminResetPassword()
    {
        $admin = self::guard('permission:admin:manage');
        $id = self::int('id');
        $newPassword = (string) request()->post('new_password');
        if (strlen($newPassword) < 8) {
            throw new AdminException('新密码长度至少 8 位');
        }
        $r = Db::name('admin_user')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('管理员不存在', Resp::NOT_FOUND);
        }
        // 密码更换后所有已签发 token 立即失效（token_hash/refresh 清空 → verify 401）
        Db::name('admin_user')->where('id', $id)->update([
            'password_hash' => password_hash($newPassword, PASSWORD_ARGON2ID),
            'token_hash' => null, 'refresh_token_hash' => null,
            'updatetime' => time(),
        ]);
        Guard::audit('admin_reset_password', (int) $admin['id'], $id, ['username' => (string) $r['username']]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.15.2 角色（168~171）
    // ========================================================

    /** GET /permission/roles（168） */
    public function roleList()
    {
        self::guard('permission:role:list');
        $rows = Db::name('admin_role')->order('id')->select()->toArray();
        $list = array_map(function ($r) {
            $permCount = Db::name('admin_role_permission')->where('role_id', $r['id'])->count();
            return [
                'id'           => (int) $r['id'],
                'name'         => (string) $r['name'],
                'code'         => (string) $r['code'],
                'description'  => (string) $r['description'],
                'data_scope'   => (int) $r['data_scope'],
                'is_preset'    => (int) $r['is_preset'],
                'status'       => (int) $r['status'],
                'permissions'  => $this->rolePermissionCodes((int) $r['id']),
                'admin_count'  => 0, // admin_user.role 为等级枚举非角色外键，恒 0 语义占位
                '_perm_count'  => $permCount,
                'create_time'  => self::iso((int) $r['createtime']),
            ];
        }, $rows);
        return Resp::ok(['list' => $list]);
    }

    /** POST /permission/roles（169） */
    public function roleCreate()
    {
        $admin = self::guard('permission:role:create');
        self::requireFields(['name', 'code']);
        $code = self::str('code');
        if (!preg_match('/^[a-z0-9_]{2,50}$/', $code)) {
            throw new AdminException('角色编码仅限小写字母数字下划线');
        }
        if (Db::name('admin_role')->where('code', $code)->find() !== null) {
            throw new AdminException('角色编码已存在', Resp::CONFLICT);
        }
        $now = time();
        $id = (int) Db::name('admin_role')->insertGetId([
            'name' => mb_substr(self::str('name'), 0, 50),
            'code' => $code,
            'description' => mb_substr(self::str('description'), 0, 255),
            'data_scope' => max(1, self::int('data_scope', 1)),
            'is_preset' => 0,
            'status' => self::int('status', 1) === 1 ? 1 : 0,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        $this->syncRolePermissions($id, request()->post('permissions'));
        Guard::audit('role_create', (int) $admin['id'], $id, ['code' => $code]);
        return Resp::ok(['id' => $id]);
    }

    /** PUT /permission/roles/:id（170） */
    public function roleEdit()
    {
        $admin = self::guard('permission:role:edit');
        $id = self::int('id');
        $r = Db::name('admin_role')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('角色不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if (self::str('name') !== '') {
            $upd['name'] = mb_substr(self::str('name'), 0, 50);
        }
        if (self::str('description') !== '') {
            $upd['description'] = mb_substr(self::str('description'), 0, 255);
        }
        if (self::str('data_scope') !== '') {
            $upd['data_scope'] = max(1, self::int('data_scope', 1));
        }
        if (self::str('status') !== '') {
            $upd['status'] = self::int('status') === 1 ? 1 : 0;
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('admin_role')->where('id', $id)->update($upd);
        }
        if (request()->post('permissions') !== null) {
            $this->syncRolePermissions($id, request()->post('permissions'));
        }
        Guard::audit('role_edit', (int) $admin['id'], $id, array_keys($upd));
        return Resp::ok(['success' => true]);
    }

    /** DELETE /permission/roles/:id（171） */
    public function roleDelete()
    {
        $admin = self::guard('permission:role:delete');
        $id = self::int('id');
        $r = Db::name('admin_role')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('角色不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['is_preset'] === 1) {
            throw new AdminException('预设角色不可删除', Resp::CONFLICT);
        }
        Db::name('admin_role')->where('id', $id)->delete();
        Db::name('admin_role_permission')->where('role_id', $id)->delete();
        Guard::audit('role_delete', (int) $admin['id'], $id, ['code' => (string) $r['code']]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.15.3 权限树（172）
    // ========================================================

    /** GET /permission/permissions（172） */
    public function permissionTree()
    {
        self::guard('permission:permission:list');
        $q = Db::name('admin_permission')->where('status', 1);
        if (self::str('module') !== '') {
            $q->where('module', self::str('module'));
        }
        $rows = $q->order('module')->order('sort_order')->select()->toArray();
        $tree = [];
        foreach ($rows as $r) {
            $tree[] = [
                'id'         => (int) $r['id'],
                'code'       => (string) $r['code'],
                'name'       => (string) $r['name'],
                'module'     => (string) $r['module'],
                'type'       => (int) $r['type'],
                'parent_id'  => (int) $r['parent_id'],
                'sort_order' => (int) $r['sort_order'],
            ];
        }
        return Resp::ok(['tree' => $tree]);
    }

    // ========================================================
    // 8.15.4 操作日志（173~175）
    // ========================================================

    /** GET /permission/operation-logs（173） */
    public function operationLogs()
    {
        self::guard('permission:log:list');
        [$page, $size] = self::paging();
        $q = Db::name('audit_log');
        if ($v = self::int('admin_id')) {
            $q->where('user_id', $v);
        }
        if ($v = self::str('module')) {
            $q->whereLike('route', '%' . $v . '%');
        }
        if ($v = self::str('action')) {
            $q->where('action', $v);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<=', $e + 86399);
        }
        $q->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->logBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** GET /permission/operation-logs/:id（174） */
    public function operationLogDetail()
    {
        self::guard('permission:log:detail');
        $r = Db::name('audit_log')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('日志不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->logBrief($r) + [
            'route'      => (string) $r['route'],
            'method'     => (string) $r['method'],
            'result'     => (int) $r['result'],
            'error_msg'  => (string) ($r['error_msg'] ?? ''),
            'duration_ms' => (int) $r['duration_ms'],
        ]);
    }

    /** GET /permission/operation-logs/export（175） */
    public function operationLogsExport()
    {
        self::guard('permission:log:export');
        $q = Db::name('audit_log');
        if ($v = self::int('admin_id')) {
            $q->where('user_id', $v);
        }
        if ($v = self::str('module')) {
            $q->whereLike('route', '%' . $v . '%');
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<=', $e + 86399);
        }
        $rows = $q->order('id', 'desc')->limit(10000)->select()->toArray();
        $lines = ["id,admin_id,username,action,route,method,target_id,result,ip,create_time"];
        foreach ($rows as $r) {
            $lines[] = implode(',', [
                (int) $r['id'], (int) $r['user_id'], $this->csvCell((string) $r['username']),
                $this->csvCell((string) $r['action']), $this->csvCell((string) $r['route']),
                (string) $r['method'], (int) $r['target_id'], (int) $r['result'],
                (string) $r['ip'], date('Y-m-d H:i:s', (int) $r['createtime']),
            ]);
        }
        $csv = implode("\n", $lines);
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename=operation-logs.csv',
        ]);
    }

    // ========================================================
    // 8.15.5 登录日志（176）
    // ========================================================

    /** GET /permission/login-logs（176） */
    public function loginLogs()
    {
        self::guard('permission:log:login');
        [$page, $size] = self::paging();
        $q = Db::name('admin_login_log');
        if ($v = self::int('admin_id')) {
            $q->where('admin_id', $v);
        }
        if ($v = self::str('username')) {
            $q->whereLike('username', '%' . $v . '%');
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<=', $e + 86399);
        }
        $q->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'          => (int) $r['id'],
            'admin_id'    => (int) $r['admin_id'],
            'username'    => (string) $r['username'],
            'ip'          => (string) $r['ip'],
            'user_agent'  => (string) ($r['user_agent'] ?? ''),
            'is_success'  => (int) $r['is_success'],
            'fail_reason' => (string) ($r['fail_reason'] ?? ''),
            'create_time' => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // --------------------------------------------------------

    /** 管理员行 → 输出（绝不泄露 password_hash/token_hash） */
    private function adminBrief(array $r): array
    {
        return [
            'id'          => (int) $r['id'],
            'username'    => (string) $r['username'],
            'real_name'   => (string) $r['real_name'],
            'role'        => (int) $r['role'],
            'phone'       => (string) $r['phone'],
            'email'       => (string) $r['email'],
            'avatar'      => (string) $r['avatar'],
            'status'      => (int) $r['status'],
            'last_login_time' => self::iso((int) $r['last_login_time']),
            'create_time' => self::iso((int) $r['createtime']),
        ];
    }

    /** 角色权限码列表 */
    private function rolePermissionCodes(int $roleId): array
    {
        return Db::name('admin_role_permission')->alias('rp')
            ->join('admin_permission p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->order('p.id')
            ->column('p.code');
    }

    /** permissions 数组 → 关联表同步（code 反查 id，未知码忽略） */
    private function syncRolePermissions(int $roleId, mixed $permissions): void
    {
        Db::name('admin_role_permission')->where('role_id', $roleId)->delete();
        if (!is_array($permissions) || $permissions === []) {
            return;
        }
        $codes = array_values(array_filter(array_map('strval', $permissions)));
        if ($codes === []) {
            return;
        }
        $ids = Db::name('admin_permission')->whereIn('code', $codes)->column('id');
        $now = time();
        foreach ($ids as $pid) {
            Db::name('admin_role_permission')->insert([
                'role_id' => $roleId, 'permission_id' => (int) $pid, 'createtime' => $now,
            ]);
        }
    }

    /** 审计日志行 → 输出 */
    private function logBrief(array $r): array
    {
        $params = json_decode((string) ($r['params'] ?? ''), true);
        return [
            'id'          => (int) $r['id'],
            'admin_id'    => (int) $r['user_id'],
            'username'    => (string) $r['username'],
            'action'      => (string) $r['action'],
            'target_id'   => (int) $r['target_id'],
            'params'      => is_array($params) ? $params : [],
            'ip'          => (string) $r['ip'],
            'create_time' => self::iso((int) $r['createtime']),
        ];
    }

    /** CSV 单元格转义 */
    private function csvCell(string $v): string
    {
        return str_contains($v, ',') || str_contains($v, '"') || str_contains($v, "\n")
            ? '"' . str_replace('"', '""', $v) . '"'
            : $v;
    }
}
