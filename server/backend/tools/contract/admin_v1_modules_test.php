<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第二批：仪表盘/用户/实名
 *
 * 锁定契约（司南后台提示词 v3.1 §8.2 §8.3 §8.4 §3.7）：
 *   1. dashboard/metrics 字段齐全且类型稳定
 *   2. users 列表手机号脱敏，明文绝不进响应体
 *   3. users/:id/wallet 余额 === 账本 SUM（资金恒等式），金额 string
 *   4. freeze 后 UserToken::verify 立即失效；unfreeze 恢复
 *   5. blacklist 需要 user:blacklist 权限（role=2 运营 → 403）
 *   6. realnames 列表全脱敏；full 需二次密码验证 + 审计落库
 *   7. realname:full 对运营（role=2）→ 403
 *
 * 用法：php tools/contract/admin_v1_modules_test.php
 * 前置：01~15 schema 已导入。
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\model\UserToken;
use app\service\admin\AdminException;
use app\controller\admin\v1\DashboardController;
use app\controller\admin\v1\RealnameController;
use app\controller\admin\v1\UserController;
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

/** 捕获 AdminException 返回 [httpCode, message] */
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
$pwd = 'S3cret-Mod-#42';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('mod_super', 1);
$operId  = $mkAdmin('mod_oper', 2);   // 运营：user:list/detail/freeze，无 user:blacklist / realname:full

$app->request->withGet([]);
$app->request->withPost([]);

$login = static function (int $adminId) use ($app, $pwd): string {
    $row = Db::name('admin_user')->where('id', $adminId)->value('username');
    $app->request->withPost(['username' => $row, 'password' => $pwd]);
    $body = (new \app\controller\admin\v1\AuthController())->login()->getData();
    return (string) ($body['data']['token'] ?? '');
};

$asSuper = null;
$asOper  = null;

try {
    $asSuper = $login($superId);
    $asOper  = $login($operId);
    check('双管理员登录成功', $asSuper !== '' && $asOper !== '');

    // C 端用户 A：钱包恒等式 + token + 实名
    $mobileA = '199' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    $uidA = (int) Db::name('user')->insertGetId([
        'username' => 'mod_a_' . $sfx, 'nickname' => 'MOD_A',
        'mobile' => $mobileA, 'money' => '70.00', 'status' => 'normal',
        'inviter_code' => 'MD' . random_int(10000000, 99999999),
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    Db::name('wallet_transaction')->insert([
        'user_id' => $uidA, 'direction' => 1, 'amount' => '100.00',
        'balance_before' => '0.00', 'balance_after' => '100.00',
        'biz_type' => 'recharge', 'remark' => '入账', 'createtime' => $now,
    ]);
    Db::name('wallet_transaction')->insert([
        'user_id' => $uidA, 'direction' => 2, 'amount' => '30.00',
        'balance_before' => '100.00', 'balance_after' => '70.00',
        'biz_type' => 'buy', 'remark' => '出账', 'createtime' => $now,
    ]);
    $plainToken = 'mod-token-' . $sfx;
    Db::name('user_token')->insert([
        'user_id' => $uidA, 'token' => UserToken::hash($plainToken),
        'device' => 'test', 'ip' => '127.0.0.1',
        'expiretime' => $now + 3600, 'createtime' => $now,
    ]);
    // A 提交实名（走服务：三态落库）；证件号按 GB11643 计算合法校验位
    $weights = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
    $checkMap = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];
    $base17 = '11010119900307' . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
    $sumCk = 0;
    for ($i = 0; $i < 17; $i++) {
        $sumCk += (int) $base17[$i] * $weights[$i];
    }
    $idcardA = $base17 . $checkMap[$sumCk % 11];
    \app\service\RealnameService::submit($uidA, '王小明', $idcardA);

    /* ========== 1. dashboard ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])->withGet([]);
    $metrics = (new DashboardController())->metrics()->getData();
    check('dashboard/metrics code=200', (int) ($metrics['code'] ?? 0) === 200, var_export($metrics, true));
    $m = $metrics['data'] ?? [];
    $needKeys = ['new_users_today', 'sales_today', 'orders_today', 'active_collectibles',
                 'active_listings', 'active_blindboxes'];
    $missing = array_diff($needKeys, array_keys($m));
    check('metrics 六大字段齐全', $missing === [], '缺: ' . implode(',', $missing));
    check('sales_today 金额 string', is_string($m['sales_today'] ?? null));
    check('new_users_today 为 int', is_int($m['new_users_today'] ?? null));

    $finance = (new DashboardController())->finance()->getData();
    check('dashboard/finance code=200', (int) ($finance['code'] ?? 0) === 200, var_export($finance, true));
    check('finance 含 total_recharge/total_fee',
        isset($finance['data']['total_recharge'], $finance['data']['total_fee']));

    /* ========== 2. users 列表脱敏 ========== */

    $app->request->withGet(['page' => '1', 'page_size' => '50', 'phone' => substr($mobileA, 0, 3)]);
    $list = (new UserController())->list()->getData();
    check('users list code=200', (int) ($list['code'] ?? 0) === 200, var_export($list, true));
    $raw = json_encode($list, JSON_UNESCAPED_UNICODE);
    check('users 列表不含明文手机号', !str_contains($raw, $mobileA));
    $rowA = null;
    foreach ($list['data']['list'] ?? [] as $r) {
        if ((int) $r['id'] === $uidA) { $rowA = $r; break; }
    }
    check('按前缀能搜到用户 A', $rowA !== null);
    check('A 手机号已脱敏 199****', isset($rowA['mobile_masked']) && str_contains($rowA['mobile_masked'], '****'),
        var_export($rowA['mobile_masked'] ?? null, true));

    /* ========== 3. users/:id/wallet 恒等式 ========== */

    $app->request->withGet(['id' => (string) $uidA]);
    $wallet = (new UserController())->wallet()->getData();
    check('wallet code=200', (int) ($wallet['code'] ?? 0) === 200, var_export($wallet, true));
    $w = $wallet['data'] ?? [];
    $sumRow = Db::query(
        'SELECT COALESCE(SUM(CASE direction WHEN 1 THEN amount ELSE -amount END),0) AS s FROM wallet_transaction WHERE user_id = ?',
        [$uidA]
    );
    $sum = (string) ($sumRow[0]['s'] ?? '0');
    check('wallet balance === 账本 SUM', bccomp((string) ($w['balance'] ?? '-1'), (string) $sum, 2) === 0,
        'balance=' . ($w['balance'] ?? '?') . ' sum=' . $sum);
    check('wallet 金额 string', is_string($w['balance'] ?? null) && is_string($w['total_recharge'] ?? null));

    /* ========== 4. freeze / unfreeze 踢线 ========== */

    check('冻结前 token 有效', UserToken::verify($plainToken) !== null);
    $app->request->withPost(['reason' => '契约测试冻结']);
    [$fc] = guardCode(fn () => (new UserController())->freeze());
    check('freeze code=200', $fc === 200);
    check('冻结后 token 立即失效', UserToken::verify($plainToken) === null);
    check('user.status=freeze 且会话全吊销',
        Db::name('user')->where('id', $uidA)->value('status') === 'freeze'
        && Db::name('user_token')->where('user_id', $uidA)->count() === 0);

    [$uc] = guardCode(fn () => (new UserController())->unfreeze());
    check('unfreeze code=200', $uc === 200);
    check('user.status=normal', Db::name('user')->where('id', $uidA)->value('status') === 'normal');
    // 会话已物理删除 → 重新登录签发新 token 可用
    $newToken = 'mod-token-new-' . $sfx;
    Db::name('user_token')->insert([
        'user_id' => $uidA, 'token' => UserToken::hash($newToken),
        'device' => 'test', 'ip' => '127.0.0.1',
        'expiretime' => $now + 3600, 'createtime' => $now,
    ]);
    check('解冻后新 token 可用', UserToken::verify($newToken) !== null);

    /* ========== 5. blacklist RBAC ========== */

    // 运营无 user:blacklist → 403
    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper])
        ->withGet(['id' => (string) $uidA])->withPost(['reason' => 'x']);
    [$bc] = guardCode(fn () => (new UserController())->addBlacklist());
    check('运营加黑名单 → 403', $bc === 403, "got $bc");

    // 超管 → 200
    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withPost(['reason' => '契约测试拉黑']);
    [$bc2] = guardCode(fn () => (new UserController())->addBlacklist());
    check('超管加黑名单 code=200', $bc2 === 200);
    check('blacklist 表有记录', Db::name('blacklist')->where('user_id', $uidA)->where('status', 1)->count() >= 1);
    // 黑名单用户冻结生效
    check('拉黑后 token 失效', UserToken::verify($plainToken) === null);

    $app->request->withPost(['reason' => '契约测试解除']);
    [$bc3] = guardCode(fn () => (new UserController())->removeBlacklist());
    check('移出黑名单 code=200', $bc3 === 200);

    /* ========== 6. realnames 脱敏 + 完整查看 ========== */

    $app->request->withGet(['page' => '1', 'page_size' => '50']);
    $rn = (new RealnameController())->list()->getData();
    check('realnames list code=200', (int) ($rn['code'] ?? 0) === 200, var_export($rn, true));
    $rnRaw = json_encode($rn, JSON_UNESCAPED_UNICODE);
    check('realnames 不含明文证件号', !str_contains($rnRaw, $idcardA));
    $rnA = null;
    foreach ($rn['data']['list'] ?? [] as $r) {
        if ((int) $r['user_id'] === $uidA) { $rnA = $r; break; }
    }
    check('实名列表含用户 A', $rnA !== null);
    check('A 姓名脱敏 王*明', ($rnA['name_masked'] ?? '') === '王*明', $rnA['name_masked'] ?? '');

    // 运营请求 full → 403
    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper])
        ->withGet(['id' => (string) $uidA])->withPost(['password' => $pwd]);
    [$rc] = guardCode(fn () => (new RealnameController())->full());
    check('运营查看完整实名 → 403', $rc === 403, "got $rc");

    // 超管无密码 → 400
    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withGet(['id' => (string) $uidA])->withPost([]);
    [$rc2, $rc2m] = guardCode(fn () => (new RealnameController())->full());
    check('完整查看缺密码 → 400', $rc2 === 400, "got $rc2 $rc2m");

    // 错误密码 → 400
    $app->request->withPost(['password' => 'wrong-' . $sfx]);
    [$rc3] = guardCode(fn () => (new RealnameController())->full());
    check('完整查看错密码 → 400', $rc3 === 400, "got $rc3");

    // 正确密码 → 200 + 明文 + 审计落库
    $app->request->withPost(['password' => $pwd]);
    $full = (new RealnameController())->full()->getData();
    check('完整查看 code=200', (int) ($full['code'] ?? 0) === 200, var_export($full, true));
    check('完整查看返回明文证件', ($full['data']['idcard'] ?? '') === $idcardA);
    $audits = Db::name('audit_log')->where('action', 'realname_full_view')
        ->where('user_id', $superId)->count();
    check('完整查看写审计日志', (int) $audits >= 1);

    // 审计日志端点能查到
    $app->request->withGet(['id' => (string) $uidA, 'page' => '1', 'page_size' => '10']);
    $logs = (new RealnameController())->auditLogs()->getData();
    check('audit-logs code=200 且 ≥1 条',
        (int) ($logs['code'] ?? 0) === 200 && (int) ($logs['data']['total'] ?? 0) >= 1,
        var_export($logs, true));
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 modules: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
