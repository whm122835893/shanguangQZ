<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * RefundController —— 退款管理（契约 §8.8，4 个接口）
 *
 * 状态机：0=待审批 1=已批准(钱已退) 2=已拒绝
 *
 * 批准 = 真实资金动作（事务内三步，缺一即回滚）：
 *   1. user.money += amount
 *   2. wallet_transaction 写入 direction=1 biz_type=refund_in（含 before/after）
 *   3. refund.status=1 + 审计
 * 守恒约束：批准后账本恒等式必须继续成立（balance_after === user.money）。
 *
 * @package app\controller\admin\v1
 */
final class RefundController
{
    use V1;

    /** GET /refunds */
    public function list()
    {
        self::guard('refund:list');
        [$page, $size] = self::paging();

        $q = Db::name('refund')->alias('f')
            ->leftJoin('user u', 'u.id = f.user_id')
            ->field('f.*, u.username AS u_username');
        if (self::str('status') !== '') {
            $q->where('f.status', self::int('status'));
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('f.createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('f.createtime', '<', $e);
        }
        $q->order('f.id', 'desc');

        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();

        $list = array_map(fn ($r) => [
            'id'         => (int) $r['id'],
            'refund_no'  => (string) $r['refund_no'],
            'order_id'   => (int) $r['order_id'],
            'order_sn'   => (string) $r['order_sn'],
            'user_id'    => (int) $r['user_id'],
            'user_name'  => (string) ($r['u_username'] ?? ''),
            'amount'     => self::money($r['amount']),
            'reason'     => (string) $r['reason'],
            'status'     => (int) $r['status'],
            'status_text' => [0 => '待审批', 1 => '已退款', 2 => '已拒绝'][(int) $r['status']] ?? '未知',
            'time'       => self::iso((int) $r['createtime']),
        ], $rows);

        return Resp::page($list, $total, $page, $size);
    }

    /** GET /refunds/:id */
    public function detail()
    {
        self::guard('refund:detail');
        $id = self::int('id');
        $r = Db::name('refund')->alias('f')
            ->leftJoin('user u', 'u.id = f.user_id')
            ->field('f.*, u.username AS u_username')
            ->where('f.id', $id)->find();
        if ($r === null) {
            throw new AdminException('退款单不存在', Resp::NOT_FOUND);
        }
        return Resp::ok([
            'id' => (int) $r['id'], 'refund_no' => (string) $r['refund_no'],
            'order_id' => (int) $r['order_id'], 'order_sn' => (string) $r['order_sn'],
            'user_id' => (int) $r['user_id'], 'user_name' => (string) ($r['u_username'] ?? ''),
            'amount' => self::money($r['amount']), 'reason' => (string) $r['reason'],
            'status' => (int) $r['status'],
            'approver_id' => (int) $r['approver_id'],
            'refunded_at' => self::iso((int) $r['refunded_at']),
            'time' => self::iso((int) $r['createtime']),
        ]);
    }

    /** POST /refunds/:id/approve —— 批准并退款（真实资金动作） */
    public function approve()
    {
        $admin = self::guard('refund:approve');
        self::verifyPassword($admin);
        $id = self::int('id');
        $comment = self::str('comment');

        $r = Db::name('refund')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('退款单不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['status'] !== 0) {
            throw new AdminException('该退款单已处理', Resp::CONFLICT);
        }
        $amount = (float) $r['amount'];

        Db::startTrans();
        try {
            $user = Db::name('user')->where('id', $r['user_id'])->lock(true)->find();
            if ($user === null) {
                throw new \RuntimeException('用户不存在');
            }
            $before = (string) $user['money'];
            $after  = bcadd($before, number_format($amount, 2, '.', ''), 2);
            Db::name('user')->where('id', $r['user_id'])->update([
                'money' => $after, 'updatetime' => time(),
            ]);
            Db::name('wallet_transaction')->insert([
                'user_id' => $r['user_id'], 'direction' => 1,
                'amount' => number_format($amount, 2, '.', ''),
                'balance_before' => $before, 'balance_after' => $after,
                'biz_type' => 'refund_in', 'biz_id' => (string) $r['refund_no'],
                'remark' => mb_substr('退款:' . (string) $r['refund_no'] . ' ' . $comment, 0, 250),
                'createtime' => time(),
            ]);
            Db::name('refund')->where('id', $id)->update([
                'status' => 1, 'approver_id' => (int) $admin['id'],
                'approved_at' => time(), 'refunded_at' => time(), 'updatetime' => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('退款失败：' . $e->getMessage());
        }

        Guard::audit('refund_approve', (int) $admin['id'], $id,
            ['amount' => $r['amount'], 'user_id' => $r['user_id'], 'comment' => $comment]);
        return Resp::ok(['success' => true]);
    }

    /** POST /refunds/:id/reject —— 拒绝退款 */
    public function reject()
    {
        $admin = self::guard('refund:approve');
        self::verifyPassword($admin);
        $id = self::int('id');
        $comment = self::str('comment');

        $r = Db::name('refund')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('退款单不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['status'] !== 0) {
            throw new AdminException('该退款单已处理', Resp::CONFLICT);
        }
        Db::name('refund')->where('id', $id)->update([
            'status' => 2, 'approver_id' => (int) $admin['id'],
            'approved_at' => time(), 'updatetime' => time(),
        ]);
        Guard::audit('refund_reject', (int) $admin['id'], $id, ['comment' => $comment]);
        return Resp::ok(['success' => true]);
    }
}
