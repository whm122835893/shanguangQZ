<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * ReportController —— 数据报表（契约 §8.18，6 个接口）
 *
 * 聚合口径：
 *   销售报表   artel_order（order_status=2 已支付，total_price / fee）
 *   用户报表   user（createtime 新增 / logintime 活跃）+ user_credential 实名率
 *   藏品报表   user_collection 持有分布 + transfer_record 转赠量
 *   盲盒报表   blind_box.sale_number + blind_box_open_record（collectible_id=0 视为空奖）
 *   财务对账   recharge_order / artel_order.fee / refund + 资金恒等式逐用户校验
 *   自定义导出 白名单表 + 字段正则校验（防注入）→ CSV 文件流
 *
 * @package app\controller\admin\v1
 */
final class ReportController
{
    use V1;

    /** 自定义导出白名单表（契约：table/fields 白名单防注入） */
    private const EXPORT_TABLES = [
        'user'               => ['id', 'username', 'nickname', 'mobile', 'money', 'status', 'createtime'],
        'artel_order'        => ['id', 'order_sn', 'user_id', 'order_type', 'order_status', 'total_price', 'num', 'pay_channel', 'fee', 'pay_time', 'createtime'],
        'recharge_order'     => ['id', 'order_sn', 'user_id', 'amount', 'status', 'pay_way', 'pay_time', 'createtime'],
        'wallet_transaction' => ['id', 'user_id', 'direction', 'amount', 'balance_before', 'balance_after', 'biz_type', 'createtime'],
        'user_collection'    => ['id', 'user_id', 'collection_id', 'num', 'sell_num', 'lock_num', 'createtime'],
        'transfer_record'    => ['id', 'user_id', 'to_user_id', 'collection_id', 'num', 'status', 'createtime'],
        'market_goods'       => ['id', 'user_id', 'collection_id', 'price', 'status', 'createtime'],
        'support_ticket'     => ['id', 'ticket_no', 'user_id', 'ticket_type', 'priority', 'status', 'createtime'],
        'feedback'           => ['id', 'user_id', 'type', 'status', 'createtime'],
        'refund'             => ['id', 'refund_no', 'user_id', 'amount', 'status', 'createtime'],
    ];

    // ========================================================
    // 8.18.1 销售报表（200）
    // ========================================================

    /** GET /reports/sales */
    public function sales()
    {
        self::guard('report:sales');
        [$page, $size] = self::paging();
        [$s, $e] = self::dateRange();
        $groupBy = self::str('group_by', 'day') ?: 'day';
        $q = Db::name('artel_order')->where('order_status', 2);
        if ($s > 0) {
            $q->where('pay_time', '>=', $s);
        }
        if ($e > 0) {
            $q->where('pay_time', '<', $e + 86400);
        }
        if ($v = self::int('collectible_id')) {
            $q->where('goods_id', $v);
        }
        $rows = $q->fieldRaw('COUNT(*) AS cnt, COALESCE(SUM(total_price),0) AS amount, COALESCE(SUM(num),0) AS num, COALESCE(SUM(fee),0) AS fee')
            ->find() ?: [];
        $stats = [
            'total_amount' => self::money($rows['amount'] ?? 0),
            'total_orders' => (int) ($rows['cnt'] ?? 0),
            'total_num'    => (int) ($rows['num'] ?? 0),
            'avg_price'    => self::money((float) ($rows['cnt'] ?? 0) > 0 ? ((float) $rows['amount']) / (int) $rows['cnt'] : 0),
            'fee_total'    => self::money($rows['fee'] ?? 0),
        ];

        // 按日/按月分组
        $fmt = $groupBy === 'month' ? '%Y-%m' : '%Y-%m-%d';
        $cq = Db::name('artel_order')->where('order_status', 2);
        if ($s > 0) {
            $cq->where('pay_time', '>=', $s);
        }
        if ($e > 0) {
            $cq->where('pay_time', '<', $e + 86400);
        }
        if ($v > 0) {
            $cq->where('goods_id', $v);
        }
        $chart = $cq->fieldRaw("FROM_UNIXTIME(pay_time, '{$fmt}') AS d, COUNT(*) AS cnt, COALESCE(SUM(total_price),0) AS amount")
            ->group('d')->order('d', 'asc')->select()->toArray();
        $chartData = array_map(static fn ($r) => [
            'date'   => (string) $r['d'],
            'orders' => (int) $r['cnt'],
            'amount' => self::money($r['amount']),
        ], $chart);
        return Resp::ok(['stats' => $stats, 'chart_data' => $chartData, 'group_by' => $groupBy]);
    }

    // ========================================================
    // 8.18.2 用户报表（201）
    // ========================================================

    /** GET /reports/users */
    public function users()
    {
        self::guard('report:user');
        [$s, $e] = self::dateRange();
        $now = time();
        $start = $s > 0 ? $s : $now - 86400;
        $end = $e > 0 ? $e + 86400 : $now + 1;

        $newUsers = (int) Db::name('user')->where('createtime', '>=', $start)->where('createtime', '<', $end)->count();
        $totalUsers = (int) Db::name('user')->count();
        $realnamed = (int) Db::name('user')->where('real_name_authentication', 1)->count();
        $dau = (int) Db::name('user')->where('logintime', '>=', $now - 86400)->count();
        $mau = (int) Db::name('user')->where('logintime', '>=', $now - 30 * 86400)->count();

        // 按日新增
        $chart = Db::name('user')
            ->where('createtime', '>=', $start)->where('createtime', '<', $end)
            ->fieldRaw("FROM_UNIXTIME(createtime, '%Y-%m-%d') AS d, COUNT(*) AS cnt")
            ->group('d')->order('d', 'asc')->select()->toArray();
        $chartData = array_map(static fn ($r) => ['date' => (string) $r['d'], 'new_users' => (int) $r['cnt']], $chart);

        return Resp::ok([
            'new_users'     => $newUsers,
            'total_users'   => $totalUsers,
            'realname_rate' => $totalUsers > 0 ? round($realnamed / $totalUsers * 100, 2) : 0,
            'dau'           => $dau,
            'mau'           => $mau,
            'chart_data'    => $chartData,
        ]);
    }

    // ========================================================
    // 8.18.3 藏品报表（202）
    // ========================================================

    /** GET /reports/collectibles */
    public function collectibles()
    {
        self::guard('report:collectible');
        $holding = Db::name('user_collection')->alias('uc')
            ->join('collection c', 'c.id = uc.collection_id')
            ->fieldRaw('uc.collection_id, c.name, COALESCE(SUM(uc.num - uc.sell_num),0) AS holding')
            ->group('uc.collection_id')->order('holding', 'desc')->limit(50)
            ->select()->toArray();
        $holdingDistribution = array_map(static fn ($r) => [
            'collection_id' => (int) $r['collection_id'],
            'name'          => (string) $r['name'],
            'holding'       => (int) $r['holding'],
        ], $holding);

        $transferCount = (int) Db::name('transfer_record')->count();
        $resaleTotal = (int) Db::name('market_goods')->count();
        $resaleDone = (int) Db::name('market_goods')->where('status', 2)->count();

        $topList = array_slice($holdingDistribution, 0, 10);
        return Resp::ok([
            'holding_distribution' => $holdingDistribution,
            'transfer_count'       => $transferCount,
            'resale_rate'          => $resaleTotal > 0 ? round($resaleDone / $resaleTotal * 100, 2) : 0,
            'top_list'             => $topList,
        ]);
    }

    // ========================================================
    // 8.18.4 盲盒报表（203）
    // ========================================================

    /** GET /reports/blindboxes */
    public function blindboxes()
    {
        self::guard('report:blindbox');
        [$s, $e] = self::dateRange();
        $bbId = self::int('blindbox_id');

        $salesQ = Db::name('blind_box');
        if ($bbId > 0) {
            $salesQ->where('id', $bbId);
        }
        $salesRows = $salesQ->fieldRaw('COUNT(*) AS boxes, COALESCE(SUM(sale_number),0) AS sold, COALESCE(SUM(price * sale_number),0) AS amount')
            ->find() ?: [];

        $openQ = Db::name('blind_box_open_record');
        if ($bbId > 0) {
            $openQ->where('blind_box_id', $bbId);
        }
        if ($s > 0) {
            $openQ->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $openQ->where('createtime', '<', $e + 86400);
        }
        $openTotal = (int) (clone $openQ)->count();
        $emptyTotal = (int) (clone $openQ)->where('collectible_id', 0)->count();
        $sold = (int) ($salesRows['sold'] ?? 0);

        $chartQ = Db::name('blind_box_open_record')->fieldRaw("FROM_UNIXTIME(createtime, '%Y-%m-%d') AS d, COUNT(*) AS cnt");
        if ($bbId > 0) {
            $chartQ->where('blind_box_id', $bbId);
        }
        if ($s > 0) {
            $chartQ->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $chartQ->where('createtime', '<', $e + 86400);
        }
        $chartData = array_map(static fn ($r) => ['date' => (string) $r['d'], 'opens' => (int) $r['cnt']],
            $chartQ->group('d')->order('d', 'asc')->select()->toArray());

        return Resp::ok([
            'sales'    => [
                'boxes'  => (int) ($salesRows['boxes'] ?? 0),
                'sold'   => $sold,
                'amount' => self::money($salesRows['amount'] ?? 0),
            ],
            'open_rate'       => $sold > 0 ? round($openTotal / $sold * 100, 2) : 0,
            'empty_rate'      => $openTotal > 0 ? round($emptyTotal / $openTotal * 100, 2) : 0,
            'item_distribution' => [],
            'chart_data'      => $chartData,
        ]);
    }

    // ========================================================
    // 8.18.5 财务对账（204）
    // ========================================================

    /** GET /reports/finance */
    public function finance()
    {
        self::guard('report:finance');
        [$s, $e] = self::dateRange();

        $rq = Db::name('recharge_order')->where('status', 2);
        $fq = Db::name('artel_order')->where('order_status', 2);
        $rf = Db::name('refund')->where('status', 1);
        if ($s > 0) {
            $rq->where('pay_time', '>=', $s);
            $fq->where('pay_time', '>=', $s);
            $rf->where('refunded_at', '>=', $s);
        }
        if ($e > 0) {
            $rq->where('pay_time', '<', $e + 86400);
            $fq->where('pay_time', '<', $e + 86400);
            $rf->where('refunded_at', '<', $e + 86400);
        }
        $recharge = $rq->fieldRaw('COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total')->find() ?: [];
        $fee = $fq->fieldRaw('COALESCE(SUM(fee),0) AS total')->find() ?: [];
        $refund = $rf->fieldRaw('COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total')->find() ?: [];

        $balance = (float) (Db::name('user')->fieldRaw('COALESCE(SUM(money),0) AS total')->find()['total'] ?? 0);

        // 资金恒等式逐用户校验：user.money === SUM(direction=1 ? +amount : -amount)
        $sums = Db::name('wallet_transaction')
            ->fieldRaw("user_id, COALESCE(SUM(CASE WHEN direction = 1 THEN amount ELSE -amount END),0) AS delta")
            ->group('user_id')->select()->toArray();
        $mismatch = [];
        foreach ($sums as $row) {
            $uid = (int) $row['user_id'];
            $real = (float) (Db::name('user')->where('id', $uid)->value('money') ?? 0);
            if (abs($real - (float) $row['delta']) >= 0.01) {
                $mismatch[] = ['user_id' => $uid, 'money' => self::money($real), 'delta' => self::money($row['delta'])];
            }
        }

        return Resp::ok([
            'recharge' => ['count' => (int) ($recharge['cnt'] ?? 0), 'total' => self::money($recharge['total'] ?? 0)],
            'fee'      => ['total' => self::money($fee['total'] ?? 0)],
            'refund'   => ['count' => (int) ($refund['cnt'] ?? 0), 'total' => self::money($refund['total'] ?? 0)],
            'balance'  => ['total' => self::money($balance)],
            'reconciliation' => [
                'balanced'       => $mismatch === [],
                'checked_users'  => count($sums),
                'mismatch_users' => $mismatch,
            ],
        ]);
    }

    // ========================================================
    // 8.18.6 自定义导出（205）
    // ========================================================

    /** POST /reports/custom-export —— CSV 文件流 */
    public function customExport()
    {
        self::guard('report:export');
        $table = self::str('table');
        if (!isset(self::EXPORT_TABLES[$table])) {
            throw new AdminException('非法导出表：仅允许业务白名单表');
        }
        $fields = self::param('fields');
        if (!is_array($fields) || $fields === []) {
            throw new AdminException('缺少必填参数: fields');
        }
        $allowed = self::EXPORT_TABLES[$table];
        foreach ($fields as $f) {
            $f = (string) $f;
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $f) || !in_array($f, $allowed, true)) {
                throw new AdminException('非法导出字段: ' . $f);
            }
        }
        $filters = self::param('filters');
        $q = Db::name($table);
        if (is_array($filters)) {
            foreach ($filters as $k => $v) {
                $k = (string) $k;
                if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $k) || !in_array($k, $allowed, true)) {
                    throw new AdminException('非法过滤字段: ' . $k);
                }
                $q->where($k, is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE));
            }
        }
        $rows = $q->field($fields)->limit(10000)->select()->toArray();

        $csv = implode(',', $fields) . "\r\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(static fn ($v) => self::csvCell($v), array_values($row))) . "\r\n";
        }
        Guard::audit('report_custom_export', 0, 0, ['table' => $table, 'rows' => count($rows)]);
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $table . '_export_' . date('Ymd_His') . '.csv"',
        ]);
    }

    // ========================================================

    /** CSV 单元格转义：逗号/引号/换行 → 双引号包裹 */
    private static function csvCell($v): string
    {
        $s = (string) $v;
        return preg_match('/[",\r\n]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
    }
}
