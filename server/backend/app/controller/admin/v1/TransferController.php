<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * TransferController —— 转赠管理（契约 §8.10，6 个接口）
 *
 * 状态机 transfer_record.status：0=待接收 1=已完成 2=已拒绝 3=已撤销 4=已强制回退
 *   （4 为本 v1 扩展终态：已完成转赠被管理员强制回退）
 *
 * 资产载体：type=1 藏品 → user_collection（uc_id）；type=2 盲盒 → user.blind_box_num。
 * 撤销（revoke）：仅待接收(0)——资产名义上仍在 sender，无实际变动，只改状态留痕。
 * 强制回退（cancel）：仅已完成(1)——资产按 uc 归属真实回滚（receiver→sender）；
 *   接收方已二次流转（在售挂单）→ 409 拦截，资产不回滚（与藏品回收边界一致）。
 *
 * @package app\controller\admin\v1
 */
final class TransferController
{
    use V1;

    public const STATUS_PENDING  = 0;
    public const STATUS_DONE     = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_REVOKED  = 3;
    public const STATUS_ROLLED   = 4;

    /** 异常监控滑动窗口（天）与触发阈值 */
    private const ABNORMAL_WINDOW_DAYS = 7;
    private const HIGH_FREQ_THRESHOLD  = 5;
    private const REPEAT_PAIR_THRESHOLD = 3;

    /** GET /transfers —— 转赠列表（87） */
    public function list()
    {
        self::guard('transfer:list');
        [$page, $size] = self::paging();

        $q = Db::name('transfer_record')->alias('tr')
            ->leftJoin('user s', 's.id = tr.sender_id')
            ->leftJoin('user r', 'r.id = tr.receiver_id')
            ->leftJoin('collection c', 'c.id = tr.collectible_id')
            ->leftJoin('blind_box b', 'b.id = tr.blind_box_id')
            ->field('tr.*,s.nickname as sender_nickname,s.mobile as sender_mobile,'
                . 'r.nickname as receiver_nickname,r.mobile as receiver_mobile,'
                . 'c.name as collectible_name,b.name as blind_box_name');
        if ($v = self::int('sender_id')) {
            $q->where('tr.sender_id', $v);
        }
        if ($v = self::int('receiver_id')) {
            $q->where('tr.receiver_id', $v);
        }
        if (self::str('status') !== '') {
            $q->where('tr.status', self::int('status'));
        }
        if ($v = self::int('type')) {
            $q->where('tr.type', $v);
        }
        $q->order('tr.id', 'desc');

        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->brief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** GET /transfers/:id —— 转赠详情（88） */
    public function detail()
    {
        self::guard('transfer:detail');
        $r = Db::name('transfer_record')->alias('tr')
            ->leftJoin('user s', 's.id = tr.sender_id')
            ->leftJoin('user r2', 'r2.id = tr.receiver_id')
            ->leftJoin('collection c', 'c.id = tr.collectible_id')
            ->leftJoin('blind_box b', 'b.id = tr.blind_box_id')
            ->field('tr.*,s.nickname as sender_nickname,s.mobile as sender_mobile,'
                . 'r2.nickname as receiver_nickname,r2.mobile as receiver_mobile,'
                . 'c.name as collectible_name,b.name as blind_box_name')
            ->where('tr.id', self::int('id'))
            ->find();
        if ($r === null) {
            throw new AdminException('转赠记录不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->brief($r));
    }

    /** POST /transfers/:id/revoke —— 撤销待接收转赠（89，高风险：密码 + 审计） */
    public function revoke()
    {
        $admin = self::guard('transfer:manage');
        $id = self::int('id');
        $reason = self::str('reason');
        if ($id <= 0) {
            throw new AdminException('缺少必填参数: id');
        }
        if ($reason === '') {
            throw new AdminException('请填写撤销原因');
        }
        self::verifyPassword($admin);

        $tr = Db::name('transfer_record')->where('id', $id)->find();
        if ($tr === null) {
            throw new AdminException('转赠记录不存在', Resp::NOT_FOUND);
        }
        if ((int) $tr['status'] !== self::STATUS_PENDING) {
            throw new AdminException('仅待接收状态的转赠可撤销', Resp::CONFLICT);
        }

        Db::startTrans();
        try {
            // 待接收：资产已划转至接收方（uc 归属=receiver），撤销即真实回滚
            Db::name('transfer_record')->where('id', $id)->update([
                'status' => self::STATUS_REVOKED,
                'revoke_by' => (int) $admin['id'],
                'revoke_reason' => mb_substr($reason, 0, 255),
            ]);
            $this->rollBackAsset($tr);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        Guard::audit('transfer_revoke', (int) $admin['id'], $id, [
            'reason' => $reason, 'sender_id' => (int) $tr['sender_id'],
            'receiver_id' => (int) $tr['receiver_id'], 'transfer_no' => (string) $tr['transfer_no'],
        ]);
        return Resp::ok(['id' => $id]);
    }

    /** POST /transfers/:id/cancel —— 强制回退已完成转赠（90，高风险：密码 + 审计） */
    public function cancel()
    {
        $admin = self::guard('transfer:manage');
        $id = self::int('id');
        $reason = self::str('reason');
        if ($id <= 0) {
            throw new AdminException('缺少必填参数: id');
        }
        if ($reason === '') {
            throw new AdminException('请填写回退原因');
        }
        self::verifyPassword($admin);

        $tr = Db::name('transfer_record')->where('id', $id)->find();
        if ($tr === null) {
            throw new AdminException('转赠记录不存在', Resp::NOT_FOUND);
        }
        if ((int) $tr['status'] !== self::STATUS_DONE) {
            throw new AdminException('仅已完成状态的转赠可强制回退', Resp::CONFLICT);
        }

        // 二次流转保护：接收方已将在售挂单 → 拦截（与藏品回收边界一致）
        $listed = Db::name('market_goods')
            ->where('user_id', (int) $tr['receiver_id'])
            ->where('collection_id', (int) $tr['collectible_id'])
            ->where('status', 1)
            ->count();
        if ($listed > 0) {
            throw new AdminException('接收方已二次流转（挂单中），不可强制回退', Resp::CONFLICT);
        }

        Db::startTrans();
        try {
            Db::name('transfer_record')->where('id', $id)->update([
                'status' => self::STATUS_ROLLED,
                'revoke_by' => (int) $admin['id'],
                'revoke_reason' => mb_substr($reason, 0, 255),
            ]);
            $this->rollBackAsset($tr);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        Guard::audit('transfer_cancel', (int) $admin['id'], $id, [
            'reason' => $reason, 'sender_id' => (int) $tr['sender_id'],
            'receiver_id' => (int) $tr['receiver_id'], 'uc_id' => (int) $tr['uc_id'],
            'transfer_no' => (string) $tr['transfer_no'],
        ]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /transfers/stats —— 转赠统计（91，按日聚合走 PHP 侧应用时区；支持用户维度下钻） */
    public function stats()
    {
        self::guard('transfer:stats');
        $days = min(90, max(1, self::int('days', 7)));
        $todayStart = strtotime(date('Y-m-d'));
        $start = $todayStart - ($days - 1) * 86400;

        $q = Db::name('transfer_record')->where('createtime', '>=', $start);
        if ($v = self::int('sender_id')) {
            $q->where('sender_id', $v);
        }
        if ($v = self::int('receiver_id')) {
            $q->where('receiver_id', $v);
        }
        $totalCount = (clone $q)->count();
        $totalCollectibles = (clone $q)->where('type', 1)->count();
        $totalBlindboxes = (clone $q)->where('type', 2)->count();
        $activeUsers = (clone $q)->group('sender_id')->column('sender_id');
        $activeReceivers = (clone $q)->group('receiver_id')->column('receiver_id');

        // 🔴 按日聚合走 PHP 侧：MySQL FROM_UNIXTIME 依赖会话时区（多为 UTC），
        //    与应用时区（Asia/Shanghai）错位会把「今天」算进「昨天」
        $byDay = [];
        $rows = (clone $q)->field('createtime')->select()->toArray();
        foreach ($rows as $r) {
            $d = date('Y-m-d', (int) $r['createtime']);
            $byDay[$d] = ($byDay[$d] ?? 0) + 1;
        }
        $daily = [];
        for ($i = 0; $i < $days; $i++) {
            $d = date('Y-m-d', $todayStart - ($days - 1 - $i) * 86400);
            $daily[] = ['date' => $d, 'count' => $byDay[$d] ?? 0];
        }

        return Resp::ok([
            'days'               => $days,
            'total_count'        => $totalCount,
            'total_collectibles' => $totalCollectibles,
            'total_blindboxes'   => $totalBlindboxes,
            'active_users'       => count(array_unique(array_merge($activeUsers, $activeReceivers))),
            'daily_stats'        => $daily,
        ]);
    }

    /** GET /transfers/abnormal —— 异常转赠监控（92，滑动 7 日窗口） */
    public function abnormal()
    {
        self::guard('transfer:monitor');
        [$page, $size] = self::paging();
        $since = time() - self::ABNORMAL_WINDOW_DAYS * 86400;

        $items = [];
        // 高频发送者 / 接收者：7 日 ≥ 5 条（含撤销/回退终态）
        foreach ([['sender_id', 'high_frequency_sender'], ['receiver_id', 'high_frequency_receiver']] as [$col, $kind]) {
            $rows = Db::name('transfer_record')->where('createtime', '>=', $since)
                ->group($col)->field($col . ' as uid, COUNT(*) as cnt')
                ->having('cnt >= ' . self::HIGH_FREQ_THRESHOLD)->select()->toArray();
            foreach ($rows as $r) {
                $items[] = [
                    'kind'    => $kind,
                    'user_id' => (int) $r['uid'],
                    'count'   => (int) $r['cnt'],
                    'window'  => self::ABNORMAL_WINDOW_DAYS . 'd',
                ];
            }
        }
        // 反复互转对：同一对用户双向合计 ≥ 3 次
        $pairs = Db::name('transfer_record')->where('createtime', '>=', $since)
            ->field('LEAST(sender_id, receiver_id) as u1, GREATEST(sender_id, receiver_id) as u2, COUNT(*) as cnt')
            ->group('u1, u2')->having('cnt >= ' . self::REPEAT_PAIR_THRESHOLD)->select()->toArray();
        foreach ($pairs as $p) {
            $items[] = [
                'kind'    => 'repeated_pair',
                'user_a'  => (int) $p['u1'],
                'user_b'  => (int) $p['u2'],
                'count'   => (int) $p['cnt'],
                'window'  => self::ABNORMAL_WINDOW_DAYS . 'd',
            ];
        }
        usort($items, static fn ($a, $b) => $b['count'] <=> $a['count']);

        $total = count($items);
        $items = array_slice($items, ($page - 1) * $size, $size);
        return Resp::page($items, $total, $page, $size);
    }

    // --------------------------------------------------------

    /**
     * 资产真实回滚：uc 归属 receiver → sender（仅当当前归属确为接收方）
     * revoke（待接收）与 cancel（已完成）共用；盲盒型（type=2）无逐盒载体，不处理
     */
    private function rollBackAsset(array $tr): void
    {
        if ((int) $tr['uc_id'] > 0) {
            $uc = Db::name('user_collection')->where('id', (int) $tr['uc_id'])->find();
            if ($uc !== null && (int) $uc['user_id'] === (int) $tr['receiver_id']) {
                Db::name('user_collection')->where('id', (int) $tr['uc_id'])->update([
                    'user_id' => (int) $tr['sender_id'],
                    'utime' => time(),
                ]);
            }
        }
    }

    /** 转赠行 → 输出（双方手机号脱敏） */
    private function brief(array $r): array
    {
        return [
            'id'                 => (int) $r['id'],
            'transfer_no'        => (string) $r['transfer_no'],
            'type'               => (int) $r['type'],
            'collectible_id'     => (int) $r['collectible_id'],
            'collectible_name'   => (string) ($r['collectible_name'] ?? ''),
            'blind_box_id'       => (int) $r['blind_box_id'],
            'blind_box_name'     => (string) ($r['blind_box_name'] ?? ''),
            'uc_id'              => (int) $r['uc_id'],
            'sender_id'          => (int) $r['sender_id'],
            'sender_nickname'    => (string) ($r['sender_nickname'] ?? ''),
            'sender_mobile'      => self::maskPhone((string) ($r['sender_mobile'] ?? '')),
            'receiver_id'        => (int) $r['receiver_id'],
            'receiver_nickname'  => (string) ($r['receiver_nickname'] ?? ''),
            'receiver_mobile'    => self::maskPhone((string) ($r['receiver_mobile'] ?? '')),
            'status'             => (int) $r['status'],
            'revoke_reason'      => (string) ($r['revoke_reason'] ?? ''),
            'create_time'        => self::iso((int) $r['createtime']),
            'confirm_time'       => self::iso((int) $r['confirm_time']),
        ];
    }
}
