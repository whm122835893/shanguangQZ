<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * BlindboxController —— 盲盒管理（契约 §8.6，17 个接口）
 *
 * 盲盒库存池恒等式（audit 端点以此校验，改动任何库存动作必须维持）：
 *   盲盒发行总量(total_num) = 盲盒库存池(stock_pool，推导值) + 已售出发售(sale_number)
 *                           + 已独立空投(airdropped_count) + 已销毁(destroyed_count)
 *
 * 状态机 status：0=草稿 1=发售中 2=已售罄 3=已下架
 *   - 编辑：仅草稿/已下架；删除：仅草稿且无子藏品/开启记录关联
 *   - 发售：草稿/已下架 → 发售中；重新上架：已售罄/已下架 → 发售中（扩容 ≤ 池）
 *   - 强制售罄不清零：剩余自动回池（池为推导值，只改状态）
 *
 * 持有载体说明：本平台未开启盲盒持有 = user.blind_box_num（聚合数，不分盒型）；
 * 逐盒明细不存在（开启即发放子藏品）。故：
 *   - 空投：user.blind_box_num += quantity，airdrop_record(target_type=2) 留逐笔来源
 *   - 强制回收：仅允许回收「空投发放」来源（user_blindbox_id = airdrop_record.id），
 *     回收后 airdropped_count 回吐 → 库存池 +1
 *   - 审计 user_holding 恒 0（无逐盒载体，如实报告）
 *
 * 概率闭环（§3.6.2）：子藏品 2~N 条、概率总和 ≤ 100%（差额=空奖）；
 * 库存不预冻结，开启时实时校验。
 *
 * @package app\controller\admin\v1
 */
final class BlindboxController
{
    use V1;

    public const STATUS_DRAFT    = 0;
    public const STATUS_ONSALE   = 1;
    public const STATUS_SOLDOUT  = 2;
    public const STATUS_FORCEOFF = 3;

    /** GET /blindboxes */
    public function list()
    {
        self::guard('blindbox:list');
        [$page, $size] = self::paging();

        $q = Db::name('blind_box');
        if ($name = self::str('name')) {
            $q->whereLike('name', '%' . $name . '%');
        }
        if (self::str('status') !== '') {
            $q->where('status', self::int('status'));
        }
        $q->order('id', 'desc');

        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->brief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /blindboxes —— 创建（草稿态） */
    public function create()
    {
        $admin = self::guard('blindbox:create');
        self::requireFields(['name', 'edition']);
        $name = self::str('name');
        $edition = self::int('edition');
        if ($edition <= 0) {
            throw new AdminException('发行总量必须大于 0');
        }
        $now = time();
        $image = self::str('image');
        $id = (int) Db::name('blind_box')->insertGetId([
            'name' => mb_substr($name, 0, 128),
            'main_image' => $image,
            'listimg' => $image,
            'image' => $image,
            'price' => self::str('price') !== '' && is_numeric(self::str('price'))
                ? number_format((float) self::str('price'), 2, '.', '') : '0.00',
            'description' => mb_substr(self::str('description'), 0, 1000),
            'total_num' => $edition,
            'total_num_show' => $edition,
            'type' => 'collection',
            'goods_type' => '2',            // ⚠️ 实测形态：字符串型数字
            'batch_id' => '',
            'status' => self::STATUS_DRAFT,
            'open_draw' => 1,
            'createtime' => $now,
        ]);
        Guard::audit('blindbox_create', (int) $admin['id'], $id, ['name' => $name, 'edition' => $edition]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /blindboxes/:id */
    public function detail()
    {
        self::guard('blindbox:detail');
        $r = Db::name('blind_box')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        $items = Db::name('blind_box_item')->alias('bi')
            ->leftJoin('collection c', 'c.id = bi.collectible_id')
            ->where('bi.blind_box_id', $r['id'])->where('bi.status', 1)
            ->field('bi.id,bi.collectible_id,c.name as collectible_name,bi.probability,bi.planned_quantity,bi.status')
            ->order('bi.id')->select()->toArray();
        $probSum = 0.0;
        $itemList = array_map(function ($it) use (&$probSum) {
            $probSum += (float) $it['probability'];
            return [
                'id' => (int) $it['id'],
                'collectible_id' => (int) $it['collectible_id'],
                'collectible_name' => (string) ($it['collectible_name'] ?? ''),
                'probability' => (string) $it['probability'],
                'planned_quantity' => (int) $it['planned_quantity'],
            ];
        }, $items);

        return Resp::ok($this->brief($r) + [
            'description' => (string) ($r['description'] ?? ''),
            'image' => (string) $r['image'],
            'onsale_at' => self::iso((int) $r['release_time']),
            'off_sale_at' => self::iso((int) $r['end_time']),
            'per_user_limit' => (int) $r['per_user_limit'],
            'release_quantity' => (int) $r['release_quantity'],
            'open_draw' => (int) $r['open_draw'],
            'items' => $itemList,
            'probability_sum' => round($probSum, 4),
            'empty_prize_rate' => round(max(0, 100 - $probSum), 4),
        ]);
    }

    /** PUT /blindboxes/:id —— 编辑（仅草稿/已下架） */
    public function edit()
    {
        $admin = self::guard('blindbox:edit');
        $id = self::int('id');
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        if (!in_array((int) $r['status'], [self::STATUS_DRAFT, self::STATUS_FORCEOFF], true)) {
            throw new AdminException('仅草稿/已下架状态可编辑', Resp::CONFLICT);
        }
        $upd = [];
        if ($v = self::str('name')) {
            $upd['name'] = mb_substr($v, 0, 128);
        }
        if ($v = self::str('image')) {
            $upd['main_image'] = $v;
            $upd['listimg'] = $v;
            $upd['image'] = $v;
        }
        if ($v = self::str('description')) {
            $upd['description'] = mb_substr($v, 0, 1000);
        }
        if (self::str('open_draw') !== '') {
            $upd['open_draw'] = self::int('open_draw') ? 1 : 0;
        }
        if ($upd === []) {
            throw new AdminException('没有需要更新的字段');
        }
        $upd['updatetime'] = time();
        Db::name('blind_box')->where('id', $id)->update($upd);
        Guard::audit('blindbox_edit', (int) $admin['id'], $id, $upd);
        return Resp::ok(['success' => true]);
    }

    /** POST /blindboxes/:id/items —— 配置子藏品（2~N，概率和 ≤ 100） */
    public function items()
    {
        $admin = self::guard('blindbox:config');
        $id = self::int('id');
        $items = request()->post('items');
        if (!is_array($items) || $items === []) {
            throw new AdminException('items 不能为空');
        }
        if (count($items) < 2) {
            throw new AdminException('盲盒至少关联 2 个子藏品', Resp::CONFLICT);
        }
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        $sum = 0.0;
        $rows = [];
        foreach ($items as $it) {
            $cid = (int) ($it['collectible_id'] ?? 0);
            $prob = (float) ($it['probability'] ?? 0);
            $planned = (int) ($it['planned_quantity'] ?? 0);
            if ($cid <= 0 || Db::name('collection')->where('id', $cid)->find() === null) {
                throw new AdminException("子藏品 {$cid} 不存在");
            }
            if ($prob <= 0 || $prob > 100) {
                throw new AdminException('中奖概率必须大于 0 且不超过 100');
            }
            if ($planned <= 0) {
                throw new AdminException('计划数量必须大于 0');
            }
            $sum += $prob;
            $rows[] = ['collectible_id' => $cid, 'probability' => $prob, 'planned_quantity' => $planned];
        }
        if ($sum > 100) {
            throw new AdminException('概率总和不得超过 100%（当前 ' . round($sum, 4) . '%）', Resp::CONFLICT);
        }
        $now = time();
        Db::startTrans();
        try {
            foreach ($rows as $row) {
                Db::name('blind_box_item')->insert($row + [
                    'blind_box_id' => $id, 'status' => 1,
                    'createtime' => $now, 'updatetime' => $now,
                ]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('子藏品配置失败：' . $e->getMessage());
        }
        Guard::audit('blindbox_item_config', (int) $admin['id'], $id,
            ['count' => count($rows), 'probability_sum' => round($sum, 4)]);
        return Resp::ok(['success' => true]);
    }

    /** PUT /blindboxes/:id/items/:item_id —— 修改子藏品 */
    public function itemUpdate()
    {
        $admin = self::guard('blindbox:config');
        $id = self::int('id');
        $itemId = self::int('item_id');
        $item = Db::name('blind_box_item')->where('id', $itemId)->where('blind_box_id', $id)->find();
        if ($item === null) {
            throw new AdminException('子藏品配置不存在', Resp::NOT_FOUND);
        }
        $upd = ['updatetime' => time()];
        if (self::str('probability') !== '') {
            $prob = (float) self::str('probability');
            if ($prob <= 0 || $prob > 100) {
                throw new AdminException('中奖概率必须大于 0 且不超过 100');
            }
            $upd['probability'] = number_format($prob, 4, '.', '');
        }
        if (self::str('planned_quantity') !== '') {
            $planned = self::int('planned_quantity');
            if ($planned <= 0) {
                throw new AdminException('计划数量必须大于 0');
            }
            $upd['planned_quantity'] = $planned;
        }
        if ($upd === [] || count($upd) === 1) {
            throw new AdminException('没有需要更新的字段');
        }
        if (isset($upd['probability'])) {
            // 改概率后全盒总和仍须 ≤ 100
            $sum = (float) Db::name('blind_box_item')
                ->where('blind_box_id', $id)->where('status', 1)
                ->where('id', '<>', $itemId)->sum('probability');
            if ($sum + (float) $upd['probability'] > 100) {
                throw new AdminException('概率总和不得超过 100%（其余子藏品已占 '
                    . round($sum, 4) . '%）', Resp::CONFLICT);
            }
        }
        Db::name('blind_box_item')->where('id', $itemId)->update($upd);
        Guard::audit('blindbox_item_update', (int) $admin['id'], $itemId, $upd);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /blindboxes/:id/items/:item_id —— 删除子藏品 */
    public function itemDelete()
    {
        $admin = self::guard('blindbox:config');
        $id = self::int('id');
        $itemId = self::int('item_id');
        $item = Db::name('blind_box_item')->where('id', $itemId)->where('blind_box_id', $id)->find();
        if ($item === null) {
            throw new AdminException('子藏品配置不存在', Resp::NOT_FOUND);
        }
        Db::name('blind_box_item')->where('id', $itemId)->delete();
        Guard::audit('blindbox_item_delete', (int) $admin['id'], $itemId,
            ['collectible_id' => (int) $item['collectible_id']]);
        return Resp::ok(['success' => true]);
    }

    /** POST /blindboxes/:id/release —— 发售配置（草稿/已下架 → 发售中） */
    public function release()
    {
        $admin = self::guard('blindbox:release');
        $id = self::int('id');
        $price = self::str('price');
        $releaseQuantity = self::int('release_quantity');
        if ($price === '' || !is_numeric($price) || (float) $price <= 0) {
            throw new AdminException('请填写正确的发售价格');
        }
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        if (!in_array((int) $r['status'], [self::STATUS_DRAFT, self::STATUS_FORCEOFF], true)) {
            throw new AdminException('仅草稿/已下架状态可配置发售', Resp::CONFLICT);
        }
        $pool = $this->stockPool($r);
        if ($releaseQuantity > $pool) {
            throw new AdminException("发售数量({$releaseQuantity})不得超过盲盒库存池({$pool})", Resp::CONFLICT);
        }
        Db::name('blind_box')->where('id', $id)->update([
            'price' => number_format((float) $price, 2, '.', ''),
            'release_time' => self::str('onsale_at') !== '' ? (int) strtotime(self::str('onsale_at')) : time(),
            'end_time' => self::str('off_sale_at') !== '' ? (int) strtotime(self::str('off_sale_at')) : 0,
            'per_user_limit' => self::int('per_user_limit'),
            'release_quantity' => $releaseQuantity,
            'status' => self::STATUS_ONSALE,
            'soldout' => 0,
            'updatetime' => time(),
        ]);
        Guard::audit('blindbox_release', (int) $admin['id'], $id,
            ['price' => $price, 'release_quantity' => $releaseQuantity]);
        return Resp::ok(['success' => true]);
    }

    /** POST /blindboxes/:id/relist —— 重新上架（扩发，高风险） */
    public function relist()
    {
        $admin = self::guard('blindbox:relist');
        self::verifyPassword($admin);
        $id = self::int('id');
        $quantity = self::int('release_quantity');
        if ($quantity <= 0) {
            throw new AdminException('追加数量必须大于 0');
        }
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['status'] === self::STATUS_DRAFT) {
            throw new AdminException('草稿状态请走发售配置', Resp::CONFLICT);
        }
        if ((int) $r['status'] === self::STATUS_ONSALE) {
            throw new AdminException('盲盒已在发售中', Resp::CONFLICT);
        }
        $pool = $this->stockPool($r);
        if ($quantity > $pool) {
            throw new AdminException("追加数量({$quantity})不得超过盲盒库存池({$pool})", Resp::CONFLICT);
        }
        Db::name('blind_box')->where('id', $id)->update([
            'total_num' => (int) $r['total_num'] + $quantity,
            'total_num_show' => (int) $r['total_num'] + $quantity,
            'release_quantity' => $quantity,
            'price' => self::str('price') !== '' && is_numeric(self::str('price'))
                ? number_format((float) self::str('price'), 2, '.', '') : $r['price'],
            'release_time' => self::str('onsale_at') !== '' ? (int) strtotime(self::str('onsale_at')) : time(),
            'end_time' => self::str('off_sale_at') !== '' ? (int) strtotime(self::str('off_sale_at')) : 0,
            'status' => self::STATUS_ONSALE,
            'soldout' => 0,
            'updatetime' => time(),
        ]);
        Guard::audit('blindbox_relist', (int) $admin['id'], $id,
            ['add_quantity' => $quantity, 'reason' => self::str('reason')]);
        return Resp::ok(['success' => true]);
    }

    /** POST /blindboxes/:id/force-soldout —— 强制售罄（剩余回池） */
    public function forceSoldout()
    {
        $admin = self::guard('blindbox:manage');
        self::verifyPassword($admin);
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['status'] !== self::STATUS_ONSALE) {
            throw new AdminException('仅发售中的盲盒可强制售罄', Resp::CONFLICT);
        }
        // 不清零：发售数量、已售出发售均不动，剩余本就在盲盒库存池（推导值自动回池）
        Db::name('blind_box')->where('id', $id)->update([
            'status' => self::STATUS_SOLDOUT, 'soldout' => 1, 'updatetime' => time(),
        ]);
        Guard::audit('blindbox_force_soldout', (int) $admin['id'], $id, ['reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** POST /blindboxes/:id/destroy —— 销毁盲盒库存 */
    public function destroy()
    {
        $admin = self::guard('blindbox:destroy');
        self::verifyPassword($admin);
        $id = self::int('id');
        $quantity = self::int('quantity');
        $reason = self::str('reason');
        if ($quantity <= 0) {
            throw new AdminException('销毁数量必须大于 0');
        }
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        $pool = $this->stockPool($r);
        if ($quantity > $pool) {
            throw new AdminException("销毁数量({$quantity})不得超过盲盒库存池({$pool})", Resp::CONFLICT);
        }
        Db::startTrans();
        try {
            Db::name('blind_box')->where('id', $id)->inc('destroyed_count', $quantity)->update(['updatetime' => time()]);
            Db::name('destroy_record')->insert([
                'target_type' => 2, 'target_id' => $id, 'target_name' => (string) $r['name'],
                'quantity' => $quantity, 'reason' => mb_substr($reason, 0, 250),
                'admin_id' => (int) $admin['id'], 'admin_name' => (string) $admin['username'],
                'ip_address' => request()->ip(), 'createtime' => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('销毁失败：' . $e->getMessage());
        }
        Guard::audit('blindbox_destroy', (int) $admin['id'], $id, ['quantity' => $quantity, 'reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /blindboxes/:id —— 删除（仅草稿且无关联） */
    public function delete()
    {
        $admin = self::guard('blindbox:delete');
        self::verifyPassword($admin);
        $id = self::int('id');
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['status'] !== self::STATUS_DRAFT) {
            throw new AdminException('仅草稿状态可删除', Resp::CONFLICT);
        }
        $items = Db::name('blind_box_item')->where('blind_box_id', $id)->count();
        $opens = Db::name('blind_box_open_record')->where('blind_box_id', $id)->count();
        if ($items > 0 || $opens > 0) {
            throw new AdminException('盲盒已配置子藏品/存在开启记录，禁止删除', Resp::CONFLICT);
        }
        Db::name('blind_box')->where('id', $id)->delete();
        Guard::audit('blindbox_delete', (int) $admin['id'], $id, ['name' => $r['name']]);
        return Resp::ok(['success' => true]);
    }

    /** POST /blindboxes/:id/airdrop —— 独立空投盲盒（≤ 盲盒库存池） */
    public function airdrop()
    {
        $admin = self::guard('blindbox:airdrop');
        self::verifyPassword($admin);
        $id = self::int('id');
        $quantity = self::int('quantity');
        $phones = request()->post('phones');
        if ($quantity <= 0) {
            throw new AdminException('空投数量必须大于 0');
        }
        if (!is_array($phones) || $phones === []) {
            throw new AdminException('phones 不能为空');
        }
        $phones = array_values(array_unique(array_map('strval', $phones)));
        if (count($phones) > 1000) {
            throw new AdminException('单次空投人数不得超过 1000', Resp::CONFLICT);
        }
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        $pool = $this->stockPool($r);
        if ($quantity * count($phones) > $pool) {
            throw new AdminException('空投总量超过盲盒库存池', Resp::CONFLICT);
        }

        $now = time();
        $taskId = (int) Db::name('airdrop_task')->insertGetId([
            'target_type' => 2, 'target_id' => $id, 'target_name' => (string) $r['name'],
            'quantity' => $quantity, 'user_count' => count($phones),
            'source' => 'blindbox', 'admin_id' => (int) $admin['id'],
            'admin_name' => (string) $admin['username'], 'createtime' => $now,
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
            // 未开启盲盒持有载体：user.blind_box_num（聚合数）
            Db::name('user')->where('id', $uid)->inc('blind_box_num', $quantity)->update(['updatetime' => $now]);
            Db::name('airdrop_record')->insert([
                'task_id' => $taskId, 'user_id' => $uid, 'phone' => $phone,
                'status' => 1, 'createtime' => $now,
            ]);
            $success++;
        }
        Db::name('blind_box')->where('id', $id)->inc('airdropped_count', $success * $quantity)
            ->update(['updatetime' => time()]);
        Db::name('airdrop_task')->where('id', $taskId)->update([
            'success_count' => $success, 'fail_count' => $fail,
        ]);
        Guard::audit('blindbox_airdrop', (int) $admin['id'], $id,
            ['task_id' => $taskId, 'quantity' => $quantity, 'phones' => count($phones),
             'success' => $success, 'fail' => $fail]);
        return Resp::ok(['task_id' => $taskId, 'success_count' => $success, 'fail_count' => $fail]);
    }

    /**
     * POST /blindboxes/:id/recover —— 强制回收盲盒
     *
     * user_blindbox_id = airdrop_record.id（空投逐笔来源）。
     * 无逐盒持有表，无法归因「已开启与否」，故仅允许回收空投发放记录，
     * 回收后 airdropped_count 回吐（库存池 +1，流通量 -1）。
     */
    public function recover()
    {
        $admin = self::guard('blindbox:recover');
        self::verifyPassword($admin);
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        $recordId = self::int('user_blindbox_id');
        $record = Db::name('airdrop_record')->where('id', $recordId)->find();
        if ($record === null) {
            throw new AdminException('未找到可回收的盲盒发放记录', Resp::NOT_FOUND);
        }
        if ((int) $record['status'] !== 1) {
            throw new AdminException('该发放记录已处理，无法重复回收', Resp::CONFLICT);
        }
        $task = Db::name('airdrop_task')->where('id', (int) $record['task_id'])->find();
        if ($task === null || (int) $task['target_type'] !== 2 || (int) $task['target_id'] !== $id) {
            throw new AdminException('发放记录与该盲盒不匹配', Resp::CONFLICT);
        }
        $quantity = (int) $task['quantity'];
        Db::startTrans();
        try {
            Db::name('airdrop_record')->where('id', $recordId)->update([
                'status' => 2, 'fail_reason' => '管理回收：' . mb_substr($reason, 0, 200),
            ]);
            Db::name('user')->where('id', (int) $record['user_id'])->dec('blind_box_num', $quantity)
                ->update(['updatetime' => time()]);
            // airdropped_count 回吐 → 库存池 +1
            Db::name('blind_box')->where('id', $id)->dec('airdropped_count', $quantity)
                ->update(['updatetime' => time()]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('回收失败：' . $e->getMessage());
        }
        Guard::audit('blindbox_recover', (int) $admin['id'], $id,
            ['record_id' => $recordId, 'user_id' => (int) $record['user_id'],
             'quantity' => $quantity, 'reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** GET /blindboxes/:id/open-records —— 盲盒开启记录（只读） */
    public function openRecords()
    {
        self::guard('blindbox:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');
        $q = Db::name('blind_box_open_record')->alias('or')
            ->leftJoin('collection c', 'c.id = or.collectible_id')
            ->leftJoin('user u', 'u.id = or.user_id')
            ->where('or.blind_box_id', $id);
        $total = (clone $q)->count();
        $rows = $q->order('or.id', 'desc')->page($page, $size)
            ->field('or.*,c.name as collectible_name,u.nickname as u_nickname,u.mobile as u_mobile')
            ->select()->toArray();
        $list = array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'user_id' => (int) $r['user_id'],
            'user_nickname' => (string) ($r['u_nickname'] ?? ''),
            'user_mobile_masked' => self::maskPhone((string) ($r['u_mobile'] ?? '')),
            'item_id' => (int) $r['item_id'],
            'collectible_id' => (int) $r['collectible_id'],
            'collectible_name' => (string) ($r['collectible_name'] ?? ''),
            'is_empty_prize' => (int) $r['collectible_id'] === 0,
            'quantity' => (int) $r['quantity'],
            'status' => (int) $r['status'],
            'remark' => (string) ($r['remark'] ?? ''),
            'time' => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /**
     * GET /blindboxes/:id/audit —— 盲盒库存审计
     *
     * diff = (user_holding + opened_count) - (sold + airdropped_count)
     *   diff > 0：开启数超过发出数 → 数据矛盾 → is_consistent=false
     *   diff ≤ 0：在途未开启（正常业务态）或全开启闭环
     * user_holding 恒 0：无逐盒持有载体（开启即发放子藏品），如实报告。
     */
    public function audit()
    {
        self::guard('blindbox:audit');
        $id = self::int('id');
        $r = Db::name('blind_box')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('盲盒不存在', Resp::NOT_FOUND);
        }
        $sold = (int) $r['sale_number'];
        $air = (int) $r['airdropped_count'];
        $destroyed = (int) $r['destroyed_count'];
        $total = (int) $r['total_num'];
        $pool = $total - $sold - $air - $destroyed;
        $opened = (int) Db::name('blind_box_open_record')
            ->where('blind_box_id', $id)->where('status', 1)->count();
        $userHolding = 0;
        $diff = ($userHolding + $opened) - ($sold + $air);

        return Resp::ok([
            'edition' => $total,
            'stock_pool' => $pool,
            'sold' => $sold,
            'airdropped_count' => $air,
            'destroyed_count' => $destroyed,
            'user_holding' => $userHolding,
            'opened_count' => $opened,
            'diff' => $diff,
            'is_consistent' => $diff <= 0,
        ]);
    }

    /** GET /blindboxes/:id/destroy-records */
    public function destroyRecords()
    {
        self::guard('blindbox:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');
        $q = Db::name('destroy_record')->where('target_type', 2)->where('target_id', $id);
        $total = (clone $q)->count();
        $rows = $q->order('id', 'desc')->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => [
            'id' => (int) $r['id'], 'quantity' => (int) $r['quantity'],
            'reason' => (string) $r['reason'], 'admin_name' => (string) $r['admin_name'],
            'time' => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // --------------------------------------------------------

    /** 盲盒库存池（推导值） */
    private function stockPool(array $r): int
    {
        return (int) $r['total_num'] - (int) $r['sale_number']
            - (int) $r['airdropped_count'] - (int) $r['destroyed_count'];
    }

    /** 盲盒简要结构 */
    private function brief(array $r): array
    {
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'cover' => (string) $r['main_image'],
            'edition' => (int) $r['total_num'],
            'price' => self::money($r['price'] ?? 0),
            'sale_status' => (int) $r['status'],
            'stock_pool' => $this->stockPool($r),
            'sold' => (int) $r['sale_number'],
            'airdropped_count' => (int) $r['airdropped_count'],
            'destroyed_count' => (int) $r['destroyed_count'],
            'open_draw' => (int) $r['open_draw'],
            'per_user_limit' => (int) $r['per_user_limit'],
            'is_transferable' => (int) $r['is_transferable'],
            'time' => self::iso((int) $r['createtime']),
        ];
    }
}
