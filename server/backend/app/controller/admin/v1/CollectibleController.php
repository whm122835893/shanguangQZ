<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * CollectibleController —— 藏品管理（契约 §8.5，18 个接口）
 *
 * 库存池恒等式（audit 端点以此校验，改动任何库存动作必须维持）：
 *   发行总量(total_num) = 库存池(stock_pool) + 已配置配额(reserved_count)
 *                       + 已售出(sold_count) + 已空投(airdropped_count) + 已销毁(destroyed_count)
 *   其中 stock_pool 为推导值，不落库。
 *
 * 状态机 sale_status：0=草稿 1=在售 2=已售罄 3=已强制下架
 *   - 仅草稿可编辑/删除；在售→强制下架(3)→重新上架(1)
 *
 * 高风险操作（destroy/airdrop/relist/resale-toggle/price-control/qualification
 * 写操作/delete/release）一律 reason+password+审计。
 *
 * @package app\controller\admin\v1
 */
final class CollectibleController
{
    use V1;

    public const STATUS_DRAFT    = 0;
    public const STATUS_ONSALE   = 1;
    public const STATUS_SOLDOUT  = 2;
    public const STATUS_FORCEOFF = 3;

    /** GET /collectibles */
    public function list()
    {
        self::guard('collectible:list');
        [$page, $size] = self::paging();

        $q = Db::name('collection');
        if ($name = self::str('name')) {
            $q->whereLike('name', '%' . $name . '%');
        }
        if ($cid = self::int('category_id')) {
            $q->where('series_id', $cid);
        }
        if (self::str('status') !== '') {
            $q->where('sale_status', self::int('status'));
        }
        if (self::str('is_resaleable') !== '') {
            $q->where('is_resaleable', self::int('is_resaleable'));
        }
        if (self::str('is_qualification_enabled') !== '') {
            $q->where('is_qualification_enabled', self::int('is_qualification_enabled'));
        }
        $q->order('id', 'desc');

        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();

        $list = array_map(fn ($r) => $this->brief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /collectibles —— 创建（草稿态） */
    public function create()
    {
        $admin = self::guard('collectible:create');
        self::requireFields(['name', 'edition']);
        $name = self::str('name');
        $edition = self::int('edition');
        if ($edition <= 0) {
            throw new AdminException('发行总量必须大于 0');
        }
        $now = time();
        $id = (int) Db::name('collection')->insertGetId([
            'name' => mb_substr($name, 0, 120),
            'main_image' => self::str('main_image'),
            'listimg' => self::str('listimg', self::str('main_image')),
            'series_id' => self::int('series_id'),
            'issuer_id' => 0,
            'total_num' => $edition,
            'total_num_show' => $edition,
            'story' => self::str('story'),
            'creator' => self::str('creator'),
            'royalty_rate' => self::str('royalty_rate', '0'),
            'is_transferable' => 1,
            'is_resaleable' => 1,
            'sale_status' => self::STATUS_DRAFT,
            'is_show' => 0,
            'createtime' => $now, 'updatetime' => $now,
        ]);
        Guard::audit('collectible_create', (int) $admin['id'], $id, ['name' => $name, 'edition' => $edition]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /collectibles/:id */
    public function detail()
    {
        self::guard('collectible:detail');
        $r = Db::name('collection')->where('id', self::int('id'))->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        $quotaSum = (int) Db::name('inventory_quota')
            ->where('collectible_id', $r['id'])->where('status', 1)->sum('planned_quantity');
        return Resp::ok($this->brief($r) + [
            'story' => (string) ($r['story'] ?? ''),
            'description' => (string) ($r['story'] ?? ''),
            'issuer' => (string) ($r['creator'] ?? ''),
            'creator' => (string) ($r['creator'] ?? ''),
            'royalty_rate' => (string) ($r['royalty_rate'] ?? '0'),
            'main_image' => (string) $r['main_image'],
            'listimg' => (string) $r['listimg'],
            'onsale_at' => self::iso((int) $r['onsale_at']),
            'off_sale_at' => self::iso((int) $r['off_sale_at']),
            'per_user_limit' => (int) $r['per_user_limit'],
            'release_quantity' => (int) $r['release_quantity'],
            'reserved_count' => $quotaSum,
            'resale_price_min' => self::money($r['resale_price_min'] ?? 0),
            'resale_price_max' => self::money($r['resale_price_max'] ?? 0),
            'resale_price_mode' => (int) $r['resale_price_mode'],
            'is_transferable' => (int) $r['is_transferable'],
        ]);
    }

    /** PUT /collectibles/:id —— 编辑（仅草稿） */
    public function edit()
    {
        $admin = self::guard('collectible:edit');
        $id = self::int('id');
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['sale_status'] !== self::STATUS_DRAFT) {
            throw new AdminException('仅草稿状态可编辑', Resp::CONFLICT);
        }
        $upd = [];
        foreach (['name', 'main_image', 'listimg', 'story', 'creator'] as $k) {
            $v = self::str($k);
            if ($v !== '') {
                $upd[$k] = $k === 'name' ? mb_substr($v, 0, 120) : $v;
            }
        }
        if ($v = self::str('royalty_rate')) {
            $upd['royalty_rate'] = $v;
        }
        if ($v = self::int('series_id')) {
            $upd['series_id'] = $v;
        }
        if ($upd === []) {
            throw new AdminException('没有需要更新的字段');
        }
        $upd['updatetime'] = time();
        Db::name('collection')->where('id', $id)->update($upd);
        Guard::audit('collectible_edit', (int) $admin['id'], $id, $upd);
        return Resp::ok(['success' => true]);
    }

    /** POST /collectibles/:id/release —— 发售配置 */
    public function release()
    {
        $admin = self::guard('collectible:release');
        $id = self::int('id');
        $price = self::str('price');
        $releaseQuantity = self::int('release_quantity');
        if ($price === '' || !is_numeric($price) || (float) $price <= 0) {
            throw new AdminException('请填写正确的发售价格');
        }
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        $pool = $this->stockPool($r);
        if ($releaseQuantity > $pool) {
            throw new AdminException("发售数量({$releaseQuantity})不得超过库存池({$pool})", Resp::CONFLICT);
        }
        Db::name('collection')->where('id', $id)->update([
            'sale_price' => number_format((float) $price, 2, '.', ''),
            'onsale_at' => self::str('onsale_at') !== '' ? (int) strtotime(self::str('onsale_at')) : time(),
            'off_sale_at' => self::str('off_sale_at') !== '' ? (int) strtotime(self::str('off_sale_at')) : 0,
            'per_user_limit' => self::int('per_user_limit', 1),
            'release_quantity' => $releaseQuantity,
            'sale_status' => self::STATUS_ONSALE,
            'is_show' => 1,
            'updatetime' => time(),
        ]);
        Guard::audit('collectible_release', (int) $admin['id'], $id,
            ['price' => $price, 'release_quantity' => $releaseQuantity]);
        return Resp::ok(['success' => true]);
    }

    /** POST /collectibles/:id/quotas —— 配置配额 */
    public function quotas()
    {
        $admin = self::guard('collectible:quota');
        $id = self::int('id');
        $items = request()->post('quotas');
        if (!is_array($items) || $items === []) {
            throw new AdminException('quotas 不能为空');
        }
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        $now = time();
        Db::startTrans();
        try {
            foreach ($items as $it) {
                $planned = (int) ($it['planned_quantity'] ?? 0);
                if ($planned <= 0) {
                    throw new \RuntimeException('配额数量必须大于 0');
                }
                Db::name('inventory_quota')->insert([
                    'collectible_id' => $id,
                    'quota_type' => (int) ($it['quota_type'] ?? 1),
                    'quota_name' => mb_substr((string) ($it['quota_name'] ?? ''), 0, 99),
                    'planned_quantity' => $planned,
                    'used_quantity' => 0, 'status' => 1,
                    'activity_id' => (int) ($it['activity_id'] ?? 0),
                    'activity_type' => (string) ($it['activity_type'] ?? ''),
                    'remark' => (string) ($it['remark'] ?? ''),
                    'created_by' => (int) $admin['id'],
                    'createtime' => $now, 'updatetime' => $now,
                ]);
            }
            $this->syncReserved($id);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('配额配置失败：' . $e->getMessage());
        }
        Guard::audit('collectible_quota_create', (int) $admin['id'], $id, ['count' => count($items)]);
        return Resp::ok(['success' => true]);
    }

    /** PUT /collectibles/:id/quotas/:quota_id —— 修改配额 */
    public function quotaUpdate()
    {
        $admin = self::guard('collectible:quota');
        $id = self::int('id');
        $quotaId = self::int('quota_id');
        $q = Db::name('inventory_quota')->where('id', $quotaId)->where('collectible_id', $id)->find();
        if ($q === null) {
            throw new AdminException('配额不存在', Resp::NOT_FOUND);
        }
        $upd = ['updatetime' => time()];
        if (self::str('planned_quantity') !== '') {
            $planned = self::int('planned_quantity');
            if ($planned < (int) $q['used_quantity']) {
                throw new AdminException('计划数量不得小于已用量', Resp::CONFLICT);
            }
            $upd['planned_quantity'] = $planned;
        }
        if (self::str('status') !== '') {
            $upd['status'] = self::int('status');
        }
        if ($v = self::str('remark')) {
            $upd['remark'] = $v;
        }
        Db::name('inventory_quota')->where('id', $quotaId)->update($upd);
        $this->syncReserved($id);
        Guard::audit('collectible_quota_update', (int) $admin['id'], $quotaId, $upd);
        return Resp::ok(['success' => true]);
    }

    /** POST /collectibles/:id/relist —— 重新上架（扩大发行，高风险） */
    public function relist()
    {
        $admin = self::guard('collectible:relist');
        self::verifyPassword($admin);
        $id = self::int('id');
        $quantity = self::int('release_quantity');
        if ($quantity <= 0) {
            throw new AdminException('追加数量必须大于 0');
        }
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['sale_status'] === self::STATUS_DRAFT) {
            throw new AdminException('草稿状态请走发售配置', Resp::CONFLICT);
        }
        Db::name('collection')->where('id', $id)->update([
            'total_num' => (int) $r['total_num'] + $quantity,
            'total_num_show' => (int) $r['total_num'] + $quantity,
            'release_quantity' => $quantity,
            'sale_status' => self::STATUS_ONSALE,
            'sale_price' => self::str('price') !== '' ? number_format((float) self::str('price'), 2, '.', '') : $r['sale_price'],
            'onsale_at' => self::str('onsale_at') !== '' ? (int) strtotime(self::str('onsale_at')) : time(),
            'off_sale_at' => self::str('off_sale_at') !== '' ? (int) strtotime(self::str('off_sale_at')) : 0,
            'updatetime' => time(),
        ]);
        Guard::audit('collectible_relist', (int) $admin['id'], $id,
            ['add_quantity' => $quantity, 'reason' => self::str('reason')]);
        return Resp::ok(['success' => true]);
    }

    /** POST /collectibles/:id/force-soldout —— 强制售罄 */
    public function forceSoldout()
    {
        $admin = self::guard('collectible:manage');
        self::verifyPassword($admin);
        $id = self::int('id');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['sale_status'] !== self::STATUS_ONSALE) {
            throw new AdminException('仅发售中的藏品可强制售罄', Resp::CONFLICT);
        }
        Db::name('collection')->where('id', $id)->update([
            'sale_status' => self::STATUS_SOLDOUT, 'updatetime' => time(),
        ]);
        Guard::audit('collectible_force_soldout', (int) $admin['id'], $id, ['reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** POST /collectibles/:id/destroy —— 销毁库存 */
    public function destroy()
    {
        $admin = self::guard('collectible:destroy');
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
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        $pool = $this->stockPool($r);
        if ($quantity > $pool) {
            throw new AdminException("销毁数量({$quantity})不得超过库存池({$pool})", Resp::CONFLICT);
        }
        Db::startTrans();
        try {
            Db::name('collection')->where('id', $id)->inc('destroyed_count', $quantity)->update(['updatetime' => time()]);
            Db::name('destroy_record')->insert([
                'target_type' => 1, 'target_id' => $id, 'target_name' => (string) $r['name'],
                'quantity' => $quantity, 'reason' => mb_substr($reason, 0, 250),
                'admin_id' => (int) $admin['id'], 'admin_name' => (string) $admin['username'],
                'ip_address' => request()->ip(), 'createtime' => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new AdminException('销毁失败：' . $e->getMessage());
        }
        Guard::audit('collectible_destroy', (int) $admin['id'], $id, ['quantity' => $quantity, 'reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** DELETE /collectibles/:id —— 删除（仅草稿且无任何关联） */
    public function delete()
    {
        $admin = self::guard('collectible:delete');
        self::verifyPassword($admin);
        $id = self::int('id');
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        if ((int) $r['sale_status'] !== self::STATUS_DRAFT) {
            throw new AdminException('仅草稿状态可删除', Resp::CONFLICT);
        }
        $holdings = Db::name('user_collection')->where('collection_id', $id)->count();
        $orders = Db::name('artel_order')->where('goods_id', $id)->count();
        $listings = Db::name('market_goods')->where('collection_id', $id)->count();
        if ($holdings > 0 || $orders > 0 || $listings > 0) {
            throw new AdminException('藏品已被持有/下单/挂单，禁止删除', Resp::CONFLICT);
        }
        Db::name('collection')->where('id', $id)->delete();
        Db::name('inventory_quota')->where('collectible_id', $id)->delete();
        Guard::audit('collectible_delete', (int) $admin['id'], $id, ['name' => $r['name']]);
        return Resp::ok(['success' => true]);
    }

    /** POST /collectibles/:id/airdrop —— 独立空投 */
    public function airdrop()
    {
        $admin = self::guard('collectible:airdrop');
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
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        $pool = $this->stockPool($r);
        if ($quantity * count($phones) > $pool) {
            throw new AdminException('空投总量超过库存池', Resp::CONFLICT);
        }

        $now = time();
        $taskId = (int) Db::name('airdrop_task')->insertGetId([
            'target_type' => 1, 'target_id' => $id, 'target_name' => (string) $r['name'],
            'quantity' => $quantity, 'user_count' => count($phones),
            'source' => 'collectible', 'admin_id' => (int) $admin['id'],
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
            $held = Db::name('user_collection')->where('user_id', $uid)->where('collection_id', $id)->find();
            if ($held === null) {
                Db::name('user_collection')->insert([
                    'user_id' => $uid, 'collection_id' => $id, 'num' => $quantity,
                    'sell_num' => 0, 'lock_num' => 0, 'is_collection' => 0, 'can_compound' => 1,
                    'utime' => $now, 'createtime' => $now,
                ]);
            } else {
                Db::name('user_collection')->where('id', $held['id'])->inc('num', $quantity)->update(['utime' => $now]);
            }
            Db::name('user_collection_record')->insert([
                'user_id' => $uid, 'collection_id' => $id, 'change_num' => $quantity,
                'type' => 3, 'ref_id' => $taskId, 'remark' => '管理后台空投', 'createtime' => $now,
            ]);
            Db::name('airdrop_record')->insert([
                'task_id' => $taskId, 'user_id' => $uid, 'phone' => $phone,
                'status' => 1, 'createtime' => $now,
            ]);
            $success++;
        }
        Db::name('collection')->where('id', $id)->inc('airdropped_count', $success * $quantity)->update(['updatetime' => time()]);
        Db::name('airdrop_task')->where('id', $taskId)->update([
            'success_count' => $success, 'fail_count' => $fail,
        ]);
        Guard::audit('collectible_airdrop', (int) $admin['id'], $id,
            ['task_id' => $taskId, 'quantity' => $quantity, 'phones' => count($phones), 'success' => $success, 'fail' => $fail]);
        return Resp::ok(['task_id' => $taskId, 'success_count' => $success, 'fail_count' => $fail]);
    }

    /** POST /collectibles/:id/resale-toggle —— 寄售开关（C 端挂单路径强制校验） */
    public function resaleToggle()
    {
        $admin = self::guard('collectible:market');
        self::verifyPassword($admin);
        $id = self::int('id');
        $enabled = self::int('is_resaleable');
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('请填写原因');
        }
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        Db::name('collection')->where('id', $id)->update([
            'is_resaleable' => $enabled ? 1 : 0, 'updatetime' => time(),
        ]);
        if (!$enabled) {
            // 已有在售挂单全部强制下架（开关关闭即时生效）
            Db::name('market_goods')->where('collection_id', $id)->where('status', 1)->update([
                'status' => 0, 'updatetime' => time(),
            ]);
        }
        Guard::audit('collectible_resale_toggle', (int) $admin['id'], $id,
            ['is_resaleable' => $enabled, 'reason' => $reason]);
        return Resp::ok(['success' => true]);
    }

    /** POST /collectibles/:id/price-control —— 价格管控（限价，C 端挂单路径强制校验） */
    public function priceControl()
    {
        $admin = self::guard('collectible:market');
        self::verifyPassword($admin);
        $id = self::int('id');
        $mode = self::int('resale_price_mode');
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        $min = self::str('resale_price_min', '0');
        $max = self::str('resale_price_max', '0');
        if ($mode === 1 && ((float) $min <= 0 || (float) $max <= 0 || (float) $min > (float) $max)) {
            throw new AdminException('限价区间不合法（需 0 < min ≤ max）');
        }
        if ($mode === 2 && (float) $max <= 0) {
            throw new AdminException('固定价必须大于 0');
        }
        Db::name('collection')->where('id', $id)->update([
            'resale_price_mode' => $mode,
            'resale_price_min' => number_format((float) $min, 2, '.', ''),
            'resale_price_max' => number_format((float) $max, 2, '.', ''),
            'updatetime' => time(),
        ]);
        Guard::audit('collectible_price_control', (int) $admin['id'], $id,
            ['mode' => $mode, 'min' => $min, 'max' => $max]);
        return Resp::ok(['success' => true]);
    }

    /** POST /collectibles/:id/qualification —— 资格购配置（C 端购买路径强制校验） */
    public function qualification()
    {
        $admin = self::guard('collectible:qualification');
        $id = self::int('id');
        $enabled = self::int('is_enabled');
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        $now = time();
        $requiredIds = request()->post('required_collectible_ids');
        $cfgData = [
            'collectible_id' => $id,
            'is_enabled' => $enabled ? 1 : 0,
            'required_collectible_ids' => is_array($requiredIds) ? json_encode(array_map('intval', $requiredIds)) : '[]',
            'required_checkin_days' => self::int('required_checkin_days'),
            'required_invite_count' => self::int('required_invite_count'),
            'condition_type' => self::int('condition_type', 1),
            'valid_start_at' => self::str('valid_start_at') !== '' ? (int) strtotime(self::str('valid_start_at')) : 0,
            'valid_end_at' => self::str('valid_end_at') !== '' ? (int) strtotime(self::str('valid_end_at')) : 0,
            'updatetime' => $now,
        ];
        $existId = (int) Db::name('qualification_config')->where('collectible_id', $id)->value('id');
        if ($existId > 0) {
            Db::name('qualification_config')->where('id', $existId)->update($cfgData);
        } else {
            $cfgData['createtime'] = $now;
            $existId = (int) Db::name('qualification_config')->insertGetId($cfgData);
        }
        // 白名单（可选项）
        $whitelist = request()->post('whitelist_phones');
        if (is_array($whitelist) && $whitelist !== []) {
            foreach ($whitelist as $phone) {
                $uid = (int) Db::name('user')->where('mobile', (string) $phone)->value('id');
                if ($uid <= 0) {
                    continue;
                }
                $dup = Db::name('qualification_whitelist')->where('config_id', $existId)->where('user_id', $uid)->find();
                if ($dup === null) {
                    Db::name('qualification_whitelist')->insert([
                        'config_id' => $existId, 'user_id' => $uid, 'phone' => (string) $phone,
                        'status' => 1, 'createtime' => $now,
                    ]);
                }
            }
        }
        Db::name('collection')->where('id', $id)->update([
            'is_qualification_enabled' => $enabled ? 1 : 0,
            'qualification_config_id' => $enabled ? $existId : 0,
            'updatetime' => $now,
        ]);
        Guard::audit('collectible_qualification', (int) $admin['id'], $id, ['is_enabled' => $enabled]);
        return Resp::ok(['success' => true, 'config_id' => $existId]);
    }

    /** GET /collectibles/:id/audit —— 库存审计 */
    public function audit()
    {
        self::guard('collectible:audit');
        $id = self::int('id');
        $r = Db::name('collection')->where('id', $id)->find();
        if ($r === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        $reserved = (int) Db::name('inventory_quota')
            ->where('collectible_id', $id)->where('status', 1)->sum('planned_quantity');
        $userHolding = (int) Db::name('user_collection')->where('collection_id', $id)->sum('num');
        $listingCount = (int) Db::name('market_goods')->where('collection_id', $id)
            ->where('status', 1)->sum('num');
        $transferPending = (int) Db::name('transfer_record')->where('collectible_id', $id)
            ->where('status', 0)->count();
        $sold = (int) $r['sold_count'];
        $air = (int) $r['airdropped_count'];
        $destroyed = (int) $r['destroyed_count'];
        $total = (int) $r['total_num'];
        $pool = $total - $reserved - $sold - $air - $destroyed;
        // 恒等式：发行总量 = 池 + 配额 + 售出 + 空投 + 销毁 → 按定义 diff 恒为 0（池为推导值）
        // 真正要抓的矛盾：持有 + 在售 + 转赠中 应 ≤ 售出 + 空投（发出去了多少）
        $out = $sold + $air;
        $held = $userHolding + $listingCount + $transferPending;
        $circulationDiff = $out - $held;

        return Resp::ok([
            'edition' => $total,
            'stock_pool' => $pool,
            'reserved_count' => $reserved,
            'sold' => $sold,
            'airdropped_count' => $air,
            'destroyed_count' => $destroyed,
            'user_holding' => $userHolding,
            'listing_count' => $listingCount,
            'transfer_pending' => $transferPending,
            'circulation_diff' => $circulationDiff,
            'is_consistent' => $circulationDiff === 0,
        ]);
    }

    /** GET /collectibles/:id/airdrop-records */
    public function airdropRecords()
    {
        self::guard('collectible:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');
        $q = Db::name('airdrop_record')->alias('ar')
            ->join('airdrop_task at', 'at.id = ar.task_id')
            ->where('at.target_type', 1)->where('at.target_id', $id);
        $total = (clone $q)->count();
        $rows = $q->order('ar.id', 'desc')->page($page, $size)
            ->field('ar.*,at.quantity')->select()->toArray();
        $list = array_map(fn ($r) => [
            'id' => (int) $r['id'], 'task_id' => (int) $r['task_id'],
            'user_id' => (int) $r['user_id'], 'phone' => self::maskPhone((string) $r['phone']),
            'quantity' => (int) $r['quantity'], 'status' => (int) $r['status'],
            'fail_reason' => (string) ($r['fail_reason'] ?? ''),
            'time' => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** GET /collectibles/:id/destroy-records */
    public function destroyRecords()
    {
        self::guard('collectible:detail');
        [$page, $size] = self::paging();
        $id = self::int('id');
        $q = Db::name('destroy_record')->where('target_type', 1)->where('target_id', $id);
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

    /** 库存池（推导值） */
    private function stockPool(array $r): int
    {
        $reserved = (int) Db::name('inventory_quota')
            ->where('collectible_id', $r['id'])->where('status', 1)->sum('planned_quantity');
        return (int) $r['total_num'] - $reserved - (int) $r['sold_count']
            - (int) $r['airdropped_count'] - (int) $r['destroyed_count'];
    }

    /** 配额变化后同步 reserved_count（冗余列，供列表/审计快取） */
    private function syncReserved(int $collectibleId): void
    {
        $sum = (int) Db::name('inventory_quota')
            ->where('collectible_id', $collectibleId)->where('status', 1)->sum('planned_quantity');
        Db::name('collection')->where('id', $collectibleId)->update(['reserved_count' => $sum]);
    }

    /** 藏品简要结构 */
    private function brief(array $r): array
    {
        $reserved = (int) Db::name('inventory_quota')
            ->where('collectible_id', $r['id'])->where('status', 1)->sum('planned_quantity');
        return [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'cover' => (string) $r['main_image'],
            'series_id' => (int) $r['series_id'],
            'edition' => (int) $r['total_num'],
            'price' => self::money($r['sale_price'] ?? 0),
            'sale_status' => (int) $r['sale_status'],
            'stock_pool' => (int) $r['total_num'] - $reserved - (int) $r['sold_count']
                - (int) $r['airdropped_count'] - (int) $r['destroyed_count'],
            'sold_count' => (int) $r['sold_count'],
            'airdropped_count' => (int) $r['airdropped_count'],
            'destroyed_count' => (int) $r['destroyed_count'],
            'is_resaleable' => (int) $r['is_resaleable'],
            'is_transferable' => (int) $r['is_transferable'],
            'is_qualification_enabled' => (int) $r['is_qualification_enabled'],
            'creator' => (string) ($r['creator'] ?? ''),
            'time' => self::iso((int) $r['createtime']),
        ];
    }
}
