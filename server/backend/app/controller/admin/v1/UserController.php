<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * UserController —— 用户管理（契约 §8.3，15 个接口）
 *
 * 红线：
 *   1. 手机号全链路脱敏（138****8888），明文绝不进响应体
 *   2. 冻结/解冻/拉黑必须带 reason 且落审计
 *   3. 强制回收：校验二次流转（寄售中/锁定/挂单中 → 拦截），成功后库存池 +1
 *   4. 钱包合计以账本 SUM 为准（money 列仅展示）
 *
 * @package app\controller\admin\v1
 */
final class UserController
{
    use V1;

    /** GET /users */
    public function list()
    {
        self::guard('user:list');
        [$page, $size] = self::paging();

        $q = Db::name('user');
        $phone = self::str('phone');
        if ($phone !== '') {
            $q->whereLike('mobile', '%' . $phone . '%');
        }
        $nickname = self::str('nickname');
        if ($nickname !== '') {
            $q->whereLike('nickname', '%' . $nickname . '%');
        }
        $uid = self::int('uid');
        if ($uid > 0) {
            $q->where('id', $uid);
        }
        $status = self::str('status');
        if ($status !== '') {
            $q->where('status', $status);
        }
        $realname = self::str('is_realname');
        if ($realname !== '') {
            $q->where('real_name_authentication', (int) $realname);
        }
        $black = self::str('is_blacklisted');
        if ($black !== '') {
            $q->where('is_blacklisted', (int) $black);
        }
        [$s, $e] = self::dateRange('reg_date_start', 'reg_date_end');
        if ($s > 0) {
            $q->where('createtime', '>=', $s);
        }
        if ($e > 0) {
            $q->where('createtime', '<', $e);
        }

        $total = (clone $q)->count();
        $rows = $q->order('id', 'desc')->page($page, $size)
            ->field('id,username,nickname,mobile,avatar,level,money,agent_money,score,status,is_blacklisted,real_name_authentication,inviter_code,invited_count,createtime,jointime,logintime')
            ->select()->toArray();

        $list = array_map(fn ($r) => [
            'id'            => (int) $r['id'],
            'username'      => $r['username'],
            'nickname'      => $r['nickname'],
            'mobile_masked' => self::maskPhone((string) $r['mobile']),
            'avatar'        => $r['avatar'],
            'level'         => (int) $r['level'],
            'money'         => $this->money($r['money']),
            'agent_money'   => $this->money($r['agent_money']),
            'status'        => (string) $r['status'],
            'is_blacklisted' => (int) $r['is_blacklisted'],
            'is_realname'   => (int) $r['real_name_authentication'],
            'inviter_code'  => $r['inviter_code'],
            'invited_count' => (int) $r['invited_count'],
            'reg_time'      => self::iso((int) $r['createtime']),
            'last_login'    => self::iso((int) $r['logintime']),
        ], $rows);

        return Resp::page($list, $total, $page, $size);
    }

    /** GET /users/:id */
    public function detail()
    {
        self::guard('user:detail');
        $id = self::int('id');
        $u = Db::name('user')->where('id', $id)->find();
        if ($u === null) {
            throw new AdminException('用户不存在', Resp::NOT_FOUND);
        }
        return Resp::ok([
            'id'             => (int) $u['id'],
            'username'       => $u['username'],
            'nickname'       => $u['nickname'],
            'mobile_masked'  => self::maskPhone((string) $u['mobile']),
            'email'          => $u['email'],
            'avatar'         => $u['avatar'],
            'level'          => (int) $u['level'],
            'gender'         => (int) $u['gender'],
            'bio'            => (string) ($u['bio'] ?? ''),
            'money'          => $this->money($u['money']),
            'agent_money'    => $this->money($u['agent_money']),
            'score'          => (int) $u['score'],
            'status'         => (string) $u['status'],
            'is_blacklisted' => (int) $u['is_blacklisted'],
            'blacklist_reason' => (string) ($u['blacklist_reason'] ?? ''),
            'is_realname'    => (int) $u['real_name_authentication'],
            'inviter_code'   => $u['inviter_code'],
            'inviter_user_id' => (int) $u['inviter_user_id'],
            'invited_count'  => (int) $u['invited_count'],
            'collection_num' => (int) $u['collection_num'],
            'blind_box_num'  => (int) $u['blind_box_num'],
            'reg_time'       => self::iso((int) $u['createtime']),
            'last_login'     => self::iso((int) $u['logintime']),
            'counts'         => [
                'orders'    => Db::name('artel_order')->where('user_id', $id)->count(),
                'holdings'  => Db::name('user_collection')->where('user_id', $id)->sum('num'),
                'listings'  => Db::name('market_goods')->where('user_id', $id)->where('status', 1)->count(),
            ],
        ]);
    }

    /** PUT /users/:id/freeze —— 冻结立即生效（UserToken::verify 对非 normal 401） */
    public function freeze()
    {
        return $this->setStatus('freeze', 'user_freeze');
    }

    /** PUT /users/:id/unfreeze */
    public function unfreeze()
    {
        return $this->setStatus('normal', 'user_unfreeze');
    }

    private function setStatus(string $status, string $action)
    {
        $admin = self::guard('user:freeze');
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        $u = Db::name('user')->where('id', $id)->find();
        if ($u === null) {
            throw new AdminException('用户不存在', Resp::NOT_FOUND);
        }
        if ($u['status'] === $status) {
            throw new AdminException($status === 'freeze' ? '该用户已是冻结状态' : '该用户未被冻结', Resp::CONFLICT);
        }
        Db::name('user')->where('id', $id)->update(['status' => $status, 'updatetime' => time()]);
        if ($status === 'freeze') {
            // 立即踢下线：吊销全部 C 端会话
            Db::name('user_token')->where('user_id', $id)->delete();
        }
        \app\service\admin\Guard::audit($action, (int) $admin['id'], $id, ['reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** PUT /users/:id/reset-tx-password —— 清空支付密码，用户下次自行重设 */
    public function resetTxPassword()
    {
        $admin = self::guard('user:manage');
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        if (Db::name('user')->where('id', $id)->find() === null) {
            throw new AdminException('用户不存在', Resp::NOT_FOUND);
        }
        Db::name('user')->where('id', $id)->update(['pay_password' => '', 'updatetime' => time()]);
        \app\service\admin\Guard::audit('user_reset_tx_password', (int) $admin['id'], $id, ['reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** PUT /users/:id/force-logout —— 吊销全部 C 端会话 */
    public function forceLogout()
    {
        $admin = self::guard('user:manage');
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        Db::name('user_token')->where('user_id', $id)->delete();
        \app\service\admin\Guard::audit('user_force_logout', (int) $admin['id'], $id, ['reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** POST /users/:id/blacklist */
    public function addBlacklist()
    {
        $admin = self::guard('user:blacklist');
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        $u = Db::name('user')->where('id', $id)->find();
        if ($u === null) {
            throw new AdminException('用户不存在', Resp::NOT_FOUND);
        }
        $expiresAt = (int) strtotime(self::str('expires_at'));

        Db::startTrans();
        try {
            Db::name('user')->where('id', $id)->update([
                'is_blacklisted' => 1, 'blacklist_reason' => $reason,
                'blacklist_at' => time(), 'blacklist_by' => (int) $admin['id'], 'updatetime' => time(),
            ]);
            Db::name('blacklist')->insert([
                'user_id' => $id, 'blacklist_type' => 1, 'target_value' => (string) $id,
                'reason' => $reason, 'admin_id' => (int) $admin['id'],
                'admin_name' => (string) $admin['username'],
                'expires_at' => $expiresAt, 'createtime' => time(), 'updatetime' => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('加入黑名单失败');
        }
        // 拉黑立即踢下线
        Db::name('user_token')->where('user_id', $id)->delete();
        \app\service\admin\Guard::audit('user_blacklist_add', (int) $admin['id'], $id, ['reason' => $reason, 'expires_at' => $expiresAt]);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /users/:id/blacklist */
    public function removeBlacklist()
    {
        $admin = self::guard('user:blacklist');
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        Db::name('user')->where('id', $id)->update([
            'is_blacklisted' => 0, 'updatetime' => time(),
        ]);
        Db::name('blacklist')->where('user_id', $id)->where('status', 1)->update([
            'status' => 0, 'lifted_at' => time(), 'lifted_by' => (int) $admin['id'], 'updatetime' => time(),
        ]);
        \app\service\admin\Guard::audit('user_blacklist_remove', (int) $admin['id'], $id, ['reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** GET /users/:id/wallet —— 钱包资产（合计以账本 SUM 为准） */
    public function wallet()
    {
        self::guard('user:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');
        $u = Db::name('user')->where('id', $id)->find();
        if ($u === null) {
            throw new AdminException('用户不存在', Resp::NOT_FOUND);
        }

        $q = Db::name('wallet_transaction')->where('user_id', $id);
        $total = (clone $q)->count();
        $rows = $q->order('id', 'desc')->page($page, $size)->select()->toArray();

        $totalIn = (float) Db::name('wallet_transaction')->where('user_id', $id)->where('direction', 1)->sum('amount');
        $totalOut = (float) Db::name('wallet_transaction')->where('user_id', $id)->where('direction', 2)->sum('amount');

        return Resp::ok([
            'balance'           => $this->money($u['money']),
            'ledger_in'         => $this->money($totalIn),
            'ledger_out'        => $this->money($totalOut),
            'ledger_balance'    => $this->money($totalIn - $totalOut),
            'total_recharge'    => $this->money((string) Db::name('recharge_order')->where('user_id', $id)->where('status', 2)->sum('amount')),
            'transactions'      => [
                'list'  => array_map(fn ($r) => [
                    'id' => (int) $r['id'], 'direction' => (int) $r['direction'],
                    'amount' => $this->money($r['amount']),
                    'balance_before' => $this->money($r['balance_before'] ?? 0),
                    'balance_after' => $this->money($r['balance_after'] ?? 0),
                    'biz_type' => (string) $r['biz_type'], 'remark' => (string) ($r['remark'] ?? ''),
                    'time' => self::iso((int) $r['createtime']),
                ], $rows),
                'total' => $total, 'page' => $page, 'page_size' => $size,
            ],
        ]);
    }

    /** GET /users/:id/collectibles —— 用户仓库藏品 */
    public function collectibles()
    {
        self::guard('user:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');

        $q = Db::name('user_collection')->alias('uc')->join('collection c', 'c.id = uc.collection_id')->where('uc.user_id', $id);
        $total = (clone $q)->count();
        $rows = $q->order('uc.id', 'desc')->page($page, $size)
            ->field('uc.id,c.id as cid,c.name,c.main_image,uc.num,uc.sell_num,uc.lock_num,uc.createtime')
            ->select()->toArray();

        return Resp::page(array_map(fn ($r) => [
            'uc_id'      => (int) $r['id'],
            'collectible' => ['id' => (int) $r['cid'], 'name' => $r['name'], 'image' => $r['main_image']],
            'num'        => (int) $r['num'],
            'on_sale'    => (int) $r['sell_num'],
            'locked'     => (int) $r['lock_num'],
            'obtained_at' => self::iso((int) $r['createtime']),
            'recoverable' => (int) $r['sell_num'] === 0 && (int) $r['lock_num'] === 0,
        ], $rows), $total, $page, $size);
    }

    /** GET /users/:id/blindboxes —— 盲盒持有（无独立载体，真实空态） */
    public function blindboxes()
    {
        self::guard('user:detail');
        [$page, $size] = self::paging();
        // 本平台盲盒开启即发放子藏品（user_collection），无「未开启盲盒持有」表。
        // 该清单恒为真实空态 —— 禁止伪造数据。
        return Resp::page([], 0, $page, $size);
    }

    /** GET /users/:id/priority-qualifications —— 优先购资格台账 */
    public function priorityQualifications()
    {
        self::guard('user:detail');
        $id = self::int('id');
        $rows = Db::name('priority_sale_whitelist')->alias('w')
            ->join('priority_sale_activity a', 'a.id = w.activity_id')
            ->join('collection c', 'c.id = w.collectible_id')
            ->where('w.user_id', $id)
            ->field('w.*,c.name as collectible_name,a.start_time,a.end_time')
            ->select()->toArray();

        $now = time();
        $valid = $expired = $exhausted = [];
        foreach ($rows as $r) {
            $item = [
                'id' => (int) $r['id'], 'collectible' => $r['collectible_name'],
                'max_quantity' => (int) $r['max_quantity'], 'used_quantity' => (int) $r['used_quantity'],
                'expires_at' => self::iso((int) $r['expires_at']),
            ];
            if ((int) $r['status'] !== 1) {
                $expired[] = $item;
            } elseif ((int) $r['expires_at'] > 0 && (int) $r['expires_at'] < $now) {
                $expired[] = $item;
            } elseif ((int) $r['max_quantity'] > 0 && (int) $r['used_quantity'] >= (int) $r['max_quantity']) {
                $exhausted[] = $item;
            } else {
                $valid[] = $item;
            }
        }
        return Resp::ok(['valid' => $valid, 'expired' => $expired, 'exhausted' => $exhausted]);
    }

    /** GET /users/:id/invites —— 邀请关系链 */
    public function invites()
    {
        self::guard('user:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');

        $q = Db::name('invite_record')->alias('ir')->join('user u', 'u.id = ir.invitee_id')->where('ir.inviter_id', $id);
        $total = (clone $q)->count();
        $rows = $q->order('ir.id', 'desc')->page($page, $size)
            ->field('ir.*,u.nickname,u.mobile')
            ->select()->toArray();

        $u = Db::name('user')->where('id', $id)->find();
        return Resp::ok([
            'list' => array_map(fn ($r) => [
                'id' => (int) $r['id'], 'invitee' => [
                    'id' => (int) $r['invitee_id'], 'nickname' => $r['nickname'],
                    'mobile_masked' => self::maskPhone((string) $r['mobile']),
                ],
                'reward_type' => (string) ($r['reward_type'] ?? ''), 'status' => (int) $r['status'],
                'time' => self::iso((int) $r['createtime']),
            ], $rows),
            'total' => $total, 'page' => $page, 'page_size' => $size,
            'stats' => [
                'invited_count' => (int) ($u['invited_count'] ?? 0),
                'inviter_code'  => (string) ($u['inviter_code'] ?? ''),
                'inviter_user_id' => (int) ($u['inviter_user_id'] ?? 0),
            ],
        ]);
    }

    /** POST /users/:id/recover-collectible —— 强制回收藏品（§3.1.8） */
    public function recoverCollectible()
    {
        $admin = self::guard('user:recover');
        $id = self::int('id');
        $ucId = self::int('user_collectible_id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        self::verifyPassword($admin);

        $uc = Db::name('user_collection')->where('id', $ucId)->where('user_id', $id)->find();
        if ($uc === null) {
            throw new AdminException('该用户无此藏品持有记录', Resp::NOT_FOUND);
        }

        // 二次流转校验：寄售占用/锁定/在售挂单 → 拦截
        if ((int) $uc['sell_num'] > 0 || (int) $uc['lock_num'] > 0) {
            throw new AdminException('该藏品已发生二次流转（寄售中/锁定），无法回收', Resp::CONFLICT);
        }
        $listing = Db::name('market_goods')->where('user_id', $id)
            ->where('collection_id', (int) $uc['collection_id'])
            ->where('status', 1)->count();
        if ($listing > 0) {
            throw new AdminException('该藏品已发生二次流转（市场挂单中），无法回收', Resp::CONFLICT);
        }
        $pendingTransfer = Db::name('transfer_record')->where('uc_id', $ucId)->where('status', 0)->count();
        if ($pendingTransfer > 0) {
            throw new AdminException('该藏品存在待确认转赠，无法回收', Resp::CONFLICT);
        }

        Db::startTrans();
        try {
            Db::name('user_collection')->where('id', $ucId)->delete();
            Db::name('user_collection_record')->insert([
                'user_id' => $id, 'collection_id' => (int) $uc['collection_id'],
                'change_num' => -(int) $uc['num'], 'type' => 9,
                'ref_id' => (int) $admin['id'], 'remark' => '管理端强制回收：' . $reason, 'createtime' => time(),
            ]);
            // 归还库存池：sold_count -1 → pool +1，流通量 -1
            Db::name('collection')->where('id', (int) $uc['collection_id'])
                ->dec('sold_count', (int) $uc['num'])->update(['updatetime' => time()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('回收失败，请稍后重试');
        }

        \app\service\admin\Guard::audit('user_recover_collectible', (int) $admin['id'], $id, [
            'user_collection_id' => $ucId, 'collection_id' => (int) $uc['collection_id'],
            'num' => (int) $uc['num'], 'reason' => $reason,
        ]);
        return Resp::ok(['success' => true]);
    }

    /** POST /users/:id/recover-blindbox —— 强制回收盲盒（无持有载体，如实拒绝） */
    public function recoverBlindbox()
    {
        self::guard('user:recover');
        self::int('id');
        self::int('user_blindbox_id');
        if (self::str('reason') === '') {
            throw new AdminException('请填写原因');
        }
        // 本平台盲盒开启即发放子藏品，不存在「未开启盲盒持有」载体；
        // 无可回收对象即如实拒绝，绝不伪造成功。
        throw new AdminException('盲盒开启后即发放子藏品，无未开启持有记录可回收', Resp::CONFLICT);
    }
}
