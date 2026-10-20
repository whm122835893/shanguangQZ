<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * OrderController —— 订单管理（契约 §8.7，8 个接口）
 *
 * 状态语义（与 C 端 OrderService 对齐）：
 *   - order_status=1 未支付（C 端下单初始态），支付窗口 300s
 *   - 已支付判定 = pay_time > 0（全站口径，不新增第四种状态）
 *   - 管理端强制取消 = order_status → 4（终态，仅未支付单可取消）
 *
 * mark-paid 是高风险修复操作：补支付时间 + 完成库存流转
 * （market_goods 扣减 → user_collection 入库），不动钱包账本
 * （线下收款不走余额，余额守恒不被破坏）。
 *
 * @package app\controller\admin\v1
 */
final class OrderController
{
    use V1;

    public const STATUS_UNPAID    = 1;
    public const STATUS_CANCELLED = 4;
    public const PAY_WINDOW       = 300;

    /** GET /orders */
    public function list()
    {
        self::guard('order:list');
        [$page, $size] = self::paging();

        $q = Db::name('artel_order')->alias('o')
            ->leftJoin('user u', 'u.id = o.user_id')
            ->field('o.*, u.username AS u_username, u.nickname AS u_nickname');
        if ($no = self::str('order_no')) {
            $q->whereLike('o.order_sn', '%' . $no . '%');
        }
        if ($uid = self::int('user_id')) {
            $q->where('o.user_id', $uid);
        }
        if (self::str('status') !== '') {
            $q->where('o.order_status', self::int('status'));
        }
        if ($source = self::str('source')) {
            $q->where('o.source', $source);
        }
        if ($type = self::str('type')) {
            $q->where('o.order_type', $type);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('o.createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('o.createtime', '<', $e);
        }
        $q->order('o.id', 'desc');

        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();

        $list = array_map(fn ($r) => $this->brief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** GET /orders/:id */
    public function detail()
    {
        self::guard('order:detail');
        $id = self::int('id');
        $r = Db::name('artel_order')->alias('o')
            ->leftJoin('user u', 'u.id = o.user_id')
            ->field('o.*, u.username AS u_username, u.nickname AS u_nickname')
            ->where('o.id', $id)->find();
        if ($r === null) {
            throw new AdminException('订单不存在', Resp::NOT_FOUND);
        }
        $refunds = Db::name('refund')->where('order_id', $id)->order('id', 'desc')->select()->toArray();
        return Resp::ok($this->brief($r) + [
            'pay_time'   => self::iso((int) $r['pay_time']),
            'ip_address' => '',
            'refunds'    => array_map(fn ($f) => [
                'id' => (int) $f['id'], 'refund_no' => (string) $f['refund_no'],
                'amount' => self::money($f['amount']), 'status' => (int) $f['status'],
                'reason' => (string) $f['reason'], 'time' => self::iso((int) $f['createtime']),
            ], $refunds),
        ]);
    }

    /** POST /orders/:id/cancel —— 强制取消（仅未支付） */
    public function cancel()
    {
        $admin = self::guard('order:manage');
        self::verifyPassword($admin);
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        $o = Db::name('artel_order')->where('id', $id)->find();
        if ($o === null) {
            throw new AdminException('订单不存在', Resp::NOT_FOUND);
        }
        if ((int) $o['pay_time'] > 0 || (int) $o['order_status'] !== self::STATUS_UNPAID) {
            throw new AdminException('仅未支付订单可取消', Resp::CONFLICT);
        }
        Db::name('artel_order')->where('id', $id)->update([
            'order_status' => self::STATUS_CANCELLED, 'updatetime' => time(),
        ]);
        Guard::audit('order_force_cancel', (int) $admin['id'], $id, ['reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** POST /orders/:id/mark-paid —— 标记已支付（高风险：含库存流转） */
    public function markPaid()
    {
        $admin = self::guard('order:manage');
        self::verifyPassword($admin);
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }

        $o = Db::name('artel_order')->where('id', $id)->find();
        if ($o === null) {
            throw new AdminException('订单不存在', Resp::NOT_FOUND);
        }
        if ((int) $o['pay_time'] > 0) {
            throw new AdminException('订单已支付', Resp::CONFLICT);
        }
        if ((int) $o['order_status'] !== self::STATUS_UNPAID) {
            throw new AdminException('仅待支付订单可标记', Resp::CONFLICT);
        }
        $num = (int) $o['num'];

        Db::startTrans();
        try {
            // 支付信息
            Db::name('artel_order')->where('id', $id)->update([
                'pay_time' => time(), 'pay_channel' => 'manual', 'fee' => 0,
                'order_status' => 2, 'updatetime' => time(),
            ]);
            // 库存流转：从挂单扣减到用户仓库（与 C 端成交语义一致）
            $listing = Db::name('market_goods')->where('collection_id', $o['goods_id'])
                ->where('status', 1)->order('id', 'asc')->find();
            if ($listing !== null) {
                Db::name('market_goods')->where('id', $listing['id'])->update([
                    'num' => max(0, (int) $listing['num'] - $num),
                    'sold_num' => (int) $listing['sold_num'] + $num,
                    'updatetime' => time(),
                ]);
            }
            $held = Db::name('user_collection')->where('user_id', $o['user_id'])
                ->where('collection_id', $o['goods_id'])->find();
            if ($held === null) {
                Db::name('user_collection')->insert([
                    'user_id' => $o['user_id'], 'collection_id' => $o['goods_id'],
                    'num' => $num, 'sell_num' => 0, 'lock_num' => 0,
                    'is_collection' => 0, 'can_compound' => 1,
                    'utime' => time(), 'createtime' => time(),
                ]);
            } else {
                Db::name('user_collection')->where('id', $held['id'])->inc('num')->update([
                    'utime' => time(),
                ]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('标记支付失败：' . $e->getMessage());
        }

        Guard::audit('order_mark_paid', (int) $admin['id'], $id,
            ['reason' => $reason, 'amount' => $o['total_price'], 'num' => $num]);
        return Resp::ok(['success' => true]);
    }

    /** POST /orders/:id/refund —— 发起退款申请（进入退款模块审批流） */
    public function refund()
    {
        $admin = self::guard('order:refund');
        $id = self::int('id');
        $amount = self::str('amount');
        $reason = self::str('reason');
        if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
            throw new AdminException('请填写正确的退款金额');
        }
        if ($reason === '') {
            throw new AdminException('请填写退款原因');
        }
        $o = Db::name('artel_order')->where('id', $id)->find();
        if ($o === null) {
            throw new AdminException('订单不存在', Resp::NOT_FOUND);
        }
        if ((int) $o['pay_time'] === 0) {
            throw new AdminException('未支付订单无可退款金额', Resp::CONFLICT);
        }
        if ((float) $amount > (float) $o['total_price']) {
            throw new AdminException('退款金额不得超过实付金额', Resp::CONFLICT);
        }
        $refundId = (int) Db::name('refund')->insertGetId([
            'order_id' => $id, 'order_sn' => (string) $o['order_sn'],
            'user_id' => (int) $o['user_id'], 'amount' => number_format((float) $amount, 2, '.', ''),
            'reason' => mb_substr($reason, 0, 250), 'status' => 0,
            'refund_no' => 'RF' . date('YmdHis') . random_int(100000, 999999),
            'admin_id' => (int) $admin['id'],
            'createtime' => time(), 'updatetime' => time(),
        ]);
        Guard::audit('order_refund_apply', (int) $admin['id'], $id,
            ['refund_id' => $refundId, 'amount' => $amount, 'reason' => $reason]);
        return Resp::ok(['refund_id' => $refundId]);
    }

    /** GET /orders/abnormal —— 异常订单 */
    public function abnormal()
    {
        self::guard('order:audit');
        [$page, $size] = self::paging();
        $type = self::str('type', 'timeout');

        $rows = [];
        if ($type === 'timeout') {
            // 超过支付窗口仍未支付的订单
            $rows = Db::name('artel_order')
                ->where('order_status', self::STATUS_UNPAID)->where('pay_time', 0)
                ->where('createtime', '<', time() - self::PAY_WINDOW)
                ->order('id', 'desc')->limit(500)->select()->toArray();
        } elseif ($type === 'paid_no_delivery') {
            // 已支付但用户仓库无对应持有记录
            $rows = Db::query(
                'SELECT o.* FROM artel_order o
                 LEFT JOIN user_collection uc ON uc.user_id = o.user_id AND uc.collection_id = o.goods_id
                 WHERE o.pay_time > 0 AND uc.id IS NULL
                 ORDER BY o.id DESC LIMIT 500');
        } else {
            throw new AdminException('不支持的异常类型: ' . $type);
        }

        $list = array_map(fn ($r) => $this->brief($r) + [
            'abnormal_type' => $type,
            'age_minutes'   => (int) floor((time() - (int) $r['createtime']) / 60),
        ], $rows);
        $total = count($list);
        return Resp::page(array_slice($list, ($page - 1) * $size, $size), $total, $page, $size);
    }

    /** POST /orders/:id/repair —— 修复异常订单 */
    public function repair()
    {
        $admin = self::guard('order:manage');
        self::verifyPassword($admin);
        $id = self::int('id');
        $repairType = self::str('repair_type');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写修复原因');
        }
        $o = Db::name('artel_order')->where('id', $id)->find();
        if ($o === null) {
            throw new AdminException('订单不存在', Resp::NOT_FOUND);
        }

        switch ($repairType) {
            case 'timeout_cancel':
                if ((int) $o['pay_time'] > 0 || (int) $o['order_status'] !== self::STATUS_UNPAID) {
                    throw new AdminException('仅未支付订单可超时取消', Resp::CONFLICT);
                }
                Db::name('artel_order')->where('id', $id)->update([
                    'order_status' => self::STATUS_CANCELLED, 'updatetime' => time(),
                ]);
                break;
            case 'supplement_delivery':
                // 已支付但未发货的订单补库存入库
                if ((int) $o['pay_time'] === 0) {
                    throw new AdminException('未支付订单无法补发', Resp::CONFLICT);
                }
                $held = Db::name('user_collection')->where('user_id', $o['user_id'])
                    ->where('collection_id', $o['goods_id'])->find();
                $num = (int) $o['num'];
                if ($held === null) {
                    Db::name('user_collection')->insert([
                        'user_id' => $o['user_id'], 'collection_id' => $o['goods_id'],
                        'num' => $num, 'sell_num' => 0, 'lock_num' => 0,
                        'is_collection' => 0, 'can_compound' => 1,
                        'utime' => time(), 'createtime' => time(),
                    ]);
                } else {
                    Db::name('user_collection')->where('id', $held['id'])->inc('num')->update(['utime' => time()]);
                }
                break;
            default:
                throw new AdminException('不支持的修复类型: ' . $repairType);
        }

        Guard::audit('order_repair', (int) $admin['id'], $id, ['repair_type' => $repairType, 'reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** GET /orders/export —— 导出订单 CSV */
    public function export()
    {
        $admin = self::guard('order:export');
        [$s, $e] = self::dateRange();
        $q = Db::name('artel_order');
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<', $e);
        }
        $rows = $q->order('id', 'desc')->limit(10000)->select()->toArray();

        $csv = "\xEF\xBB\xBF" . 'ID,订单号,用户ID,状态,商品,数量,金额,手续费,渠道,下单时间' . "\n";
        foreach ($rows as $r) {
            $csv .= implode(',', array_map(fn ($v) => '"' . str_replace('"', '""', (string) $v) . '"', [
                $r['id'], $r['order_sn'], $r['user_id'], $r['order_status'],
                $r['goods_name'], $r['num'], $r['total_price'], $r['fee'],
                $r['pay_channel'], date('Y-m-d H:i:s', (int) $r['createtime']),
            ])) . "\n";
        }
        Guard::audit('order_export', (int) $admin['id'], 0, ['count' => count($rows)]);
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="orders_' . date('YmdHis') . '.csv"',
        ]);
    }

    // --------------------------------------------------------

    /** 订单简要结构（金额 string 红线） */
    private function brief(array $r): array
    {
        return [
            'id'           => (int) $r['id'],
            'order_sn'     => (string) $r['order_sn'],
            'user_id'      => (int) $r['user_id'],
            'user_name'    => (string) ($r['u_username'] ?? ''),
            'order_type'   => (int) $r['order_type'],
            'order_status' => (int) $r['order_status'],
            'is_paid'      => (int) $r['pay_time'] > 0,
            'goods_id'     => (int) $r['goods_id'],
            'goods_name'   => (string) ($r['goods_name'] ?? ''),
            'num'          => (int) $r['num'],
            'total_price'  => self::money($r['total_price'] ?? 0),
            'fee'          => self::money($r['fee'] ?? 0),
            'pay_channel'  => (string) ($r['pay_channel'] ?? ''),
            'source'       => (string) ($r['source'] ?? ''),
            'time'         => self::iso((int) $r['createtime']),
        ];
    }
}
