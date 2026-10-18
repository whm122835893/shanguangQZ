<?php
declare(strict_types=1);

/**
 * 订单域契约用例：orderList / batchPayOrder / createVipOrder
 *
 * 三个端点三种信封（msg 都叫「订单信息」/「暂无订单」/「下单成功」），
 * 是「同实体跨端点契约不同」的实证组，改动任一信封前先读另两个。
 */

use app\model\ArtelOrder;
use app\model\VipOrder;
use app\service\PayFormatter;
use app\service\TradeFormatter;
use think\facade\Db;

if (!function_exists('runOrderCases')) {
    /**
     * @global array $results 用例结果行（run.php 注入）
     * @global int   $failed  失败计数（run.php 注入）
     */
    function runOrderCases(): void
    {
        global $results, $failed;

        // ---- 10. orderList（原站样本 2 条，逐条按 id 匹配比对）----
        try {
            $sampleRows = raw('order_order_orderlist')['data']['data'] ?? [];
            if ($sampleRows === []) {
                $results[] = 'orderList          [跳过]（原站样本 total=0）';
                $failed++;
            } else {
                $n = 0;
                foreach ($sampleRows as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    $rec = $id > 0 ? ArtelOrder::find($id) : null;
                    if ($rec === null) {
                        $results[] = sprintf('  orderList id=%d [缺失] 本地缺该订单', $id);
                        $failed++;
                        continue;
                    }
                    $n++;
                    check('orderList#' . $id, 'order_order_orderlist',
                        TradeFormatter::order($rec->toArray()), 'data.data', [], $id, '订单列表');
                }
                if ($n === 0) {
                    $results[] = 'orderList          [跳过]（本地无对应订单）';
                }
            }
        } catch (\Throwable $e) {
            $results[] = 'orderList          异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 11. order/order/batchPayOrder（批量下单列表：实测空态 2 字段）----
        //
        // 🔴 batch_order_id 是 int 0（不是 "0"），data 是 []——与用例 12 的
        //    CreateVipOrder 全 string 形态相反，两个端点就在同一文件里，
        //    「统一类型」的冲动必须靠这两个对置用例压住。
        //    恒空态是复探实证（原站账号挂着 2 市场单 + 2 VIP 待付单时仍空），
        //    不是偷懒占位；出参与 Controller 共用 PayFormatter::noBatchOrder()。
        try {
            check('batchPayOrder', 'order_order_batchpayorder',
                PayFormatter::noBatchOrder(), 'data', [], 0, '暂无订单');
        } catch (\Throwable $e) {
            $results[] = 'batchPayOrder       异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 12. order/pay/CreateVipOrder（VIP 下单：3 字段全 string）----
        //
        // 🔴 本用例会真写库——端点语义就是落单，只测形状不落库等于没测。
        //    用事务包住并回滚，避免每次跑回归都往 vip_order 堆一行：
        //    堆行会推进 auto_increment，让手工验证时的 order_id 对不上号。
        //
        // 🔴 order_sn / order_id 是运行时字段（每单一号），排除值；
        //    但 check() 的字段集比对保证两个键不丢，time="300" 参与值比对
        //    （实测两次采样恒定，有人改倒计时立刻红）。
        try {
            Db::startTrans();
            try {
                $order = VipOrder::createForUser(16797);
                check('createVipOrder', 'order_pay_createviporder',
                    PayFormatter::vipOrderCreated($order), 'data',
                    ['order_sn', 'order_id'], 0, '下单成功');
            } finally {
                Db::rollback();
            }
        } catch (\Throwable $e) {
            $results[] = 'createVipOrder      异常：' . $e->getMessage();
            $failed++;
        }
    }
}
