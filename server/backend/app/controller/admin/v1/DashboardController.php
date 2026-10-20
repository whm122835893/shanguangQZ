<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\Resp;
use think\facade\Db;

/**
 * DashboardController —— 仪表盘（契约 §8.2，6 个接口）
 *
 * 统计口径全部显式（与 P1 StatsController 同源）：
 *   已支付订单 = pay_time > 0；已入账充值 = recharge_order.status = 2；
 *   在售挂单 = market_goods.status = 1；在售藏品 = sale_status = 1；在售盲盒 = status = 1
 *
 * @package app\controller\admin\v1
 */
final class DashboardController
{
    use V1;

    /** GET /dashboard/metrics */
    public function metrics()
    {
        self::guard('dashboard:view');
        $today0 = strtotime(date('Y-m-d 00:00:00'));

        return Resp::ok([
            'new_users_today'     => Db::name('user')->where('createtime', '>=', $today0)->count(),
            'sales_today'         => $this->money((string) Db::name('artel_order')->where('pay_time', '>=', $today0)->sum('total_price')),
            'orders_today'        => Db::name('artel_order')->where('createtime', '>=', $today0)->count(),
            'active_collectibles' => Db::name('collection')->where('sale_status', 1)->count(),
            'active_listings'     => Db::name('market_goods')->where('status', 1)->count(),
            'active_blindboxes'   => Db::name('blind_box')->where('status', 1)->count(),
            'pending_refunds'     => Db::name('refund')->where('status', 1)->count(),
            'pending_realnames'   => Db::name('user')->where('real_name_authentication', 0)->count(),
        ]);
    }

    /** GET /dashboard/finance */
    public function finance()
    {
        self::guard('dashboard:view');
        $days = min(30, max(7, self::int('days', 7)));
        $since = time() - $days * 86400;

        $totalRecharge = (float) Db::name('recharge_order')->where('status', 2)->sum('amount');
        $totalFee = (float) Db::name('wallet_transaction')->where('biz_type', 'market_fee')->sum('amount');

        // 余额分布（按金额档位）
        $tiers = [
            ['name' => '0-100',   'min' => 0, 'max' => 100],
            ['name' => '100-500', 'min' => 100, 'max' => 500],
            ['name' => '500-1000', 'min' => 500, 'max' => 1000],
            ['name' => '1000+',   'min' => 1000, 'max' => PHP_INT_MAX],
        ];
        $distribution = [];
        foreach ($tiers as $t) {
            $distribution[] = [
                'name'  => $t['name'],
                'value' => Db::name('user')->whereBetween('money', [(string) $t['min'], $t['max'] === PHP_INT_MAX ? '99999999' : (string) $t['max']])->count(),
            ];
        }

        return Resp::ok([
            'days'              => $days,
            'total_recharge'    => $this->money($totalRecharge),
            'total_fee'         => $this->money($totalFee),
            'recharge_in_range' => $this->money((string) Db::name('recharge_order')->where('status', 2)->where('pay_time', '>=', $since)->sum('amount')),
            'pending_settlement' => $this->money((string) Db::name('market_goods')->where('status', 1)->sum('price')),
            'wallet_distribution' => $distribution,
        ]);
    }

    /** GET /dashboard/alerts —— 库存预警 */
    public function alerts()
    {
        self::guard('dashboard:view');

        // 低库存：在售藏品库存池 < 10
        $lowStock = [];
        $rows = Db::name('collection')->where('sale_status', 1)->field('id,name,total_num,reserved_count,sold_count,airdropped_count,destroyed_count')->select()->toArray();
        foreach ($rows as $r) {
            $pool = (int) $r['total_num'] - (int) $r['reserved_count'] - (int) $r['sold_count'] - (int) $r['airdropped_count'] - (int) $r['destroyed_count'];
            if ($pool < 10) {
                $lowStock[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'stock_pool' => max(0, $pool)];
            }
        }

        // 库存恒等式异常：user 持有合计 > 流通量
        $anomaly = [];
        foreach ($rows as $r) {
            $circulate = (int) $r['sold_count'] + (int) $r['airdropped_count'];
            $holding = (int) Db::name('user_collection')->where('collection_id', (int) $r['id'])->sum('num');
            if ($holding > max($circulate, 0)) {
                $anomaly[] = ['id' => (int) $r['id'], 'name' => $r['name'], 'holding' => $holding, 'circulate' => $circulate];
            }
        }

        // 盲盒缺口：在售且发售剩余 > 盲盒库存池
        $shortage = [];
        $boxes = Db::name('blind_box')->where('status', 1)->field('id,name,total_num,sale_number,airdropped_count,destroyed_count,release_quantity')->select()->toArray();
        foreach ($boxes as $b) {
            $pool = (int) $b['total_num'] - (int) $b['sale_number'] - (int) $b['airdropped_count'] - (int) $b['destroyed_count'];
            $remaining = max(0, (int) $b['release_quantity'] - (int) $b['sale_number']);
            if ($remaining > max(0, $pool)) {
                $shortage[] = ['id' => (int) $b['id'], 'name' => $b['name'], 'remaining' => $remaining, 'stock_pool' => max(0, $pool)];
            }
        }

        return Resp::ok(['low_stock' => $lowStock, 'stock_anomaly' => $anomaly, 'blindbox_shortage' => $shortage]);
    }

    /** GET /dashboard/activities —— 实时动态（审计日志滚动） */
    public function activities()
    {
        self::guard('dashboard:view');
        $limit = min(50, max(1, self::int('limit', 20)));
        $rows = Db::name('audit_log')->order('id', 'desc')->limit($limit)->field('id,username,action,route,createtime')->select()->toArray();
        return Resp::ok(['list' => array_map(fn ($r) => [
            'id' => (int) $r['id'], 'admin' => $r['username'], 'action' => $r['action'],
            'route' => $r['route'], 'time' => self::iso((int) $r['createtime']),
        ], $rows)]);
    }

    /** GET /dashboard/trends —— 趋势图数据 */
    public function trends()
    {
        self::guard('dashboard:view');
        $days = min(90, max(1, self::int('days', 7)));
        $metric = self::str('metric', 'sales'); // sales / users / orders

        $dates = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day0 = strtotime(date('Y-m-d 00:00:00', time() - $i * 86400));
            $day1 = $day0 + 86400;
            $dates[] = date('m-d', $day0);
            $values[] = match ($metric) {
                'users'  => Db::name('user')->whereBetween('createtime', [$day0, $day1])->count(),
                'orders' => Db::name('artel_order')->whereBetween('createtime', [$day0, $day1])->count(),
                default  => (float) Db::name('artel_order')->where('pay_time', '>=', $day0)->where('pay_time', '<', $day1)->sum('total_price'),
            };
        }
        return Resp::ok(['metric' => $metric, 'dates' => $dates, 'values' => $values]);
    }

    /** GET /dashboard/priority-stats —— 优先购统计 */
    public function priorityStats()
    {
        self::guard('dashboard:view');
        $totalIssued = (int) Db::name('priority_sale_whitelist')->where('status', 1)->count();
        $used = (int) Db::name('priority_sale_whitelist')->where('status', 1)->sum('used_quantity');
        $totalQuota = (int) Db::name('priority_sale_whitelist')->where('status', 1)->sum('max_quantity');
        return Resp::ok([
            'total_issued' => $totalIssued,
            'used'         => $used,
            'remaining'    => max(0, $totalQuota - $used),
        ]);
    }
}
