<?php
declare(strict_types=1);

/**
 * 求购域契约用例：begBuyOrderList / begBuyOrderInfo / createBegBuyOrder /
 *                cancelBegBuyOrder
 *
 * 🔴 本组是「原站制造数据」授权的直接产出（2026-10-18，账号 16797）：
 *    在原站真实创建两笔求购单（order_id 76/77，藏品 162，单价 20000.00），
 *    76 自动过期、77 手动取消，全链路样本落在 recon/raw5/。本地 seed.php
 *    把两笔终态逐字段灌入（显式保留原站 id 与 order_sn），因此列表/详情
 *    用例是**逐字段样本比对**——76/77 的 order_sn/canceltime/updatetime
 *    全部参与比对，不是结构自证。
 *
 * 用例分两类：
 *   A. 样本比对（check()）—— 出参 vs 原站实测
 *   B. 行为断言（直连 Controller）—— 校验链文案、取消语义、IDOR、信封。
 *      B 类为什么绕开 check()：check() 只比对成功响应的 data 节点，而本域
 *      最脆的契约全在失败路径（9 步校验链的消息文案逐字、归属校验的最小
 *      披露）。Controller 与用例同进程直连（withGet/withPost 绑定请求），
 *      写库用例一律事务包住回滚。
 *
 * ⚠️ InnoDB 自增计数不回退：事务回滚能撤销行，但 beg_buy_order.auto_increment
 *    仍会前进（实测 78 → 79）。契约用例按 id 定位种子行不受影响；手工验证时
 *    下一个真实单号从 79 起，不是 bug。
 *
 * ⚠️ 运行顺序：B.1（列表信封）必须在 B.4（建单）之前——建单会 +1 行，
 *    之后 total 就是 3 不是 2。
 */

require_once __DIR__ . '/BegBuyTestKit.php';

use app\controller\api\BegBuyController;
use app\model\BegBuyOrder;
use app\model\Collection;
use app\service\BegBuyFormatter;
use think\facade\Db;

if (!function_exists('runBegBuyCases')) {
    /**
     * @global array $results 用例结果行（run.php 注入）
     * @global int   $failed  失败计数（run.php 注入）
     */
    function runBegBuyCases(): void
    {
        global $results, $failed;

        // ==================== A. 样本比对（check） ====================

        // ---- 13/14. begBuyOrderList 两行终态（raw5 after_cancel 样本逐 id 比对）----
        //
        // 🔴 为什么有两行样本可比：本地灌的就是这两笔的终态。76 的 canceltime
        //    落值（自动过期）、77 的 canceltime 留 null（手动取消）——两种取消
        //    路径在 DB 层的差异就是这一列，抄错会把「手动取消」显示成「已过期」。
        //    surplus_time_text 逐秒递减，是运行时字段：排除值、保留键。
        try {
            $sampleRows = raw('market_trade_begBuyOrderList_after_cancel')['data']['data'] ?? [];
            if ($sampleRows === []) {
                $results[] = 'begBuyOrderList      [跳过]（缺 raw5 样本）';
                $failed++;
            }
            foreach ($sampleRows as $srow) {
                $id  = (int) ($srow['id'] ?? 0);
                $rec = $id > 0 ? BegBuyOrder::find($id) : null;
                if ($rec === null) {
                    $results[] = "begBuyOrderList#{$id}   [缺失] 本地无该单（先跑 seed.php）";
                    $failed++;
                    continue;
                }
                check("begBuyOrderList#{$id}", 'market_trade_begBuyOrderList_after_cancel',
                    BegBuyFormatter::row($rec, true), 'data.data',
                    ['surplus_time_text'], $id, '订单信息');
            }

            // 键序断言（28 键全序）——compare() 只比字段集，sale_number 位于
            // order_type 之后、surplus_time_text 之前（实测，不是行尾）
            $rec77 = BegBuyOrder::find(77);
            if ($rec77 !== null) {
                $keys = array_keys(BegBuyFormatter::row($rec77, true));
                if (count($keys) !== 28 || $keys[25] !== 'order_type'
                    || $keys[26] !== 'sale_number' || $keys[27] !== 'surplus_time_text') {
                    $results[] = 'begBuyOrderList#77   [键序] 期望 order_type|sale_number|surplus_time_text 收尾'
                        . '，实际 ' . implode('|', array_slice($keys, 25));
                    $failed++;
                }
            }
        } catch (\Throwable $e) {
            $results[] = 'begBuyOrderList      异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 15. begBuyOrderInfo#77（27 字段，info 样本比对 + 键序）----
        //
        // 🔴 为什么只比 77 不比 76：本地灌的是终态，76 的 info 样本抓于待付款
        //    时刻（status=1/canceltime=null），与本地（status=4/canceltime=落值）
        //    是同一订单的不同生命周期瞬间——比了必红，但不是实现错。
        //    77 的 info 样本抓于取消后，与本地灌入态一致，可比。
        try {
            $rec = BegBuyOrder::find(77);
            if ($rec === null) {
                $results[] = 'begBuyOrderInfo      [跳过]（本地无 77，先跑 seed.php）';
                $failed++;
            } else {
                check('begBuyOrderInfo#77', 'market_trade_begBuyOrderInfo_77_cancelled',
                    BegBuyFormatter::row($rec, false), 'data',
                    ['surplus_time_text'], 0, '订单信息');

                $keys = array_keys(BegBuyFormatter::row($rec, false));
                if (count($keys) !== 27 || $keys[25] !== 'order_type' || $keys[26] !== 'surplus_time_text') {
                    $results[] = 'begBuyOrderInfo#77   [键序] 期望 27 键 order_type|surplus_time_text 收尾'
                        . '，实际 ' . implode('|', array_slice($keys, 25));
                    $failed++;
                }
            }
        } catch (\Throwable $e) {
            $results[] = 'begBuyOrderInfo      异常：' . $e->getMessage();
            $failed++;
        }

        // ==================== B. 行为断言（直连 Controller，事务包住） ====================
        Db::startTrans();
        try {
            $token = begBuyTempToken(16797);

            // ---- B.1 begBuyOrderList 信封（Controller 真实出参）----
            begBuyBind($token, []);
            begBuyAssert('list:信封 msg', (new BegBuyController())->begBuyOrderList(), 1, '订单信息');
            $env = (new BegBuyController())->begBuyOrderList()->getData()['data'];
            begBuyExpect('list:total=2', $env['total'], 2);
            begBuyExpect('list:per_page=15', $env['per_page'], 15);
            begBuyExpect('list:current_page=1', $env['current_page'], 1);
            begBuyExpect('list:last_page=1', $env['last_page'], 1);
            begBuyExpect('list:行数=2', count($env['data']), 2);
            begBuyExpect('list:首行 id=77（倒序）', $env['data'][0]['id'], 77);
            begBuyExpect('list:次行 id=76', $env['data'][1]['id'], 76);
            begBuyExpect('list:行键数=28', count($env['data'][0]), 28);

            // ---- B.2 begBuyOrderInfo 信封 + 无 token 401 ----
            begBuyBind($token, ['order_id' => 77]);
            begBuyAssert('info:77 信封 msg', (new BegBuyController())->begBuyOrderInfo(), 1, '订单信息');
            begBuyBind($token, ['order_id' => 0]);
            begBuyAssert('info:空 order_id', (new BegBuyController())->begBuyOrderInfo(), 0, '订单ID不能为空');
            begBuyBind($token, ['order_id' => 99999]);
            begBuyAssert('info:不存在', (new BegBuyController())->begBuyOrderInfo(), 0, '订单不存在');
            begBuyBind('', ['collection_id' => 162, 'price' => '1.00', 'number' => 1, 'pay_way' => 'huifu']);
            $noAuth = (new BegBuyController())->createBegBuyOrder();
            begBuyAssert('create:无 token', $noAuth, 401, '请登录后操作');
            begBuyExpect('create:无 token HTTP 码', $noAuth->getCode(), 401);

            // ---- B.3 createBegBuyOrder 校验链（9 步，消息逐字）----
            //
            // 文案两个动态值不硬编码：区间提示取 162 样本 floor_price.tips 原文，
            // 上限提示取 collection.reprice_beg_max_limit 取整——与 Controller
            // 同源，改配置两边一起变，不会出现「用例过、线上错」。
            $detail162 = raw('market_trade_collectionDetail_162');
            $rangeMsg  = (string) ($detail162['data']['floor_price']['tips'] ?? '');
            $begMax    = (float) (string) Collection::find(162)['reprice_beg_max_limit'];
            if ($rangeMsg === '' || $begMax <= 0) {
                $results[] = 'create:校验链        [跳过]（缺 162 样本或配置，先跑 seed.php）';
                $failed++;
            } else {
                $chain = [
                    ['空 collection_id',   ['collection_id' => 0, 'price' => '20000.00', 'number' => 1, 'pay_way' => 'huifu'], '藏品ID不能为空'],
                    ['number=0',           ['collection_id' => 162, 'price' => '20000.00', 'number' => 0, 'pay_way' => 'huifu'], '数量不能为空'],
                    ['number=abc',         ['collection_id' => 162, 'price' => '20000.00', 'number' => 'abc', 'pay_way' => 'huifu'], '数量不能为空'],
                    ['pay_way=wechat',     ['collection_id' => 162, 'price' => '20000.00', 'number' => 1, 'pay_way' => 'wechat'], '支付方式不能为空'],
                    ['藏品 99999 不存在',   ['collection_id' => 99999, 'price' => '20000.00', 'number' => 1, 'pay_way' => 'huifu'], '藏品不存在'],
                    ['price 空串',          ['collection_id' => 162, 'price' => '', 'number' => 1, 'pay_way' => 'huifu'], '请输入挂单价格'],
                    ['price=abc',          ['collection_id' => 162, 'price' => 'abc', 'number' => 1, 'pay_way' => 'huifu'], '请输入挂单价格'],
                    ['藏品 8 不可求购',     ['collection_id' => 8, 'price' => '20000.00', 'number' => 1, 'pay_way' => 'huifu'], '该藏品不可求购'],
                    ['price 低于地板价',    ['collection_id' => 162, 'price' => '19999.99', 'number' => 1, 'pay_way' => 'huifu'], $rangeMsg],
                    ['price 超上限',       ['collection_id' => 162, 'price' => '20000.01', 'number' => 1, 'pay_way' => 'huifu'], '限制最大求购金额' . (int) $begMax],
                    // 顺序实证：多错并发时先报哪步（调换实现顺序会静默改文案）
                    ['顺序:全错先报藏品ID',  ['collection_id' => 0, 'price' => 'abc', 'number' => 0, 'pay_way' => 'x'], '藏品ID不能为空'],
                    ['顺序:数量错压价格错', ['collection_id' => 162, 'price' => 'abc', 'number' => 0, 'pay_way' => 'huifu'], '数量不能为空'],
                    ['顺序:支付错压价格错', ['collection_id' => 162, 'price' => 'abc', 'number' => 1, 'pay_way' => 'x'], '支付方式不能为空'],
                    ['顺序:不存在压价格错', ['collection_id' => 99999, 'price' => 'abc', 'number' => 1, 'pay_way' => 'huifu'], '藏品不存在'],
                    ['顺序:价格错压不可求购', ['collection_id' => 8, 'price' => 'abc', 'number' => 1, 'pay_way' => 'huifu'], '请输入挂单价格'],
                ];
                foreach ($chain as [$label, $post, $expect]) {
                    begBuyBind($token, $post);
                    begBuyAssert('create:' . $label, (new BegBuyController())->createBegBuyOrder(), 0, $expect);
                }
            }

            // ---- B.4 createBegBuyOrder 成功建单（事务内真写，结束回滚）----
            begBuyBind($token, ['collection_id' => 162, 'price' => '20000.00', 'number' => 2, 'pay_way' => 'huifu']);
            $created = (new BegBuyController())->createBegBuyOrder();
            begBuyAssert('create:成功建单', $created, 1, '下单成功');
            $d = $created->getData()['data'];
            begBuyExpect('create:响应键序', array_keys($d), ['order_sn', 'order_id', 'time']);
            begBuyExpect('create:time="300"', $d['time'], '300');
            begBuyExpect('create:order_id 是 string', is_string($d['order_id']), true);
            begBuyExpect('create:order_sn 28 位', strlen((string) $d['order_sn']), 28);

            $row = BegBuyOrder::find((int) $d['order_id']);
            $coll = Collection::find(162);
            $fields = [
                'user_id' => 16797, 'goods_id' => 162, 'order_status' => 1, 'number' => 2,
                'price' => '20000.00', 'total_price' => '40000.00',
                'platform_service_rate' => '6.00', 'platform_service_price' => '2400.00',
                'single_platform_service_price' => '1200.00',
                'goods_name' => (string) $coll['name'], 'goods_image' => (string) $coll['listimg'],
                'series_id' => 9, 'series_name' => '卷土重来',
                'pay_scene' => '', 'pay_way' => 'huifu',
                'order_type' => 11, 'sale_number' => 0, 'is_refund' => 0,
            ];
            foreach ($fields as $k => $v) {
                begBuyExpect("create:row.{$k}", $row[$k], $v);
            }
            foreach (['pay_time', 'pay_type', 'canceltime', 'real_trans_amt', 'huifu_pay_type'] as $k) {
                begBuyExpect("create:row.{$k}=null", $row[$k], null);
            }
            begBuyExpect('create:surplus=300（新建瞬刻）', $row->surplusSeconds(), 300);

            // ---- B.5 cancelBegBuyOrder ----
            begBuyBind($token, ['order_id' => 0]);
            begBuyAssert('cancel:空 order_id', (new BegBuyController())->cancelBegBuyOrder(), 0, '订单ID不能为空');
            begBuyBind($token, ['order_id' => 99999]);
            begBuyAssert('cancel:不存在', (new BegBuyController())->cancelBegBuyOrder(), 0, '订单不存在');
            begBuyBind($token, ['order_id' => 76]);
            begBuyAssert('cancel:已终态(76 自动过期)', (new BegBuyController())->cancelBegBuyOrder(), 0, '订单不存在');

            begBuyBind($token, ['collection_id' => 162, 'price' => '20000.00', 'number' => 1, 'pay_way' => 'huifu']);
            $newId = (int) (new BegBuyController())->createBegBuyOrder()->getData()['data']['order_id'];
            begBuyBind($token, ['order_id' => $newId]);
            begBuyAssert('cancel:成功', (new BegBuyController())->cancelBegBuyOrder(), 1, '取消成功');
            $row2 = BegBuyOrder::find($newId);
            begBuyExpect('cancel:status=4', $row2['order_status'], 4);
            begBuyExpect('cancel:canceltime 留 null（手动取消语义）', $row2['canceltime'], null);
            begBuyExpect('cancel:updatetime 不低于 createtime', $row2['updatetime'] >= $row2['createtime'], true);
            begBuyBind($token, ['order_id' => $newId]);
            begBuyAssert('cancel:重复取消', (new BegBuyController())->cancelBegBuyOrder(), 0, '订单不存在');

            // ---- B.6 IDOR：第二个用户碰 16797 的单 ----
            $otherId = (int) Db::name('user')->insertGetId([
                'username' => 'idor_contract_test', 'nickname' => 'IDOR契约测试',
                'status' => 'normal', 'createtime' => time(), 'updatetime' => time(),
            ]);
            $token2 = begBuyTempToken($otherId);
            begBuyBind($token2, ['order_id' => 77]);
            begBuyAssert('IDOR:info 77', (new BegBuyController())->begBuyOrderInfo(), 0, '订单不存在');
            begBuyBind($token2, ['order_id' => 77]);
            begBuyAssert('IDOR:cancel 77', (new BegBuyController())->cancelBegBuyOrder(), 0, '订单不存在');
        } finally {
            Db::rollback();
            request()->withPost([]);
            request()->withGet([]);
        }
    }
}
