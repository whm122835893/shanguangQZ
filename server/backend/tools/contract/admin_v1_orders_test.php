<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第四批：订单 §8.7 + 退款 §8.8
 *
 * 锁定契约：
 *   1. 订单列表/详情金额 string；is_paid = pay_time>0
 *   2. 强制取消仅限未支付单；已支付/已取消 → 409
 *   3. mark-paid 高风险（密码验证）：补 pay_time + 库存流转（挂单扣减→仓库入库），不动钱包
 *   4. 退款金额上限 = 实付金额；approve 真实资金动作（money += amount + 账本 refund_in）
 *   5. 退款批准后账本恒等式继续成立；重复审批 → 409
 *   6. abnormal 超时单可被 repair timeout_cancel 修复
 *   7. RBAC：运营（role=2）无 order:manage / refund:approve → 403
 *
 * 用法：php tools/contract/admin_v1_orders_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\admin\v1\OrderController;
use app\controller\admin\v1\RefundController;
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
$pwd = 'S3cret-Ord-#42';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('ord_super', 1);
$operId  = $mkAdmin('ord_oper', 2);

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
    $asOper  = $login($operId);
    check('管理员登录成功', $asSuper !== '' && $asOper !== '');

    // 买家 D（余额 100，含初始账本行保证恒等）+ 卖家挂单
    $uidD = (int) Db::name('user')->insertGetId([
        'username' => 'ord_d_' . $sfx, 'nickname' => 'ORD_D',
        'mobile' => '196' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'money' => '100.00', 'status' => 'normal',
        'inviter_code' => 'OD' . random_int(10000000, 99999999),
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    Db::name('wallet_transaction')->insert([
        'user_id' => $uidD, 'direction' => 1, 'amount' => '100.00',
        'balance_before' => '0.00', 'balance_after' => '100.00',
        'biz_type' => 'recharge', 'remark' => '初始入账', 'createtime' => $now,
    ]);
    $uidE = (int) Db::name('user')->insertGetId([
        'username' => 'ord_e_' . $sfx, 'nickname' => 'ORD_E',
        'mobile' => '195' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'money' => '0.00', 'status' => 'normal',
        'inviter_code' => 'OE' . random_int(10000000, 99999999),
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    // 每次运行使用独立 collection_id，避免跨运行挂单污染
    $colId = 90000000 + random_int(1, 9999999);
    $mgId = (int) Db::name('market_goods')->insertGetId([
        'user_id' => $uidE, 'collection_id' => $colId, 'collection_code' => 'OD' . $sfx,
        'price' => '20.00', 'num' => 10, 'sold_num' => 0, 'status' => 1,
        'createtime' => $now, 'updatetime' => $now,
    ]);

    $mkOrder = static function (string $sn, int $status, int $payTime, string $price = '40.00') use ($uidD, $colId, $now): int {
        return (int) Db::name('artel_order')->insertGetId([
            'order_sn' => $sn, 'user_id' => $uidD, 'artel_order' => 'mh_secondary_market_order',
            'order_type' => 2, 'order_status' => $status, 'total_price' => $price, 'num' => 2,
            'goods_id' => $colId, 'goods_type' => 2, 'goods_name' => 'ORD测试藏品',
            'goods_cover_image' => '', 'series_name' => '', 'crystal_type' => 0, 'crystal_number' => 0,
            'uc_ids' => '', 'collection_code' => '', 'pay_channel' => '', 'pay_time' => $payTime,
            'createtime' => $now - 3600, 'updatetime' => $now,
        ]);
    };
    $oidUnpaid   = $mkOrder('ORD' . $sfx . 'U1', 1, 0);              // 未支付（已超窗口）
    $oidPaid     = $mkOrder('ORD' . $sfx . 'P1', 2, $now - 1800);    // 已支付
    $oidCancel   = $mkOrder('ORD' . $sfx . 'C1', 4, 0);              // 已取消

    $ordCtrl = new OrderController();
    $refCtrl = new RefundController();

    /* ========== 1. 列表 / 详情 ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withGet(['page' => '1', 'page_size' => '10', 'user_id' => (string) $uidD]);
    $list = $ordCtrl->list()->getData();
    check('orders list code=200 且命中 3 单', (int) ($list['code'] ?? 0) === 200 && (int) ($list['data']['total'] ?? 0) === 3,
        var_export($list['data']['total'] ?? null, true));
    $raw = json_encode($list, JSON_UNESCAPED_UNICODE);
    check('订单金额 string', str_contains($raw, '"total_price":"40.00"'));

    $app->request->withGet(['id' => (string) $oidPaid]);
    $detail = $ordCtrl->detail()->getData();
    check('order detail is_paid=true', (int) ($detail['code'] ?? 0) === 200 && ($detail['data']['is_paid'] ?? false) === true);

    /* ========== 2. RBAC ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper])
        ->withGet(['id' => (string) $oidUnpaid])->withPost(['reason' => 'x', 'password' => $pwd]);
    [$c1] = guardCode(fn () => $ordCtrl->cancel());
    check('运营强制取消 → 403', $c1 === 403, "got $c1");

    /* ========== 3. 强制取消 ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper]);
    // 无密码 → 400
    $app->request->withGet(['id' => (string) $oidUnpaid])->withPost(['reason' => '契约测试']);
    [$c2] = guardCode(fn () => $ordCtrl->cancel());
    check('cancel 缺密码 → 400', $c2 === 400, "got $c2");
    // 带密码 → 200
    $app->request->withPost(['reason' => '契约测试取消', 'password' => $pwd]);
    [$c3] = guardCode(fn () => $ordCtrl->cancel());
    check('cancel 未支付单 → 200', $c3 === 200, "got $c3");
    check('order_status → 4', (int) Db::name('artel_order')->where('id', $oidUnpaid)->value('order_status') === 4);
    // 已取消单再取消 → 409
    [$c4] = guardCode(fn () => $ordCtrl->cancel());
    check('重复取消 → 409', $c4 === 409, "got $c4");
    // 已支付单不可取消
    $app->request->withGet(['id' => (string) $oidPaid])->withPost(['reason' => 'x', 'password' => $pwd]);
    [$c5] = guardCode(fn () => $ordCtrl->cancel());
    check('已支付单取消 → 409', $c5 === 409, "got $c5");

    /* ========== 4. mark-paid ========== */

    $oidNew = $mkOrder('ORD' . $sfx . 'M1', 1, 0);
    $txnBefore = (int) Db::name('wallet_transaction')->where('user_id', $uidD)->count();
    $app->request->withGet(['id' => (string) $oidNew])
        ->withPost(['reason' => '线下已收款', 'password' => $pwd]);
    [$m1] = guardCode(fn () => $ordCtrl->markPaid());
    check('mark-paid → 200', $m1 === 200, "got $m1");
    $after = Db::name('artel_order')->where('id', $oidNew)->find();
    check('pay_time 已补 且 channel=manual', (int) $after['pay_time'] > 0 && $after['pay_channel'] === 'manual');
    $mg = Db::name('market_goods')->where('id', $mgId)->find();
    check('挂单库存扣减 num=8 sold=2', (int) $mg['num'] === 8 && (int) $mg['sold_num'] === 2,
        "num={$mg['num']} sold={$mg['sold_num']}");
    $held = Db::name('user_collection')->where('user_id', $uidD)->where('collection_id', $colId)->find();
    check('用户仓库入库 num=2', $held !== null && (int) $held['num'] === 2);
    check('mark-paid 不动钱包', (string) Db::name('user')->where('id', $uidD)->value('money') === '100.00'
        && (int) Db::name('wallet_transaction')->where('user_id', $uidD)->count() === $txnBefore);
    // 已支付单再标记 → 409
    [$m2] = guardCode(fn () => $ordCtrl->markPaid());
    check('重复 mark-paid → 409', $m2 === 409, "got $m2");

    /* ========== 5. 退款流 ========== */

    // 发起退款：金额超上限 → 409
    $app->request->withGet(['id' => (string) $oidPaid])->withPost(['amount' => '999.00', 'reason' => 'x']);
    [$r1] = guardCode(fn () => $ordCtrl->refund());
    check('退款超实付 → 409', $r1 === 409, "got $r1");
    // 正常发起
    $app->request->withPost(['amount' => '40.00', 'reason' => '契约测试退款']);
    $rr = $ordCtrl->refund()->getData();
    $refundId = (int) ($rr['data']['refund_id'] ?? 0);
    check('退款单创建', (int) ($rr['code'] ?? 0) === 200 && $refundId > 0, var_export($rr, true));

    // 审批 RBAC
    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper])
        ->withGet(['id' => (string) $refundId])->withPost(['comment' => 'x', 'password' => $pwd]);
    [$r2] = guardCode(fn () => $refCtrl->approve());
    check('运营批准退款 → 403', $r2 === 403, "got $r2");

    // 超管批准（真实资金动作）
    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withPost(['comment' => '同意', 'password' => $pwd]);
    [$r3] = guardCode(fn () => $refCtrl->approve());
    check('approve → 200', $r3 === 200, "got $r3");
    $uD = Db::name('user')->where('id', $uidD)->find();
    check('money 100→140', ($uD['money'] ?? '') === '140.00', $uD['money'] ?? '?');
    $txn = Db::name('wallet_transaction')->where('user_id', $uidD)->where('biz_type', 'refund_in')->find();
    check('账本 refund_in + before/after 自洽', $txn !== null
        && ($txn['balance_before'] ?? '') === '100.00' && ($txn['balance_after'] ?? '') === '140.00');
    // 账本恒等式
    $ledger = (float) (Db::query(
        'SELECT COALESCE(SUM(CASE direction WHEN 1 THEN amount ELSE -amount END),0) AS v FROM wallet_transaction WHERE user_id = ?',
        [$uidD])[0]['v'] ?? 0);
    check('批准后账本恒等成立', bccomp((string) $uD['money'], (string) $ledger, 2) === 0,
        "money={$uD['money']} ledger=$ledger");
    // 重复审批 → 409
    [$r4] = guardCode(fn () => $refCtrl->approve());
    check('重复批准 → 409', $r4 === 409, "got $r4");

    // 拒绝流：重新发起一单（重传 amount/reason）
    $app->request->withGet(['id' => (string) $oidPaid])
        ->withPost(['amount' => '20.00', 'reason' => '第二次退款', 'password' => $pwd]);
    $rr2 = $ordCtrl->refund()->getData();
    $rid2 = (int) ($rr2['data']['refund_id'] ?? 0);
    $app->request->withGet(['id' => (string) $rid2])
        ->withPost(['comment' => '证据不足', 'password' => $pwd]);
    [$r5, $r5m] = guardCode(fn () => $refCtrl->reject());
    check('reject → 200', $r5 === 200, "got $r5 $r5m");
    check('refund status=2', (int) Db::name('refund')->where('id', $rid2)->value('status') === 2);

    /* ========== 6. abnormal + repair ========== */

    // 造一单超时未支付单并修复
    $oidStale = $mkOrder('ORD' . $sfx . 'U2', 1, 0);
    Db::name('artel_order')->where('id', $oidStale)->update(['createtime' => $now - 3600]);
    $app->request->withGet(['type' => 'timeout', 'page' => '1', 'page_size' => '100']);
    $abn = $ordCtrl->abnormal()->getData();
    $found = false;
    foreach ($abn['data']['list'] ?? [] as $r) {
        if ((int) $r['id'] === $oidStale) { $found = true; }
    }
    check('超时未支付单被点名', $found);
    $app->request->withGet(['id' => (string) $oidStale])
        ->withPost(['repair_type' => 'timeout_cancel', 'reason' => '超时修复', 'password' => $pwd]);
    [$p1] = guardCode(fn () => $ordCtrl->repair());
    check('repair timeout_cancel → 200', $p1 === 200, "got $p1");
    check('修复后状态=4', (int) Db::name('artel_order')->where('id', $oidStale)->value('order_status') === 4);

    /* ========== 7. export ========== */

    $app->request->withGet(['date_start' => date('Y-m-d 00:00:00', $now - 86400)]);
    $exp = $ordCtrl->export();
    $raw = $exp->getContent();
    check('orders export CSV 含 BOM+表头', str_starts_with($raw, "\xEF\xBB\xBF") && str_contains($raw, '订单号'));
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 orders+refunds: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
