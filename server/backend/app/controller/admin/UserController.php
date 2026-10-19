<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\common\Request;
use app\common\Response;
use app\controller\admin\Concerns\HasAdminAuth;
use app\model\UserToken;
use app\service\Audit;
use app\service\RealnameService;
use think\facade\Db;

/**
 * 管理端用户治理（v3.1 P1）
 *
 *   GET|POST /admin/user/list    列表（手机号一律脱敏）
 *   GET|POST /admin/user/detail  详情（钱包合计 = 账本 SUM，实名脱敏）
 *   POST     /admin/user/freeze  冻结/解冻（status 置 'freeze'/'normal'，
 *                                C 端 UserToken::verify 对非 normal 即 401，
 *                                冻结即时生效；动作写 audit_log）
 *
 * 🔐 响应体绝不出现明文证件号/完整手机号（红线，admin_ops_test 锁定）。
 *
 * ⚠️ 改动后必须跑：php tools/contract/admin_ops_test.php
 *
 * @package app\controller\admin
 */
final class UserController
{
    use HasAdminAuth;

    private static function maskMobile(string $mobile): string
    {
        return preg_match('/^\d{11}$/', $mobile)
            ? substr($mobile, 0, 3) . '****' . substr($mobile, -4)
            : '';
    }

    /** @return array 行契约：金额 string、时间 int、mobile 脱敏 */
    private static function row(array $u): array
    {
        return [
            'id'                       => (int) $u['id'],
            'username'                 => (string) $u['username'],
            'nickname'                 => (string) $u['nickname'],
            'mobile'                   => self::maskMobile((string) ($u['mobile'] ?? '')),
            'money'                    => number_format((float) ($u['money'] ?? 0), 2, '.', ''),
            'crystal_yellow'           => (int) ($u['crystal_yellow'] ?? 0),
            'status'                   => (string) ($u['status'] ?? ''),
            'freeze'                   => (int) ($u['freeze'] ?? 0),
            'real_name_authentication' => (int) ($u['real_name_authentication'] ?? 0),
            'createtime'               => (int) ($u['createtime'] ?? 0),
        ];
    }

    public function list()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $page    = max(1, Request::int('page', 1));
        $perPage = min(100, max(1, Request::int('per_page', 10)));
        $kw      = Request::str('keyword');

        $q = Db::name('user')->order('id', 'desc');
        if ($kw !== '') {
            $q->whereRaw('username LIKE ? OR nickname LIKE ? OR mobile LIKE ?', ["%{$kw}%", "%{$kw}%", "{$kw}%"]);
        }
        $total = (clone $q)->count();
        $rows  = array_map(self::row(...), $q->page($page, $perPage)->select()->toArray());
        return Response::page($rows, $total, $perPage, $page, '用户列表');
    }

    public function detail()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $id  = Request::int('id');
        $u   = Db::name('user')->where('id', $id)->find();
        if ($u === null) {
            return Response::fail('用户不存在');
        }

        $wallet = Db::query(
            'SELECT COALESCE(SUM(CASE WHEN direction = 1 THEN amount ELSE 0 END), 0) AS cin,
                    COALESCE(SUM(CASE WHEN direction <> 1 THEN amount ELSE 0 END), 0) AS cout
             FROM wallet_transaction WHERE user_id = ?',
            [$id]
        )[0] ?? ['cin' => 0, 'cout' => 0];

        $auth   = (int) ($u['real_name_authentication'] ?? 0) === 1;
        $idCard = $auth ? (string) ($u['id_card'] ?? '') : '';

        return Response::ok([
            'user' => self::row($u),
            'wallet' => [
                'money'          => number_format((float) ($u['money'] ?? 0), 2, '.', ''),
                'crystal_yellow' => (int) ($u['crystal_yellow'] ?? 0),
                'crystal_purple' => (int) ($u['crystal_purple'] ?? 0),
                'crystal_blue'   => (int) ($u['crystal_blue'] ?? 0),
                'credit_total'   => number_format((float) $wallet['cin'], 2, '.', ''),
                'debit_total'    => number_format((float) $wallet['cout'], 2, '.', ''),
            ],
            'counts' => [
                'orders'       => (int) Db::name('artel_order')->where('user_id', $id)->count(),
                'collections'  => (int) Db::name('user_collection')->where('user_id', $id)->count(),
                'consignments' => (int) Db::name('market_goods')->where('user_id', $id)->where('status', 1)->count(),
            ],
            'realname' => [
                'auth'           => $auth ? 1 : 0,
                'name'           => $auth ? RealnameService::maskName((string) ($u['real_name'] ?? '')) : '',
                'id_card'        => $auth && $idCard !== '' ? RealnameService::maskIdCard($idCard) : '',
                'real_name_time' => (int) ($u['real_name_time'] ?? 0),
            ],
        ]);
    }

    public function freeze()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $id    = Request::int('id');
        $action = Request::str('action') === 'unfreeze' ? 'unfreeze' : 'freeze';
        $exists = Db::name('user')->where('id', $id)->find();
        if ($exists === null) {
            return Response::fail('用户不存在');
        }

        // C 端 UserToken::verify 对 status !== 'normal' 一律 401 —— 冻结即时生效
        Db::name('user')->where('id', $id)->update([
            'status'     => $action === 'freeze' ? 'freeze' : 'normal',
            'updatetime' => time(),
        ]);
        Audit::db('admin_user_freeze', (int) $admin['id'], $id,
            ['action' => $action, 'username' => (string) $exists['username']]);

        return Response::ok(['id' => $id, 'status' => $action === 'freeze' ? 'freeze' : 'normal']);
    }
}
