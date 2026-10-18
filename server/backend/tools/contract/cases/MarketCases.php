<?php
declare(strict_types=1);

/**
 * 市场域契约用例：tradeDetail / prepayDetail / tradingInfo / begBuyList
 *
 * 共同点：全部走 MarketFormatter + MarketStats（与 Controller 同一入口），
 * 测的是真实查询链路而非另写的副本 SQL。
 */

use app\model\Collection;
use app\model\ConsignmentPriceRule;
use app\service\MarketFormatter;
use app\service\MarketStats;

if (!function_exists('runMarketCases')) {
    /**
     * @global array $results 用例结果行（run.php 注入）
     * @global int   $failed  失败计数（run.php 注入）
     */
    function runMarketCases(): void
    {
        global $results, $failed;

        // ---- 6. market/trade/collectionDetail（嵌套版：顶层 5 字段 + floor_price 11 字段）----
        //
        // 🔴 本用例是两端点契约的「锚」：trade 的 floor_price 是嵌套对象，
        //    若有人「优化」成平铺（或反之），前端 data.floor_price.real_price
        //    直接变 undefined，页面静默显示空——正是最难查的 bug 形态。
        try {
            $coll = Collection::find(1);
            $rule = ConsignmentPriceRule::findForCollection(1, 'trade');
            if ($coll === null || $rule === null) {
                $results[] = 'tradeDetail        [跳过]（本地无 collection id=1 或 trade 规则，请先跑 seed.php）';
                $failed++;
            } else {
                check('tradeDetail', 'market_trade_collectiondetail',
                    MarketFormatter::tradeCollectionDetail($coll->toArray(), $rule),
                    'data', [], 0, '藏品详情');
            }
        } catch (\Throwable $e) {
            $results[] = 'tradeDetail         异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 7. market/Prepayment/collectionDetail（平铺版：11 字段直接铺在 data 下）----
        //
        // 🔴 与用例 6 是同源数据的两个投影，唯一差别是 tips 与嵌套层级。
        //    两个用例都过 = 证明 scene 区分和结构区分同时成立。
        //    只过一个 = 说明有一半用户会看到错误文案或取不到字段。
        try {
            $rule = ConsignmentPriceRule::findForCollection(1, 'prepay');
            if ($rule === null) {
                $results[] = 'prepayDetail       [跳过]（本地无 prepay 场景规则，请先跑 seed.php）';
                $failed++;
            } else {
                check('prepayDetail', 'market_prepayment_collectiondetail',
                    MarketFormatter::prepaymentCollectionDetail($rule),
                    'data', [], 0, '相关金额');
            }
        } catch (\Throwable $e) {
            $results[] = 'prepayDetail        异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 8. market/trade/tradingInformation（空态契约：9 键 + 双 null）----
        //
        // 🔴 本用例锁的是「空态结构契约」而非数据值：
        //    样本账号在原站无挂单/成交，9 个键的类型是唯一可依的契约——
        //    6 个 int 统计 + list=[] + today_highest_price/today_lowest_price = null。
        //    若有人把 null "优化"成 0，或删掉某个统计键，本用例立刻红。
        try {
            // goods_id=1 是本地真实存在的藏品；三表对它均无数据，
            // 统计必为 0/null——与样本一致且是真实查询结果，不是硬编码
            $stats = MarketStats::tradingStats(1);
            check('tradingInfo', 'market_trade_tradinginformation',
                MarketFormatter::tradingInformation($stats),
                'data', [], 0, '交易信息');
        } catch (\Throwable $e) {
            $results[] = 'tradingInfo          异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 9. market/trade/begBuyList（求购列表：5 键信封，空态全 0）----
        //
        // 🔴 锁两类契约：
        //    a. 5 键集合与键序 —— current_page 开头，与 orderList 的 total 开头
        //       「同 5 键不同序」正是两个端点的区分点（compare() 只比字段集
        //       不比顺序，故键序单独断言）
        //    b. 空态值 last_page=0 / per_page=0 —— 不是 Response::page() 的
        //       max(1,...) 钳位值；直接复用那个方法会静默把 0 钳成 1
        //
        // ⚠️ 样本是空态契约（原站该账号无求购单）。本地 beg_buy_order 表 0 行
        //    （无种子来源：求购写端点无成功样本，D-002），total 恒 0 与样本一致。
        //    若将来灌入求购数据，本用例会红——那是预期信号：非空态契约
        //    需要新的非空样本才能确立（D-006）。
        try {
            // collection id=1 本地真实存在；计数走与 Controller 同一入口
            $total    = MarketStats::begBuyOrderCount(1);
            $envelope = MarketFormatter::begBuyList($total, 1, 10);
            check('begBuyList', 'market_trade_begBuyList_161',
                $envelope, 'data', [], 0, '订单信息');

            // 键序断言（顺序漂移前端不崩，但契约已变，必须归零）
            $expectedKeys = ['current_page', 'data', 'last_page', 'per_page', 'total'];
            if (array_keys($envelope) !== $expectedKeys) {
                $results[] = 'begBuyList          [键序] 期望 ' . implode(',', $expectedKeys)
                    . ' 实际 ' . implode(',', array_keys($envelope));
                $failed++;
            }
        } catch (\Throwable $e) {
            $results[] = 'begBuyList          异常：' . $e->getMessage();
            $failed++;
        }
    }
}
