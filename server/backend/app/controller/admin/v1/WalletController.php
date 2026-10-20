<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * WalletController —— 钱包管理（契约 §8.12，6 个接口）
 *
 * 资金口径铁律：
 *   - 余额核对永远以账本 SUM(direction=1 ? +amount : -amount) 为准，
 *     user.money 只是被核对对象，不是真值来源
 *   - 手续费来自 artel_order.fee（C 端市场成交时落库）
 *   - 守恒校验 diff = SUM(user.money) - SUM(账本恒等式)，非零即资金事故
 *
 * @package app\controller\admin\v1
 */
final class WalletController
{
    use V1;

    /** GET /wallet/recharges —— 充值记录 */
    public function recharges()
    {
        self::guard('wallet:recharge');
        [$page, $size] = self::paging();

        $q = Db::name('recharge_order');
        if ($uid = self::int('user_id')) {
            $q->where('user_id', $uid);
        }
        if ($channel = self::str('channel')) {
            $q->where('pay_way', $channel);
        }
        if ($status = self::str('status')) {
            $q->where('status', (int) $status);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<', $e);
        }

        $total = (clone $q)->count();
        $rows = $q->order('id', 'desc')->page($page, $size)->select()->toArray();

        $list = array_map(fn ($r) => [
            'id'       => (int) $r['id'],
            'order_sn' => (string) $r['order_sn'],
            'user_id'  => (int) $r['user_id'],
            'amount'   => self::money($r['amount']),
            'status'   => (int) $r['status'],
            'channel'  => (string) $r['pay_way'],
            'pay_no'   => (string) $r['pay_no'],
            'time'     => self::iso((int) $r['createtime']),
        ], $rows);

        return Resp::page($list, $total, $page, $size);
    }

    /** GET /wallet/transactions —— 资金流水 */
    public function transactions()
    {
        self::guard('wallet:transaction');
        [$page, $size] = self::paging();

        $q = Db::name('wallet_transaction');
        if ($uid = self::int('user_id')) {
            $q->where('user_id', $uid);
        }
        if ($type = self::str('type')) {
            $q->where('biz_type', $type);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<', $e);
        }

        $total = (clone $q)->count();
        $rows = $q->order('id', 'desc')->page($page, $size)->select()->toArray();

        $list = array_map(fn ($r) => [
            'id'             => (int) $r['id'],
            'user_id'        => (int) $r['user_id'],
            'direction'      => (int) $r['direction'],
            'amount'         => self::money($r['amount']),
            'balance_before' => self::money($r['balance_before'] ?? 0),
            'balance_after'  => self::money($r['balance_after'] ?? 0),
            'biz_type'       => (string) $r['biz_type'],
            'biz_id'         => (string) ($r['biz_id'] ?? ''),
            'remark'         => (string) ($r['remark'] ?? ''),
            'time'           => self::iso((int) $r['createtime']),
        ], $rows);

        return Resp::page($list, $total, $page, $size);
    }

    /** GET /wallet/fee-stats —— 手续费统计 */
    public function feeStats()
    {
        self::guard('wallet:fee');
        $days = min(90, max(1, self::int('days', 30)));
        $from = strtotime(date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days')));

        $totalFee = (float) Db::name('artel_order')->where('fee', '>', 0)
            ->where('pay_time', '>', 0)->sum('fee');

        // 🔴 按日聚合在 PHP 侧做（应用时区 Asia/Shanghai 的日界），
        //    不用 MySQL FROM_UNIXTIME —— 沙箱/生产 MySQL 会话时区不保证一致
        $rows = Db::name('artel_order')
            ->where('fee', '>', 0)->where('pay_time', '>=', $from)
            ->field('pay_time,fee')->select()->toArray();
        $buckets = [];
        foreach ($rows as $r) {
            $d = date('Y-m-d', (int) $r['pay_time']);
            $buckets[$d] ??= ['fee' => 0.0, 'count' => 0];
            $buckets[$d]['fee'] += (float) $r['fee'];
            $buckets[$d]['count']++;
        }
        ksort($buckets);

        $byChannel = Db::query(
            'SELECT COALESCE(NULLIF(pay_channel, ""), "unknown") AS channel, SUM(fee) AS fee
             FROM artel_order WHERE fee > 0 AND pay_time > 0 GROUP BY channel');

        return Resp::ok([
            'days'      => $days,
            'total_fee' => self::money($totalFee),
            'daily_stats' => array_map(fn ($d, $b) => [
                'date'  => $d,
                'fee'   => self::money($b['fee']),
                'count' => $b['count'],
            ], array_keys($buckets), $buckets),
            'channel_distribution' => array_map(fn ($r) => [
                'channel' => (string) $r['channel'],
                'fee'     => self::money($r['fee']),
            ], $byChannel),
        ]);
    }

    /** GET /wallet/conservation —— 资金守恒校验（账本恒等式全库版） */
    public function conservation()
    {
        self::guard('wallet:audit');

        $totalBalance = (float) Db::name('user')->sum('money');
        $totalFee     = (float) Db::name('artel_order')->where('fee', '>', 0)->sum('fee');
        $totalRecharge = (float) Db::name('recharge_order')->where('status', 2)->sum('amount');
        // 奖励类入账：充值以外的收入（奖励/空投/邀请/签到/合成返还）
        $totalReward = (float) Db::query(
            "SELECT COALESCE(SUM(amount),0) AS v FROM wallet_transaction
             WHERE direction = 1 AND biz_type NOT IN ('recharge','sell','refund_in','transfer_in')"
        )[0]['v'] ?? 0;
        $ledgerSum = (float) Db::query(
            'SELECT COALESCE(SUM(CASE direction WHEN 1 THEN amount ELSE -amount END),0) AS v FROM wallet_transaction'
        )[0]['v'] ?? 0;

        // diff=0：余额表与账本完全一致；非 0 即资金事故，必须立即排查
        $diff = round($totalBalance - $ledgerSum, 2);

        return Resp::ok([
            'total_user_balance' => self::money($totalBalance),
            'total_fee'          => self::money($totalFee),
            'total_recharge'     => self::money($totalRecharge),
            'total_reward'       => self::money($totalReward),
            'ledger_sum'         => self::money($ledgerSum),
            'diff'               => self::money($diff),
            'is_consistent'      => bccomp((string) $diff, '0.00', 2) === 0,
        ]);
    }

    /** GET /wallet/abnormal —— 异常资金监控 */
    public function abnormal()
    {
        self::guard('wallet:monitor');
        [$page, $size] = self::paging();
        $type = self::str('type');

        $rows = [];
        if ($type === '' || $type === 'balance_mismatch') {
            // 余额 ≠ 账本 的用户（资金事故最高优先级）
            $rows = array_merge($rows, Db::query(
                'SELECT u.id AS user_id, u.money, t.ledger_sum
                 FROM user u
                 JOIN (SELECT user_id, SUM(CASE direction WHEN 1 THEN amount ELSE -amount END) AS ledger_sum
                       FROM wallet_transaction GROUP BY user_id) t ON t.user_id = u.id
                 WHERE ABS(u.money - t.ledger_sum) > 0.001
                 LIMIT 200'
            ));
        }
        if ($type === '' || $type === 'negative') {
            $neg = Db::query('SELECT id AS user_id, money AS ledger_sum FROM user WHERE money < 0 LIMIT 200');
            $rows = array_merge($rows, array_map(fn ($r) => [
                'user_id' => $r['user_id'], 'money' => $r['ledger_sum'], 'ledger_sum' => $r['ledger_sum'],
            ], $neg));
        }

        $list = array_map(fn ($r) => [
            'user_id'     => (int) $r['user_id'],
            'balance'     => self::money($r['money'] ?? 0),
            'ledger_sum'  => self::money($r['ledger_sum'] ?? 0),
            'type'        => ($r['money'] ?? 0) < 0 ? 'negative' : 'balance_mismatch',
            'diff'        => self::money((float) ($r['money'] ?? 0) - (float) ($r['ledger_sum'] ?? 0)),
        ], $rows);

        $total = count($list);
        $offset = ($page - 1) * $size;
        return Resp::page(array_slice($list, $offset, $size), $total, $page, $size);
    }

    /** GET /wallet/export —— 导出财务数据（CSV 文件流） */
    public function export()
    {
        $admin = self::guard('wallet:export');
        [$s, $e] = self::dateRange();
        $type = self::str('type');

        if ($type === 'recharge') {
            $q = Db::name('recharge_order');
            if ($s > 0) {
                $q->where('createtime', '>=', $s);
            }
            if ($e > 0) {
                $q->where('createtime', '<', $e);
            }
            $rows = $q->order('id', 'desc')->limit(10000)->select()->toArray();
            $csv = $this->csv([
                ['ID', '订单号', '用户ID', '金额', '状态', '渠道', '时间'],
                ...array_map(fn ($r) => [
                    (string) $r['id'], $r['order_sn'], (string) $r['user_id'],
                    $r['amount'], (string) $r['status'], (string) $r['pay_way'],
                    date('Y-m-d H:i:s', (int) $r['createtime']),
                ], $rows),
            ]);
        } else {
            $q = Db::name('wallet_transaction');
            if ($s > 0) {
                $q->where('createtime', '>=', $s);
            }
            if ($e > 0) {
                $q->where('createtime', '<', $e);
            }
            $rows = $q->order('id', 'desc')->limit(10000)->select()->toArray();
            $csv = $this->csv([
                ['ID', '用户ID', '方向', '金额', '变动前', '变动后', '业务', '备注', '时间'],
                ...array_map(fn ($r) => [
                    (string) $r['id'], (string) $r['user_id'], (string) $r['direction'],
                    $r['amount'], (string) $r['balance_before'], (string) $r['balance_after'],
                    (string) $r['biz_type'], (string) ($r['remark'] ?? ''),
                    date('Y-m-d H:i:s', (int) $r['createtime']),
                ], $rows),
            ]);
        }

        \app\service\admin\Guard::audit('wallet_export', (int) $admin['id'], 0,
            ['type' => $type, 'date_start' => $s, 'date_end' => $e]);

        $filename = 'wallet_' . ($type ?: 'transactions') . '_' . date('YmdHis') . '.csv';
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** CSV 组装（带 BOM，Excel 直接打开不乱码） */
    private function csv(array $rows): string
    {
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', $row));
            $out .= "\n";
        }
        return $out;
    }
}
