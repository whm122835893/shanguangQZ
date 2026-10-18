<?php
declare(strict_types=1);

/**
 * 内容域契约用例：antMycollection / newList / myunion / getBanner
 *
 * 共同点：数据全部来自 seed.php 灌入的本地表，
 * 用例必须走与 Controller 同一查询入口（禁止另写 SQL 副本）。
 */

use app\model\Banner;
use app\model\Collection;
use app\model\Message;
use app\model\UserCollection;
use app\service\Formatter;
use app\service\TradeFormatter;
use app\service\UnionFormatter;

if (!function_exists('runContentCases')) {
    /**
     * @global array $results 用例结果行（run.php 注入）
     * @global int   $failed  失败计数（run.php 注入）
     */
    function runContentCases(): void
    {
        global $results, $failed;

        // ---- 2. antMycollection（我的持有：嵌套藏品对象）----
        try {
            $rows = UserCollection::where('user_id', 16797)->order('id', 'asc')->select()->toArray();
            if ($rows === []) {
                $results[] = 'antMycollection    [跳过]（本地无持有记录）';
                $failed++;
            } else {
                // 必须复刻 Controller 的关联加载，否则测的不是真实链路
                // （Controller 在 Core.php 里手工建 $collMap 后作为第二参数传入）
                $cids = array_values(array_unique(array_map(
                    static fn($r) => (int) $r['collection_id'],
                    $rows
                )));
                $collRows = Collection::whereIn('id', $cids)->select()->toArray();
                $collMap  = [];
                foreach ($collRows as $cr) {
                    $collMap[(int) $cr['id']] = $cr;
                }
                check('antMycollection', 'user_collection_user_collection_antmycollection',
                    TradeFormatter::userCollection($rows[0], $collMap[(int) $rows[0]['collection_id']] ?? null),
                    'data.data', [], 0, '获取成功');
            }
        } catch (\Throwable $e) {
            $results[] = 'antMycollection    异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 3. newList（消息）----
        try {
            $list = Message::order('id', 'asc')->select()->toArray();
            if ($list === []) {
                $results[] = 'newList            [跳过]（本地无消息）';
                $failed++;
            } else {
                check('newList', 'user_newlist', TradeFormatter::message($list[0]), 'data.list', [], 0, '消息列表');
            }
        } catch (\Throwable $e) {
            $results[] = 'newList            异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 4. myunion（公会）----
        try {
            check('myunion', 'union_user_myunion', UnionFormatter::myUnion(16797), 'data', [], 0, '成功');
        } catch (\Throwable $e) {
            $results[] = 'myunion            异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 5. getBanner（首页轮播，实测 2 条 5 字段）----
        try {
            $sampleRows = raw('banner_show_getBanner')['data']['data'] ?? [];
            if ($sampleRows === []) {
                $results[] = 'getBanner          [跳过]（原站样本无数据）';
                $failed++;
            } else {
                foreach ($sampleRows as $row) {
                    $id  = (int) ($row['id'] ?? 0);
                    $rec = $id > 0 ? Banner::find($id) : null;
                    if ($rec === null) {
                        $results[] = sprintf('  getBanner id=%d [缺失] 本地缺该记录，请先跑 seed.php', $id);
                        $failed++;
                        continue;
                    }
                    // 走真实出参链路（Controller 里的排序+切片也要覆盖，故查全量再排序）
                    $all = Banner::where('is_show', 1)
                        ->order('sort', 'desc')->order('id', 'desc')
                        ->select()->toArray();
                    $all = array_slice($all, 0, 15);
                    $hit = null;
                    foreach ($all as $r) {
                        if ((int) $r['id'] === $id) {
                            $hit = $r;
                            break;
                        }
                    }
                    check('getBanner#' . $id, 'banner_show_getBanner',
                        Formatter::bannerList($hit === null ? [] : [$hit])[0] ?? [],
                        'data.data', [], $id, 'banner列表');
                }
            }
        } catch (\Throwable $e) {
            $results[] = 'getBanner          异常：' . $e->getMessage();
            $failed++;
        }
    }
}
