<?php
declare(strict_types=1);

/**
 * 钱包账本 TDD 契约测试 —— WalletService 行为级用例（不依赖原站样本）
 *
 * 背景：全库 0 外键、无流水表，user.money 是裸加减，出了错无法审计。
 * 本套用例锁定 WalletService 的四条红线：
 *   1. 每一笔资金变动必须落 wallet_transaction 流水（含 balance_before/after 快照）
 *   2. 借记余额不足必须拒绝（fail-closed），且**不落任何行**
 *   3. 同一 (biz_type, biz_id) 幂等：重复入账必须被拒（防回调重放双入账）
 *   4. 资金恒等式：user.money == SUM(收入 - 支出)，可随时校验、篡改可发现
 *
 * 用法：
 *   php tools/contract/wallet_test.php
 *
 * 前置：10-wallet.sql 已导入（wallet_transaction 表存在）。
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 *
 * 输出约定：[OK]/[FAIL] 文本标记（CLI 禁 emoji，见 pitfalls gate/emoji-in-cli-console-output）
 */

require __DIR__ . '/../../vendor/autoload.php';

// 启动 ThinkPHP（Db 门面依赖应用初始化，否则报 Undefined db config）
$app = new think\App();
$app->initialize();

use app\service\WalletException;
use app\service\WalletService;
use think\facade\Db;

const MARK_OK = '[OK]  ';
const MARK_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

/** 断言助手：相同则 [OK]，否则 [FAIL] 并附差异 */
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

/** 断言抛出 WalletException，并返回异常对象；未抛/抛错类型均计失败 */
function assertThrows(string $label, callable $fn): ?WalletException
{
    global $passed, $failed;
    try {
        $fn();
    } catch (WalletException $e) {
        echo MARK_OK . $label . "\n";
        $passed++;
        return $e;
    } catch (\Throwable $e) {
        echo MARK_FAIL . $label . "（抛了错误类型 " . get_class($e) . ": {$e->getMessage()}）\n";
        $failed++;
        return null;
    }
    echo MARK_FAIL . $label . "（预期抛 WalletException，实际未抛）\n";
    $failed++;
    return null;
}

/** 读用户余额（DB 原始值） */
function balanceOf(int $userId): string
{
    $v = Db::name('user')->where('id', $userId)->value('money');
    return (string) $v;
}

/** 读该用户流水行数 */
function txCountOf(int $userId): int
{
    return (int) Db::name('wallet_transaction')->where('user_id', $userId)->count();
}

echo "钱包账本 TDD 契约测试\n";
echo str_repeat('=', 64) . "\n";

/* ---------- 准备：独立测试用户，结束按 id 精确清理 ---------- */
$testMobile = '199' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
$userId = (int) Db::name('user')->insertGetId([
    'username'  => 'wallet_t_' . random_int(1000, 9999),
    'nickname'  => '钱包TDD测试',
    'mobile'    => $testMobile,
    // 🔴 uk_inviter_code 是全局唯一键且空串只容一个用户，测试必须显式带唯一码
    'inviter_code' => 'T' . random_int(100000, 999999),
    'status'    => 'normal',
    'jointime'  => time(),
    'createtime' => time(),
    'updatetime' => time(),
]);
if ($userId <= 0) {
    fwrite(STDERR, "[FAIL] 无法创建测试用户\n");
    exit(1);
}

try {
    /* ========== 1. 贷记：落流水 + 快照 + 余额同步 ========== */
    $txId = WalletService::credit($userId, '100.00', 'recharge', 'RC-TEST-1', '测试充值');
    $row = Db::name('wallet_transaction')->where('id', $txId)->find();
    assertSame('credit 返回流水 id', true, $txId > 0);
    assertSame('credit 后余额 = 100.00', '100.00', balanceOf($userId));
    assertSame('credit 流水 direction = 1', 1, (int) ($row['direction'] ?? 0));
    assertSame('credit 流水 amount = 100.00', '100.00', (string) ($row['amount'] ?? ''));
    assertSame('credit 快照 balance_before = 0.00', '0.00', (string) ($row['balance_before'] ?? ''));
    assertSame('credit 快照 balance_after = 100.00', '100.00', (string) ($row['balance_after'] ?? ''));
    assertSame('credit biz_type 回写', 'recharge', (string) ($row['biz_type'] ?? ''));
    assertSame('credit biz_id 回写', 'RC-TEST-1', (string) ($row['biz_id'] ?? ''));

    /* ========== 2. 借记：快照链不断 ========== */
    $txId2 = WalletService::debit($userId, '30.50', 'market_buy', 'MG-TEST-1', '测试购买');
    $row2 = Db::name('wallet_transaction')->where('id', $txId2)->find();
    assertSame('debit 后余额 = 69.50', '69.50', balanceOf($userId));
    assertSame('debit 流水 direction = 2', 2, (int) ($row2['direction'] ?? 0));
    assertSame('debit 快照链 before 接上一笔 after', '100.00', (string) ($row2['balance_before'] ?? ''));
    assertSame('debit 快照链 after = 69.50', '69.50', (string) ($row2['balance_after'] ?? ''));

    /* ========== 3. 余额不足：拒绝且零副作用（fail-closed） ========== */
    $rowsBefore = txCountOf($userId);
    assertThrows(
        'debit 超额抛 WalletException',
        fn () => WalletService::debit($userId, '999.99', 'market_buy', 'MG-TEST-2')
    );
    assertSame('超额拒绝后余额不变', '69.50', balanceOf($userId));
    assertSame('超额拒绝不落流水', $rowsBefore, txCountOf($userId));

    /* ========== 4. 资金恒等式：账实相符 ========== */
    $audit = WalletService::verifyInvariant($userId);
    assertSame('恒等式校验通过', true, (bool) ($audit['ok'] ?? false));
    assertSame('恒等式余额读数', '69.50', (string) ($audit['balance'] ?? ''));
    assertSame('恒等式账本合计', '69.50', (string) ($audit['ledger'] ?? ''));

    /* ========== 5. 篡改可发现：直接改 money 必须红 ========== */
    Db::name('user')->where('id', $userId)->update(['money' => '5.55']);
    $audit2 = WalletService::verifyInvariant($userId);
    assertSame('篡改后恒等式必须失败', false, (bool) ($audit2['ok'] ?? true));
    Db::name('user')->where('id', $userId)->update(['money' => '69.50']);
    $audit3 = WalletService::verifyInvariant($userId);
    assertSame('恢复后恒等式重新通过', true, (bool) ($audit3['ok'] ?? false));

    /* ========== 6. 金额入参防线：非法值一律拒绝且零副作用 ========== */
    $rowsBefore = txCountOf($userId);
    foreach (['0.00', '-1.00', 'abc', '', '1.999'] as $bad) {
        assertThrows(
            "非法金额 '{$bad}' 拒绝",
            fn () => WalletService::credit($userId, $bad, 'recharge', 'RC-BAD')
        );
    }
    assertSame('非法金额不落流水', $rowsBefore, txCountOf($userId));
    assertSame('非法金额不改余额', '69.50', balanceOf($userId));

    /* ========== 7. 幂等红线：同 (biz_type, biz_id) 不得双入账 ========== */
    assertThrows(
        '重复 biz 键二次入账被拒',
        fn () => WalletService::credit($userId, '100.00', 'recharge', 'RC-TEST-1')
    );
    assertSame('幂等拒绝后余额不变', '69.50', balanceOf($userId));
    assertSame('幂等拒绝不落流水', $rowsBefore, txCountOf($userId));

    /* ========== 8. 汇总 ========== */
    echo str_repeat('=', 64) . "\n";
    echo $failed === 0 ? "全部通过（{$passed} 项断言）\n" : "失败 {$failed} 项 / 通过 {$passed} 项\n";
} catch (\Throwable $e) {
    echo MARK_FAIL . '测试执行中断: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    /* 按精确 id 清理自建行（pitfalls db/auto-increment 家族约定） */
    Db::name('wallet_transaction')->where('user_id', $userId)->delete();
    Db::name('user')->where('id', $userId)->delete();
}

exit($failed === 0 ? 0 : 1);
