<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 市值排行服务（v3.0 从内存 MOCK 切实时聚合）
 *
 * 市值定义：用户持有藏品数 × 该藏品当前最低在售挂单价（无挂单按 0）。
 * 无快照表，实时聚合 user_collection × market_goods，天然真实。
 *
 * 契约：{data: [{rank, id, nickname, avatar, market_value(string)}], my, activityRule}
 * 见 tools/contract/user_extra_test.php。
 *
 * @package app\service
 */
final class RankService
{
    public static function ranking(int $userId, int $limit = 50): array
    {
        $rows = Db::query(
            'SELECT u.id, u.nickname, u.avatar,
                    SUM(IFNULL(g.min_price, 0) * uc.num) AS mv
             FROM user_collection uc
             JOIN user u ON u.id = uc.user_id
             LEFT JOIN (
                 SELECT collection_id, MIN(price) AS min_price
                 FROM market_goods
                 WHERE status = 1 AND sold_num < num
                 GROUP BY collection_id
             ) g ON g.collection_id = uc.collection_id
             GROUP BY uc.user_id, u.id, u.nickname, u.avatar
             HAVING mv > 0
             ORDER BY mv DESC
             LIMIT ?',
            [$limit]
        );

        $data = [];
        $rank = 1;
        foreach ($rows as $r) {
            $data[] = [
                'rank'         => $rank++,
                'id'           => (int) $r['id'],
                'nickname'     => (string) $r['nickname'],
                'avatar'       => (string) ($r['avatar'] ?? ''),
                // 金额红线：string 下发
                'market_value' => number_format((float) $r['mv'], 2, '.', ''),
            ];
        }

        // 原站实测结构：my 为空串（前端不渲染细节），activityRule 透传空
        $my = '';
        return ['data' => $data, 'my' => $my, 'activityRule' => ''];
    }
}
