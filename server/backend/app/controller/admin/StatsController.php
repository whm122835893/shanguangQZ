<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\common\Request;
use app\common\Response;
use app\controller\admin\Concerns\HasAdminAuth;
use think\facade\Db;

/**
 * 管理端仪表盘（v3.1 P1）
 *
 * GET|POST /admin/dashboard —— 全库统计，口径全部注明：
 *   - 已支付订单 = artel_order.pay_time > 0（SettlementService 结算口径）
 *   - 已入账充值 = recharge_order.status = 2（SettlementService::RECHARGE_PAID）
 *   - 在售挂单   = market_goods.status = 1（可售量 = num - sold_num）
 *   - 钱包进出   = wallet_transaction 按 direction 聚合（1=入账，其余=出账）
 * 金额一律 string 两位小数（红线）。
 *
 * ⚠️ 改动后必须跑：php tools/contract/admin_ops_test.php
 *
 * @package app\controller\admin
 */
final class StatsController
{
    use HasAdminAuth;

    private static function money($v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    public function dashboard()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }

        $today = strtotime('today midnight');

        $paidQ = Db::name('artel_order')->where('pay_time', '>', 0);
        $orderPaidTotal = (clone $paidQ)->count();
        $gmvPaid = (clone $paidQ)->sum('total_price');

        $saleQ = Db::name('market_goods')->where('status', 1);
        $marketOnSale = (clone $saleQ)->count();
        $marketQty = (clone $saleQ)->sum('num') - (clone $saleQ)->sum('sold_num');

        $rchQ = Db::name('recharge_order')->where('status', 2);
        $rechargeTotal = (clone $rchQ)->count();
        $rechargeAmount = (clone $rchQ)->sum('amount');

        $wallet = Db::query(
            'SELECT COALESCE(SUM(CASE WHEN direction = 1 THEN amount ELSE 0 END), 0) AS cin,
                    COALESCE(SUM(CASE WHEN direction <> 1 THEN amount ELSE 0 END), 0) AS cout
             FROM wallet_transaction'
        )[0] ?? ['cin' => 0, 'cout' => 0];

        return Response::ok([
            'user_total'           => (int) Db::name('user')->count(),
            'user_today'           => (int) Db::name('user')->where('createtime', '>=', $today)->count(),
            'order_total'          => (int) Db::name('artel_order')->count(),
            'order_paid_total'     => (int) $orderPaidTotal,
            'gmv_paid'             => self::money($gmvPaid),
            'market_on_sale'       => (int) $marketOnSale,
            'market_on_sale_qty'   => max(0, (int) $marketQty),
            'recharge_paid_total'  => (int) $rechargeTotal,
            'recharge_paid_amount' => self::money($rechargeAmount),
            'vip_total'            => (int) Db::name('vip_order')->count(),
            'wallet_in'            => self::money($wallet['cin']),
            'wallet_out'           => self::money($wallet['cout']),
        ]);
    }
}
