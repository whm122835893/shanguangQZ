<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第七批：营销活动 §8.11（35 接口 / 8 组）
 *
 * 锁定契约：
 *   1. 优先购：priority_sale_activity + priority_sale_whitelist；白名单支持单条/批量导入
 *      （失败逐条回报）；cleanup 仅清理 expires_at 已过期且 status=1 的资格（status→2），
 *      高风险需密码；RBAC：财务无 marketing:* → 403
 *   2. 签到/邀请/注册福利：site_config KV（marketing.checkin / marketing.invite / marketing.register）；
 *      记录走 check_in_record / invite_record，输出手机号脱敏
 *   3. 抽奖：lottery + lottery_prize + lottery_record；奖项概率 ∈ (0,100]，
 *      非空奖概率总和 > 100 → 409；free_chances ↔ lottery.free_number
 *   4. 合成：synthesis_plan（materials → need_items JSON，total_limit → stock）+
 *      synthesis_record（C 端写入目标，管理端只读）；目标藏品必须存在
 *   5. 活动空投：airdrop_task(source=activity) + airdrop_record；
 *      藏品奖励校验库存池（恒等式同藏品模块）、盲盒奖励落 user.blind_box_num；
 *      高风险需密码；成功数回写任务且 airdropped_count 仅按成功数累加
 *   6. 盲盒数据：blind_box_open_record 聚合——total_opened / empty_rate / item_distribution
 *
 * 用法：php tools/contract/admin_v1_marketing_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\admin\v1\MarketingController;
use app\service\admin\AdminException;
use think\facade\Db;

const MKT_OK   = '[OK]  ';
const MKT_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        echo MKT_OK . $label . "\n";
        $passed++;
        return;
    }
    echo MKT_FAIL . $label . "\n";
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
$pwd = 'S3cret-Mktg-#7';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('mkt_super', 1);
$finId   = $mkAdmin('mkt_fin', 3);

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
    check('管理员登录成功', $asSuper !== '' && $asFin !== '');

    $mk = new MarketingController();
    $asSuperHeader = ['authorization' => 'Bearer ' . $asSuper];
    $asFinHeader   = ['authorization' => 'Bearer ' . $asFin];

    // 基础 fixture：用户 / 藏品 / 盲盒
    $mkUser = static function (string $tag) use ($now, $sfx): array {
        $mobile = '193' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $uid = (int) Db::name('user')->insertGetId([
            'username' => $tag . '_' . $sfx, 'nickname' => strtoupper($tag),
            'mobile' => $mobile, 'money' => '0.00', 'blind_box_num' => 0, 'status' => 'normal',
            'inviter_code' => strtoupper(substr($tag, 0, 2)) . random_int(10000000, 99999999),
            'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
        ]);
        return [$uid, $mobile];
    };
    [$uidU1, $phU1] = $mkUser('mktu1');
    [$uidU2, $phU2] = $mkUser('mktu2');

    $colId = (int) Db::name('collection')->insertGetId([
        'name' => '营销藏品_' . $sfx, 'total_num' => 10, 'total_num_show' => 10,
        'sale_price' => '88.00', 'createtime' => $now, 'updatetime' => $now,
    ]);

    /* ==================== 8.11.1 优先购（93~102） ==================== */

    // RBAC：财务无 marketing:*
    $app->request->withHeader($asFinHeader)
        ->withPost(['collectible_id' => (string) $colId, 'start_time' => '2026-10-21 10:00:00', 'end_time' => '2026-10-22 10:00:00']);
    [$p0] = guardCode(fn () => $mk->priorityCreate());
    check('财务建优先购 → 403', $p0 === 403, "got $p0");

    $app->request->withHeader($asSuperHeader)
        ->withPost(['collectible_id' => (string) $colId, 'start_time' => '2026-10-21 10:00:00', 'end_time' => '2026-10-22 10:00:00', 'max_quantity_per_user' => '2']);
    $pc = $mk->priorityCreate()->getData();
    $prioId = (int) ($pc['data']['id'] ?? 0);
    check('创建优先购活动 → id', (int) ($pc['code'] ?? 0) === 200 && $prioId > 0, var_export($pc, true));

    $app->request->withGet(['id' => (string) $prioId])->withPost([]);
    $pd = $mk->priorityDetail()->getData();
    check('优先购详情 ISO 时间', (int) ($pd['code'] ?? 0) === 200
        && ($pd['data']['start_time'] ?? '') === '2026-10-21T10:00:00+08:00'
        && (int) ($pd['data']['max_quantity_per_user'] ?? 0) === 2,
        var_export($pd['data'] ?? null, true));

    $app->request->withGet(['page' => '1', 'page_size' => '10', 'collectible_id' => (string) $colId]);
    $pl = $mk->priorityList()->getData();
    check('优先购列表 collectible 筛选 ≥1', (int) ($pl['code'] ?? 0) === 200 && (int) ($pl['data']['total'] ?? 0) >= 1);

    $app->request->withGet(['id' => (string) $prioId])->withPost(['status' => '0']);
    [$pe] = guardCode(fn () => $mk->priorityEdit());
    check('编辑优先购（停用）→ 200', $pe === 200, "got $pe");
    check('停用落库', (int) Db::name('priority_sale_activity')->where('id', $prioId)->value('status') === 0);
    $app->request->withPost(['status' => '1']);
    guardCode(fn () => $mk->priorityEdit());

    // 白名单
    $app->request->withGet(['id' => (string) $prioId])->withPost([
        'user_id' => (string) $uidU1, 'phone' => $phU1, 'max_quantity' => '3', 'expires_at' => (string) ($now + 3600),
    ]);
    $wa = $mk->whitelistAdd()->getData();
    $wid1 = (int) Db::name('priority_sale_whitelist')->where('activity_id', $prioId)->where('user_id', $uidU1)->value('id');
    check('白名单添加', (int) ($wa['code'] ?? 0) === 200 && $wid1 > 0, var_export($wa, true));
    check('白名单冗余 collectible_id', (int) Db::name('priority_sale_whitelist')->where('id', $wid1)->value('collectible_id') === $colId);

    // 批量导入：1 条有效 + 1 条用户不存在
    $app->request->withPost(['items' => [
        ['user_id' => (string) $uidU2, 'phone' => $phU2, 'max_quantity' => '1', 'expires_at' => (string) ($now + 3600)],
        ['user_id' => '0', 'phone' => '19900000000', 'max_quantity' => '1', 'expires_at' => '0'],
    ]]);
    $wb = $mk->whitelistBatch()->getData();
    check('批量导入 success/fail 计数', (int) ($wb['code'] ?? 0) === 200
        && (int) ($wb['data']['success_count'] ?? 0) === 1 && (int) ($wb['data']['fail_count'] ?? 0) === 1
        && is_array($wb['data']['fail_list'] ?? null) && count($wb['data']['fail_list']) === 1,
        var_export($wb, true));

    $app->request->withGet(['id' => (string) $prioId, 'page' => '1', 'page_size' => '10']);
    $wl = $mk->whitelistList()->getData();
    check('白名单列表 total=2', (int) ($wl['code'] ?? 0) === 200 && (int) ($wl['data']['total'] ?? 0) === 2,
        var_export($wl['data']['total'] ?? null, true));

    $wid2 = (int) Db::name('priority_sale_whitelist')->where('activity_id', $prioId)->where('user_id', $uidU2)->value('id');
    $app->request->withGet(['id' => (string) $prioId, 'wid' => (string) $wid2])->withPost(['max_quantity' => '5']);
    [$we] = guardCode(fn () => $mk->whitelistEdit());
    check('白名单编辑 → 200', $we === 200, "got $we");
    check('白名单编辑落库', (int) Db::name('priority_sale_whitelist')->where('id', $wid2)->value('max_quantity') === 5);

    // cleanup：只清过期资格。U2 资格改为已过期；U1 未过期
    Db::name('priority_sale_whitelist')->where('id', $wid2)->update(['expires_at' => $now - 100]);
    $app->request->withPost([]);
    [$cl0] = guardCode(fn () => $mk->priorityCleanup());
    check('cleanup 缺密码 → 400', $cl0 === 400, "got $cl0");
    $app->request->withPost(['password' => $pwd]);
    $cl = $mk->priorityCleanup()->getData();
    check('cleanup → cleaned_count=1', (int) ($cl['code'] ?? 0) === 200 && (int) ($cl['data']['cleaned_count'] ?? 0) === 1,
        var_export($cl, true));
    check('cleanup 状态→2 且未动未过期', (int) Db::name('priority_sale_whitelist')->where('id', $wid2)->value('status') === 2
        && (int) Db::name('priority_sale_whitelist')->where('id', $wid1)->value('status') === 1);

    $app->request->withGet(['id' => (string) $prioId, 'wid' => (string) $wid1])->withPost([]);
    [$wd] = guardCode(fn () => $mk->whitelistDelete());
    check('白名单删除 → 200', $wd === 200, "got $wd");
    check('白名单删除落库', Db::name('priority_sale_whitelist')->where('id', $wid1)->find() === null);

    /* ==================== 8.11.2 签到（103~105） ==================== */

    $app->request->withGet([])->withPost([]);
    $cc = $mk->checkinConfig()->getData();
    check('签到配置读种子', (int) ($cc['code'] ?? 0) === 200
        && isset($cc['data']['is_enabled']) && is_array($cc['data']['rewards'] ?? null) && $cc['data']['rewards'] !== [],
        var_export($cc, true));

    $app->request->withPost(['is_enabled' => '1', 'rewards' => [
        ['day' => '1', 'amount' => '0.60'], ['day' => '2', 'amount' => '0.80'], ['day' => '7', 'amount' => '5.00'],
    ]]);
    [$cu] = guardCode(fn () => $mk->checkinConfigUpdate());
    check('签到配置更新 → 200', $cu === 200, "got $cu");
    $cfg = json_decode((string) Db::name('site_config')->where('key', 'marketing.checkin')->value('value'), true);
    check('签到配置落库', (int) ($cfg['is_enabled'] ?? 0) === 1 && ($cfg['rewards'][0]['amount'] ?? '') === '0.60');

    Db::name('check_in_record')->insert([
        'user_id' => $uidU1, 'checkin_date' => date('Y-m-d', $now), 'successions' => 3,
        'reward_type' => 'points', 'reward_status' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'user_id' => (string) $uidU1]);
    $cr = $mk->checkinRecords()->getData();
    $rawCr = json_encode($cr, JSON_UNESCAPED_UNICODE);
    check('签到记录命中且手机号脱敏', (int) ($cr['code'] ?? 0) === 200 && (int) ($cr['data']['total'] ?? 0) >= 1
        && !str_contains($rawCr, $phU1)
        && str_contains($rawCr, substr($phU1, 0, 3) . '****' . substr($phU1, -4)));

    /* ==================== 8.11.3 邀请（106~108） ==================== */

    $app->request->withGet([]);
    $ic = $mk->inviteConfig()->getData();
    check('邀请配置读种子', (int) ($ic['code'] ?? 0) === 200
        && is_array($ic['data']['tiers'] ?? null) && ($ic['data']['register_reward'] ?? '') !== '');

    $app->request->withPost(['is_enabled' => '1', 'tiers' => [
        ['level' => '1', 'reward' => '3.00'], ['level' => '2', 'reward' => '1.50'],
    ], 'register_reward' => '2.00']);
    [$iu] = guardCode(fn () => $mk->inviteConfigUpdate());
    check('邀请配置更新 → 200', $iu === 200, "got $iu");

    Db::name('invite_record')->insert([
        'inviter_id' => $uidU1, 'invitee_id' => $uidU2, 'invitee_phone' => $phU2,
        'reward_type' => 'points', 'status' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'inviter_id' => (string) $uidU1]);
    $ir = $mk->inviteRecords()->getData();
    $rawIr = json_encode($ir, JSON_UNESCAPED_UNICODE);
    check('邀请记录命中且脱敏', (int) ($ir['code'] ?? 0) === 200 && (int) ($ir['data']['total'] ?? 0) >= 1
        && !str_contains($rawIr, $phU2)
        && str_contains($rawIr, substr($phU2, 0, 3) . '****' . substr($phU2, -4)));

    /* ==================== 8.11.4 抽奖（109~117） ==================== */

    $app->request->withPost(['name' => '契约抽奖_' . $sfx, 'description' => '契约说明',
        'start_time' => '2026-10-21 10:00:00', 'end_time' => '2026-10-31 10:00:00',
        'free_chances' => '3', 'exchange_config' => ['points_per_draw' => 100]]);
    $lc = $mk->luckyCreate()->getData();
    $luckyId = (int) ($lc['data']['id'] ?? 0);
    check('创建抽奖活动 → id', (int) ($lc['code'] ?? 0) === 200 && $luckyId > 0, var_export($lc, true));

    $app->request->withGet(['id' => (string) $luckyId]);
    $ld = $mk->luckyDetail()->getData();
    check('抽奖详情字段映射', (int) ($ld['code'] ?? 0) === 200
        && ($ld['data']['name'] ?? '') === '契约抽奖_' . $sfx
        && (int) ($ld['data']['free_chances'] ?? 0) === 3
        && (int) ($ld['data']['exchange_config']['points_per_draw'] ?? 0) === 100
        && ($ld['data']['start_time'] ?? '') === '2026-10-21T10:00:00+08:00',
        var_export($ld['data'] ?? null, true));

    $app->request->withGet(['page' => '1', 'page_size' => '10', 'status' => '1']);
    $ll = $mk->luckyList()->getData();
    check('抽奖列表命中', (int) ($ll['code'] ?? 0) === 200 && (int) ($ll['data']['total'] ?? 0) >= 1);

    $app->request->withGet(['id' => (string) $luckyId])->withPost(['description' => '契约说明v2', 'free_chances' => '5']);
    [$le] = guardCode(fn () => $mk->luckyEdit());
    check('抽奖编辑 → 200', $le === 200, "got $le");
    check('抽奖编辑落库', (int) Db::name('lottery')->where('id', $luckyId)->value('free_number') === 5);

    // 奖项：概率闭环（非空奖概率总和 ≤ 100）
    $app->request->withGet(['id' => (string) $luckyId]);
    $app->request->withPost(['name' => '大奖', 'prize_type' => '1',
        'prize_config' => ['collectible_id' => $colId], 'probability' => '60', 'planned_quantity' => '2', 'status' => '1']);
    [$pa1] = guardCode(fn () => $mk->prizeAdd());
    check('奖项添加 → 200', $pa1 === 200, "got $pa1");
    $prizeId = (int) Db::name('lottery_prize')->where('lottery_id', $luckyId)->where('name', '大奖')->value('id');
    check('奖项落库（非空奖标记）', $prizeId > 0
        && (int) Db::name('lottery_prize')->where('id', $prizeId)->value('is_empty_prize') === 0);

    $app->request->withPost(['name' => '二等奖', 'prize_type' => '1',
        'prize_config' => ['collectible_id' => $colId], 'probability' => '30', 'planned_quantity' => '3', 'status' => '1']);
    [$pa2b] = guardCode(fn () => $mk->prizeAdd());
    check('第二个非空奖 → 200', $pa2b === 200, "got $pa2b");

    $app->request->withPost(['name' => '超概奖', 'prize_type' => '1',
        'prize_config' => ['collectible_id' => $colId], 'probability' => '50', 'planned_quantity' => '1', 'status' => '1']);
    [$pa2] = guardCode(fn () => $mk->prizeAdd());
    check('非空奖概率总和>100 → 409', $pa2 === 409, "got $pa2");

    $app->request->withPost(['name' => '谢谢参与', 'prize_type' => '6',
        'prize_config' => [], 'probability' => '40', 'planned_quantity' => '0', 'status' => '1']);
    [$pa3] = guardCode(fn () => $mk->prizeAdd());
    check('空奖补充 → 200', $pa3 === 200, "got $pa3");

    $app->request->withGet(['id' => (string) $luckyId, 'pid' => (string) $prizeId]);
    $app->request->withPost(['probability' => '80']);
    [$pue] = guardCode(fn () => $mk->prizeEdit());
    check('奖项编辑概率总和>100 → 409', $pue === 409, "got $pue");
    $app->request->withPost(['probability' => '35', 'name' => '特等奖']);
    [$pue2] = guardCode(fn () => $mk->prizeEdit());
    check('奖项编辑 → 200', $pue2 === 200, "got $pue2");

    $app->request->withGet(['id' => (string) $luckyId]);
    $pz = $mk->prizeList()->getData();
    check('奖项池 3 条', (int) ($pz['code'] ?? 0) === 200 && count($pz['data']['list'] ?? []) === 3,
        var_export($pz, true));

    Db::name('lottery_record')->insert([
        'lottery_id' => $luckyId, 'user_id' => $uidU1, 'collection_id' => $colId,
        'lottery_lv' => '特等奖', 'score' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['id' => (string) $luckyId, 'page' => '1', 'page_size' => '10']);
    $lr = $mk->luckyRecords()->getData();
    $rawLr = json_encode($lr, JSON_UNESCAPED_UNICODE);
    check('抽奖记录命中且脱敏', (int) ($lr['code'] ?? 0) === 200 && (int) ($lr['data']['total'] ?? 0) >= 1
        && !str_contains($rawLr, $phU1));

    $app->request->withGet(['id' => (string) $luckyId, 'pid' => (string) $prizeId])->withPost([]);
    [$pdel] = guardCode(fn () => $mk->prizeDelete());
    check('奖项删除 → 200', $pdel === 200, "got $pdel");
    check('奖项删除落库', Db::name('lottery_prize')->where('id', $prizeId)->find() === null);

    /* ==================== 8.11.5 合成（118~122） ==================== */

    $app->request->withPost(['name' => '契约合成_' . $sfx, 'target_collectible_id' => (string) $colId,
        'materials' => [['collectible_id' => $colId, 'num' => 2]],
        'per_user_limit' => '1', 'total_limit' => '5',
        'start_time' => '2026-10-21 10:00:00', 'end_time' => '2026-10-30 10:00:00']);
    $sc = $mk->synthesisCreate()->getData();
    $synId = (int) ($sc['data']['id'] ?? 0);
    check('创建合成活动 → id', (int) ($sc['code'] ?? 0) === 200 && $synId > 0, var_export($sc, true));

    $app->request->withPost(['name' => '坏目标_' . $sfx, 'target_collectible_id' => '99999999',
        'start_time' => '2026-10-21 10:00:00', 'end_time' => '2026-10-30 10:00:00']);
    [$sx] = guardCode(fn () => $mk->synthesisCreate());
    check('合成目标藏品不存在 → 404', $sx === 404, "got $sx");

    $app->request->withGet(['id' => (string) $synId]);
    $sd = $mk->synthesisDetail()->getData();
    check('合成详情字段映射', (int) ($sd['code'] ?? 0) === 200
        && (int) ($sd['data']['target_collectible_id'] ?? 0) === $colId
        && (int) ($sd['data']['total_limit'] ?? 0) === 5
        && is_array($sd['data']['materials'] ?? null) && ($sd['data']['materials'][0]['num'] ?? 0) === 2,
        var_export($sd['data'] ?? null, true));

    $app->request->withPost(['per_user_limit' => '2', 'status' => '1']);
    [$se] = guardCode(fn () => $mk->synthesisEdit());
    check('合成编辑 → 200', $se === 200, "got $se");

    Db::name('synthesis_record')->insert([
        'plan_id' => $synId, 'user_id' => $uidU1, 'materials' => json_encode([['collectible_id' => $colId, 'num' => 2]]),
        'target_collectible_id' => $colId, 'uc_id' => 0, 'status' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['id' => (string) $synId, 'page' => '1', 'page_size' => '10']);
    $sr = $mk->synthesisRecords()->getData();
    check('合成记录命中', (int) ($sr['code'] ?? 0) === 200 && (int) ($sr['data']['total'] ?? 0) >= 1);

    /* ==================== 8.11.6 活动空投（123~124） ==================== */

    // 藏品奖励：缺密码 400
    $app->request->withPost(['activity_type' => 'lucky', 'activity_id' => (string) $luckyId,
        'reward_type' => '1', 'reward_config' => ['collectible_id' => $colId, 'quantity' => '4'],
        'phones' => [$phU1, $phU2, '19900000000']]);
    [$ad0] = guardCode(fn () => $mk->airdrop());
    check('空投缺密码 → 400', $ad0 === 400, "got $ad0");

    // 超库存池 → 409（total_num=10，3 人 ×4=12）
    $app->request->withPost(['activity_type' => 'lucky', 'activity_id' => (string) $luckyId,
        'reward_type' => '1', 'reward_config' => ['collectible_id' => $colId, 'quantity' => '4'],
        'phones' => [$phU1, $phU2, '19900000000'], 'password' => $pwd]);
    [$ad1] = guardCode(fn () => $mk->airdrop());
    check('空投超库存池 → 409', $ad1 === 409, "got $ad1");

    // 正常：2 有效 + 1 无效手机号
    $app->request->withPost(['activity_type' => 'lucky', 'activity_id' => (string) $luckyId,
        'reward_type' => '1', 'reward_config' => ['collectible_id' => $colId, 'quantity' => '2'],
        'phones' => [$phU1, $phU2, '19900000000'], 'password' => $pwd]);
    $ad = $mk->airdrop()->getData();
    check('活动空投 → task_id+计数', (int) ($ad['code'] ?? 0) === 200
        && (int) ($ad['data']['success_count'] ?? 0) === 2 && (int) ($ad['data']['fail_count'] ?? 0) === 1
        && (int) ($ad['data']['task_id'] ?? 0) > 0,
        var_export($ad, true));
    $taskId = (int) ($ad['data']['task_id'] ?? 0);
    check('空投任务 source=activity', (string) Db::name('airdrop_task')->where('id', $taskId)->value('source') === 'activity'
        && (string) Db::name('airdrop_task')->where('id', $taskId)->value('activity_type') === 'lucky');
    check('藏品到账 + airdropped_count 按成功数累加', (int) Db::name('user_collection')->where('user_id', $uidU1)->where('collection_id', $colId)->value('num') === 2
        && (int) Db::name('collection')->where('id', $colId)->value('airdropped_count') === 4);

    // 盲盒奖励：落 user.blind_box_num
    $bbId = (int) Db::name('blind_box')->insertGetId([
        'name' => '营销盲盒_' . $sfx, 'total_num' => 5, 'total_num_show' => 5,
        'sale_number' => 0, 'airdropped_count' => 0, 'destroyed_count' => 0,
        'type' => 'collection', 'goods_type' => '2', 'batch_id' => '', 'status' => 1,
        'open_draw' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
    $app->request->withPost(['activity_type' => 'checkin', 'activity_id' => '0', 'reward_type' => '2',
        'reward_config' => ['blind_box_id' => $bbId, 'quantity' => '1'],
        'phones' => [$phU1], 'password' => $pwd]);
    $adb = $mk->airdrop()->getData();
    check('盲盒空投 → user.blind_box_num', (int) ($adb['code'] ?? 0) === 200 && (int) ($adb['data']['success_count'] ?? 0) === 1
        && (int) Db::name('user')->where('id', $uidU1)->value('blind_box_num') === 1
        && (int) Db::name('blind_box')->where('id', $bbId)->value('airdropped_count') === 1,
        var_export($adb, true));

    $app->request->withGet(['page' => '1', 'page_size' => '20', 'activity_type' => 'lucky', 'activity_id' => (string) $luckyId])->withPost([]);
    $adr = $mk->airdropRecords()->getData();
    $rawAdr = json_encode($adr, JSON_UNESCAPED_UNICODE);
    check('空投记录筛选+计数', (int) ($adr['code'] ?? 0) === 200 && (int) ($adr['data']['total'] ?? 0) === 3,
        var_export($adr['data']['total'] ?? null, true));
    check('空投记录手机号脱敏', !str_contains($rawAdr, $phU2)
        && str_contains($rawAdr, substr($phU2, 0, 3) . '****' . substr($phU2, -4)));

    /* ==================== 8.11.7 注册福利（125~126） ==================== */

    $app->request->withGet([]);
    $rc = $mk->registerConfig()->getData();
    check('注册福利读种子', (int) ($rc['code'] ?? 0) === 200
        && isset($rc['data']['is_enabled']) && ($rc['data']['reward_type'] ?? '') !== '');

    $app->request->withPost(['is_enabled' => '0', 'reward_type' => 'blindbox',
        'reward_config' => ['blind_box_id' => $bbId, 'quantity' => '1']]);
    [$ru] = guardCode(fn () => $mk->registerConfigUpdate());
    check('注册福利更新 → 200', $ru === 200, "got $ru");
    $rcfg = json_decode((string) Db::name('site_config')->where('key', 'marketing.register')->value('value'), true);
    check('注册福利落库', (int) ($rcfg['is_enabled'] ?? 1) === 0 && ($rcfg['reward_type'] ?? '') === 'blindbox');

    /* ==================== 8.11.8 盲盒数据（127） ==================== */

    $item1 = (int) Db::name('blind_box_item')->insertGetId([
        'blind_box_id' => $bbId, 'collectible_id' => $colId, 'probability' => '60.0000',
        'planned_quantity' => 3, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
    Db::name('blind_box_open_record')->insertAll([
        ['blind_box_id' => $bbId, 'user_id' => $uidU1, 'item_id' => $item1, 'collectible_id' => $colId, 'quantity' => 1, 'status' => 1, 'createtime' => $now],
        ['blind_box_id' => $bbId, 'user_id' => $uidU1, 'item_id' => $item1, 'collectible_id' => $colId, 'quantity' => 1, 'status' => 1, 'createtime' => $now],
        ['blind_box_id' => $bbId, 'user_id' => $uidU2, 'item_id' => 0, 'collectible_id' => 0, 'quantity' => 0, 'status' => 2, 'createtime' => $now],
    ]);
    $app->request->withGet(['blindbox_id' => (string) $bbId]);
    $bs = $mk->blindboxStats()->getData();
    check('盲盒数据 total_opened=3', (int) ($bs['code'] ?? 0) === 200 && (int) ($bs['data']['total_opened'] ?? 0) === 3,
        var_export($bs, true));
    check('盲盒数据 empty_rate≈33.33', isset($bs['data']['empty_rate']) && abs((float) $bs['data']['empty_rate'] - 33.33) < 0.01);
    $dist = $bs['data']['item_distribution'] ?? [];
    $hit = false;
    foreach ($dist as $d) {
        if ((int) ($d['collectible_id'] ?? 0) === $colId && (int) ($d['count'] ?? 0) === 2) { $hit = true; }
    }
    check('盲盒数据 item_distribution 命中', $hit && is_array($dist) && count($dist) === 1, var_export($dist, true));
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 marketing: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
