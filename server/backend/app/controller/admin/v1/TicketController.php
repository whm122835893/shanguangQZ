<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * TicketController —— 客服工单（契约 §8.17，8 个接口）
 *
 * 数据载体：support_ticket（1=待处理 2=处理中 3=待用户确认 4=已解决 5=已关闭）
 *           ticket_reply（sender_type 1=用户 2=客服 3=系统）
 *           feedback（1=反馈 2=举报 3=建议）
 *
 * 快捷补偿（199）：高风险操作 → 密码验证 + 事务（user.money + wallet_transaction
 *                 biz_type=ticket_compensate）+ 审计落库。
 *
 * @package app\controller\admin\v1
 */
final class TicketController
{
    use V1;

    /** 工单状态 → 响应文案（契约 §6.14） */
    private const STATUS_MAP = [1 => '待处理', 2 => '处理中', 3 => '待用户确认', 4 => '已解决', 5 => '已关闭'];

    // ========================================================
    // 8.17.1 工单列表（192）
    // ========================================================

    /** GET /tickets */
    public function list()
    {
        self::guard('ticket:list');
        [$page, $size] = self::paging();
        $q = Db::name('support_ticket');
        if ($v = self::int('type')) {
            $q->where('ticket_type', $v);
        }
        if ($v = self::int('status')) {
            $q->where('status', $v);
        }
        if ($v = self::int('priority')) {
            $q->where('priority', $v);
        }
        if ($v = self::int('assignee_id')) {
            $q->where('assignee_id', $v);
        }
        if ($v = self::int('user_id')) {
            $q->where('user_id', $v);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<', $e + 86400);
        }
        $q->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => self::ticketRow($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.17.2 工单详情（193）
    // ========================================================

    /** GET /tickets/:id */
    public function detail()
    {
        self::guard('ticket:detail');
        $id = self::int('id');
        $r = Db::name('support_ticket')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('工单不存在', Resp::NOT_FOUND);
        }
        $row = self::ticketRow($r);
        $replies = Db::name('ticket_reply')->where('ticket_id', $id)
            ->order('id', 'asc')->select()->toArray();
        $row['replies'] = array_map(static fn ($x) => [
            'id'          => (int) $x['id'],
            'sender_type' => (int) $x['sender_type'],
            'sender_id'   => (int) $x['sender_id'],
            'sender_name' => (string) $x['sender_name'],
            'content'     => (string) $x['content'],
            'attachments' => json_decode((string) ($x['attachments'] ?? '[]'), true) ?: [],
            'is_internal' => (int) $x['is_internal'],
            'time'        => self::iso((int) $x['createtime']),
        ], $replies);
        return Resp::ok($row);
    }

    // ========================================================
    // 8.17.3 分配工单（194）
    // ========================================================

    /** PUT /tickets/:id/assign */
    public function assign()
    {
        $admin = self::guard('ticket:assign');
        $id = self::int('id');
        $assigneeId = self::int('assignee_id');
        if ($assigneeId <= 0) {
            throw new AdminException('缺少必填参数: assignee_id');
        }
        $assignee = Db::name('admin_user')->where('id', $assigneeId)->where('status', 1)->find();
        if ($assignee === null) {
            throw new AdminException('分配对象不存在或已停用', Resp::NOT_FOUND);
        }
        $ok = Db::name('support_ticket')->where('id', $id)->update([
            'assignee_id'   => $assigneeId,
            'assignee_name' => (string) $assignee['username'],
            'status'        => Db::raw('CASE WHEN status = 1 THEN 2 ELSE status END'),
            'updatetime'    => time(),
        ]);
        if (!$ok) {
            throw new AdminException('工单不存在', Resp::NOT_FOUND);
        }
        Guard::audit('ticket_assign', (int) $admin['id'], $id, ['assignee_id' => $assigneeId]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.17.4 回复工单（195）
    // ========================================================

    /** POST /tickets/:id/reply */
    public function reply()
    {
        $admin = self::guard('ticket:reply');
        $id = self::int('id');
        $content = self::str('content');
        if ($content === '') {
            throw new AdminException('缺少必填参数: content');
        }
        if (Db::name('support_ticket')->where('id', $id)->count() === 0) {
            throw new AdminException('工单不存在', Resp::NOT_FOUND);
        }
        $attachments = self::param('attachments');
        Db::name('ticket_reply')->insert([
            'ticket_id'   => $id,
            'sender_type' => 2, // 客服
            'sender_id'   => (int) $admin['id'],
            'sender_name' => (string) $admin['username'],
            'content'     => $content,
            'attachments' => is_array($attachments) ? json_encode($attachments, JSON_UNESCAPED_UNICODE) : '[]',
            'is_internal' => self::int('is_internal') === 1 ? 1 : 0,
            'createtime'  => time(),
        ]);
        Db::name('support_ticket')->where('id', $id)
            ->update(['status' => Db::raw('CASE WHEN status = 1 THEN 2 ELSE status END'), 'updatetime' => time()]);
        Guard::audit('ticket_reply', (int) $admin['id'], $id, ['is_internal' => self::int('is_internal')]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.17.5 更新工单状态（196）
    // ========================================================

    /** PUT /tickets/:id/status */
    public function updateStatus()
    {
        $admin = self::guard('ticket:manage');
        $id = self::int('id');
        $status = self::int('status');
        if (!isset(self::STATUS_MAP[$status])) {
            throw new AdminException('非法工单状态: ' . $status);
        }
        $r = Db::name('support_ticket')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('工单不存在', Resp::NOT_FOUND);
        }
        $now = time();
        $upd = ['status' => $status, 'updatetime' => $now];
        // 契约：3=待用户确认（客服已给出方案）与 4=已解决 均视为客服侧解决动作 → 落 solved_at
        if (($status === 3 || $status === 4) && (int) $r['solved_at'] === 0) {
            $upd['solved_at'] = $now;
        }
        if ($status === 5 && (int) $r['closed_at'] === 0) {
            $upd['closed_at'] = $now;
        }
        Db::name('support_ticket')->where('id', $id)->update($upd);
        $comment = self::str('comment');
        if ($comment !== '') {
            Db::name('ticket_reply')->insert([
                'ticket_id'   => $id, 'sender_type' => 3, 'sender_id' => (int) $admin['id'],
                'sender_name' => (string) $admin['username'], 'content' => $comment,
                'attachments' => '[]', 'is_internal' => 0, 'createtime' => $now,
            ]);
        }
        Guard::audit('ticket_status', (int) $admin['id'], $id, ['status' => $status]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.17.6 用户反馈列表（197）
    // ========================================================

    /** GET /tickets/feedbacks */
    public function feedbacks()
    {
        self::guard('ticket:feedback');
        [$page, $size] = self::paging();
        $q = Db::name('feedback')->alias('f')
            ->leftJoin('user u', 'u.id = f.user_id')
            ->field('f.*,u.nickname,u.mobile');
        if ($v = self::int('status')) {
            $q->where('f.status', $v);
        }
        if ($v = self::int('type')) {
            $q->where('f.type', $v);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('f.createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('f.createtime', '<', $e + 86400);
        }
        $q->order('f.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'         => (int) $r['id'],
            'user_id'    => (int) $r['user_id'],
            'nickname'   => (string) ($r['nickname'] ?? ''),
            'mobile'     => self::maskPhone((string) ($r['mobile'] ?? '')),
            'type'       => (int) $r['type'],
            'content'    => (string) $r['content'],
            'contact'    => self::maskPhone((string) $r['contact']),
            'status'     => (int) $r['status'],
            'reply'      => (string) $r['reply'],
            'time'       => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.17.7 用户反馈详情（198）
    // ========================================================

    /** GET /tickets/feedbacks/:id */
    public function feedbackDetail()
    {
        self::guard('ticket:feedback');
        $id = self::int('id');
        $r = Db::name('feedback')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('反馈不存在', Resp::NOT_FOUND);
        }
        $user = Db::name('user')->where('id', (int) $r['user_id'])->field('nickname,mobile')->find();
        return Resp::ok([
            'id'         => (int) $r['id'],
            'user_id'    => (int) $r['user_id'],
            'nickname'   => (string) ($user['nickname'] ?? ''),
            'mobile'     => self::maskPhone((string) ($user['mobile'] ?? '')),
            'type'       => (int) $r['type'],
            'content'    => (string) $r['content'],
            'images'     => json_decode((string) ($r['images'] ?? '[]'), true) ?: [],
            'contact'    => self::maskPhone((string) $r['contact']),
            'status'     => (int) $r['status'],
            'reply'      => (string) $r['reply'],
            'time'       => self::iso((int) $r['createtime']),
        ]);
    }

    // ========================================================
    // 8.17.8 快捷补偿（199）—— 高风险
    // ========================================================

    /** POST /tickets/:id/compensate */
    public function compensate()
    {
        $admin = self::guard('ticket:compensate');
        self::verifyPassword($admin);
        $id = self::int('id');
        $ticket = Db::name('support_ticket')->where('id', $id)->find();
        if ($ticket === null) {
            throw new AdminException('工单不存在', Resp::NOT_FOUND);
        }
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('缺少必填参数: reason');
        }
        $type = self::int('compensate_type') ?: 1;
        $config = self::param('compensate_config');
        if (!is_array($config) || $config === []) {
            throw new AdminException('缺少必填参数: compensate_config');
        }
        if ($type !== 1) {
            throw new AdminException('补偿类型暂仅支持余额（1）');
        }
        $amount = (float) ($config['amount'] ?? 0);
        if ($amount <= 0) {
            throw new AdminException('补偿金额必须大于 0');
        }
        $amountStr = number_format($amount, 2, '.', '');

        Db::startTrans();
        try {
            $user = Db::name('user')->where('id', (int) $ticket['user_id'])->lock(true)->find();
            if ($user === null) {
                throw new AdminException('工单用户不存在', Resp::NOT_FOUND);
            }
            $before = (string) $user['money'];
            $after = bcadd($before, $amountStr, 2);
            Db::name('user')->where('id', (int) $user['id'])->update(['money' => $after, 'updatetime' => time()]);
            Db::name('wallet_transaction')->insert([
                'user_id'        => (int) $user['id'],
                'direction'      => 1,
                'amount'         => $amountStr,
                'balance_before' => $before,
                'balance_after'  => $after,
                'biz_type'       => 'ticket_compensate',
                'biz_id'         => (string) $ticket['ticket_no'],
                'remark'         => mb_substr('工单补偿:' . $reason, 0, 250),
                'createtime'     => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            if ($e instanceof AdminException) {
                throw $e;
            }
            throw new AdminException('补偿失败：' . $e->getMessage());
        }

        Guard::audit('ticket_compensate', (int) $admin['id'], $id,
            ['user_id' => (int) $ticket['user_id'], 'amount' => $amountStr, 'reason' => $reason]);
        return Resp::ok(['success' => true, 'amount' => $amountStr]);
    }

    // ========================================================

    /** 工单行序列化（脱敏契约 §3.7） */
    private static function ticketRow(array $r): array
    {
        return [
            'id'             => (int) $r['id'],
            'ticket_no'      => (string) $r['ticket_no'],
            'user_id'        => (int) $r['user_id'],
            'user_phone'     => self::maskPhone((string) $r['user_phone']),
            'ticket_type'    => (int) $r['ticket_type'],
            'priority'       => (int) $r['priority'],
            'title'          => (string) $r['title'],
            'description'    => (string) ($r['description'] ?? ''),
            'related_order_id'      => (int) ($r['related_order_id'] ?? 0),
            'related_collectible_id' => (int) ($r['related_collectible_id'] ?? 0),
            'related_blindbox_id'    => (int) ($r['related_blindbox_id'] ?? 0),
            'status'         => (int) $r['status'],
            'status_text'    => self::STATUS_MAP[(int) $r['status']] ?? '未知',
            'assignee_id'    => (int) ($r['assignee_id'] ?? 0),
            'assignee_name'  => (string) ($r['assignee_name'] ?? ''),
            'solved_at'      => self::iso((int) ($r['solved_at'] ?? 0)),
            'closed_at'      => self::iso((int) ($r['closed_at'] ?? 0)),
            'time'           => self::iso((int) $r['createtime']),
        ];
    }
}
