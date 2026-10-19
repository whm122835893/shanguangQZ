<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\common\Request;
use app\common\Response;
use app\controller\admin\Concerns\HasAdminAuth;
use app\service\RealnameService;
use think\facade\Db;

/**
 * 管理端实名复核（v3.1 P1，只读）
 *
 * GET|POST /admin/realname/list —— real_name_authentication=1 的用户分页
 * 姓名/证件号一律复用 RealnameService 的脱敏契约（对齐原站实测格式），
 * 明文绝不进响应体（admin_ops_test 锁定）。
 *
 * ⚠️ 改动后必须跑：php tools/contract/admin_ops_test.php
 *
 * @package app\controller\admin
 */
final class RealnameController
{
    use HasAdminAuth;

    public function list()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $page    = max(1, Request::int('page', 1));
        $perPage = min(100, max(1, Request::int('per_page', 10)));

        $q = Db::name('user')
            ->where('real_name_authentication', 1)
            ->order('real_name_time', 'desc');
        $total = (clone $q)->count();
        $rows  = array_map(static fn (array $u): array => [
            'user_id'        => (int) $u['id'],
            'username'       => (string) $u['username'],
            'nickname'       => (string) $u['nickname'],
            'real_name'      => RealnameService::maskName((string) ($u['real_name'] ?? '')),
            'id_card'        => RealnameService::maskIdCard((string) ($u['id_card'] ?? '')),
            'real_name_time' => (int) ($u['real_name_time'] ?? 0),
            'createtime'     => (int) ($u['createtime'] ?? 0),
        ], $q->page($page, $perPage)->select()->toArray());

        return Response::page($rows, $total, $perPage, $page, '实名列表');
    }
}
