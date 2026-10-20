<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第六批：市场 §8.9 + 转赠 §8.10
 *
 * 锁定契约：
 *   1. 挂单真实载体为 market_goods（consignment 为遗留表，v1 不用）
 *   2. delist 强制下架：仅在售单；status→3 + is_system_delisted=1 + delist_reason + 审计；
 *      RBAC：运营仅 market:list（无 manage/config）→ 403；财务有 market:config
 *   3. fee-config：site_config KV 读写；fee_rate ∈ (0,100]；修改需密码 + 审计
 *   4. price-alerts：实时扫描——低于发行价 1% 或高于 10 倍 → 低价/高价预警
 *   5. revoke（撤销）：仅待接收(status=0)；资产按 uc 归属真实回滚（receiver→sender）
 *   6. cancel（强制回退）：仅已完成(status=1)；接收方已二次流转（挂单）→ 409；status→4
 *   7. 转赠列表手机号脱敏；stats 按日聚合走 PHP 侧（应用时区）；abnormal 高频/反复转赠监控
 *
 * 用法：php tools/contract/admin_v1_market_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\admin\v1\MarketController;
use app\controller\admin\v1\TransferController;
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
$pwd = 'S3cret-Mkt-#42';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('mk_super', 1);
$operId  = $mkAdmin('mk_oper', 2);
$finId   = $mkAdmin('mk_fin', 3);

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
    $asFin   = $login($finId);
    check('管理员登录成功', $asSuper !== '' && $asOper !== '' && $asFin !== '');

    // ---------- 用户：卖家 S1/S2、买家 B、转赠发送方 T1、接收方 T2 ----------
    $mkUser = static function (string $tag, string $mobile) use ($now, $sfx): array {
        $uid = (int) Db::name('user')->insertGetId([
            'username' => $tag . '_' . $sfx, 'nickname' => strtoupper($tag),
            'mobile' => $mobile, 'money' => '0.00', 'status' => 'normal',
            'inviter_code' => strtoupper(substr($tag, 0, 2)) . random_int(10000000, 99999999),
            'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
        ]);
        return [$uid, $mobile];
    };
    [$uidS1, $phS1] = $mkUser('mks1', '192' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
    [$uidS2]        = $mkUser('mks2', '192' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
    [$uidB]         = $mkUser('mkb',  '192' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
    [$uidT1]        = $mkUser('mkt1', '192' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));
    [$uidT2, $phT2] = $mkUser('mkt2', '192' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT));

    // 藏品（发行价 100，低价预警基准）
    $colId = (int) Db::name('collection')->insertGetId([
        'name' => '市场藏品_' . $sfx, 'total_num' => 100, 'total_num_show' => 100,
        'sale_price' => '100.00',
        'createtime' => $now, 'updatetime' => $now,
    ]);

    // 挂单：S1 正常价在售 / S1 低价(0.01)在售 / S2 已下架
    $mkListing = static function (int $uid, string $price, int $status) use ($colId, $now, $sfx): int {
        return (int) Db::name('market_goods')->insertGetId([
            'user_id' => $uid, 'collection_id' => $colId, 'collection_code' => 'MK' . $sfx,
            'price' => $price, 'num' => 5, 'sold_num' => 0, 'status' => $status,
            'createtime' => $now - 60, 'updatetime' => $now - 60,
        ]);
    };
    $mgNormal = $mkListing($uidS1, '120.00', 1);
    $mgCheap  = $mkListing($uidS1, '0.01', 1);
    $mgOff    = $mkListing($uidS2, '90.00', 3);

    // 已支付市场订单（成交记录 + 价格趋势数据源）
    $oidPaid = (int) Db::name('artel_order')->insertGetId([
        'order_sn' => 'MKT' . $sfx . 'P1', 'user_id' => $uidB, 'artel_order' => 'mh_secondary_market_order',
        'order_type' => 2, 'order_status' => 2, 'total_price' => '240.00', 'num' => 2,
        'goods_id' => $colId, 'goods_type' => 2, 'goods_name' => '市场藏品_' . $sfx,
        'goods_cover_image' => '', 'series_name' => '', 'crystal_type' => 0, 'crystal_number' => 0,
        'uc_ids' => '', 'collection_code' => '', 'pay_channel' => 'alipay', 'pay_time' => $now - 300,
        'createtime' => $now - 600, 'updatetime' => $now,
    ]);

    /* ========== 1. 挂单列表（80） ========== */

    $mkt = new MarketController();
    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withGet(['page' => '1', 'page_size' => '10', 'status' => '1', 'collectible_id' => (string) $colId])->withPost([]);
    $l = $mkt->listings()->getData();
    check('listings 在售筛选命中 2', (int) ($l['code'] ?? 0) === 200 && (int) ($l['data']['total'] ?? 0) === 2,
        var_export($l['data']['total'] ?? null, true));
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'collectible_id' => (string) $colId,
        'price_min' => '100', 'price_max' => '200']);
    $l2 = $mkt->listings()->getData();
    $onlyNormal = true;
    foreach ($l2['data']['list'] ?? [] as $r) {
        if ((float) $r['price'] < 100 || (float) $r['price'] > 200) { $onlyNormal = false; }
    }
    check('listings 价格区间过滤', (int) ($l2['data']['total'] ?? 0) === 1 && $onlyNormal);
    $raw = json_encode($l, JSON_UNESCAPED_UNICODE);
    check('listings 金额 string 且卖家手机号脱敏',
        str_contains($raw, '"price":"120.00"') && !str_contains($raw, substr($phS1, 0, 11))
        && str_contains($raw, substr($phS1, 0, 3) . '****' . substr($phS1, -4)),
        'leak=' . var_export(str_contains($raw, $phS1), true));

    /* ========== 2. 强制下架（81） ========== */

    $app->request->withGet(['id' => (string) $mgNormal])->withPost(['reason' => '契约下架']);
    [$d1] = guardCode(fn () => $mkt->delist());
    check('delist 缺密码 → 400', $d1 === 400, "got $d1");
    $app->request->withPost(['reason' => '契约下架', 'password' => $pwd]);
    [$d2] = guardCode(fn () => $mkt->delist());
    check('delist → 200', $d2 === 200, "got $d2");
    $mg = Db::name('market_goods')->where('id', $mgNormal)->find();
    check('delist 状态/留痕落库', (int) $mg['status'] === 3 && (int) $mg['is_system_delisted'] === 1
        && $mg['delist_reason'] === '契约下架');
    [$d3] = guardCode(fn () => $mkt->delist());
    check('已下架再 delist → 409', $d3 === 409, "got $d3");

    // RBAC：运营无 market:manage
    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper])
        ->withGet(['id' => (string) $mgCheap])->withPost(['reason' => 'x', 'password' => $pwd]);
    [$d4] = guardCode(fn () => $mkt->delist());
    check('运营 delist → 403', $d4 === 403, "got $d4");

    /* ========== 3. 成交记录（82） ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withGet(['page' => '1', 'page_size' => '10', 'collectible_id' => (string) $colId]);
    $t = $mkt->trades()->getData();
    $found = false;
    foreach ($t['data']['list'] ?? [] as $r) {
        if ((int) $r['id'] === $oidPaid) { $found = true; }
    }
    check('trades 命中已支付单', (int) ($t['code'] ?? 0) === 200 && $found);
    $raw = json_encode($t, JSON_UNESCAPED_UNICODE);
    check('trades 金额 string', str_contains($raw, '"total_price":"240.00"'));

    /* ========== 4. 异常价格预警（83） ========== */

    $app->request->withGet(['page' => '1', 'page_size' => '50']);
    $al = $mkt->priceAlerts()->getData();
    $hitCheap = false;
    foreach ($al['data']['list'] ?? [] as $r) {
        if ((int) $r['listing_id'] === $mgCheap && $r['kind'] === 'low') { $hitCheap = true; }
    }
    check('price-alerts 低价预警命中', (int) ($al['code'] ?? 0) === 200 && $hitCheap, var_export($al['data'] ?? null, true));

    /* ========== 5. 手续费配置（84/85） ========== */

    $app->request->withGet([])->withPost([]);
    $fc = $mkt->feeConfig()->getData();
    check('fee-config 默认读种子', (int) ($fc['code'] ?? 0) === 200
        && ($fc['data']['fee_rate'] ?? '') !== '' && ($fc['data']['fee_min'] ?? '') !== ''
        && ($fc['data']['fee_max'] ?? '') !== '', var_export($fc, true));

    $app->request->withPost(['fee_rate' => '3', 'fee_min' => '0.50', 'fee_max' => '300.00']);
    [$f1] = guardCode(fn () => $mkt->feeConfigUpdate());
    check('fee-config 修改缺密码 → 400', $f1 === 400, "got $f1");
    $app->request->withPost(['fee_rate' => '200', 'fee_min' => '0.50', 'fee_max' => '300.00', 'password' => $pwd]);
    [$f2] = guardCode(fn () => $mkt->feeConfigUpdate());
    check('fee_rate>100 → 400', $f2 === 400, "got $f2");
    $app->request->withPost(['fee_rate' => '3', 'fee_min' => '0.50', 'fee_max' => '300.00', 'password' => $pwd]);
    [$f3] = guardCode(fn () => $mkt->feeConfigUpdate());
    check('fee-config 修改 → 200', $f3 === 200, "got $f3");
    $cfg = json_decode((string) Db::name('site_config')->where('key', 'market.fee_config')->value('value'), true);
    check('fee-config 落库', ($cfg['fee_rate'] ?? '') === '3' && ($cfg['fee_min'] ?? '') === '0.50');

    // RBAC：运营无 market:config，财务有
    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper])
        ->withPost(['fee_rate' => '4', 'fee_min' => '1.00', 'fee_max' => '300.00', 'password' => $pwd]);
    [$f4] = guardCode(fn () => $mkt->feeConfigUpdate());
    check('运营改费率 → 403', $f4 === 403, "got $f4");
    $app->request->withHeader(['authorization' => 'Bearer ' . $asFin])
        ->withPost(['fee_rate' => '5', 'fee_min' => '1.00', 'fee_max' => '500.00', 'password' => $pwd]);
    [$f5] = guardCode(fn () => $mkt->feeConfigUpdate());
    check('财务改费率 → 200', $f5 === 200, "got $f5");

    /* ========== 6. 某藏品寄售监控（86） ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withGet(['id' => (string) $colId, 'page' => '1', 'page_size' => '10'])->withPost([]);
    $cl = $mkt->collectibleListings()->getData();
    check('collectible listings 命中 3 条', (int) ($cl['code'] ?? 0) === 200 && (int) ($cl['data']['total'] ?? 0) === 3,
        var_export($cl['data']['total'] ?? null, true));
    check('price_trend 数组结构', isset($cl['data']['price_trend']) && is_array($cl['data']['price_trend']));

    /* ==================== 转赠 §8.10 ==================== */

    $tr = new TransferController();

    // 转赠场景各用独立藏品（user_collection 唯一键 user_id+collection_id，不可重复建行）
    $mkTrCol = static function (string $name) use ($now, $sfx): int {
        return (int) Db::name('collection')->insertGetId([
            'name' => $name . '_' . $sfx, 'total_num' => 10, 'total_num_show' => 10,
            'sale_price' => '50.00', 'createtime' => $now, 'updatetime' => $now,
        ]);
    };
    $colIdA = $mkTrCol('转赠藏品A');
    $colIdB = $mkTrCol('转赠藏品B');
    $colIdC = $mkTrCol('转赠藏品C');

    // 待接收转赠（status=0）：资产已划转至 receiver（uc 归属=receiver）
    $ucId = (int) Db::name('user_collection')->insertGetId([
        'user_id' => $uidT2, 'collection_id' => $colIdA, 'num' => 1,
        'sell_num' => 0, 'lock_num' => 0, 'is_collection' => 0, 'can_compound' => 1,
        'utime' => $now, 'createtime' => $now,
    ]);
    $trPending = (int) Db::name('transfer_record')->insertGetId([
        'transfer_no' => 'TR' . $sfx . 'A', 'type' => 1, 'collectible_id' => $colIdA,
        'uc_id' => $ucId, 'sender_id' => $uidT1, 'receiver_id' => $uidT2,
        'status' => 0, 'createtime' => $now - 60,
    ]);
    // 已完成转赠（status=1）：藏品归属 receiver
    $ucId2 = (int) Db::name('user_collection')->insertGetId([
        'user_id' => $uidT2, 'collection_id' => $colIdB, 'num' => 1,
        'sell_num' => 0, 'lock_num' => 0, 'is_collection' => 0, 'can_compound' => 1,
        'utime' => $now, 'createtime' => $now,
    ]);
    $trDone = (int) Db::name('transfer_record')->insertGetId([
        'transfer_no' => 'TR' . $sfx . 'B', 'type' => 1, 'collectible_id' => $colIdB,
        'uc_id' => $ucId2, 'sender_id' => $uidT1, 'receiver_id' => $uidT2,
        'status' => 1, 'createtime' => $now - 30, 'confirm_time' => $now - 20,
    ]);

    /* ========== 7. 转赠列表/详情（87/88） ========== */

    $app->request->withGet(['page' => '1', 'page_size' => '10', 'sender_id' => (string) $uidT1]);
    $tl = $tr->list()->getData();
    check('transfers sender 筛选命中 2', (int) ($tl['code'] ?? 0) === 200 && (int) ($tl['data']['total'] ?? 0) === 2,
        var_export($tl['data']['total'] ?? null, true));
    $raw = json_encode($tl, JSON_UNESCAPED_UNICODE);
    check('transfers 手机号脱敏', !str_contains($raw, $phT2)
        && str_contains($raw, substr($phT2, 0, 3) . '****' . substr($phT2, -4)));

    $app->request->withGet(['id' => (string) $trPending]);
    $td = $tr->detail()->getData();
    check('transfer detail 字段', (int) ($td['code'] ?? 0) === 200
        && ($td['data']['transfer_no'] ?? '') === 'TR' . $sfx . 'A'
        && (int) ($td['data']['status'] ?? -1) === 0);

    /* ========== 8. 撤销转赠（89，资产真实回滚） ========== */

    $app->request->withGet(['id' => (string) $trPending])->withPost(['reason' => '契约撤销']);
    [$rv1] = guardCode(fn () => $tr->revoke());
    check('revoke 缺密码 → 400', $rv1 === 400, "got $rv1");
    $app->request->withPost(['reason' => '契约撤销', 'password' => $pwd]);
    [$rv2] = guardCode(fn () => $tr->revoke());
    check('revoke → 200', $rv2 === 200, "got $rv2");
    check('status→3 + revoke 留痕', (int) Db::name('transfer_record')->where('id', $trPending)->value('status') === 3
        && Db::name('transfer_record')->where('id', $trPending)->value('revoke_reason') === '契约撤销');
    $ucAfter = Db::name('user_collection')->where('id', $ucId)->find();
    check('资产回滚至 sender', (int) $ucAfter['user_id'] === $uidT1, "owner={$ucAfter['user_id']}");
    [$rv3] = guardCode(fn () => $tr->revoke());
    check('重复 revoke → 409', $rv3 === 409, "got $rv3");

    /* ========== 9. 强制回退（90，已完成转赠） ========== */

    $app->request->withGet(['id' => (string) $trDone])->withPost(['reason' => '契约回退', 'password' => $pwd]);
    [$cc1] = guardCode(fn () => $tr->cancel());
    check('cancel 已完成单 → 200', $cc1 === 200, "got $cc1");
    check('status→4', (int) Db::name('transfer_record')->where('id', $trDone)->value('status') === 4);
    $uc2After = Db::name('user_collection')->where('id', $ucId2)->find();
    check('资产回退至 sender', (int) $uc2After['user_id'] === $uidT1);
    [$cc2] = guardCode(fn () => $tr->cancel());
    check('重复 cancel → 409', $cc2 === 409, "got $cc2");

    // 接收方已二次流转（挂单）→ 409
    $ucId3 = (int) Db::name('user_collection')->insertGetId([
        'user_id' => $uidT2, 'collection_id' => $colIdC, 'num' => 1,
        'sell_num' => 0, 'lock_num' => 0, 'is_collection' => 0, 'can_compound' => 1,
        'utime' => $now, 'createtime' => $now,
    ]);
    $trDone2 = (int) Db::name('transfer_record')->insertGetId([
        'transfer_no' => 'TR' . $sfx . 'C', 'type' => 1, 'collectible_id' => $colIdC,
        'uc_id' => $ucId3, 'sender_id' => $uidT1, 'receiver_id' => $uidT2,
        'status' => 1, 'createtime' => $now - 30, 'confirm_time' => $now - 20,
    ]);
    Db::name('market_goods')->insert([
        'user_id' => $uidT2, 'collection_id' => $colIdC, 'collection_code' => 'TRF' . $sfx,
        'price' => '99.00', 'num' => 1, 'sold_num' => 0, 'status' => 1,
        'createtime' => $now, 'updatetime' => $now,
    ]);
    $app->request->withGet(['id' => (string) $trDone2])->withPost(['reason' => 'x', 'password' => $pwd]);
    [$cc3] = guardCode(fn () => $tr->cancel());
    check('接收方已挂单 cancel → 409', $cc3 === 409, "got $cc3");
    check('挂单流转保护：资产未动', (int) Db::name('user_collection')->where('id', $ucId3)->value('user_id') === $uidT2);

    /* ========== 10. 转赠统计（91） ========== */

    $app->request->withGet(['days' => '7', 'sender_id' => (string) $uidT1])->withPost([]);
    $st = $tr->stats()->getData();
    check('stats 结构与计数', (int) ($st['code'] ?? 0) === 200
        && (int) ($st['data']['total_count'] ?? 0) === 3
        && (int) ($st['data']['total_collectibles'] ?? 0) === 3
        && (int) ($st['data']['total_blindboxes'] ?? 0) === 0
        && is_array($st['data']['daily_stats'] ?? null) && count($st['data']['daily_stats']) === 7,
        var_export($st['data'] ?? null, true));

    /* ========== 11. 异常转赠监控（92，高频发送者） ========== */

    // 同一 sender 7 天内再补 4 条（共 5 条，含已撤销/回退态均计入）
    for ($i = 0; $i < 4; $i++) {
        Db::name('transfer_record')->insert([
            'transfer_no' => 'TR' . $sfx . 'H' . $i, 'type' => 1, 'collectible_id' => $colId,
            'uc_id' => 0, 'sender_id' => $uidT1, 'receiver_id' => $uidT2,
            'status' => 1, 'createtime' => $now - 3600 * $i,
        ]);
    }
    $app->request->withGet(['page' => '1', 'page_size' => '20']);
    $ab = $tr->abnormal()->getData();
    $hitHigh = false;
    foreach ($ab['data']['list'] ?? [] as $r) {
        if (($r['kind'] ?? '') === 'high_frequency_sender' && (int) ($r['user_id'] ?? 0) === $uidT1) {
            $hitHigh = true;
        }
    }
    check('abnormal 高频发送者命中', (int) ($ab['code'] ?? 0) === 200 && $hitHigh, var_export($ab['data'] ?? null, true));
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 market+transfers: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
