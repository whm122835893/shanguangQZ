<?php
declare(strict_types=1);

namespace app\service;

use think\db\exception\PDOException;
use think\facade\Db;

/**
 * SettlementService —— 支付结算唯一入口（回调确认收款后的落地动作）
 *
 * 🔴 为什么必须有这一层：
 *   PaymentGateway 建立后，「通道确认收款」到「业务落地」之间仍是断的：
 *   doPay 即使收到 paid=true 也只改订单状态，充值更是只回显假单号。
 *   本类把两类单据的结算收口成一个幂等事务入口：
 *
 *     充值单  settleRecharge  → recharge_order 置已入账 + WalletService::credit
 *     订单    settleOrder     → artel_order 置已支付 + 卖家入账 + 挂单核销
 *
 * 四条红线（tools/contract/payment_test.php 逐条锁定）：
 *   1. 金额必须与单据一致（bccomp 两位小数），不符即拒绝
 *   2. 幂等：已结算单重放返回成功，但**零副作用**（绝不双入账）
 *   3. 行锁（SELECT ... FOR UPDATE）串行化同单并发回调
 *   4. 资金流必须走 WalletService 账本，禁止直改 user.money
 */
final class SettlementService
{
    public const RECHARGE_PAID = 2;

    /** 充值结算：order_sn + 通道流水号 + 回调金额（字符串，两位小数） */
    public static function settleRecharge(string $orderSn, string $payNo, string $amount, string $channel): void
    {
        Db::transaction(static function () use ($orderSn, $payNo, $amount, $channel) {
            // 行锁取单：并发重放在此串行化
            $rows = Db::query(
                'SELECT id, user_id, amount, status, pay_no FROM recharge_order WHERE order_sn = ? FOR UPDATE',
                [$orderSn]
            );
            if (!isset($rows[0])) {
                throw new PaymentException('充值单不存在');
            }
            $rc = $rows[0];

            // 金额校验必须在幂等短路**之前**：伪造重放（金额不符）绝不能被静默接受
            if (bccomp((string) $rc['amount'], $amount, 2) !== 0) {
                throw new PaymentException('回调金额与充值单不符');
            }

            if ((int) $rc['status'] === self::RECHARGE_PAID) {
                // 幂等重放：pay_no 一致才放行；不一致 = 渠道重复扣款或伪造，必须暴露
                if ((string) $rc['pay_no'] !== $payNo) {
                    throw new PaymentException('充值单已结算且支付流水号不一致');
                }
                return;
            }

            Db::name('recharge_order')->where('id', $rc['id'])->update([
                'status'     => self::RECHARGE_PAID,
                'pay_no'     => $payNo,
                'pay_way'    => $channel,
                'pay_time'   => time(),
                'updatetime' => time(),
            ]);

            // 资金流走账本：biz 键含 order_sn，WalletService 层还有一重幂等
            WalletService::credit(
                (int) $rc['user_id'],
                (string) $rc['amount'],
                'recharge',
                $orderSn,
                '充值入账（' . $channel . '）'
            );
        });
    }

    /** 订单结算：市场购买单支付成功后的业务落地 */
    public static function settleOrder(string $orderSn, string $payNo, string $channel): void
    {
        Db::transaction(static function () use ($orderSn, $payNo, $channel) {
            $rows = Db::query(
                'SELECT id, user_id, total_price, goods_id, pay_time, pay_no FROM artel_order WHERE order_sn = ? FOR UPDATE',
                [$orderSn]
            );
            if (!isset($rows[0])) {
                throw new PaymentException('订单不存在');
            }
            $order = $rows[0];

            if ((int) $order['pay_time'] > 0) {
                // 幂等重放：pay_no 一致才放行；不一致 = 重复支付或伪造，必须暴露
                if ((string) $order['pay_no'] !== $payNo) {
                    throw new PaymentException('订单已结算且支付流水号不一致');
                }
                return;
            }

            Db::name('artel_order')->where('id', $order['id'])->update([
                'pay_time'   => time(),
                'pay_channel' => $channel,
                'pay_no'     => $payNo,
                'updatetime' => time(),
            ]);

            // 挂单核销 + 卖家入账（市场购买单：goods_id 即 market_goods.id）
            $mgRows = Db::query(
                'SELECT id, user_id, price, num, sold_num FROM market_goods WHERE id = ? FOR UPDATE',
                [(int) $order['goods_id']]
            );
            if (isset($mgRows[0])) {
                $mg = $mgRows[0];
                Db::name('market_goods')->where('id', $mg['id'])->update([
                    'sold_num'   => (int) $mg['sold_num'] + 1,
                    'num'        => max(0, (int) $mg['num'] - 1),
                    'status'     => max(0, (int) $mg['num'] - 1) === 0 ? 2 : (int) $mg['status'], // 售罄置已售
                    'updatetime' => time(),
                ]);

                $sellerProceed = (string) $mg['price'];
                WalletService::credit(
                    (int) $mg['user_id'],
                    $sellerProceed,
                    'order_settle',
                    $orderSn,
                    '市场售出结算（' . $channel . '）'
                );
            }
        });
    }

    /** 回调分发：按单号前缀路由到对应结算（RC = 充值单，其余 = 订单） */
    public static function dispatch(string $orderSn, string $payNo, string $amount, string $channel): void
    {
        if (str_starts_with($orderSn, 'RC')) {
            self::settleRecharge($orderSn, $payNo, $amount, $channel);
            return;
        }
        self::settleOrder($orderSn, $payNo, $channel);
    }
}
