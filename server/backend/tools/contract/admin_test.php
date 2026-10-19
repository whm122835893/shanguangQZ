<?php
declare(strict_types=1);

/**
 * 管理端 TDD 契约测试 —— AdminAuthService 行为级用例
 *
 * 背景：本项目此前没有管理端，一切运营动作（发 banner / 发公告 /
 *       藏品上下架）只能直接改数据库。P0-4 最小管理端从「能安全登录」开始。
 * 本套用例锁定 AdminAuthService 的四条红线：
 *   1. Argon2id 校验，密码错误文案与「账号不存在」一致（防用户枚举）
 *   2. token 明文只下发一次，库内只存 sha256；有效期 12 小时
 *   3. 禁用管理员即使密码正确也拒绝，且现有 token 立即失效
 *   4. 登出立即吊销 token；垃圾 token verify 返回 null
 *
 * 用法：
 *   php tools/contract/admin_test.php
 *
 * 前置：13-admin.sql 已导入（admin_user 表存在）。
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\AdminAuthException;
use app\service\AdminAuthService;
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

function assertThrows(string $label, callable $fn, string $msgContains = ''): ?AdminAuthException
{
    global $passed, $failed;
    try {
        $fn();
    } catch (AdminAuthException $e) {
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
    echo MARK_FAIL . $label . "（预期抛 AdminAuthException，实际未抛）\n";
    $failed++;
    return null;
}

echo "管理端 TDD 契约测试\n";
echo str_repeat('=', 64) . "\n";

/* ---------- 准备：测试管理员（Argon2id 哈希入库） ---------- */
$username = 'tdd_admin_' . random_int(1000, 9999);
$adminId  = (int) Db::name('admin_user')->insertGetId([
    'username'      => $username,
    'password_hash' => password_hash('S3cret-Admin-#42', PASSWORD_ARGON2ID),
    'status'        => 1,
    'createtime'    => time(),
    'updatetime'    => time(),
]);
try {
    /* ========== 1. 登录成功：token 明文只出现一次，库内只存哈希 ========== */
    $session = AdminAuthService::login($username, 'S3cret-Admin-#42', '127.0.0.1');
    assertSame('登录返回 token（40 hex）', 40, strlen($session['token'] ?? ''));
    assertSame('有效期 = 12 小时', AdminAuthService::TTL, ($session['expiretime'] ?? 0) - time() > 0 ? AdminAuthService::TTL : -1);
    $row = (array) Db::name('admin_user')->where('id', $adminId)->find();
    assertSame('库内存的是 sha256 而非明文', hash('sha256', (string) $session['token']), (string) ($row['token_hash'] ?? ''));
    assertSame('明文绝不落库', false, str_contains((string) ($row['token_hash'] ?? ''), (string) $session['token']));
    assertSame('last_login_time 已记录', true, (int) ($row['last_login_time'] ?? 0) > 0);

    /* ========== 2. verify：有效 token 放行 ========== */
    $admin = AdminAuthService::verify((string) $session['token']);
    assertSame('有效 token 返回管理员', $username, (string) ($admin['username'] ?? ''));
    assertSame('verify 结果不含密码哈希', true, !isset($admin['password_hash']));

    /* ========== 3. 错误密码：文案与「账号不存在」一致 ========== */
    assertThrows('错误密码拒绝', fn () => AdminAuthService::login($username, 'wrong-password', '127.0.0.1'), '账号或密码错误');
    assertThrows('账号不存在同文案', fn () => AdminAuthService::login('no_such_admin_' . random_int(1, 999999), 'x', '127.0.0.1'), '账号或密码错误');

    /* ========== 4. 登出立即吊销 ========== */
    AdminAuthService::logout((string) $session['token']);
    assertSame('登出后 verify 返回 null', null, AdminAuthService::verify((string) $session['token']));

    /* ========== 5. 垃圾 token / 空 token ========== */
    assertSame('垃圾 token 返回 null', null, AdminAuthService::verify('garbage-token-' . random_int(1, 999999)));
    assertSame('空 token 返回 null', null, AdminAuthService::verify(''));

    /* ========== 6. 禁用管理员：拒绝登录 + 存量 token 失效 ========== */
    $s2 = AdminAuthService::login($username, 'S3cret-Admin-#42', '127.0.0.1');
    Db::name('admin_user')->where('id', $adminId)->update(['status' => 0]);
    assertThrows('禁用后登录拒绝', fn () => AdminAuthService::login($username, 'S3cret-Admin-#42', '127.0.0.1'), '禁用');
    assertSame('禁用后存量 token 立即失效', null, AdminAuthService::verify((string) $s2['token']));

    /* ========== 7. token 过期立即失效 ========== */
    Db::name('admin_user')->where('id', $adminId)->update(['status' => 1, 'token_expire' => time() - 10]);
    assertSame('过期 token 返回 null', null, AdminAuthService::verify((string) $s2['token']));

    echo str_repeat('=', 64) . "\n";
    echo $failed === 0 ? "全部通过（{$passed} 项断言）\n" : "失败 {$failed} 项 / 通过 {$passed} 项\n";
} catch (\Throwable $e) {
    echo MARK_FAIL . '测试执行中断: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    Db::name('admin_user')->where('id', $adminId)->delete();
}

exit($failed === 0 ? 0 : 1);
