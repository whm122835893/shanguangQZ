<?php
declare(strict_types=1);

/**
 * C 端规则联动契约测试 —— 资格购 / 优先购 / 寄售开关 / 寄售限价 / 转赠开关
 *
 * 规格（司南 v3.1 §3.2~3.5）：
 *   1. 资格购三步判定（后端强制）：优先购资格绕过 → 资格购开关 → 条件判定
 *      （A 白名单手机号 / B 持资格藏品 / C 累计签到 / D 累计邀请，
 *       condition_type 1=任一 2=全部，有效期约束）
 *   2. 优先购资格：expires_at > now 且 used_quantity < max_quantity
 *   3. 寄售开关：collection.is_resaleable=0 → 挂单拒绝（C 端 entrust）
 *   4. 寄售限价：resale_price_mode=1 → 挂单价必须落在 [min, max] 闭区间
 *   5. 转赠开关：C 端 collectionDetail.product.weither_regift === is_transferable
 *   6. per_user_limit：用户已购数量达限 → 购买拒绝
 *
 * 用法：php tools/contract/api_rule_enforcement_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\model\UserToken;
use app\service\EligibilityService;
use think\facade\Db;

const ARE_OK   = '[OK]  ';
const ARE_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        echo ARE_OK . $label . "\n";
        $passed++;
        return;
    }
    echo ARE_FAIL . $label . "\n";
    if ($detail !== '') {
        echo '       ' . $detail . "\n";
    }
    $failed++;
}

/* ==================== fixtures ==================== */

$sfx = (string) random_int(100000, 999999);
$now = time();

$mkUser = static function (string $tag) use ($now, $sfx): array {
    $mobile = '197' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    $uid = (int) Db::name('user')->insertGetId([
        'username' => 'are_' . $tag . '_' . $sfx, 'nickname' => strtoupper($tag), 'mobile' => $mobile,
        'money' => '0.00', 'status' => 'normal', 'inviter_code' => 'A' . $sfx . strtoupper($tag),
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    $plainToken = 'are-token-' . $tag . '-' . $sfx;
    Db::name('user_token')->insert([
        'user_id' => $uid, 'token' => UserToken::hash($plainToken),
        'device' => 'test', 'ip' => '127.0.0.1',
        'expiretime' => $now + 3600, 'createtime' => $now,
    ]);
    return [$uid, $mobile, $plainToken];
};
[$uidBuyer, $phBuyer, $tkBuyer] = $mkUser('buyer');   // 白名单 + 签到 + 邀请满足者
[$uidHolder, $phHolder, $tkHolder] = $mkUser('holdr'); // 持资格藏品者
[$uidNoone, $phNoone, $tkNoone] = $mkUser('noone');   // 什么都不满足

$mkCollection = static function (string $name, array $extra = []) use ($now): int {
    // 🔴 collection 无 circulate 列：库存池 = total_num - reserved - sold - airdropped - destroyed
    $data = array_merge([
        'name' => $name, 'total_num' => 100, 'sale_price' => '10.00',
        'is_transferable' => 1, 'is_resaleable' => 1, 'resale_price_mode' => 0,
        'is_qualification_enabled' => 0, 'per_user_limit' => 0,
        'createtime' => $now, 'updatetime' => $now,
    ], $extra);
    return (int) Db::name('collection')->insertGetId($data);
};
// 资格藏品（买它解锁资格）
$gateId = $mkCollection('ARE_G_' . $sfx);
// 目标藏品：开启资格购 + 限价 + 限购 2
$targetId = $mkCollection('ARE_T_' . $sfx, [
    'is_qualification_enabled' => 1, 'per_user_limit' => 2,
    'resale_price_mode' => 1, 'resale_price_min' => '5.00', 'resale_price_max' => '20.00',
]);
// 关闭寄售的藏品
$noResaleId = $mkCollection('ARE_N_' . $sfx, ['is_resaleable' => 0]);
// 不可转赠藏品
$noTransferId = $mkCollection('ARE_X_' . $sfx, ['is_transferable' => 0]);

// buyer 持有目标藏品（测试 per_user_limit 与寄售）
Db::name('user_collection')->insert([
    'user_id' => $uidBuyer, 'collection_id' => $targetId, 'num' => 3, 'sell_num' => 0, 'lock_num' => 0,
    'is_collection' => 0, 'can_compound' => 1, 'utime' => $now, 'createtime' => $now,
]);
// buyer 持有关闭寄售藏品
Db::name('user_collection')->insert([
    'user_id' => $uidBuyer, 'collection_id' => $noResaleId, 'num' => 1, 'sell_num' => 0, 'lock_num' => 0,
    'is_collection' => 0, 'can_compound' => 1, 'utime' => $now, 'createtime' => $now,
]);
// buyer 持有不可转赠藏品
Db::name('user_collection')->insert([
    'user_id' => $uidBuyer, 'collection_id' => $noTransferId, 'num' => 1, 'sell_num' => 0, 'lock_num' => 0,
    'is_collection' => 0, 'can_compound' => 1, 'utime' => $now, 'createtime' => $now,
]);
// holder 持有资格藏品（条件 B）
Db::name('user_collection')->insert([
    'user_id' => $uidHolder, 'collection_id' => $gateId, 'num' => 1, 'sell_num' => 0, 'lock_num' => 0,
    'is_collection' => 0, 'can_compound' => 1, 'utime' => $now, 'createtime' => $now,
]);
// buyer 已购 2 件目标藏品（per_user_limit=2 → 已达限）
Db::name('artel_order')->insert([
    'order_sn' => 'ARERE' . $sfx . '1', 'user_id' => $uidBuyer, 'order_type' => 1, 'order_status' => 2,
    'total_price' => '20.00', 'num' => 2, 'goods_id' => $targetId, 'goods_type' => 1,
    'source' => 'release', 'pay_channel' => 'alipay', 'pay_time' => $now,
    'createtime' => $now, 'updatetime' => $now,
]);
// buyer 签到 3 天（条件 C）
foreach ([0, 86400, 172800] as $off) {
    Db::name('check_in_record')->insert([
        'user_id' => $uidBuyer, 'checkin_date' => date('Y-m-d', $now - $off),
        'successions' => 3, 'reward_status' => 1, 'createtime' => $now - $off,
    ]);
}
// buyer 邀请 2 人（条件 D）
foreach ([1, 2] as $i) {
    Db::name('invite_record')->insert([
        'inviter_id' => $uidBuyer, 'invitee_id' => 0, 'invitee_phone' => '1970000' . $i . $sfx,
        'status' => 1, 'createtime' => $now,
    ]);
}
// 资格购配置：condition_type=1（任一），条件 B/C/D 齐配 + 白名单(buyer)
$cfgId = (int) Db::name('qualification_config')->insertGetId([
    'collectible_id' => $targetId, 'is_enabled' => 1,
    'required_collectible_ids' => json_encode([$gateId]),
    'required_checkin_days' => 3, 'required_invite_count' => 2,
    'condition_type' => 1, 'valid_start_at' => $now - 3600, 'valid_end_at' => $now + 3600,
    'createtime' => $now, 'updatetime' => $now,
]);
Db::name('collection')->where('id', $targetId)->update(['qualification_config_id' => $cfgId]);
Db::name('qualification_whitelist')->insert([
    'config_id' => $cfgId, 'user_id' => $uidBuyer, 'phone' => $phBuyer,
    'status' => 1, 'expires_at' => $now + 3600, 'createtime' => $now, 'updatetime' => $now,
]);
// 优先购活动 + 白名单（holder 有效；buyer 已用尽）
$actId = (int) Db::name('priority_sale_activity')->insertGetId([
    'collectible_id' => $targetId, 'start_time' => $now - 3600, 'end_time' => $now + 3600,
    'max_quantity_per_user' => 1, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
]);
Db::name('priority_sale_whitelist')->insert([
    'activity_id' => $actId, 'collectible_id' => $targetId, 'user_id' => $uidHolder,
    'phone' => $phHolder, 'max_quantity' => 1, 'used_quantity' => 0,
    'expires_at' => $now + 3600, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
]);
Db::name('priority_sale_whitelist')->insert([
    'activity_id' => $actId, 'collectible_id' => $targetId, 'user_id' => $uidBuyer,
    'phone' => $phBuyer, 'max_quantity' => 1, 'used_quantity' => 1,
    'expires_at' => $now + 3600, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
]);

/* ==================== 1. 资格购判定（service 层） ==================== */

// 未开启资格购 → 无门槛
[$ok, $msg] = EligibilityService::canBuy($uidNoone, $gateId);
check('未开启资格购 → 可购买', $ok === true, "msg=$msg");

// 开启 + 什么都不满足 → 拒
[$ok, $msg] = EligibilityService::canBuy($uidNoone, $targetId);
check('开启资格购无资格 → 拒「未获得购买资格」', $ok === false && str_contains($msg, '购买资格'), "ok=$ok msg=$msg");

// 条件 A：白名单手机号
[$ok, $msg] = EligibilityService::canBuy($uidBuyer, $targetId);
check('白名单命中 → 可购买', $ok === true, "msg=$msg");

// 条件 B：持资格藏品（holder 无白名单但持 gate）
Db::name('qualification_whitelist')->where('config_id', $cfgId)->where('user_id', $uidBuyer)->delete();
[$ok, $msg] = EligibilityService::canBuy($uidHolder, $targetId);
check('持资格藏品 → 可购买', $ok === true, "msg=$msg");

// 条件 C：累计签到（给 buyer 补签到，保持其可测 D 前置状态）
[$ok, $msg] = EligibilityService::canBuy($uidBuyer, $targetId);
check('累计签到天数达标 → 可购买', $ok === true, "msg=$msg");

// 条件 D：累计邀请
[$ok, $msg] = EligibilityService::canBuy($uidBuyer, $targetId);
check('累计邀请数达标 → 可购买', $ok === true, "msg=$msg");

// condition_type=2（全部）：只满足签到+邀请（无白名单/无 gate 持仓）→ 拒
Db::name('qualification_config')->where('id', $cfgId)->update(['condition_type' => 2]);
Db::name('user_collection')->where('user_id', $uidBuyer)->where('collection_id', $gateId)->delete();
[$ok, $msg] = EligibilityService::canBuy($uidBuyer, $targetId);
check('全部模式未全满足 → 拒', $ok === false, "ok=$ok msg=$msg");
Db::name('qualification_config')->where('id', $cfgId)->update(['condition_type' => 1]);

// 有效期外 → 拒（valid_end_at 过去）
Db::name('qualification_config')->where('id', $cfgId)->update(['valid_end_at' => $now - 10]);
[$ok, $msg] = EligibilityService::canBuy($uidBuyer, $targetId);
check('资格购有效期已过 → 拒', $ok === false, "ok=$ok msg=$msg");
Db::name('qualification_config')->where('id', $cfgId)->update(['valid_end_at' => $now + 3600]);

// 优先购绕过：holder 无条件 B 之外满足项？——holder 持 gate 已可买；改为测 buyer（used=max 不绕过）
// buyer 优先购白名单 used=1/max=1 → 不绕过；buyer 也不满足资格购条件（上面已清白名单、gate 持仓删了）
// 为了测「优先购有效 → 绕过」，给 holder 删 gate 持仓，仅留优先购资格
Db::name('user_collection')->where('user_id', $uidHolder)->where('collection_id', $gateId)->delete();
[$ok, $msg] = EligibilityService::canBuy($uidHolder, $targetId);
check('优先购资格有效 → 绕过资格购', $ok === true, "ok=$ok msg=$msg");

// 优先购用尽（buyer used=1/max=1）→ 不绕过，回退资格购判定（buyer 签到+邀请满足 → 仍可买）
Db::name('user_collection')->where('user_id', $uidBuyer)->where('collection_id', $targetId)->delete();
Db::name('user_collection')->insert([
    'user_id' => $uidBuyer, 'collection_id' => $targetId, 'num' => 3, 'sell_num' => 0, 'lock_num' => 0,
    'is_collection' => 0, 'can_compound' => 1, 'utime' => $now, 'createtime' => $now,
]);
[$ok, $msg] = EligibilityService::canBuy($uidBuyer, $targetId);
check('优先购用尽 → 回退资格购条件', $ok === true, "ok=$ok msg=$msg");

// per_user_limit：已购 2 / 限 2 → 拒
[$ok, $msg] = EligibilityService::checkPerUserLimit($uidBuyer, $targetId);
check('per_user_limit 达限 → 拒', $ok === false, "ok=$ok msg=$msg");

// per_user_limit：未达限 → ok
Db::name('artel_order')->where('id', (int) Db::name('artel_order')->where('order_sn', 'ARERE' . $sfx . '1')->value('id'))->update(['num' => 1]);
[$ok, $msg] = EligibilityService::checkPerUserLimit($uidBuyer, $targetId);
check('per_user_limit 未达限 → 可购买', $ok === true, "ok=$ok msg=$msg");

/* ==================== 2. 寄售开关 + 限价（C 端 entrust） ==================== */

$trade = new \app\controller\api\TradeController();
$buyerHeader = ['token' => $tkBuyer]; // C 端鉴权走 token header（非 authorization）

// 关闭寄售 → 拒
$ucNoResale = (int) Db::name('user_collection')->where('user_id', $uidBuyer)->where('collection_id', $noResaleId)->value('id');
$app->request->withHeader($buyerHeader)->withGet([])
    ->withPost(['user_collection_id' => (string) $ucNoResale, 'price' => '10.00', 'num' => '1']);
$er = $trade->entrust()->getData();
check('寄售开关关闭 → 拒绝挂单', (int) ($er['code'] ?? 1) === 0, var_export($er, true));

// 限价越界（< min）→ 拒
$ucTarget = (int) Db::name('user_collection')->where('user_id', $uidBuyer)->where('collection_id', $targetId)->value('id');
$app->request->withPost(['user_collection_id' => (string) $ucTarget, 'price' => '3.00', 'num' => '1']);
$er = $trade->entrust()->getData();
check('挂单价低于限价下限 → 拒', (int) ($er['code'] ?? 1) === 0, var_export($er, true));

// 限价越界（> max）→ 拒
$app->request->withPost(['user_collection_id' => (string) $ucTarget, 'price' => '30.00', 'num' => '1']);
$er = $trade->entrust()->getData();
check('挂单价高于限价上限 → 拒', (int) ($er['code'] ?? 1) === 0, var_export($er, true));

// 区间内 → 成功
$app->request->withPost(['user_collection_id' => (string) $ucTarget, 'price' => '10.00', 'num' => '1']);
$er = $trade->entrust()->getData();
check('挂单价在限价区间内 → 成功', (int) ($er['code'] ?? 0) === 1, var_export($er, true));
check('挂单落库 market_goods', Db::name('market_goods')->where('collection_id', $targetId)->where('price', '10.00')->count() === 1);
$mgId = (int) Db::name('market_goods')->where('collection_id', $targetId)->where('price', '10.00')->value('id');
if ($mgId > 0) {
    Db::name('market_goods')->where('id', $mgId)->delete();
}
Db::name('artel_order')->where('uc_ids', (string) $ucTarget)->delete();
Db::name('user_collection')->where('id', $ucTarget)->update(['sell_num' => 0]);

/* ==================== 3. 转赠开关（C 端 collectionDetail） ==================== */

$ucNoTransfer = (int) Db::name('user_collection')->where('user_id', $uidBuyer)->where('collection_id', $noTransferId)->value('id');
$app->request->withHeader($buyerHeader)->withGet(['ids' => (string) $ucNoTransfer])->withPost([]);
$cd = $trade->collectionDetail()->getData();
check('转赠开关关闭 → weither_regift=0', (int) ($cd['data']['product']['weither_regift'] ?? -1) === 0, var_export($cd, true));

$ucTarget2 = (int) Db::name('user_collection')->where('user_id', $uidBuyer)->where('collection_id', $targetId)->value('id');
$app->request->withGet(['ids' => (string) $ucTarget2])->withPost([]);
$cd = $trade->collectionDetail()->getData();
check('转赠开关开启 → weither_regift=1', (int) ($cd['data']['product']['weither_regift'] ?? -1) === 1, var_export($cd, true));

/* ==================== cleanup ==================== */

foreach ([
    ['user', [['id', $uidBuyer], ['id', $uidHolder], ['id', $uidNoone]]],
    ['collection', [['id', $gateId], ['id', $targetId], ['id', $noResaleId], ['id', $noTransferId]]],
    ['qualification_config', [['id', $cfgId]]],
    ['priority_sale_activity', [['id', $actId]]],
] as [$table, $conds]) {
    foreach ($conds as [$col, $val]) {
        Db::name($table)->where($col, $val)->delete();
    }
}

echo "\n===== api rule enforcement: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
