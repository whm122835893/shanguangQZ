<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\common\Request;
use app\common\Response;
use app\controller\admin\Concerns\HasAdminAuth;
use think\facade\Db;

/**
 * 管理端钱包核对（v3.1 P1，只读）
 *
 *   GET|POST /admin/wallet/transactions  账本流水分页（可按 user_id 过滤）
 *   GET|POST /admin/wallet/summary       进出合计（direction 1=入账，其余=出账）
 *
 * 资金恒等式红线：user.money === credit_total - debit_total（账本 SUM），
 * 管理端核对即以本端点为准，绝不直改 user.money。
 *
 * ⚠️ 改动后必须跑：php tools/contract/admin_ops_test.php
 *
 * @package app\controller\admin
 */
final class WalletController
{
    use HasAdminAuth;

    private static function money($v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    public function transactions()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $page    = max(1, Request::int('page', 1));
        $perPage = min(100, max(1, Request::int('per_page', 15)));
        $userId  = Request::int('user_id');

        $q = Db::name('wallet_transaction')->order('id', 'desc');
        if ($userId > 0) {
            $q->where('user_id', $userId);
        }
        $total = (clone $q)->count();
        $rows  = array_map(static fn (array $r): array => [
            'id'             => (int) $r['id'],
            'user_id'        => (int) $r['user_id'],
            'direction'      => (int) $r['direction'],
            'amount'         => self::money($r['amount']),
            'balance_before' => self::money($r['balance_before']),
            'balance_after'  => self::money($r['balance_after']),
            'biz_type'       => (string) ($r['biz_type'] ?? ''),
            'remark'         => (string) ($r['remark'] ?? ''),
            'createtime'     => (int) ($r['createtime'] ?? 0),
        ], $q->page($page, $perPage)->select()->toArray());

        return Response::page($rows, $total, $perPage, $page, '钱包流水');
    }

    public function summary()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $userId = Request::int('user_id');

        $sql = 'SELECT COALESCE(SUM(CASE WHEN direction = 1 THEN amount ELSE 0 END), 0) AS cin,
                       COALESCE(SUM(CASE WHEN direction <> 1 THEN amount ELSE 0 END), 0) AS cout,
                       COUNT(*) AS cnt
                FROM wallet_transaction';
        $args = [];
        if ($userId > 0) {
            $sql .= ' WHERE user_id = ?';
            $args = [$userId];
        }
        $r = Db::query($sql, $args)[0] ?? ['cin' => 0, 'cout' => 0, 'cnt' => 0];

        return Response::ok([
            'credit_total' => self::money($r['cin']),
            'debit_total'  => self::money($r['cout']),
            'tx_total'     => (int) $r['cnt'],
        ]);
    }
}
