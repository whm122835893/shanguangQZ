<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第三批：钱包模块 §8.12
 *
 * 锁定契约：
 *   1. 充值/流水列表：金额 string、direction int、时间 ISO
 *   2. fee-stats：total_fee 来自 artel_order.fee，按日聚合
 *   3. conservation：diff = SUM(user.money) - SUM(账本)，is_consistent 布尔
 *   4. abnormal：余额≠账本的用户必须被点名
 *   5. export：CSV 文件流（BOM + 表头），权限 wallet:export
 *   6. RBAC：运营（role=2）请求钱包接口 → 403
 *
 * 用法：php tools/contract/admin_v1_wallet_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\admin\v1\WalletController;
use app\service\admin\AdminException;
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
$pwd = 'S3cret-Wal-#42';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('wal_super', 1);
$finId   = $mkAdmin('wal_fin', 3);    // 财务：wallet:* 全有
$operId  = $mkAdmin('wal_oper', 2);   // 运营：无 wallet:*

$app->request->withGet([]);
$app->request->withPost([]);

$login = static function (int $adminId) use ($app, $pwd): string {
    $name = Db::name('admin_user')->where('id', $adminId)->value('username');
    $app->request->withPost(['username' => $name, 'password' => $pwd]);
    $body = (new \app\controller\admin\v1\AuthController())->login()->getData();
    return (string) ($body['data']['token'] ?? '');
};

try {
    $asSuper = $login($superId);
    $asFin   = $login($finId);
    $asOper  = $login($operId);
    check('三管理员登录成功', $asSuper !== '' && $asFin !== '' && $asOper !== '');

    // 钱包用户 B：入 200 出 50 → money=150（账本恒等）
    $uidB = (int) Db::name('user')->insertGetId([
        'username' => 'wal_b_' . $sfx, 'nickname' => 'WAL_B',
        'mobile' => '198' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'money' => '150.00', 'status' => 'normal',
        'inviter_code' => 'WB' . random_int(10000000, 99999999),
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    Db::name('wallet_transaction')->insert([
        'user_id' => $uidB, 'direction' => 1, 'amount' => '200.00',
        'balance_before' => '0.00', 'balance_after' => '200.00',
        'biz_type' => 'recharge', 'remark' => '充值', 'createtime' => $now,
    ]);
    Db::name('wallet_transaction')->insert([
        'user_id' => $uidB, 'direction' => 2, 'amount' => '50.00',
        'balance_before' => '200.00', 'balance_after' => '150.00',
        'biz_type' => 'buy', 'remark' => '购买', 'createtime' => $now,
    ]);
    // 已入账充值单
    $rcSn = 'WRC' . $sfx . '01';
    Db::name('recharge_order')->insert([
        'order_sn' => $rcSn, 'user_id' => $uidB, 'amount' => '200.00',
        'status' => 2, 'pay_way' => 'alipay', 'pay_no' => 'PN' . $sfx,
        'pay_time' => $now, 'createtime' => $now, 'updatetime' => $now,
    ]);
    // 带手续费的市场成交单
    Db::name('artel_order')->insert([
        'order_sn' => 'WFO' . $sfx . '01', 'user_id' => $uidB,
        'artel_order' => '', 'order_type' => 2, 'order_status' => 1,
        'total_price' => '50.00', 'num' => 1, 'fee' => '2.50',
        'goods_type' => 2, 'goods_name' => 'WAL费测试',
        'pay_channel' => 'balance', 'pay_time' => $now,
        'createtime' => $now, 'updatetime' => $now,
    ]);
    // 异常用户 C：money=5，账本 10
    $uidC = (int) Db::name('user')->insertGetId([
        'username' => 'wal_c_' . $sfx, 'nickname' => 'WAL_C',
        'mobile' => '197' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'money' => '5.00', 'status' => 'normal',
        'inviter_code' => 'WC' . random_int(10000000, 99999999),
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    Db::name('wallet_transaction')->insert([
        'user_id' => $uidC, 'direction' => 1, 'amount' => '10.00',
        'balance_before' => '0.00', 'balance_after' => '10.00',
        'biz_type' => 'recharge', 'remark' => '账实不符样本', 'createtime' => $now,
    ]);

    $ctrl = new WalletController();

    /* ========== 1. RBAC：运营无 wallet:* ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper])
        ->withGet(['page' => '1']);
    [$c1] = guardCode(fn () => $ctrl->recharges());
    check('运营查充值 → 403', $c1 === 403, "got $c1");
    [$c2] = guardCode(fn () => $ctrl->conservation());
    check('运营查守恒 → 403', $c2 === 403, "got $c2");

    /* ========== 2. recharges ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asFin])
        ->withGet(['page' => '1', 'page_size' => '50', 'user_id' => (string) $uidB]);
    $rch = $ctrl->recharges()->getData();
    check('recharges code=200', (int) ($rch['code'] ?? 0) === 200, var_export($rch, true));
    check('recharges 命中充值单', (int) ($rch['data']['total'] ?? 0) >= 1
        && ($rch['data']['list'][0]['order_sn'] ?? '') === $rcSn);
    check('recharges 金额 string', is_string($rch['data']['list'][0]['amount'] ?? null));
    check('recharges 渠道=alipay', ($rch['data']['list'][0]['channel'] ?? '') === 'alipay');

    /* ========== 3. transactions ========== */

    $app->request->withGet(['page' => '1', 'page_size' => '50', 'user_id' => (string) $uidB]);
    $txn = $ctrl->transactions()->getData();
    check('transactions code=200', (int) ($txn['code'] ?? 0) === 200, var_export($txn, true));
    check('transactions 命中 2 条', (int) ($txn['data']['total'] ?? 0) === 2, 'total=' . ($txn['data']['total'] ?? '?'));
    $first = $txn['data']['list'][0] ?? [];
    check('direction int / 金额 string',
        is_int($first['direction'] ?? null) && is_string($first['amount'] ?? null));

    /* ========== 4. fee-stats ========== */

    $app->request->withGet(['days' => '7']);
    $fee = $ctrl->feeStats()->getData();
    check('fee-stats code=200', (int) ($fee['code'] ?? 0) === 200, var_export($fee, true));
    check('total_fee 含 2.50', bccomp((string) ($fee['data']['total_fee'] ?? '0'), '2.50', 2) >= 0,
        'total_fee=' . ($fee['data']['total_fee'] ?? '?'));
    check('daily_stats 有今日', in_array(date('Y-m-d'), array_column($fee['data']['daily_stats'] ?? [], 'date'), true),
        'today=' . date('Y-m-d') . ' stats=' . json_encode($fee['data']['daily_stats'] ?? [], JSON_UNESCAPED_UNICODE));
    check('channel_distribution 有 balance', in_array('balance', array_column($fee['data']['channel_distribution'] ?? [], 'channel'), true));

    /* ========== 5. conservation ========== */

    $app->request->withGet([]);
    $cons = $ctrl->conservation()->getData();
    check('conservation code=200', (int) ($cons['code'] ?? 0) === 200, var_export($cons, true));
    $cd = $cons['data'] ?? [];
    $balanceSum = (float) Db::name('user')->sum('money');
    $ledgerAll = (float) (Db::query(
        'SELECT COALESCE(SUM(CASE direction WHEN 1 THEN amount ELSE -amount END),0) AS v FROM wallet_transaction'
    )[0]['v'] ?? 0);
    $expectDiff = round($balanceSum - $ledgerAll, 2);
    check('diff === 全库余额-账本', bccomp((string) ($cd['diff'] ?? '999'), (string) $expectDiff, 2) === 0,
        'diff=' . ($cd['diff'] ?? '?') . ' expect=' . $expectDiff);
    check('is_consistent 布尔且与 diff 自洽',
        is_bool($cd['is_consistent'] ?? null)
        && ($cd['is_consistent'] === (bccomp((string) $expectDiff, '0.00', 2) === 0)));
    check('total_recharge 含 200', bccomp((string) ($cd['total_recharge'] ?? '0'), '200.00', 2) >= 0);

    /* ========== 6. abnormal ========== */

    $app->request->withGet(['page' => '1', 'page_size' => '100', 'type' => 'balance_mismatch']);
    $abn = $ctrl->abnormal()->getData();
    check('abnormal code=200', (int) ($abn['code'] ?? 0) === 200, var_export($abn, true));
    $hit = false;
    foreach ($abn['data']['list'] ?? [] as $r) {
        if ((int) $r['user_id'] === $uidC) { $hit = true; }
    }
    check('账实不符用户 C 被点名', $hit);
    if ($hit) {
        foreach ($abn['data']['list'] as $r) {
            if ((int) $r['user_id'] === $uidC) {
                check('C diff=-5.00', ($r['diff'] ?? '') === '-5.00', $r['diff'] ?? '?');
            }
        }
    }

    // 样本 C 用后即弃：不留下账实不符数据污染后续全局对账断言（reports/finance）
    Db::name('wallet_transaction')->where('user_id', $uidC)->delete();
    Db::name('user')->where('id', $uidC)->delete();

    /* ========== 7. export CSV ========== */

    $app->request->withGet(['date_start' => date('Y-m-d 00:00:00', $now - 3600), 'date_end' => date('Y-m-d 23:59:59', $now + 3600)]);
    $exp = $ctrl->export();
    $expRaw = $exp->getContent();
    check('export CSV 含 BOM', str_starts_with($expRaw, "\xEF\xBB\xBF"));
    check('export CSV 含表头', str_contains($expRaw, '用户ID') && str_contains($expRaw, '金额'));
    check('export CSV 含流水数据', str_contains($expRaw, $rcSn) || str_contains($expRaw, 'recharge'));
    $auditCnt = Db::name('audit_log')->where('action', 'wallet_export')->where('user_id', $finId)->count();
    check('export 写审计日志', (int) $auditCnt >= 1);
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 wallet: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
