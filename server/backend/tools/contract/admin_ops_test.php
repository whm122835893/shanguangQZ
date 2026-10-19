<?php
declare(strict_types=1);

/**
 * 管理端 P1 运营查询/操作 TDD 契约测试
 *
 * 背景：P0 管理端只有内容运营最小集（banner/公告/藏品/抽奖/合成）。
 *       运营要做用户治理、订单核对、资金核对、实名复核，只能直接连库。
 * 本套用例锁定五组端点的契约红线：
 *   1. dashboard   全库统计口径明确（已支付 = pay_time>0 / 充值 status=2 / 在售 = status=1）
 *   2. user        列表手机号脱敏；detail 钱包合计=账本 SUM；freeze 立即踢下线
 *   3. order       金额 string（红线），keyword 按 order_sn 精确检索
 *   4. wallet      流水方向 int、金额 string；summary 与逐条流水一致
 *   5. realname    只出实名用户，姓名/证件号一律脱敏，明文绝不进响应体
 *
 * 用法：php tools/contract/admin_ops_test.php
 * 前置：01~14 schema 已导入。
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\model\UserToken;
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

/* ==================== fixtures ==================== */

$sfx  = (string) random_int(100000, 999999);
$now  = time();
$pwd  = 'S3cret-Admin-#42';

$adminId = (int) Db::name('admin_user')->insertGetId([
    'username' => 'ops_test_' . $sfx,
    'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
    'status' => 1, 'createtime' => $now, 'updatetime' => $now,
]);

$mkUser = static function (string $nick, string $mobile) use ($now): int {
    return (int) Db::name('user')->insertGetId([
        'username' => 'ops_t_' . $nick . '_' . random_int(100000, 999999),
        'nickname' => 'OPS_' . $nick,
        'mobile'   => $mobile,
        'money'    => '0.00', 'status' => 'normal',
        'inviter_code' => 'OP' . random_int(10000000, 99999999),
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
};
$mobileA = '199' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
$uidA = $mkUser('A', $mobileA);
$uidB = $mkUser('B', '198' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
$uidC = $mkUser('C', '197' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));

// A：有钱包流水（入 100 出 30 → money=70 满足账本恒等式）、已付订单、已入账充值、在售挂单、已实名
Db::name('user')->where('id', $uidA)->update([
    'money' => '70.00', 'crystal_yellow' => 5,
    'real_name_authentication' => 1,
    'real_name' => '张三丰', 'real_name_time' => $now,
]);
Db::name('user')->where('id', $uidA)->update(['id_card' => '110101199001011234']);
$uTokenPlain = 'c-token-' . $sfx;
Db::name('user_token')->insert([
    'user_id' => $uidA, 'token' => UserToken::hash($uTokenPlain),
    'device' => 'test', 'ip' => '127.0.0.1',
    'expiretime' => $now + 3600, 'createtime' => $now,
]);
Db::name('wallet_transaction')->insert([
    'user_id' => $uidA, 'direction' => 1, 'amount' => '100.00',
    'balance_before' => '0.00', 'balance_after' => '100.00',
    'biz_type' => 'recharge',
    'remark' => '测试入账', 'createtime' => $now,
]);
Db::name('wallet_transaction')->insert([
    'user_id' => $uidA, 'direction' => 2, 'amount' => '30.00',
    'balance_before' => '100.00', 'balance_after' => '70.00',
    'biz_type' => 'buy',
    'remark' => '测试出账', 'createtime' => $now,
]);
$mgId = (int) Db::name('market_goods')->insertGetId([
    'user_id' => $uidA, 'collection_id' => 99999998, 'collection_code' => 'OP' . $sfx,
    'price' => '10.00', 'num' => 5, 'sold_num' => 2, 'status' => 1,
    'createtime' => $now, 'updatetime' => $now,
]);
$orderSn = 'OPORD' . $sfx . '0001';
Db::name('artel_order')->insert([
    'order_sn' => $orderSn, 'user_id' => $uidA, 'artel_order' => '',
    'order_type' => 2, 'order_status' => 1, 'total_price' => '19.90', 'num' => 1,
    'goods_id' => $mgId, 'goods_type' => 2, 'goods_name' => 'OPS测试藏品',
    'goods_cover_image' => '', 'series_name' => '', 'crystal_type' => 0, 'crystal_number' => 0,
    'uc_ids' => '', 'collection_code' => '', 'pay_channel' => 'wallet', 'pay_no' => 'PN' . $sfx,
    'pay_time' => $now, 'createtime' => $now, 'updatetime' => $now,
]);
Db::name('recharge_order')->insert([
    'order_sn' => 'OPRCH' . $sfx . '0001', 'user_id' => $uidA,
    'amount' => '50.00', 'status' => 2, 'pay_way' => 'wallet',
    'pay_no' => 'RPN' . $sfx, 'pay_time' => $now,
    'createtime' => $now, 'updatetime' => $now,
]);

$session = \app\service\AdminAuthService::login('ops_test_' . $sfx, $pwd, '127.0.0.1');
$token   = (string) $session['token'];

/** 带管理员会话调用控制器方法（POST 参数 + header token） */
$call = static function (object $controller, string $method, array $post = []) use ($app, $token) {
    $app->request->withPost($post);
    $app->request->withHeader(['x-admin-token' => $token]);
    return $controller->$method()->getData();
};

try {
    $stats   = new \app\controller\admin\StatsController();
    $userCtl = new \app\controller\admin\UserController();
    $orderCtl = new \app\controller\admin\OrderController();
    $walletCtl = new \app\controller\admin\WalletController();
    $realCtl = new \app\controller\admin\RealnameController();

    /* ========== 1. 鉴权红线 ========== */
    $app->request->withPost([])->withHeader(['x-admin-token' => 'garbage-' . $sfx]);
    $body = $stats->dashboard()->getData();
    check('垃圾 token → 401', (int) ($body['code'] ?? 0) === 401, 'got ' . var_export($body, true));

    /* ========== 2. dashboard 统计口径 ========== */
    $body = $call($stats, 'dashboard');
    check('dashboard 成功', (int) ($body['code'] ?? 0) === 1, 'got ' . var_export($body, true));
    $d = $body['data'] ?? [];
    check('user_total ≥ 3（int）', (int) ($d['user_total'] ?? 0) >= 3);
    check('user_today ≥ 3', (int) ($d['user_today'] ?? 0) >= 3);
    check('order_paid_total ≥ 1（口径 pay_time>0）', (int) ($d['order_paid_total'] ?? 0) >= 1);
    check('gmv_paid 是 string 且 ≥ 19.90',
        is_string($d['gmv_paid'] ?? null) && bccomp($d['gmv_paid'], '19.90', 2) >= 0,
        'got ' . var_export($d['gmv_paid'] ?? null, true));
    check('market_on_sale ≥ 1（口径 status=1）', (int) ($d['market_on_sale'] ?? 0) >= 1);
    check('recharge_paid_total ≥ 1 且金额 ≥ 50.00（string）',
        (int) ($d['recharge_paid_total'] ?? 0) >= 1
        && is_string($d['recharge_paid_amount'] ?? null)
        && bccomp($d['recharge_paid_amount'], '50.00', 2) >= 0,
        'got ' . var_export([$d['recharge_paid_total'] ?? null, $d['recharge_paid_amount'] ?? null], true));

    /* ========== 3. user/list 脱敏 + 检索 ========== */
    $body = $call($userCtl, 'list', ['page' => 1, 'per_page' => 2]);
    check('user/list 分页信封 total ≥ 3', (int) ($body['data']['total'] ?? 0) >= 3,
        'got ' . var_export($body, true));
    $body = $call($userCtl, 'list', ['keyword' => 'OPS_A']);
    $rowA = ($body['data']['data'] ?? [])[0] ?? [];
    check('列表手机号脱敏（含 ****，明文不出现）',
        str_contains((string) $rowA['mobile'], '****')
        && !str_contains((string) $rowA['mobile'], $mobileA),
        'got ' . var_export($rowA['mobile'] ?? null, true));
    check('金额字段是 string（红线）', is_string($rowA['money'] ?? null));

    $body = $call($userCtl, 'list', ['keyword' => 'OPS_A']);
    $ids = array_column($body['data']['data'] ?? [], 'id');
    check('keyword 检索命中 A 且不含 B/C',
        in_array($uidA, $ids, true) && !in_array($uidB, $ids, true) && !in_array($uidC, $ids, true),
        'got ' . var_export($ids, true));

    /* ========== 4. user/detail：钱包合计=账本 SUM ========== */
    $body = $call($userCtl, 'detail', ['id' => $uidA]);
    check('detail 成功', (int) ($body['code'] ?? 0) === 1, 'got ' . var_export($body, true));
    $w = $body['data']['wallet'] ?? [];
    check('credit_total = 100.00 / debit_total = 30.00（string）',
        ($w['credit_total'] ?? '') === '100.00' && ($w['debit_total'] ?? '') === '30.00',
        'got ' . var_export($w, true));
    check('counts.orders ≥ 1', (int) (($body['data']['counts'] ?? [])['orders'] ?? 0) >= 1);
    $rn = $body['data']['realname'] ?? [];
    check('detail 实名脱敏：姓名 张*丰 / 证件 110******234',
        ($rn['name'] ?? '') === '张*丰' && ($rn['id_card'] ?? '') === '110******234',
        'got ' . var_export($rn, true));
    check('detail 响应体绝无明文证件号',
        !str_contains(json_encode($body, JSON_UNESCAPED_UNICODE), '110101199001011234'));

    /* ========== 5. freeze：立即踢下线 + 审计 ========== */
    $body = $call($userCtl, 'freeze', ['id' => $uidA, 'action' => 'freeze']);
    check('freeze 成功', (int) ($body['code'] ?? 0) === 1, 'got ' . var_export($body, true));
    check('冻结后 C 端 token 立即失效', UserToken::verify($uTokenPlain) === null);
    $audit = Db::name('audit_log')->where('action', 'admin_user_freeze')
        ->where('target_id', (string) $uidA)->count();
    check('freeze 写审计日志', $audit >= 1);
    $body = $call($userCtl, 'freeze', ['id' => 999999999, 'action' => 'freeze']);
    check('用户不存在 → 业务失败', (int) ($body['code'] ?? 0) !== 1);
    $body = $call($userCtl, 'freeze', ['id' => $uidA, 'action' => 'unfreeze']);
    check('unfreeze 成功且 token 恢复可用',
        (int) ($body['code'] ?? 0) === 1 && UserToken::verify($uTokenPlain) !== null,
        'got ' . var_export($body, true));

    /* ========== 6. order/list ========== */
    $body = $call($orderCtl, 'list', ['keyword' => $orderSn]);
    $row = ($body['data']['data'] ?? [])[0] ?? [];
    check('order/list 按 order_sn 命中',
        ($row['order_sn'] ?? '') === $orderSn, 'got ' . var_export($body, true));
    check('行含 user_nickname / goods_name，total_price 是 string',
        ($row['user_nickname'] ?? '') === 'OPS_A' && ($row['goods_name'] ?? '') === 'OPS测试藏品'
        && is_string($row['total_price'] ?? null),
        'got ' . var_export($row, true));

    /* ========== 7. wallet：流水 + summary 一致 ========== */
    $body = $call($walletCtl, 'transactions', ['user_id' => $uidA, 'per_page' => 10]);
    $txs = $body['data']['data'] ?? [];
    check('流水按 user_id 过滤且全量 2 条', (int) ($body['data']['total'] ?? 0) === 2
        && count($txs) === 2, 'got ' . var_export($body, true));
    check('流水 amount/balance 是 string，direction 是 int',
        ($txs[0]['amount'] ?? '') !== '' && is_string($txs[0]['amount'])
        && is_int($txs[0]['direction'] ?? null)
        && is_string($txs[0]['balance_after'] ?? null),
        'got ' . var_export($txs[0] ?? null, true));
    $body = $call($walletCtl, 'summary', ['user_id' => $uidA]);
    check('summary 与逐条流水一致（100.00 / 30.00）',
        (($body['data']['credit_total'] ?? '') === '100.00')
        && (($body['data']['debit_total'] ?? '') === '30.00'),
        'got ' . var_export($body, true));

    /* ========== 8. realname/list：只出实名用户，一律脱敏 ========== */
    $body = $call($realCtl, 'list', ['per_page' => 100]);
    $rows = $body['data']['data'] ?? [];
    $ids = array_column($rows, 'user_id');
    check('realname/list 含 A 不含未实名 B/C',
        in_array($uidA, $ids, true) && !in_array($uidB, $ids, true) && !in_array($uidC, $ids, true),
        'got ' . var_export($ids, true));
    $raw = json_encode($body, JSON_UNESCAPED_UNICODE);
    check('列表绝不泄露明文姓名/证件号',
        !str_contains($raw, '张三丰') && !str_contains($raw, '110101199001011234'),
        'got ' . var_export($rows[0] ?? null, true));

    echo "\n==============================\n";
    echo $failed === 0 ? "全部通过（{$passed} 项断言）\n" : "失败 {$failed} 项 / 通过 {$passed} 项\n";
    exit($failed === 0 ? 0 : 1);
} finally {
    // ---- 清理 fixtures（幂等：按 id/前缀删） ----
    Db::name('admin_user')->where('id', $adminId)->delete();
    foreach ([$uidA, $uidB, $uidC] as $uid) {
        Db::name('user_token')->where('user_id', $uid)->delete();
        Db::name('wallet_transaction')->where('user_id', $uid)->delete();
        Db::name('artel_order')->where('user_id', $uid)->delete();
        Db::name('recharge_order')->where('user_id', $uid)->delete();
        Db::name('market_goods')->where('user_id', $uid)->delete();
        Db::name('audit_log')->where('target_id', (string) $uid)->delete();
        Db::name('user')->where('id', $uid)->delete();
    }
}
