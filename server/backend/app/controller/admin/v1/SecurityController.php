<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * SecurityController —— 风控安全（契约 §8.16，15 个接口 / 5 组）
 *
 * 数据载体：
 *   黑名单     blacklist（type 1=用户 2=IP 3=设备；移出 status→0 + lifted_at/by + 审计）
 *   风控告警   risk_alert（处理 status + handle_comment + handler 留痕）
 *   安全事件   security_event（处理 status + handle_comment + handler 留痕）
 *   交易密码锁 tx_lock（只读 + 手动解锁 status→0/locked_until→0 + 审计）
 *   敏感审批   approval（approve→2 / reject→3；密码必填 + approver 留痕 + 审计；终态再处理 → 409）
 *
 * @package app\controller\admin\v1
 */
final class SecurityController
{
    use V1;

    // ========================================================
    // 8.16.1 黑名单（177~179）
    // ========================================================

    /** GET /security/blacklist（177） */
    public function blacklistList()
    {
        self::guard('security:blacklist:list');
        [$page, $size] = self::paging();
        $q = Db::name('blacklist')->alias('b')
            ->leftJoin('user u', 'u.id = b.user_id')
            ->field('b.*,u.nickname,u.mobile');
        if ($v = self::int('type')) {
            $q->where('b.blacklist_type', $v);
        }
        if (self::str('status') !== '') {
            $q->where('b.status', self::int('status') === 1 ? 1 : 0);
        }
        if ($v = self::int('user_id')) {
            $q->where('b.user_id', $v);
        }
        $q->order('b.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'             => (int) $r['id'],
            'user_id'        => (int) $r['user_id'],
            'nickname'       => (string) ($r['nickname'] ?? ''),
            'mobile'         => self::maskPhone((string) ($r['mobile'] ?? '')),
            'type'           => (int) $r['blacklist_type'],
            'target_value'   => (string) $r['target_value'],
            'reason'         => (string) $r['reason'],
            'evidence'       => (string) ($r['evidence'] ?? ''),
            'status'         => (int) $r['status'],
            'expires_at'     => self::iso((int) $r['expires_at']),
            'lifted_at'      => self::iso((int) $r['lifted_at']),
            'admin_name'     => (string) $r['admin_name'],
            'create_time'    => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /security/blacklist（178） */
    public function blacklistAdd()
    {
        $admin = self::guard('security:blacklist:create');
        self::requireFields(['user_id', 'type', 'reason']);
        $uid = self::int('user_id');
        $user = Db::name('user')->where('id', $uid)->find();
        if ($user === null) {
            throw new AdminException('用户不存在', Resp::NOT_FOUND);
        }
        $type = self::int('type');
        if ($type < 1 || $type > 3) {
            throw new AdminException('type 不合法（1=用户 2=IP 3=设备）');
        }
        $now = time();
        $id = (int) Db::name('blacklist')->insertGetId([
            'user_id' => $uid,
            'blacklist_type' => $type,
            'target_value' => (string) ($user['mobile'] !== '' ? $user['mobile'] : $user['username']),
            'reason' => mb_substr(self::str('reason'), 0, 255),
            'evidence' => self::str('evidence'),
            'admin_id' => (int) $admin['id'],
            'admin_name' => (string) $admin['username'],
            'status' => 1,
            'expires_at' => $this->timeParam('expires_at'),
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('security_blacklist_add', (int) $admin['id'], $id, ['user_id' => $uid, 'type' => $type]);
        return Resp::ok(['id' => $id]);
    }

    /** DELETE /security/blacklist/:id（179） */
    public function blacklistRemove()
    {
        $admin = self::guard('security:blacklist:delete');
        $id = self::int('id');
        if (self::str('reason') === '') {
            throw new AdminException('缺少必填参数: reason');
        }
        $r = Db::name('blacklist')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('黑名单记录不存在', Resp::NOT_FOUND);
        }
        $now = time();
        Db::name('blacklist')->where('id', $id)->update([
            'status' => 0, 'lifted_at' => $now, 'lifted_by' => (int) $admin['id'], 'updatetime' => $now,
        ]);
        Guard::audit('security_blacklist_remove', (int) $admin['id'], $id, ['reason' => self::str('reason')]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.16.2 风控告警（180~182）
    // ========================================================

    /** GET /security/risk-alerts（180） */
    public function riskAlerts()
    {
        self::guard('security:alert:list');
        [$page, $size] = self::paging();
        $q = Db::name('risk_alert');
        if ($v = self::int('type')) {
            $q->where('alert_type', $v);
        }
        if ($v = self::int('level')) {
            $q->where('alert_level', $v);
        }
        if (self::str('status') !== '') {
            $q->where('status', self::int('status'));
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<=', $e + 86399);
        }
        $q->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->alertBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** GET /security/risk-alerts/:id（181） */
    public function riskAlertDetail()
    {
        self::guard('security:alert:detail');
        $r = Db::name('risk_alert')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('告警不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->alertBrief($r) + [
            'evidence' => json_decode((string) ($r['evidence'] ?? ''), true)
                ?? (string) ($r['evidence'] ?? ''),
        ]);
    }

    /** PUT /security/risk-alerts/:id（182） */
    public function riskAlertHandle()
    {
        $admin = self::guard('security:alert:handle');
        $id = self::int('id');
        $r = Db::name('risk_alert')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('告警不存在', Resp::NOT_FOUND);
        }
        $status = self::int('status');
        if ($status < 1 || $status > 4) {
            throw new AdminException('status 不合法');
        }
        Db::name('risk_alert')->where('id', $id)->update([
            'status' => $status,
            'handle_comment' => mb_substr(self::str('handle_comment'), 0, 255),
            'handler_id' => (int) $admin['id'],
            'handler_name' => (string) $admin['username'],
            'handled_at' => time(),
        ]);
        Guard::audit('security_alert_handle', (int) $admin['id'], $id, ['status' => $status]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.16.3 安全事件（183~185）
    // ========================================================

    /** GET /security/events（183） */
    public function eventList()
    {
        self::guard('security:event:list');
        [$page, $size] = self::paging();
        $q = Db::name('security_event');
        if ($v = self::int('type')) {
            $q->where('event_type', $v);
        }
        if ($v = self::int('level')) {
            $q->where('event_level', $v);
        }
        if (self::str('status') !== '') {
            $q->where('status', self::int('status'));
        }
        if ($v = self::str('ip')) {
            $q->where('ip_address', $v);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<=', $e + 86399);
        }
        $q->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->eventBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** GET /security/events/:id（184） */
    public function eventDetail()
    {
        self::guard('security:event:detail');
        $r = Db::name('security_event')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('安全事件不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->eventBrief($r) + [
            'user_agent'     => (string) ($r['user_agent'] ?? ''),
            'request_method' => (string) ($r['request_method'] ?? ''),
            'request_params' => (string) ($r['request_params'] ?? ''),
            'response_status' => (int) ($r['response_status'] ?? 0),
        ]);
    }

    /** PUT /security/events/:id（185） */
    public function eventHandle()
    {
        $admin = self::guard('security:event:handle');
        $id = self::int('id');
        $r = Db::name('security_event')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('安全事件不存在', Resp::NOT_FOUND);
        }
        $status = self::int('status');
        if ($status < 1 || $status > 4) {
            throw new AdminException('status 不合法');
        }
        Db::name('security_event')->where('id', $id)->update([
            'status' => $status,
            'handle_comment' => mb_substr(self::str('comment'), 0, 255),
            'handler_id' => (int) $admin['id'],
            'handler_name' => (string) $admin['username'],
            'handled_at' => time(),
        ]);
        Guard::audit('security_event_handle', (int) $admin['id'], $id, ['status' => $status]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.16.4 交易密码锁定（186~187）
    // ========================================================

    /** GET /security/tx-locks（186） */
    public function txLocks()
    {
        self::guard('security:txlock:list');
        [$page, $size] = self::paging();
        $q = Db::name('tx_lock')->alias('t')
            ->leftJoin('user u', 'u.id = t.user_id')
            ->field('t.*,u.nickname,u.mobile');
        if ($v = self::int('user_id')) {
            $q->where('t.user_id', $v);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('t.createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('t.createtime', '<=', $e + 86399);
        }
        $q->order('t.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'            => (int) $r['id'],
            'user_id'       => (int) $r['user_id'],
            'nickname'      => (string) ($r['nickname'] ?? ''),
            'mobile'        => self::maskPhone((string) ($r['mobile'] ?? '')),
            'fail_count'    => (int) $r['fail_count'],
            'locked_until'  => self::iso((int) $r['locked_until']),
            'status'        => (int) $r['status'],
            'unlock_reason' => (string) $r['unlock_reason'],
            'unlocked_by'   => (int) $r['unlocked_by'],
            'create_time'   => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /security/tx-locks/:id/unlock（187） */
    public function txLockUnlock()
    {
        $admin = self::guard('security:txlock:manage');
        $id = self::int('id');
        if (self::str('reason') === '') {
            throw new AdminException('缺少必填参数: reason');
        }
        $r = Db::name('tx_lock')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('锁定记录不存在', Resp::NOT_FOUND);
        }
        $now = time();
        Db::name('tx_lock')->where('id', $id)->update([
            'status' => 0, 'locked_until' => 0,
            'unlock_reason' => mb_substr(self::str('reason'), 0, 255),
            'unlocked_by' => (int) $admin['id'], 'updatetime' => $now,
        ]);
        Guard::audit('security_txlock_unlock', (int) $admin['id'], $id, [
            'user_id' => (int) $r['user_id'], 'reason' => self::str('reason'),
        ]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.16.5 敏感操作审批（188~191）
    // ========================================================

    /** GET /security/approvals（188） */
    public function approvals()
    {
        self::guard('security:approval:list');
        [$page, $size] = self::paging();
        $q = Db::name('approval');
        if ($v = self::int('type')) {
            $q->where('approval_type', $v);
        }
        if (self::str('status') !== '') {
            $q->where('status', self::int('status'));
        }
        if ($v = self::int('applicant_id')) {
            $q->where('applicant_id', $v);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<=', $e + 86399);
        }
        $q->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->approvalBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** GET /security/approvals/:id（189） */
    public function approvalDetail()
    {
        self::guard('security:approval:detail');
        $r = Db::name('approval')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('审批不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->approvalBrief($r) + [
            'request_data' => json_decode((string) ($r['request_data'] ?? ''), true)
                ?? (string) ($r['request_data'] ?? ''),
        ]);
    }

    /** POST /security/approvals/:id/approve（190，高风险） */
    public function approvalApprove()
    {
        return $this->handleApproval(2, 'approve');
    }

    /** POST /security/approvals/:id/reject（191，高风险） */
    public function approvalReject()
    {
        return $this->handleApproval(3, 'reject');
    }

    // --------------------------------------------------------

    /** 审批处理共用（status 2=批准 3=拒绝） */
    private function handleApproval(int $targetStatus, string $action)
    {
        $admin = self::guard('security:approval:handle');
        self::verifyPassword($admin);
        $id = self::int('id');
        $r = Db::name('approval')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('审批不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['status'] !== 1) {
            throw new AdminException('审批已处理，不可重复操作', Resp::CONFLICT);
        }
        $now = time();
        Db::name('approval')->where('id', $id)->update([
            'status' => $targetStatus,
            'comment' => mb_substr(self::str('comment'), 0, 255),
            'approver_id' => (int) $admin['id'],
            'approver_name' => (string) $admin['username'],
            'approved_at' => $now,
        ]);
        Guard::audit('security_approval_' . $action, (int) $admin['id'], $id, ['status' => $targetStatus]);
        return Resp::ok(['success' => true]);
    }

    /** 告警行 → 输出 */
    private function alertBrief(array $r): array
    {
        return [
            'id'             => (int) $r['id'],
            'type'           => (int) $r['alert_type'],
            'level'          => (int) $r['alert_level'],
            'user_id'        => (int) ($r['user_id'] ?? 0),
            'target_type'    => (string) ($r['target_type'] ?? ''),
            'target_id'      => (int) ($r['target_id'] ?? 0),
            'title'          => (string) $r['title'],
            'description'    => (string) ($r['description'] ?? ''),
            'status'         => (int) $r['status'],
            'handler_name'   => (string) ($r['handler_name'] ?? ''),
            'handle_comment' => (string) ($r['handle_comment'] ?? ''),
            'handled_at'     => self::iso((int) ($r['handled_at'] ?? 0)),
            'create_time'    => self::iso((int) $r['createtime']),
        ];
    }

    /** 事件行 → 输出 */
    private function eventBrief(array $r): array
    {
        return [
            'id'             => (int) $r['id'],
            'type'           => (int) $r['event_type'],
            'level'          => (int) $r['event_level'],
            'user_id'        => (int) ($r['user_id'] ?? 0),
            'ip_address'     => (string) ($r['ip_address'] ?? ''),
            'request_path'   => (string) ($r['request_path'] ?? ''),
            'description'    => (string) ($r['description'] ?? ''),
            'status'         => (int) $r['status'],
            'handler_name'   => (string) ($r['handler_name'] ?? ''),
            'handle_comment' => (string) ($r['handle_comment'] ?? ''),
            'handled_at'     => self::iso((int) ($r['handled_at'] ?? 0)),
            'create_time'    => self::iso((int) $r['createtime']),
        ];
    }

    /** 审批行 → 输出 */
    private function approvalBrief(array $r): array
    {
        return [
            'id'             => (int) $r['id'],
            'type'           => (int) $r['approval_type'],
            'target_type'    => (string) $r['target_type'],
            'target_id'      => (int) $r['target_id'],
            'title'          => (string) $r['title'],
            'description'    => (string) ($r['description'] ?? ''),
            'applicant_id'   => (int) $r['applicant_id'],
            'applicant_name' => (string) $r['applicant_name'],
            'approver_id'    => (int) ($r['approver_id'] ?? 0),
            'approver_name'  => (string) ($r['approver_name'] ?? ''),
            'status'         => (int) $r['status'],
            'comment'        => (string) ($r['comment'] ?? ''),
            'create_time'    => self::iso((int) $r['createtime']),
            'approved_at'    => self::iso((int) $r['approved_at']),
        ];
    }

    /** 时间参数：unixtime 数字或日期字符串 → unixtime（空 → 0） */
    private function timeParam(string $key): int
    {
        $v = self::str($key);
        if ($v === '') {
            return 0;
        }
        return is_numeric($v) ? (int) $v : (int) strtotime($v);
    }
}
