<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * EligibilityService —— C 端购买资格判定（规格 §3.2 资格购 / §3.3 优先购）
 *
 * 三步判定（后端强制，规格 §3.2.3）：
 *   Step 1 优先购资格有效（expires_at > now 且 used < max 且 status=1）→ 直接放行
 *   Step 2 藏品未开启资格购（is_qualification_enabled != 1）→ 放行
 *   Step 3 资格购条件判定（condition_type 1=任一 2=全部）：
 *     A 白名单手机号（qualification_whitelist，status=1 且未过期）
 *     B 持资格藏品（user_collection 可售数量 > 0，required_collectible_ids）
 *     C 累计签到天数 ≥ required_checkin_days（check_in_record 去重日期计数）
 *     D 累计邀请好友数 ≥ required_invite_count（invite_record status=1）
 *   均不满足 → 「未获得购买资格」
 *
 * ⚠️ all 模式只校验配置了非零值的条件（白名单未配置则跳过 A），避免
 *    「配置了 2 个条件却因未配白名单而全盘死锁」。
 *
 * @package app\service
 */
final class EligibilityService
{
    /**
     * 购买资格判定
     *
     * @return array{0:bool,1:string} [是否可购买, 拒绝原因]
     */
    public static function canBuy(int $userId, int $collectionId): array
    {
        if ($collectionId <= 0 || $userId <= 0) {
            return [false, '参数错误'];
        }

        // Step 1：优先购资格绕过资格购（规格 §3.2.4）
        if (self::hasValidPriority($userId, $collectionId)) {
            return [true, ''];
        }

        // Step 2：未开启资格购 → 无门槛
        $coll = Db::name('collection')->where('id', $collectionId)
            ->field('is_qualification_enabled,per_user_limit')->find();
        if ($coll === null) {
            return [false, '藏品不存在'];
        }
        if ((int) $coll['is_qualification_enabled'] !== 1) {
            return [true, ''];
        }

        // Step 3：条件判定
        $cfg = Db::name('qualification_config')->where('collectible_id', $collectionId)
            ->where('is_enabled', 1)->order('id', 'desc')->find();
        if ($cfg === null) {
            // 开关开启但无配置 → 保守拒绝（开关即门槛）
            return [false, '未获得购买资格'];
        }
        $now = time();
        if ((int) $cfg['valid_start_at'] > 0 && $now < (int) $cfg['valid_start_at']) {
            return [false, '资格购尚未开始'];
        }
        if ((int) $cfg['valid_end_at'] > 0 && $now > (int) $cfg['valid_end_at']) {
            return [false, '资格购已结束'];
        }

        $requireAll = (int) $cfg['condition_type'] === 2;
        $requiredIds = json_decode((string) $cfg['required_collectible_ids'], true) ?: [];
        $requiredIds = array_map('intval', is_array($requiredIds) ? $requiredIds : []);

        // 各条件：null = 未配置（不参与判定）；false = 配置但未满足
        $conds = [];
        $conds['A'] = self::whitelistHits($cfg['id'], $userId);
        $conds['B'] = $requiredIds === [] ? null : self::holdsAny($userId, $requiredIds);
        $conds['C'] = (int) $cfg['required_checkin_days'] > 0
            ? self::checkinDays($userId) >= (int) $cfg['required_checkin_days'] : null;
        $conds['D'] = (int) $cfg['required_invite_count'] > 0
            ? self::inviteCount($userId) >= (int) $cfg['required_invite_count'] : null;

        $active = array_filter($conds, static fn ($v) => $v !== null);
        if ($active === []) {
            // 无任何有效条件 → 视为白名单模式但白名单为空 → 拒绝
            return [false, '未获得购买资格'];
        }
        if ($requireAll) {
            $ok = !in_array(false, $active, true);
        } else {
            $ok = in_array(true, $active, true);
        }
        return $ok ? [true, ''] : [false, '未获得购买资格'];
    }

    /**
     * 每人限购校验（规格 §3.2.4：资格购只是购买门槛，购买仍受 per_user_limit 约束）
     *
     * 口径：该用户该藏品 goods_type=1 已支付订单的 num 总和 ≥ per_user_limit → 拒
     *
     * @return array{0:bool,1:string}
     */
    public static function checkPerUserLimit(int $userId, int $collectionId): array
    {
        $limit = (int) (Db::name('collection')->where('id', $collectionId)->value('per_user_limit') ?? 0);
        if ($limit <= 0) {
            return [true, ''];
        }
        $bought = (int) (Db::name('artel_order')
            ->where('user_id', $userId)
            ->where('goods_id', $collectionId)
            ->where('goods_type', 1)
            ->where('order_status', 2)
            ->fieldRaw('COALESCE(SUM(num),0) AS n')->find()['n'] ?? 0);
        if ($bought >= $limit) {
            return [false, '已达到每人限购数量'];
        }
        return [true, ''];
    }

    /** 优先购资格有效：status=1 且 expires_at > now 且 used < max */
    public static function hasValidPriority(int $userId, int $collectionId): bool
    {
        $row = Db::name('priority_sale_whitelist')
            ->where('collectible_id', $collectionId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->where('expires_at', '>', time())
            ->whereRaw('used_quantity < max_quantity')
            ->find();
        return $row !== null;
    }

    /** 条件 A：白名单命中（未配置白名单返回 null 不参与判定） */
    private static function whitelistHits(int $configId, int $userId): ?bool
    {
        $configured = Db::name('qualification_whitelist')->where('config_id', $configId)->count();
        if ($configured === 0) {
            return null;
        }
        $hit = Db::name('qualification_whitelist')
            ->where('config_id', $configId)
            ->where('user_id', $userId)
            ->where('status', 1)
            ->where(function ($q) {
                $q->where('expires_at', 0)->whereOr('expires_at', '>', time());
            })
            ->count();
        return $hit > 0;
    }

    /** 条件 B：持有任一资格藏品（可售数量 > 0） */
    private static function holdsAny(int $userId, array $requiredIds): bool
    {
        $hit = Db::name('user_collection')
            ->where('user_id', $userId)
            ->whereIn('collection_id', $requiredIds)
            ->whereRaw('(num - sell_num - lock_num) > 0')
            ->count();
        return $hit > 0;
    }

    /** 条件 C：累计签到天数（去重日期计数） */
    private static function checkinDays(int $userId): int
    {
        $row = Db::name('check_in_record')->where('user_id', $userId)
            ->fieldRaw('COUNT(DISTINCT checkin_date) AS d')->find();
        return (int) ($row['d'] ?? 0);
    }

    /** 条件 D：累计邀请成功注册人数 */
    private static function inviteCount(int $userId): int
    {
        return (int) Db::name('invite_record')
            ->where('inviter_id', $userId)
            ->where('status', 1)
            ->count();
    }
}
