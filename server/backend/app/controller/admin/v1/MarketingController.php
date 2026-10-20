<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * MarketingController —— 营销活动（契约 §8.11，35 个接口 / 8 组）
 *
 * 数据载体：
 *   优先购   priority_sale_activity + priority_sale_whitelist（cleanup 终态 status=2）
 *   签到     site_config(marketing.checkin) + check_in_record
 *   邀请     site_config(marketing.invite)  + invite_record
 *   抽奖     lottery + lottery_prize + lottery_record（free_chances ↔ lottery.free_number）
 *   合成     synthesis_plan（materials→need_items，total_limit→stock）+ synthesis_record（C 端写入，管理端只读）
 *   活动空投 airdrop_task(source=activity) + airdrop_record（藏品→user_collection+airdropped_count；
 *            盲盒→user.blind_box_num，口径同藏品/盲盒模块空投）
 *   注册福利 site_config(marketing.register)
 *   盲盒数据 blind_box_open_record 聚合（total_opened / empty_rate / item_distribution）
 *
 * 概率闭环（同盲盒 §3.6.2）：奖项概率 ∈ (0,100]，非空奖概率总和 > 100 → 409 拦截；
 *   prize_type=6 为空奖（is_empty_prize=1），不参与总和校验（空奖=差额兜底）。
 *
 * @package app\controller\admin\v1
 */
final class MarketingController
{
    use V1;

    private const PRIZE_EMPTY = 6;

    // ========================================================
    // 8.11.1 优先购（93~102）
    // ========================================================

    /** GET /marketing/priority（93） */
    public function priorityList()
    {
        self::guard('marketing:priority:list');
        [$page, $size] = self::paging();
        $q = Db::name('priority_sale_activity')->alias('a')
            ->leftJoin('collection c', 'c.id = a.collectible_id')
            ->field('a.*,c.name as collectible_name');
        if ($cid = self::int('collectible_id')) {
            $q->where('a.collectible_id', $cid);
        }
        if (self::str('status') !== '') {
            $q->where('a.status', self::int('status'));
        }
        $q->order('a.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->priorityBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /marketing/priority（94） */
    public function priorityCreate()
    {
        $admin = self::guard('marketing:priority:create');
        self::requireFields(['collectible_id', 'start_time', 'end_time']);
        $cid = self::int('collectible_id');
        $col = Db::name('collection')->where('id', $cid)->find();
        if ($col === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        [$start, $end] = $this->timeRange('start_time', 'end_time');
        if ($end <= $start) {
            throw new AdminException('结束时间必须晚于开始时间');
        }
        $now = time();
        $id = (int) Db::name('priority_sale_activity')->insertGetId([
            'collectible_id' => $cid,
            'start_time' => $start,
            'end_time' => $end,
            'max_quantity_per_user' => max(0, self::int('max_quantity_per_user')),
            'status' => 1,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('priority_create', (int) $admin['id'], $id, ['collectible_id' => $cid, 'start' => $start, 'end' => $end]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /marketing/priority/:id（95） */
    public function priorityDetail()
    {
        self::guard('marketing:priority:detail');
        $r = Db::name('priority_sale_activity')->alias('a')
            ->leftJoin('collection c', 'c.id = a.collectible_id')
            ->field('a.*,c.name as collectible_name')
            ->where('a.id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('优先购活动不存在', Resp::NOT_FOUND);
        }
        $data = $this->priorityBrief($r) + [
            'whitelist_count' => (int) Db::name('priority_sale_whitelist')->where('activity_id', $r['id'])->where('status', 1)->count(),
        ];
        return Resp::ok($data);
    }

    /** PUT /marketing/priority/:id（96） */
    public function priorityEdit()
    {
        $admin = self::guard('marketing:priority:edit');
        $id = self::int('id');
        $r = Db::name('priority_sale_activity')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('优先购活动不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if (self::str('start_time') !== '') {
            $upd['start_time'] = $this->timeParam('start_time');
        }
        if (self::str('end_time') !== '') {
            $upd['end_time'] = $this->timeParam('end_time');
        }
        if (isset($upd['start_time'], $upd['end_time']) && $upd['end_time'] <= $upd['start_time']) {
            throw new AdminException('结束时间必须晚于开始时间');
        }
        if (self::str('max_quantity_per_user') !== '') {
            $upd['max_quantity_per_user'] = max(0, self::int('max_quantity_per_user'));
        }
        if (self::str('status') !== '') {
            $upd['status'] = self::int('status') === 1 ? 1 : 0;
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('priority_sale_activity')->where('id', $id)->update($upd);
        }
        Guard::audit('priority_edit', (int) $admin['id'], $id, $upd);
        return Resp::ok(['success' => true]);
    }

    /** GET /marketing/priority/:id/whitelist（97） */
    public function whitelistList()
    {
        self::guard('marketing:priority:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');
        $q = Db::name('priority_sale_whitelist')->alias('w')
            ->leftJoin('user u', 'u.id = w.user_id')
            ->field('w.*,u.nickname')
            ->where('w.activity_id', $id);
        if (self::str('status') !== '') {
            $q->where('w.status', self::int('status'));
        }
        $q->order('w.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->whitelistBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /marketing/priority/:id/whitelist（98） */
    public function whitelistAdd()
    {
        $admin = self::guard('marketing:priority:whitelist');
        $id = self::int('id');
        self::requireFields(['user_id']);
        $activity = Db::name('priority_sale_activity')->where('id', $id)->find();
        if ($activity === null) {
            throw new AdminException('优先购活动不存在', Resp::NOT_FOUND);
        }
        $this->whitelistInsert((int) $activity['collectible_id'], $id, self::int('user_id'),
            self::str('phone'), max(1, self::int('max_quantity', 1)), $this->timeParam('expires_at'));
        Guard::audit('priority_whitelist_add', (int) $admin['id'], $id, ['user_id' => self::int('user_id')]);
        return Resp::ok(['success' => true]);
    }

    /** POST /marketing/priority/:id/whitelist/batch（99） */
    public function whitelistBatch()
    {
        $admin = self::guard('marketing:priority:whitelist');
        $id = self::int('id');
        $activity = Db::name('priority_sale_activity')->where('id', $id)->find();
        if ($activity === null) {
            throw new AdminException('优先购活动不存在', Resp::NOT_FOUND);
        }
        $items = request()->post('items');
        if (!is_array($items) || $items === []) {
            throw new AdminException('items 不能为空');
        }
        $success = 0;
        $fail = 0;
        $failList = [];
        foreach (array_values($items) as $i => $item) {
            $item = is_array($item) ? $item : [];
            $uid = (int) ($item['user_id'] ?? 0);
            $phone = trim((string) ($item['phone'] ?? ''));
            if ($uid <= 0 && $phone !== '') {
                $uid = (int) Db::name('user')->where('mobile', $phone)->value('id');
            }
            $exists = $uid > 0 && Db::name('user')->where('id', $uid)->find() !== null;
            if (!$exists) {
                $fail++;
                $failList[] = ['index' => $i, 'user_id' => $uid, 'phone' => self::maskPhone($phone), 'reason' => '用户不存在'];
                continue;
            }
            $this->whitelistInsert((int) $activity['collectible_id'], $id, $uid, $phone,
                max(1, (int) ($item['max_quantity'] ?? 1)), (int) ($item['expires_at'] ?? 0));
            $success++;
        }
        Guard::audit('priority_whitelist_batch', (int) $admin['id'], $id,
            ['success' => $success, 'fail' => $fail]);
        return Resp::ok(['success_count' => $success, 'fail_count' => $fail, 'fail_list' => $failList]);
    }

    /** PUT /marketing/priority/:id/whitelist/:wid（100） */
    public function whitelistEdit()
    {
        $admin = self::guard('marketing:priority:whitelist');
        $id = self::int('id');
        $wid = self::int('wid');
        $w = Db::name('priority_sale_whitelist')->where('id', $wid)->where('activity_id', $id)->find();
        if ($w === null) {
            throw new AdminException('白名单记录不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if (self::str('max_quantity') !== '') {
            $upd['max_quantity'] = max(1, self::int('max_quantity'));
        }
        if (self::str('expires_at') !== '') {
            $upd['expires_at'] = $this->timeParam('expires_at');
        }
        if (self::str('status') !== '') {
            $upd['status'] = self::int('status');
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('priority_sale_whitelist')->where('id', $wid)->update($upd);
        }
        Guard::audit('priority_whitelist_edit', (int) $admin['id'], $wid, $upd);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /marketing/priority/:id/whitelist/:wid（101） */
    public function whitelistDelete()
    {
        $admin = self::guard('marketing:priority:whitelist');
        $id = self::int('id');
        $wid = self::int('wid');
        $w = Db::name('priority_sale_whitelist')->where('id', $wid)->where('activity_id', $id)->find();
        if ($w === null) {
            throw new AdminException('白名单记录不存在', Resp::NOT_FOUND);
        }
        Db::name('priority_sale_whitelist')->where('id', $wid)->delete();
        Guard::audit('priority_whitelist_delete', (int) $admin['id'], $wid, ['user_id' => (int) $w['user_id']]);
        return Resp::ok(['success' => true]);
    }

    /** POST /marketing/priority/cleanup（102，高风险：密码 + 审计） */
    public function priorityCleanup()
    {
        $admin = self::guard('marketing:priority:manage');
        self::verifyPassword($admin);
        $now = time();
        $cleaned = Db::name('priority_sale_whitelist')
            ->where('status', 1)
            ->where('expires_at', '>', 0)
            ->where('expires_at', '<', $now)
            ->update(['status' => 2, 'updatetime' => $now]);
        Guard::audit('priority_cleanup', (int) $admin['id'], 0, ['cleaned_count' => $cleaned]);
        return Resp::ok(['cleaned_count' => $cleaned]);
    }

    // ========================================================
    // 8.11.2 签到（103~105）
    // ========================================================

    /** GET /marketing/checkin/config（103） */
    public function checkinConfig()
    {
        self::guard('marketing:checkin:config');
        return Resp::ok($this->readConfig('marketing.checkin',
            ['is_enabled' => 1, 'rewards' => []]));
    }

    /** PUT /marketing/checkin/config（104） */
    public function checkinConfigUpdate()
    {
        $admin = self::guard('marketing:checkin:config');
        $rewards = request()->post('rewards');
        if (!is_array($rewards)) {
            throw new AdminException('rewards 必须为数组');
        }
        $list = [];
        foreach (array_values($rewards) as $r) {
            $r = is_array($r) ? $r : [];
            $day = (int) ($r['day'] ?? 0);
            if ($day < 1) {
                throw new AdminException('奖励天数必须 ≥ 1');
            }
            $list[] = ['day' => $day, 'amount' => self::money($r['amount'] ?? '0')];
        }
        $this->writeConfig('marketing.checkin', 'marketing', [
            'is_enabled' => self::int('is_enabled') === 1 ? 1 : 0,
            'rewards' => $list,
        ]);
        Guard::audit('checkin_config', (int) $admin['id'], 0, ['count' => count($list)]);
        return Resp::ok(['success' => true]);
    }

    /** GET /marketing/checkin/records（105） */
    public function checkinRecords()
    {
        self::guard('marketing:checkin:list');
        [$page, $size] = self::paging();
        $q = Db::name('check_in_record')->alias('r')
            ->leftJoin('user u', 'u.id = r.user_id')
            ->field('r.*,u.nickname,u.mobile');
        if ($uid = self::int('user_id')) {
            $q->where('r.user_id', $uid);
        }
        if ($d = self::str('date_start')) {
            $q->where('r.checkin_date', '>=', $d);
        }
        if ($d = self::str('date_end')) {
            $q->where('r.checkin_date', '<=', $d);
        }
        $q->order('r.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'            => (int) $r['id'],
            'user_id'       => (int) $r['user_id'],
            'nickname'      => (string) ($r['nickname'] ?? ''),
            'mobile'        => self::maskPhone((string) ($r['mobile'] ?? '')),
            'checkin_date'  => (string) $r['checkin_date'],
            'successions'   => (int) $r['successions'],
            'reward_type'   => (string) $r['reward_type'],
            'reward_status' => (int) $r['reward_status'],
            'create_time'   => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.11.3 邀请（106~108）
    // ========================================================

    /** GET /marketing/invite/config（106） */
    public function inviteConfig()
    {
        self::guard('marketing:invite:config');
        return Resp::ok($this->readConfig('marketing.invite',
            ['is_enabled' => 1, 'tiers' => [], 'register_reward' => '0.00']));
    }

    /** PUT /marketing/invite/config（107） */
    public function inviteConfigUpdate()
    {
        $admin = self::guard('marketing:invite:config');
        $tiers = request()->post('tiers');
        if (!is_array($tiers)) {
            throw new AdminException('tiers 必须为数组');
        }
        $list = [];
        foreach (array_values($tiers) as $t) {
            $t = is_array($t) ? $t : [];
            $level = (int) ($t['level'] ?? 0);
            if ($level < 1) {
                throw new AdminException('档位级别必须 ≥ 1');
            }
            $list[] = ['level' => $level, 'reward' => self::money($t['reward'] ?? '0')];
        }
        $this->writeConfig('marketing.invite', 'marketing', [
            'is_enabled' => self::int('is_enabled') === 1 ? 1 : 0,
            'tiers' => $list,
            'register_reward' => self::money(self::str('register_reward', '0')),
        ]);
        Guard::audit('invite_config', (int) $admin['id'], 0, ['tiers' => count($list)]);
        return Resp::ok(['success' => true]);
    }

    /** GET /marketing/invite/records（108） */
    public function inviteRecords()
    {
        self::guard('marketing:invite:list');
        [$page, $size] = self::paging();
        $q = Db::name('invite_record')->alias('ir')
            ->leftJoin('user si', 'si.id = ir.inviter_id')
            ->leftJoin('user re', 're.id = ir.invitee_id')
            ->field('ir.*,si.nickname as inviter_nickname,re.nickname as invitee_nickname');
        if ($uid = self::int('inviter_id')) {
            $q->where('ir.inviter_id', $uid);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('ir.createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('ir.createtime', '<=', $e);
        }
        $q->order('ir.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'                => (int) $r['id'],
            'inviter_id'        => (int) $r['inviter_id'],
            'inviter_nickname'  => (string) ($r['inviter_nickname'] ?? ''),
            'invitee_id'        => (int) $r['invitee_id'],
            'invitee_nickname'  => (string) ($r['invitee_nickname'] ?? ''),
            'invitee_phone'     => self::maskPhone((string) $r['invitee_phone']),
            'reward_type'       => (string) ($r['reward_type'] ?? ''),
            'status'            => (int) $r['status'],
            'create_time'       => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.11.4 抽奖（109~117）
    // ========================================================

    /** GET /marketing/lucky-draw（109） */
    public function luckyList()
    {
        self::guard('marketing:lucky:list');
        [$page, $size] = self::paging();
        $q = Db::name('lottery');
        if (self::str('status') !== '') {
            $q->where('switch', self::int('status') === 1 ? 1 : 0);
        }
        $q->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->luckyBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /marketing/lucky-draw（110） */
    public function luckyCreate()
    {
        $admin = self::guard('marketing:lucky:create');
        self::requireFields(['name', 'start_time', 'end_time']);
        [$start, $end] = $this->timeRange('start_time', 'end_time');
        if ($end <= $start) {
            throw new AdminException('结束时间必须晚于开始时间');
        }
        $now = time();
        $id = (int) Db::name('lottery')->insertGetId([
            'name' => mb_substr(self::str('name'), 0, 255),
            'content' => self::str('description'),
            'crystal' => '10',
            'lottery_max' => '0',
            'collection_json' => '[]',
            'free_number' => max(0, self::int('free_chances')),
            'exchange_config' => json_encode($this->arrayParam('exchange_config'), JSON_UNESCAPED_UNICODE),
            'start_time' => $start,
            'end_time' => $end,
            'switch' => 1,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('lucky_create', (int) $admin['id'], $id, ['name' => self::str('name')]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /marketing/lucky-draw/:id（111） */
    public function luckyDetail()
    {
        self::guard('marketing:lucky:detail');
        $r = Db::name('lottery')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('抽奖活动不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->luckyBrief($r) + [
            'description'     => (string) ($r['content'] ?? ''),
            'exchange_config' => $this->jsonDecode((string) ($r['exchange_config'] ?? '')),
            'grand_total'     => (int) $r['grand_total'],
        ]);
    }

    /** PUT /marketing/lucky-draw/:id（112） */
    public function luckyEdit()
    {
        $admin = self::guard('marketing:lucky:edit');
        $id = self::int('id');
        $r = Db::name('lottery')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('抽奖活动不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if ($v = self::str('name')) {
            $upd['name'] = mb_substr($v, 0, 255);
        }
        if (self::str('description') !== '') {
            $upd['content'] = self::str('description');
        }
        if (self::str('start_time') !== '') {
            $upd['start_time'] = $this->timeParam('start_time');
        }
        if (self::str('end_time') !== '') {
            $upd['end_time'] = $this->timeParam('end_time');
        }
        if (isset($upd['start_time'], $upd['end_time']) && $upd['end_time'] <= $upd['start_time']) {
            throw new AdminException('结束时间必须晚于开始时间');
        }
        if (self::str('status') !== '') {
            $upd['switch'] = self::int('status') === 1 ? 1 : 0;
        }
        if (self::str('free_chances') !== '') {
            $upd['free_number'] = max(0, self::int('free_chances'));
        }
        if (request()->post('exchange_config') !== null) {
            $upd['exchange_config'] = json_encode($this->arrayParam('exchange_config'), JSON_UNESCAPED_UNICODE);
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('lottery')->where('id', $id)->update($upd);
        }
        Guard::audit('lucky_edit', (int) $admin['id'], $id, $upd);
        return Resp::ok(['success' => true]);
    }

    /** GET /marketing/lucky-draw/:id/prizes（113） */
    public function prizeList()
    {
        self::guard('marketing:lucky:detail');
        $id = self::int('id');
        if (Db::name('lottery')->where('id', $id)->find() === null) {
            throw new AdminException('抽奖活动不存在', Resp::NOT_FOUND);
        }
        $rows = Db::name('lottery_prize')->where('lottery_id', $id)->order('id')->select()->toArray();
        $list = array_map(fn ($r) => $this->prizeBrief($r), $rows);
        return Resp::ok(['list' => $list]);
    }

    /** POST /marketing/lucky-draw/:id/prizes（114） */
    public function prizeAdd()
    {
        $admin = self::guard('marketing:lucky:prize');
        $id = self::int('id');
        $lottery = Db::name('lottery')->where('id', $id)->find();
        if ($lottery === null) {
            throw new AdminException('抽奖活动不存在', Resp::NOT_FOUND);
        }
        self::requireFields(['name', 'prize_type', 'probability']);
        $prizeType = self::int('prize_type');
        if ($prizeType < 1 || $prizeType > 6) {
            throw new AdminException('prize_type 不合法');
        }
        $prob = (float) self::str('probability');
        if ($prob <= 0 || $prob > 100) {
            throw new AdminException('probability 必须 ∈ (0, 100]');
        }
        $isEmpty = $prizeType === self::PRIZE_EMPTY ? 1 : 0;
        if ($isEmpty === 0) {
            $sum = (float) Db::name('lottery_prize')
                ->where('lottery_id', $id)->where('is_empty_prize', 0)
                ->sum('probability');
            if ($sum + $prob > 100 + 1e-9) {
                throw new AdminException('非空奖概率总和超过 100%，当前已占 ' . round($sum, 4) . '%', Resp::CONFLICT);
            }
        }
        $now = time();
        $pid = (int) Db::name('lottery_prize')->insertGetId([
            'lottery_id' => $id,
            'name' => mb_substr(self::str('name'), 0, 100),
            'prize_type' => $prizeType,
            'prize_config' => json_encode($this->arrayParam('prize_config'), JSON_UNESCAPED_UNICODE),
            'probability' => number_format($prob, 4, '.', ''),
            'planned_quantity' => max(0, self::int('planned_quantity')),
            'is_empty_prize' => $isEmpty,
            'status' => self::int('status', 1) === 1 ? 1 : 0,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('lucky_prize_add', (int) $admin['id'], $pid, ['lottery_id' => $id, 'probability' => $prob]);
        return Resp::ok(['id' => $pid]);
    }

    /** PUT /marketing/lucky-draw/:id/prizes/:pid（115） */
    public function prizeEdit()
    {
        $admin = self::guard('marketing:lucky:prize');
        $id = self::int('id');
        $pid = self::int('pid');
        $r = Db::name('lottery_prize')->where('id', $pid)->where('lottery_id', $id)->find();
        if ($r === null) {
            throw new AdminException('奖项不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if ($v = self::str('name')) {
            $upd['name'] = mb_substr($v, 0, 100);
        }
        if (self::str('prize_type') !== '') {
            $pt = self::int('prize_type');
            if ($pt < 1 || $pt > 6) {
                throw new AdminException('prize_type 不合法');
            }
            $upd['prize_type'] = $pt;
            $upd['is_empty_prize'] = $pt === self::PRIZE_EMPTY ? 1 : 0;
        }
        if (request()->post('prize_config') !== null) {
            $upd['prize_config'] = json_encode($this->arrayParam('prize_config'), JSON_UNESCAPED_UNICODE);
        }
        if (self::str('planned_quantity') !== '') {
            $upd['planned_quantity'] = max(0, self::int('planned_quantity'));
        }
        if (self::str('status') !== '') {
            $upd['status'] = self::int('status') === 1 ? 1 : 0;
        }
        if (self::str('probability') !== '') {
            $prob = (float) self::str('probability');
            if ($prob <= 0 || $prob > 100) {
                throw new AdminException('probability 必须 ∈ (0, 100]');
            }
            // 概率总和闭环：排除本行后校验（空奖不参与）
            $willEmpty = (int) ($upd['is_empty_prize'] ?? $r['is_empty_prize']) === 1;
            if (!$willEmpty) {
                $sum = (float) Db::name('lottery_prize')
                    ->where('lottery_id', $id)->where('is_empty_prize', 0)
                    ->where('id', '<>', $pid)->sum('probability');
                if ($sum + $prob > 100 + 1e-9) {
                    throw new AdminException('非空奖概率总和超过 100%，当前已占 ' . round($sum, 4) . '%', Resp::CONFLICT);
                }
            }
            $upd['probability'] = number_format($prob, 4, '.', '');
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('lottery_prize')->where('id', $pid)->update($upd);
        }
        Guard::audit('lucky_prize_edit', (int) $admin['id'], $pid, $upd);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /marketing/lucky-draw/:id/prizes/:pid（116） */
    public function prizeDelete()
    {
        $admin = self::guard('marketing:lucky:prize');
        $id = self::int('id');
        $pid = self::int('pid');
        $r = Db::name('lottery_prize')->where('id', $pid)->where('lottery_id', $id)->find();
        if ($r === null) {
            throw new AdminException('奖项不存在', Resp::NOT_FOUND);
        }
        Db::name('lottery_prize')->where('id', $pid)->delete();
        Guard::audit('lucky_prize_delete', (int) $admin['id'], $pid, ['name' => (string) $r['name']]);
        return Resp::ok(['success' => true]);
    }

    /** GET /marketing/lucky-draw/:id/records（117） */
    public function luckyRecords()
    {
        self::guard('marketing:lucky:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');
        $q = Db::name('lottery_record')->alias('lr')
            ->leftJoin('user u', 'u.id = lr.user_id')
            ->field('lr.*,u.nickname,u.mobile')
            ->where('lr.lottery_id', $id)
            ->order('lr.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'            => (int) $r['id'],
            'user_id'       => (int) $r['user_id'],
            'nickname'      => (string) ($r['nickname'] ?? ''),
            'mobile'        => self::maskPhone((string) ($r['mobile'] ?? '')),
            'collectible_id' => (int) $r['collection_id'],
            'prize_name'    => (string) $r['lottery_lv'],
            'score'         => (int) $r['score'],
            'create_time'   => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.11.5 合成（118~122）
    // ========================================================

    /** GET /marketing/synthesis（118） */
    public function synthesisList()
    {
        self::guard('marketing:synthesis:list');
        [$page, $size] = self::paging();
        $q = Db::name('synthesis_plan')->alias('sp')
            ->leftJoin('collection c', 'c.id = sp.target_collectible_id')
            ->field('sp.*,c.name as target_name');
        if (self::str('status') !== '') {
            $q->where('sp.status', self::int('status'));
        }
        $q->order('sp.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->synthesisBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /marketing/synthesis（119） */
    public function synthesisCreate()
    {
        $admin = self::guard('marketing:synthesis:create');
        self::requireFields(['name', 'target_collectible_id', 'start_time', 'end_time']);
        $cid = self::int('target_collectible_id');
        if (Db::name('collection')->where('id', $cid)->find() === null) {
            throw new AdminException('目标藏品不存在', Resp::NOT_FOUND);
        }
        [$start, $end] = $this->timeRange('start_time', 'end_time');
        if ($end <= $start) {
            throw new AdminException('结束时间必须晚于开始时间');
        }
        $materials = $this->arrayParam('materials');
        $now = time();
        $id = (int) Db::name('synthesis_plan')->insertGetId([
            'theme_id' => self::int('theme_id'),
            'title' => mb_substr(self::str('name'), 0, 255),
            'target_collectible_id' => $cid,
            'need_items' => json_encode($materials, JSON_UNESCAPED_UNICODE),
            'per_user_limit' => max(0, self::int('per_user_limit')),
            'stock' => max(0, self::int('total_limit')),
            'start_time' => $start * 1000,   // ⚠️ 实测形态：synthesis_plan 时间为 13 位毫秒
            'end_time' => $end * 1000,
            'status' => 1,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
        Guard::audit('synthesis_create', (int) $admin['id'], $id, ['name' => self::str('name'), 'target' => $cid]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /marketing/synthesis/:id（120） */
    public function synthesisDetail()
    {
        self::guard('marketing:synthesis:detail');
        $r = Db::name('synthesis_plan')->alias('sp')
            ->leftJoin('collection c', 'c.id = sp.target_collectible_id')
            ->field('sp.*,c.name as target_name')
            ->where('sp.id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('合成活动不存在', Resp::NOT_FOUND);
        }
        return Resp::ok($this->synthesisBrief($r) + [
            'target_name' => (string) ($r['target_name'] ?? ''),
            'compose_count' => (int) Db::name('synthesis_record')->where('plan_id', $r['id'])->count(),
        ]);
    }

    /** PUT /marketing/synthesis/:id（121） */
    public function synthesisEdit()
    {
        $admin = self::guard('marketing:synthesis:edit');
        $id = self::int('id');
        $r = Db::name('synthesis_plan')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('合成活动不存在', Resp::NOT_FOUND);
        }
        $upd = [];
        if ($v = self::str('name')) {
            $upd['title'] = mb_substr($v, 0, 255);
        }
        if (self::str('target_collectible_id') !== '') {
            $cid = self::int('target_collectible_id');
            if (Db::name('collection')->where('id', $cid)->find() === null) {
                throw new AdminException('目标藏品不存在', Resp::NOT_FOUND);
            }
            $upd['target_collectible_id'] = $cid;
        }
        if (request()->post('materials') !== null) {
            $upd['need_items'] = json_encode($this->arrayParam('materials'), JSON_UNESCAPED_UNICODE);
        }
        if (self::str('per_user_limit') !== '') {
            $upd['per_user_limit'] = max(0, self::int('per_user_limit'));
        }
        if (self::str('total_limit') !== '') {
            $upd['stock'] = max(0, self::int('total_limit'));
        }
        if (self::str('start_time') !== '') {
            $upd['start_time'] = $this->timeParam('start_time') * 1000;
        }
        if (self::str('end_time') !== '') {
            $upd['end_time'] = $this->timeParam('end_time') * 1000;
        }
        if (self::str('status') !== '') {
            $upd['status'] = self::int('status') === 1 ? 1 : 0;
        }
        if ($upd !== []) {
            $upd['updatetime'] = time();
            Db::name('synthesis_plan')->where('id', $id)->update($upd);
        }
        Guard::audit('synthesis_edit', (int) $admin['id'], $id, $upd);
        return Resp::ok(['success' => true]);
    }

    /** GET /marketing/synthesis/:id/records（122） */
    public function synthesisRecords()
    {
        self::guard('marketing:synthesis:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');
        $q = Db::name('synthesis_record')->alias('sr')
            ->leftJoin('user u', 'u.id = sr.user_id')
            ->field('sr.*,u.nickname,u.mobile')
            ->where('sr.plan_id', $id)
            ->order('sr.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => [
            'id'                    => (int) $r['id'],
            'user_id'               => (int) $r['user_id'],
            'nickname'              => (string) ($r['nickname'] ?? ''),
            'mobile'                => self::maskPhone((string) ($r['mobile'] ?? '')),
            'materials'             => $this->jsonDecode((string) ($r['materials'] ?? '')),
            'target_collectible_id' => (int) $r['target_collectible_id'],
            'status'                => (int) $r['status'],
            'fail_reason'           => (string) $r['fail_reason'],
            'create_time'           => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.11.6 活动空投（123~124）
    // ========================================================

    /**
     * POST /marketing/airdrop（123，高风险：密码 + 审计）
     * reward_type：1=藏品 2=盲盒；reward_config：{collectible_id|blind_box_id, quantity}
     */
    public function airdrop()
    {
        $admin = self::guard('marketing:airdrop');
        self::verifyPassword($admin);
        $activityType = self::str('activity_type');
        $activityId = self::int('activity_id');
        $rewardType = self::int('reward_type');
        if ($activityType === '') {
            throw new AdminException('缺少必填参数: activity_type');
        }
        if (!in_array($rewardType, [1, 2], true)) {
            throw new AdminException('reward_type 不合法（1=藏品 2=盲盒）');
        }
        $cfg = $this->arrayParam('reward_config');
        $quantity = max(1, (int) ($cfg['quantity'] ?? 1));
        $phones = request()->post('phones');
        if (!is_array($phones) || $phones === []) {
            throw new AdminException('phones 不能为空');
        }
        $phones = array_values(array_unique(array_map('strval', $phones)));
        if (count($phones) > 1000) {
            throw new AdminException('单次空投人数不得超过 1000', Resp::CONFLICT);
        }

        $now = time();
        if ($rewardType === 1) {
            $cid = (int) ($cfg['collectible_id'] ?? 0);
            $col = Db::name('collection')->where('id', $cid)->find();
            if ($col === null) {
                throw new AdminException('藏品不存在', Resp::NOT_FOUND);
            }
            $reserved = (int) Db::name('inventory_quota')
                ->where('collectible_id', $cid)->where('status', 1)->sum('planned_quantity');
            $pool = (int) $col['total_num'] - $reserved - (int) $col['sold_count']
                - (int) $col['airdropped_count'] - (int) $col['destroyed_count'];
            if ($quantity * count($phones) > $pool) {
                throw new AdminException('空投总量超过库存池', Resp::CONFLICT);
            }
            [$taskId, $success, $fail] = $this->doAirdrop($admin, 1, $cid, (string) $col['name'],
                $quantity, $phones, $activityType, $activityId, $rewardType);
            Db::name('collection')->where('id', $cid)->inc('airdropped_count', $success * $quantity)
                ->update(['updatetime' => time()]);
        } else {
            $bid = (int) ($cfg['blind_box_id'] ?? 0);
            $box = Db::name('blind_box')->where('id', $bid)->find();
            if ($box === null) {
                throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
            }
            $pool = (int) $box['total_num'] - (int) $box['sale_number']
                - (int) $box['airdropped_count'] - (int) $box['destroyed_count'];
            if ($quantity * count($phones) > $pool) {
                throw new AdminException('空投总量超过盲盒库存池', Resp::CONFLICT);
            }
            [$taskId, $success, $fail] = $this->doAirdrop($admin, 2, $bid, (string) $box['name'],
                $quantity, $phones, $activityType, $activityId, $rewardType);
            Db::name('blind_box')->where('id', $bid)->inc('airdropped_count', $success * $quantity)
                ->update(['updatetime' => time()]);
        }
        Db::name('airdrop_task')->where('id', $taskId)->update(['success_count' => $success, 'fail_count' => $fail]);
        Guard::audit('marketing_airdrop', (int) $admin['id'], $taskId, [
            'activity_type' => $activityType, 'activity_id' => $activityId,
            'reward_type' => $rewardType, 'quantity' => $quantity,
            'phones' => count($phones), 'success' => $success, 'fail' => $fail,
        ]);
        return Resp::ok(['task_id' => $taskId, 'success_count' => $success, 'fail_count' => $fail]);
    }

    /** GET /marketing/airdrop/records（124） */
    public function airdropRecords()
    {
        self::guard('marketing:airdrop');
        [$page, $size] = self::paging();
        $q = Db::name('airdrop_record')->alias('ar')
            ->join('airdrop_task at', 'at.id = ar.task_id')
            ->field('ar.*,at.target_name,at.quantity,at.activity_type,at.activity_id,at.reward_type,at.admin_name')
            ->where('at.source', 'activity');
        if ($v = self::str('activity_type')) {
            $q->where('at.activity_type', $v);
        }
        if ($v = self::int('activity_id')) {
            $q->where('at.activity_id', $v);
        }
        [$s, $e] = self::dateRange();
        if ($s > 0) {
            $q->where('ar.createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('ar.createtime', '<=', $e);
        }
        $q->order('ar.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'             => (int) $r['id'],
            'task_id'        => (int) $r['task_id'],
            'user_id'        => (int) $r['user_id'],
            'phone'          => self::maskPhone((string) $r['phone']),
            'target_name'    => (string) $r['target_name'],
            'quantity'       => (int) $r['quantity'],
            'activity_type'  => (string) $r['activity_type'],
            'activity_id'    => (int) $r['activity_id'],
            'reward_type'    => (int) $r['reward_type'],
            'admin_name'     => (string) ($r['admin_name'] ?? ''),
            'status'         => (int) $r['status'],
            'fail_reason'    => (string) $r['fail_reason'],
            'create_time'    => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.11.7 注册福利（125~126）
    // ========================================================

    /** GET /marketing/register/reward（125） */
    public function registerConfig()
    {
        self::guard('marketing:register:config');
        return Resp::ok($this->readConfig('marketing.register',
            ['is_enabled' => 0, 'reward_type' => 'balance', 'reward_config' => []]));
    }

    /** PUT /marketing/register/reward（126） */
    public function registerConfigUpdate()
    {
        $admin = self::guard('marketing:register:config');
        $rewardType = self::str('reward_type');
        if ($rewardType === '') {
            throw new AdminException('缺少必填参数: reward_type');
        }
        $this->writeConfig('marketing.register', 'marketing', [
            'is_enabled' => self::int('is_enabled') === 1 ? 1 : 0,
            'reward_type' => $rewardType,
            'reward_config' => $this->arrayParam('reward_config'),
        ]);
        Guard::audit('register_config', (int) $admin['id'], 0, ['reward_type' => $rewardType]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================
    // 8.11.8 盲盒活动数据（127）
    // ========================================================

    /** GET /marketing/blindbox/stats（127） */
    public function blindboxStats()
    {
        self::guard('marketing:blindbox:stats');
        $id = self::int('blindbox_id');
        if ($id > 0 && Db::name('blind_box')->where('id', $id)->find() === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        $q = Db::name('blind_box_open_record');
        if ($id > 0) {
            $q->where('blind_box_id', $id);
        }
        $total = (int) (clone $q)->count();
        $empty = (int) (clone $q)->where(function ($query) {
            $query->whereOr('item_id', 0)->whereOr('collectible_id', 0);
        })->count();

        $distRows = (clone $q)->where('collectible_id', '>', 0)
            ->field('collectible_id, COUNT(*) as cnt')
            ->group('collectible_id')->select()->toArray();
        $names = [];
        if ($distRows !== []) {
            $ids = array_map(static fn ($r) => (int) $r['collectible_id'], $distRows);
            $names = Db::name('collection')->whereIn('id', $ids)->column('name', 'id');
        }
        $distribution = array_map(static function ($r) use ($total, $names) {
            $cnt = (int) $r['cnt'];
            return [
                'collectible_id' => (int) $r['collectible_id'],
                'name'           => (string) ($names[(int) $r['collectible_id']] ?? ''),
                'count'          => $cnt,
                'rate'           => $total > 0 ? round($cnt / $total * 100, 2) : 0.0,
            ];
        }, $distRows);

        return Resp::ok([
            'blindbox_id'       => $id,
            'total_opened'      => $total,
            'empty_rate'        => $total > 0 ? round($empty / $total * 100, 2) : 0.0,
            'item_distribution' => $distribution,
        ]);
    }

    // --------------------------------------------------------

    /** 优先购行 → 输出 */
    private function priorityBrief(array $r): array
    {
        return [
            'id'                    => (int) $r['id'],
            'collectible_id'        => (int) $r['collectible_id'],
            'collectible_name'      => (string) ($r['collectible_name'] ?? ''),
            'start_time'            => self::iso((int) $r['start_time']),
            'end_time'              => self::iso((int) $r['end_time']),
            'max_quantity_per_user' => (int) $r['max_quantity_per_user'],
            'status'                => (int) $r['status'],
            'create_time'           => self::iso((int) $r['createtime']),
        ];
    }

    /** 白名单行 → 输出（手机号脱敏） */
    private function whitelistBrief(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'activity_id'   => (int) $r['activity_id'],
            'user_id'       => (int) $r['user_id'],
            'nickname'      => (string) ($r['nickname'] ?? ''),
            'phone'         => self::maskPhone((string) $r['phone']),
            'max_quantity'  => (int) $r['max_quantity'],
            'used_quantity' => (int) $r['used_quantity'],
            'expires_at'    => self::iso((int) $r['expires_at']),
            'status'        => (int) $r['status'],
            'create_time'   => self::iso((int) $r['createtime']),
        ];
    }

    /** 抽奖行 → 输出（free_chances ↔ lottery.free_number，status ↔ switch） */
    private function luckyBrief(array $r): array
    {
        return [
            'id'           => (int) $r['id'],
            'name'         => (string) $r['name'],
            'start_time'   => self::iso((int) $r['start_time']),
            'end_time'     => self::iso((int) $r['end_time']),
            'free_chances' => (int) $r['free_number'],
            'status'       => (int) $r['switch'],
            'prize_count'  => Db::name('lottery_prize')->where('lottery_id', $r['id'])->count(),
        ];
    }

    /** 奖项行 → 输出 */
    private function prizeBrief(array $r): array
    {
        return [
            'id'               => (int) $r['id'],
            'lottery_id'       => (int) $r['lottery_id'],
            'name'             => (string) $r['name'],
            'prize_type'       => (int) $r['prize_type'],
            'prize_config'     => $this->jsonDecode((string) ($r['prize_config'] ?? '')),
            'probability'      => (string) $r['probability'],
            'planned_quantity' => (int) $r['planned_quantity'],
            'sent_quantity'    => (int) $r['sent_quantity'],
            'is_empty_prize'   => (int) $r['is_empty_prize'],
            'status'           => (int) $r['status'],
        ];
    }

    /** 合成行 → 输出（13 位毫秒时间还原为秒再转 ISO） */
    private function synthesisBrief(array $r): array
    {
        return [
            'id'                    => (int) $r['id'],
            'name'                  => (string) $r['title'],
            'target_collectible_id' => (int) ($r['target_collectible_id'] ?? 0),
            'materials'             => $this->jsonDecode((string) ($r['need_items'] ?? '')),
            'per_user_limit'        => (int) ($r['per_user_limit'] ?? 0),
            'total_limit'           => (int) $r['stock'],
            'start_time'            => self::iso((int) ((int) $r['start_time'] / 1000)),
            'end_time'              => self::iso((int) ((int) $r['end_time'] / 1000)),
            'status'                => (int) $r['status'],
            'create_time'           => self::iso((int) $r['createtime']),
        ];
    }

    /** 白名单落库（批量/单条共用） */
    private function whitelistInsert(int $collectibleId, int $activityId, int $userId, string $phone, int $maxQuantity, int $expiresAt): void
    {
        $now = time();
        Db::name('priority_sale_whitelist')->insert([
            'activity_id' => $activityId,
            'collectible_id' => $collectibleId,
            'user_id' => $userId,
            'phone' => mb_substr($phone, 0, 20),
            'max_quantity' => $maxQuantity,
            'expires_at' => $expiresAt,
            'status' => 1,
            'createtime' => $now,
            'updatetime' => $now,
        ]);
    }

    /**
     * 活动空投发放主体（藏品/盲盒共用）
     * @return array{0:int,1:int,2:int} [taskId, success, fail]
     */
    private function doAirdrop(array $admin, int $targetType, int $targetId, string $targetName,
        int $quantity, array $phones, string $activityType, int $activityId, int $rewardType): array
    {
        $now = time();
        $taskId = (int) Db::name('airdrop_task')->insertGetId([
            'target_type' => $targetType, 'target_id' => $targetId, 'target_name' => $targetName,
            'quantity' => $quantity, 'user_count' => count($phones),
            'source' => 'activity', 'activity_type' => mb_substr($activityType, 0, 50),
            'activity_id' => $activityId, 'reward_type' => $rewardType,
            'admin_id' => (int) $admin['id'], 'admin_name' => (string) $admin['username'],
            'createtime' => $now,
        ]);
        $success = 0;
        $fail = 0;
        foreach ($phones as $phone) {
            $uid = (int) Db::name('user')->where('mobile', $phone)->value('id');
            if ($uid <= 0) {
                $fail++;
                Db::name('airdrop_record')->insert([
                    'task_id' => $taskId, 'user_id' => 0, 'phone' => $phone,
                    'status' => 0, 'fail_reason' => '用户不存在', 'createtime' => $now,
                ]);
                continue;
            }
            if ($targetType === 1) {
                // 藏品：uc 聚合行 num += quantity（口径同藏品模块空投）
                $held = Db::name('user_collection')->where('user_id', $uid)->where('collection_id', $targetId)->find();
                if ($held === null) {
                    Db::name('user_collection')->insert([
                        'user_id' => $uid, 'collection_id' => $targetId, 'num' => $quantity,
                        'sell_num' => 0, 'lock_num' => 0, 'is_collection' => 0, 'can_compound' => 1,
                        'utime' => $now, 'createtime' => $now,
                    ]);
                } else {
                    Db::name('user_collection')->where('id', $held['id'])->inc('num', $quantity)->update(['utime' => $now]);
                }
            } else {
                // 盲盒：user.blind_box_num 聚合数（无逐盒载体）
                Db::name('user')->where('id', $uid)->inc('blind_box_num', $quantity)->update(['updatetime' => $now]);
            }
            Db::name('airdrop_record')->insert([
                'task_id' => $taskId, 'user_id' => $uid, 'phone' => $phone,
                'status' => 1, 'createtime' => $now,
            ]);
            $success++;
        }
        return [$taskId, $success, $fail];
    }

    /** site_config 读（JSON 解析，缺省兜底） */
    private function readConfig(string $key, array $defaults): array
    {
        $raw = Db::name('site_config')->where('key', $key)->value('value');
        $cfg = json_decode((string) $raw, true);
        if (!is_array($cfg)) {
            $cfg = [];
        }
        return $cfg + $defaults;
    }

    /** site_config 写（KV upsert） */
    private function writeConfig(string $key, string $group, array $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $now = time();
        $exists = Db::name('site_config')->where('key', $key)->find();
        if ($exists === null) {
            Db::name('site_config')->insert([
                'key' => $key, 'value' => $json, 'group_name' => $group,
                'remark' => '', 'updatetime' => $now,
            ]);
        } else {
            Db::name('site_config')->where('key', $key)->update(['value' => $json, 'updatetime' => $now]);
        }
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

    /** 双时间参数 */
    private function timeRange(string $startKey, string $endKey): array
    {
        return [$this->timeParam($startKey), $this->timeParam($endKey)];
    }

    /** 对象/数组参数（route→post→get），非数组返回 [] */
    private function arrayParam(string $key): array
    {
        $v = request()->post($key);
        if ($v === null) {
            $v = request()->get($key);
        }
        if (is_string($v)) {
            $v = json_decode($v, true);
        }
        return is_array($v) ? $v : [];
    }

    /** JSON 字符串 → 数组（空/非法 → []） */
    private function jsonDecode(string $raw): array
    {
        $v = json_decode($raw, true);
        return is_array($v) ? $v : [];
    }
}
