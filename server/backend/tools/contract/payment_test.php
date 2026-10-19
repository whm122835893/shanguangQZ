<?php
declare(strict_types=1);

/**
 * 支付闭环 TDD 契约测试 —— SettlementService / WalletGateway / PayNotify
 *
 * 背景（PaymentGateway.php 头注释记录的资损事故）：
 *   此前 doPay/batchDoPay 直接伪造 pay_no='MOCKPAY...' 假成功；
 *   修复后网关未接入时 fail-closed 拒绝。但即便真实通道确认收款，
 *   落地也只有「改订单状态」一件事 —— 无资金流、无回调、无幂等结算。
 * 本套用例锁定支付闭环的四条红线：
 *   1. 充值结算：有效回调 → recharge_order 置已入账 + WalletService::credit 落账本
 *   2. 结算幂等：同一回调重放 → 返回成功但**零副作用**（不双入账）
 *   3. 金额校验：回调金额 ≠ 单据金额 → 拒绝，零副作用
 *   4. 钱包支付通道：余额充足 → 扣款成功；不足 → 明确拒绝零副作用；
 *      渠道未配置的 pay_way 依旧 NullGateway fail-closed
 *
 * 用法：
 *   php tools/contract/payment_test.php
 *
 * 前置：12-payment.sql 已导入（recharge_order 表 / artel_order.pay_no / payment_channel.notify_secret）。
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\Gateways;
use app\service\PaymentException;
use app\service\PayNotify;
use app\service\SettlementService;
use app\service\WalletService;
use think\facade\Db;

const MARK_OK   = '[OK]  ';
const MARK_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function assertSame(string $label, $expected, $actual): bool
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo MARK_OK . $label . "\n";
        $passed++;
        return true;
    }
    echo MARK_FAIL . $label . "\n";
    echo '       期望: ' . var_export($expected, true) . "\n";
    echo '       实际: ' . var_export($actual, true) . "\n";
    $failed++;
    return false;
}

function assertThrows(string $label, callable $fn, string $msgContains = ''): ?PaymentException
{
    global $passed, $failed;
    try {
        $fn();
    } catch (PaymentException $e) {
        if ($msgContains !== '' && !str_contains($e->getMessage(), $msgContains)) {
            echo MARK_FAIL . $label . "（异常文案不含「{$msgContains}」: {$e->getMessage()}）\n";
            $failed++;
            return null;
        }
        echo MARK_OK . $label . "\n";
        $passed++;
        return $e;
    } catch (\Throwable $e) {
        echo MARK_FAIL . $label . "（抛了错误类型 " . get_class($e) . ": {$e->getMessage()}）\n";
        $failed++;
        return null;
    }
    echo MARK_FAIL . $label . "（预期抛 PaymentException，实际未抛）\n";
    $failed++;
    return null;
}

$ids = ['users' => [], 'recharge' => [], 'orders' => [], 'mg' => []];

function cleanup(): void
{
    global $ids;
    Db::name('wallet_transaction')->whereIn('user_id', $ids['users'])->delete();
    Db::name('user_token')->whereIn('user_id', $ids['users'])->delete();
    Db::name('recharge_order')->whereIn('id', $ids['recharge'])->delete();
    Db::name('artel_order')->whereIn('id', $ids['orders'])->delete();
    Db::name('market_goods')->whereIn('id', $ids['mg'])->delete();
    Db::name('payment_channel')->where('code', 'wallet')->delete();
    Db::name('user')->whereIn('id', $ids['users'])->delete();
}

register_shutdown_function(static function () {
    try {
        cleanup();
    } catch (\Throwable $e) {
        echo '[FAIL] 清理失败: ' . $e->getMessage() . "\n";
    }
});

/** 建测试用户，预置钱包余额（走 WalletService 基线入账，保证恒等式成立） */
function mkUser(string $tag, string $initMoney = '0.00'): int
{
    global $ids;
    $uid = (int) Db::name('user')->insertGetId([
        'username'     => 'pay_t_' . $tag,
        'nickname'     => '支付TDD-' . $tag,
        'mobile'       => '195' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'inviter_code' => 'P' . $tag . random_int(100, 999),
        'money'        => $initMoney,
        'status'       => 'normal',
        'jointime'     => time(),
        'createtime'   => time(),
        'updatetime'   => time(),
    ]);
    $ids['users'][] = $uid;
    if (bccomp($initMoney, '0', 2) > 0) {
        Db::name('wallet_transaction')->insert([
            'user_id' => $uid, 'direction' => 1, 'amount' => $initMoney,
            'balance_before' => '0.00', 'balance_after' => $initMoney,
            'biz_type' => 'migrate_baseline', 'biz_id' => 'user_' . $uid,
            'remark' => 'TDD 基线', 'createtime' => time(),
        ]);
    }
    return $uid;
}

function balanceOf(int $uid): string
{
    return (string) Db::name('user')->where('id', $uid)->value('money');
}

echo "支付闭环 TDD 契约测试\n";
echo str_repeat('=', 64) . "\n";

$buyer  = mkUser('b' . random_int(100, 999), '100.00');
$seller = mkUser('s' . random_int(100, 999), '0.00');
$rcUser = mkUser('r' . random_int(100, 999), '0.00');

/* ========== 1. 充值结算：回调 → 入账 + 落账本 ========== */
$rcSn   = 'RC' . date('YmdHis') . random_int(100000, 999999);
$rcId   = (int) Db::name('recharge_order')->insertGetId([
    'order_sn' => $rcSn, 'user_id' => $rcUser, 'amount' => '50.00',
    'status' => 1, 'pay_way' => 'huifu', 'createtime' => time(), 'updatetime' => time(),
]);
$ids['recharge'][] = $rcId;

SettlementService::settleRecharge($rcSn, 'PAY-NO-001', '50.00', 'huifu');
$rc = (array) Db::name('recharge_order')->where('id', $rcId)->find();
assertSame('充值单置为已入账', 2, (int) ($rc['status'] ?? 0));
assertSame('充值单记录 pay_no', 'PAY-NO-001', (string) ($rc['pay_no'] ?? ''));
assertSame('钱包余额到账', '50.00', balanceOf($rcUser));
$inv = WalletService::verifyInvariant($rcUser);
assertSame('充值入账后恒等式成立', true, (bool) ($inv['ok'] ?? false));

/* ========== 2. 结算幂等：重放零副作用 ========== */
SettlementService::settleRecharge($rcSn, 'PAY-NO-001', '50.00', 'huifu');
assertSame('重放后余额不变（不双入账）', '50.00', balanceOf($rcUser));
// 零余额用户无基线行（mkUser 仅在 initMoney>0 时灌基线），故只有入账 1 行
assertSame('重放后流水仍只有入账 1 行', 1, (int) Db::name('wallet_transaction')->where('user_id', $rcUser)->count());

/* ========== 3. 金额校验与未知单号 ========== */
assertThrows('金额不一致拒绝', fn () => SettlementService::settleRecharge($rcSn, 'PAY-NO-002', '999.00', 'huifu'), '金额');
assertSame('金额拒绝后余额不变', '50.00', balanceOf($rcUser));
assertThrows('未知充值单号拒绝', fn () => SettlementService::settleRecharge('RC-UNKNOWN', 'P', '1.00', 'huifu'), '不存在');

/* ========== 4. 钱包支付通道 ========== */
Db::name('payment_channel')->insert([
    'code' => 'wallet', 'name' => '钱包余额', 'is_open' => 1,
    'notify_secret' => 'tdd-wallet-secret', 'sort' => 0, 'status' => 1,
    'createtime' => time(), 'updatetime' => time(),
]);
assertSame('已配置渠道返回 WalletGateway', true, Gateways::make('wallet') instanceof \app\service\WalletGateway);
assertSame('未配置渠道依旧 NullGateway', true, Gateways::make('huifu') instanceof \app\service\NullGateway);

$mgId = (int) Db::name('market_goods')->insertGetId([
    'user_id' => $seller, 'collection_id' => 1, 'price' => '30.50',
    'num' => 1, 'sold_num' => 0, 'status' => 1,
    'createtime' => time(), 'updatetime' => time(),
]);
$ids['mg'][] = $mgId;
$orderSn = date('YmdHis') . '57' . random_int(100000000, 999999999);
$orderId = (int) Db::name('artel_order')->insertGetId([
    'order_sn' => $orderSn, 'user_id' => $buyer, 'artel_order' => 'mh_secondary_market_order',
    'order_type' => '2', 'order_status' => 1, 'total_price' => '30.50', 'num' => '1',
    'goods_id' => $mgId, 'goods_type' => 2, 'goods_name' => 'TDD藏品', 'goods_cover_image' => '',
    'series_name' => '', 'crystal_type' => 'crystal_no', 'crystal_number' => '0',
    'uc_ids' => null, 'collection_code' => '', 'createtime' => time(), 'updatetime' => time(),
]);
$ids['orders'][] = $orderId;

$order = (array) Db::name('artel_order')->where('id', $orderId)->find();
$result = Gateways::make('wallet')->charge($order, 'wallet');
assertSame('余额充足支付成功', true, $result->paid);
assertSame('支付后买家余额扣减', '69.50', balanceOf($buyer));
assertSame('支付流水 biz_type=order_pay', 'order_pay', (string) Db::name('wallet_transaction')->where('user_id', $buyer)->where('biz_type', 'order_pay')->value('biz_type'));

$result2 = Gateways::make('wallet')->charge($order, 'wallet');
assertSame('重复支付被拒', false, $result2->paid);
assertSame('重复支付余额不变', '69.50', balanceOf($buyer));

/* ========== 5. 订单结算（回调驱动）：买家入藏、卖家入账 ========== */
$payNo = 'WAL' . random_int(100000, 999999);
SettlementService::settleOrder($orderSn, $payNo, 'wallet');
$order = (array) Db::name('artel_order')->where('id', $orderId)->find();
assertSame('订单置为已支付', true, (int) ($order['pay_time'] ?? 0) > 0);
assertSame('卖家余额入账', '30.50', balanceOf($seller));
$mg = (array) Db::name('market_goods')->where('id', $mgId)->find();
assertSame('挂单售出 sold_num+1', 1, (int) ($mg['sold_num'] ?? 0));
assertSame('挂单余量归零', 0, (int) ($mg['num'] ?? -1));
assertSame('售罄挂单置为已售', 2, (int) ($mg['status'] ?? 0));

// 同 pay_no 重放 = 幂等成功，零副作用
SettlementService::settleOrder($orderSn, $payNo, 'wallet');
assertSame('订单结算同号重放卖家不双入账', '30.50', balanceOf($seller));
// 异 pay_no 重放 = 渠道重复扣款或伪造，必须拒绝
assertThrows(
    '订单结算异号重放拒绝',
    fn () => SettlementService::settleOrder($orderSn, 'WAL-tampered', 'wallet'),
    '流水号不一致'
);
assertSame('异号重放拒绝后卖家余额不变', '30.50', balanceOf($seller));

/* ========== 6. 回调签名 ========== */
$payload = ['order_sn' => 'RC-1', 'pay_no' => 'P-1', 'amount' => '10.00'];
$sign    = PayNotify::sign($payload, 'tdd-wallet-secret');
assertSame('签名可复现', true, hash_equals($sign, PayNotify::sign($payload, 'tdd-wallet-secret')));
assertSame('正确签名通过', true, PayNotify::verify(['order_sn' => 'RC-1', 'pay_no' => 'P-1', 'amount' => '10.00', 'sign' => $sign], 'tdd-wallet-secret'));
assertSame('篡改金额签名失败', false, PayNotify::verify(['order_sn' => 'RC-1', 'pay_no' => 'P-1', 'amount' => '99.00', 'sign' => $sign], 'tdd-wallet-secret'));
assertSame('错误密钥签名失败', false, PayNotify::verify(['order_sn' => 'RC-1', 'pay_no' => 'P-1', 'amount' => '10.00', 'sign' => $sign], 'other-secret'));

echo str_repeat('=', 64) . "\n";
echo $failed === 0 ? "全部通过（{$passed} 项断言）\n" : "失败 {$failed} 项 / 通过 {$passed} 项\n";
exit($failed === 0 ? 0 : 1);
