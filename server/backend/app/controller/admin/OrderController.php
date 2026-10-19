<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\common\Request;
use app\common\Response;
use app\controller\admin\Concerns\HasAdminAuth;
use think\facade\Db;

/**
 * 管理端订单查询（v3.1 P1，只读）
 *
 * GET|POST /admin/order/list —— artel_order 分页 + keyword（order_sn/用户名）
 * 口径：已支付 = pay_time > 0；金额一律 string 两位小数（红线）。
 *
 * ⚠️ 改动后必须跑：php tools/contract/admin_ops_test.php
 *
 * @package app\controller\admin
 */
final class OrderController
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
        $kw      = Request::str('keyword');

        $q = Db::name('artel_order')->alias('o')
            ->leftJoin('user u', 'u.id = o.user_id')
            ->field('o.*, u.username AS u_username, u.nickname AS u_nickname')
            ->order('o.id', 'desc');
        if ($kw !== '') {
            $q->whereRaw('o.order_sn LIKE ? OR u.username LIKE ? OR u.nickname LIKE ?',
                ["%{$kw}%", "%{$kw}%", "%{$kw}%"]);
        }

        $total = (clone $q)->count();
        $rows  = array_map(static fn (array $r): array => [
            'id'           => (int) $r['id'],
            'order_sn'     => (string) $r['order_sn'],
            'user_id'      => (int) $r['user_id'],
            'user_name'    => (string) ($r['u_username'] ?? ''),
            'user_nickname' => (string) ($r['u_nickname'] ?? ''),
            'order_type'   => (int) $r['order_type'],
            'order_status' => (int) $r['order_status'],
            'goods_name'   => (string) ($r['goods_name'] ?? ''),
            'num'          => (int) $r['num'],
            'total_price'  => number_format((float) ($r['total_price'] ?? 0), 2, '.', ''),
            'pay_channel'  => (string) ($r['pay_channel'] ?? ''),
            'pay_no'       => (string) ($r['pay_no'] ?? ''),
            'pay_time'     => (int) ($r['pay_time'] ?? 0),
            'createtime'   => (int) ($r['createtime'] ?? 0),
        ], $q->page($page, $perPage)->select()->toArray());

        return Response::page($rows, $total, $perPage, $page, '订单列表');
    }
}
