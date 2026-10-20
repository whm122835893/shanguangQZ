<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第一批：认证/RBAC/JWT
 *
 * 锁定契约（司南后台提示词 v3.1 §1.2 §五 §8.1 §十）：
 *   1. 登录：Argon2id、用户枚举防护、失败锁定、2FA 可选
 *   2. JWT：Bearer、2h access、refresh 旋转一次性
 *   3. RBAC：role=1 直通；role=2 按权限码放行/拒绝（403）
 *   4. 响应信封 {code, data, message}，code===200 成功
 *   5. 高风险密码验证：错 5 次锁 15 分钟，写 security_event
 *
 * 用法：php tools/contract/admin_v1_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\JwtService;
use app\service\admin\Totp;
use think\facade\Db;

const MARK_OK   = '[OK]  ';
const MARK_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        echo MARK_OK . $label . "\n";
        $passed++;
        return;
    }
    echo MARK_FAIL . $label . "\n";
    if ($detail !== '') {
        echo '       ' . $detail . "\n";
    }
    $failed++;
}

/** 捕获 AdminException 返回 [code, message]；非异常返回 [0, ''] */
function guardCode(callable $fn): array
{
    try {
        $fn();
        return [200, ''];
    } catch (AdminException $e) {
        return [$e->httpCode(), $e->getMessage()];
    }
}

/* ==================== fixtures ==================== */

$sfx = (string) random_int(100000, 999999);
$now = time();
$pwd = 'S3cret-V1-#42';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('v1_super', 1);
$operId  = $mkAdmin('v1_oper', 2);   // 运营：有 collectible:*，无 wallet:export
$wrong   = 'wrong-' . $sfx;

$app->request->withGet([]);
$app->request->withPost([]);

try {
    /* ========== 1. 登录 ========== */

    // 错误密码 → 400，文案统一（防枚举）
    $app->request->withPost(['username' => 'v1_super_' . $sfx, 'password' => 'definitely-wrong']);
    [$code, $msg] = guardCode(fn () => (new \app\controller\admin\v1\AuthController())->login());
    check('错误密码 → 400', $code === 400, "got $code $msg");
    check('错误文案统一「账号或密码错误」', $msg === '账号或密码错误', $msg);

    // 不存在的账号 → 同文案
    $app->request->withPost(['username' => 'no_such_admin_' . $sfx, 'password' => 'whatever-1']);
    [$code2, $msg2] = guardCode(fn () => (new \app\controller\admin\v1\AuthController())->login());
    check('不存在账号 → 同文案防枚举', $code2 === 400 && $msg2 === '账号或密码错误', "got $code2 $msg2");

    // 正确登录 → token + refresh_token + admin
    $app->request->withPost(['username' => 'v1_super_' . $sfx, 'password' => $pwd]);
    $body = (new \app\controller\admin\v1\AuthController())->login()->getData();
    check('登录 code=200', (int) ($body['code'] ?? 0) === 200, var_export($body, true));
    $token   = (string) ($body['data']['token'] ?? '');
    $refresh = (string) ($body['data']['refresh_token'] ?? '');
    check('下发 token/refresh_token', $token !== '' && $refresh !== '');
    check('admin.role=1', (int) ($body['data']['admin']['role'] ?? 0) === 1);
    $claims = JwtService::decode($token);
    check('JWT 可解码且 sub=超管', $claims !== null && (int) ($claims['sub'] ?? 0) === $superId);
    check('JWT typ=access、2h 内过期', ($claims['typ'] ?? '') === 'access' && (int) $claims['exp'] - (int) $claims['iat'] <= 7200);

    /* ========== 2. /auth/me + RBAC ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $token])->withPost([]);
    $me = (new \app\controller\admin\v1\AuthController())->me()->getData();
    check('/auth/me code=200', (int) ($me['code'] ?? 0) === 200, var_export($me, true));
    $perms = $me['data']['permissions'] ?? [];
    check('超管权限码全量（含 user:list 与 platform:cleanup）',
        in_array('user:list', $perms, true) && in_array('platform:cleanup', $perms, true));

    // 垃圾 JWT → 401
    $app->request->withHeader(['authorization' => 'Bearer garbage-' . $sfx]);
    [$code3] = guardCode(fn () => Guard::require('user:list'));
    check('垃圾 JWT → 401', $code3 === 401, "got $code3");
    [$code3b] = guardCode(fn () => Guard::require('user:list'));
    check('垃圾 JWT → admin()=null', Guard::admin() === null && $code3b === 401);

    // 运营 role=2：有 collectible:list，无 wallet:export
    $app->request->withPost(['username' => 'v1_oper_' . $sfx, 'password' => $pwd]);
    $operLogin = (new \app\controller\admin\v1\AuthController())->login()->getData();
    $operToken = (string) ($operLogin['data']['token'] ?? '');
    $app->request->withHeader(['authorization' => 'Bearer ' . $operToken]);
    check('运营有 collectible:list', Guard::hasPerm($operId, 'collectible:list'));
    check('运营无 wallet:export', !Guard::hasPerm($operId, 'wallet:export'));
    [$code4, $msg4] = guardCode(fn () => Guard::require('wallet:export'));
    check('运营请求 wallet:export → 403', $code4 === 403, "got $code4 $msg4");
    [$code5] = guardCode(fn () => Guard::require('collectible:list'));
    check('运营请求 collectible:list → 通过', $code5 === 200, "got $code5");

    /* ========== 3. refresh 旋转 ========== */

    $app->request->withPost(['refresh_token' => $refresh]);
    $r1 = (new \app\controller\admin\v1\AuthController())->refresh()->getData();
    check('refresh 换新对 code=200', (int) ($r1['code'] ?? 0) === 200, var_export($r1, true));
    $refresh2 = (string) ($r1['data']['refresh_token'] ?? '');
    check('refresh 旋转出新值', $refresh2 !== '' && $refresh2 !== $refresh);

    // 旧 refresh 已被吊销 → 401
    $app->request->withPost(['refresh_token' => $refresh]);
    [$code6] = guardCode(fn () => (new \app\controller\admin\v1\AuthController())->refresh());
    check('旧 refresh 复用 → 401', $code6 === 401, "got $code6");

    /* ========== 4. 登出吊销 ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $token])
        ->withPost(['refresh_token' => $refresh2]);
    $out = (new \app\controller\admin\v1\AuthController())->logout()->getData();
    check('登出 code=200', (int) ($out['code'] ?? 0) === 200);
    $app->request->withPost(['refresh_token' => $refresh2]);
    [$code7] = guardCode(fn () => (new \app\controller\admin\v1\AuthController())->refresh());
    check('登出后 refresh 失效 → 401', $code7 === 401, "got $code7");

    /* ========== 5. 高风险密码验证 + 锁定 ========== */

    $guardAdmin = Db::name('admin_user')->where('id', $superId)->find();
    unset($guardAdmin['password_hash'], $guardAdmin['token_hash'], $guardAdmin['refresh_token_hash'], $guardAdmin['twofa_secret']);
    // 归还正常态
    Db::name('admin_user')->where('id', $superId)->update(['login_fail_count' => 0, 'locked_until' => 0]);

    $app->request->withHeader(['authorization' => 'Bearer ' . $r1['data']['token'] ?? '']);
    $app->request->withPost(['password' => 'nope-wrong']);
    [$code8, $msg8] = guardCode(function () use ($superId) {
        $admin = \think\facade\Db::name('admin_user')->where('id', $superId)->find();
        unset($admin['password_hash']);
        Guard::verifyPassword($admin, 'nope-wrong');
    });
    check('密码错误 → 400', $code8 === 400, "got $code8 $msg8");

    for ($i = 0; $i < 4; $i++) {
        try {
            $admin = Db::name('admin_user')->where('id', $superId)->find();
            unset($admin['password_hash']);
            Guard::verifyPassword($admin, 'nope-wrong');
        } catch (AdminException) {
            // 预期失败
        }
    }
    $row = Db::name('admin_user')->where('id', $superId)->find();
    check('连续错 5 次 → locked_until 生效', (int) $row['locked_until'] > time(), 'locked_until=' . $row['locked_until']);
    $secCount = Db::name('security_event')->where('admin_id', $superId)->where('event_type', 5)->count();
    check('失败写 security_event（≥1）', (int) $secCount >= 1);

    // 锁定期内即使密码正确也 403
    [$code9, $msg9] = guardCode(function () use ($superId) {
        $admin = \think\facade\Db::name('admin_user')->where('id', $superId)->find();
        unset($admin['password_hash']);
        Guard::verifyPassword($admin, 'S3cret-V1-#42');
    });
    check('锁定期内 → 403', $code9 === 403, "got $code9 $msg9");

    // 解锁后正确密码通过
    Db::name('admin_user')->where('id', $superId)->update(['locked_until' => 0, 'login_fail_count' => 0]);
    [$code10] = guardCode(function () use ($superId) {
        $admin = \think\facade\Db::name('admin_user')->where('id', $superId)->find();
        unset($admin['password_hash']);
        Guard::verifyPassword($admin, 'S3cret-V1-#42');
    });
    check('解锁后正确密码通过', $code10 === 200, "got $code10");

    /* ========== 6. TOTP 往返 ========== */

    $secret = Totp::generateSecret();
    $code6v = Totp::code($secret);
    check('TOTP 生成/校验一致', Totp::verify($secret, $code6v));
    check('TOTP 错码拒绝', !Totp::verify($secret, '000000') || $code6v === '000000');
    check('TOTP 格式 6 位', preg_match('/^\d{6}$/', $code6v) === 1);
    $url = Totp::otpauthUrl($secret, 'tester');
    check('otpauth URL 含 secret', str_contains($url, 'secret=' . $secret));

    /* ========== 7. 修改密码 ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $r1['data']['token'] ?? ''])
        ->withPost(['old_password' => $pwd, 'new_password' => 'NewPass-#2026x']);
    $body = (new \app\controller\admin\v1\AuthController())->password()->getData();
    check('修改密码 code=200', (int) ($body['code'] ?? 0) === 200, var_export($body, true));
    $row = Db::name('admin_user')->where('id', $superId)->find();
    check('新密码生效（Argon2id 可验）', password_verify('NewPass-#2026x', (string) $row['password_hash']));
    // 还原
    Db::name('admin_user')->where('id', $superId)->update([
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID), 'updatetime' => time(),
    ]);
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 auth: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
