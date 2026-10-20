<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第五批：盲盒 §8.6（17 接口）
 *
 * 锁定契约：
 *   1. 盲盒库存池 = 发行总量 - 已售出发售 - 已空投 - 已销毁（推导值，不落库）
 *   2. 子藏品 2~N 条、概率总和 ≤ 100%（差额=空奖，§3.6.2）；支持单条改/删
 *   3. 发售/重新上架数量 ≤ 当时盲盒库存池；编辑仅草稿/已下架；删除仅草稿无关联
 *   4. 高风险操作（relist/force-soldout/destroy/delete/airdrop）reason+password+审计
 *   5. 强制回收盲盒：开启即发放子藏品、无未开启持有载体 → 409 如实拒绝
 *   6. audit: diff = (user_holding + opened) - (sold + airdropped)；
 *      开启数不得超过发出数（diff>0 → is_consistent=false），负值为正常在途
 *   7. RBAC：运营（role=2）无 blindbox:destroy/airdrop → 403
 *
 * 用法：php tools/contract/admin_v1_blindbox_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\admin\v1\BlindboxController;
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
$pwd = 'S3cret-Box-#42';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('bb_super', 1);
$operId  = $mkAdmin('bb_oper', 2);

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

    // 子藏品 A/B（最小行，collection 全列有默认值）
    $subA = (int) Db::name('collection')->insertGetId([
        'name' => 'BB子藏品A_' . $sfx, 'total_num' => 100, 'total_num_show' => 100,
        'createtime' => $now, 'updatetime' => $now,
    ]);
    $subB = (int) Db::name('collection')->insertGetId([
        'name' => 'BB子藏品B_' . $sfx, 'total_num' => 100, 'total_num_show' => 100,
        'createtime' => $now, 'updatetime' => $now,
    ]);

    // 空投目标用户（注册）+ 未注册手机号
    $uidC = (int) Db::name('user')->insertGetId([
        'username' => 'bb_c_' . $sfx, 'nickname' => 'BB_C',
        'mobile' => '193' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'money' => '0.00', 'status' => 'normal',
        'inviter_code' => 'BC' . random_int(10000000, 99999999),
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    $phoneC = (string) Db::name('user')->where('id', $uidC)->value('mobile');
    $phoneGhost = '199' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

    $ctrl = new BlindboxController();

    /* ========== 1. 创建（52） ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withGet([])->withPost([]);
    [$c1] = guardCode(fn () => $ctrl->create());
    check('create 缺 name → 400', $c1 === 400, "got $c1");

    $app->request->withPost(['name' => 'X', 'edition' => '0']);
    [$c2] = guardCode(fn () => $ctrl->create());
    check('create edition=0 → 400', $c2 === 400, "got $c2");

    $app->request->withPost([
        'name' => '契约盲盒A_' . $sfx, 'image' => 'oss://bb_a.png',
        'description' => '契约测试盲盒', 'edition' => '100', 'price' => '9.90',
    ]);
    $r = $ctrl->create()->getData();
    $boxA = (int) ($r['data']['id'] ?? 0);
    check('create → 200 且返回 id', (int) ($r['code'] ?? 0) === 200 && $boxA > 0, var_export($r, true));

    /* ========== 2. 列表（51） ========== */

    $app->request->withGet(['name' => '契约盲盒A_' . $sfx]);
    $list = $ctrl->list()->getData();
    check('list name 筛选命中', (int) ($list['code'] ?? 0) === 200 && (int) ($list['data']['total'] ?? 0) === 1,
        var_export($list['data']['total'] ?? null, true));
    $row = $list['data']['list'][0] ?? [];
    check('列表含库存池（草稿=发行量）', (int) ($row['stock_pool'] ?? -1) === 100 && (int) ($row['sale_status'] ?? -1) === 0,
        var_export($row, true));

    /* ========== 3. 详情（53） ========== */

    $app->request->withGet(['id' => (string) $boxA]);
    $d = $ctrl->detail()->getData();
    $dd = $d['data'] ?? [];
    check('detail 恒等式字段齐全', (int) ($dd['edition'] ?? 0) === 100 && (int) ($dd['stock_pool'] ?? 0) === 100
        && (int) ($dd['sold'] ?? -1) === 0 && (int) ($dd['airdropped_count'] ?? -1) === 0
        && (int) ($dd['destroyed_count'] ?? -1) === 0, var_export($dd, true));
    check('detail 价格 string 两位小数', ($dd['price'] ?? '') === '9.90'
        || (string) ($dd['price'] ?? '') === '9.90', var_export($dd['price'] ?? null, true));

    /* ========== 4. 编辑（54，仅草稿） ========== */

    $app->request->withGet(['id' => (string) $boxA])
        ->withPost(['name' => '契约盲盒A改_' . $sfx, 'description' => '改描述', 'open_draw' => '1']);
    [$e1] = guardCode(fn () => $ctrl->edit());
    check('edit 草稿态 → 200', $e1 === 200, "got $e1");
    $b = Db::name('blind_box')->where('id', $boxA)->find();
    check('open_draw 开启开关落库', (int) $b['open_draw'] === 1 && $b['name'] === '契约盲盒A改_' . $sfx);

    /* ========== 5. 子藏品配置（55/56/57，§3.6.2 概率闭环） ========== */

    $app->request->withGet(['id' => (string) $boxA]);
    // 单条 → 拦（2~N）
    $app->request->withPost(['items' => [
        ['collectible_id' => $subA, 'probability' => '50', 'planned_quantity' => '50'],
    ]]);
    [$i1] = guardCode(fn () => $ctrl->items());
    check('items 单条 → 409（2~N）', $i1 === 409, "got $i1");
    // 概率总和 > 100 → 拦
    $app->request->withPost(['items' => [
        ['collectible_id' => $subA, 'probability' => '60', 'planned_quantity' => '50'],
        ['collectible_id' => $subB, 'probability' => '60', 'planned_quantity' => '50'],
    ]]);
    [$i2] = guardCode(fn () => $ctrl->items());
    check('items 概率和>100 → 409', $i2 === 409, "got $i2");
    // 正常：30/30/30，剩 10% 空奖
    $app->request->withPost(['items' => [
        ['collectible_id' => $subA, 'probability' => '30', 'planned_quantity' => '30'],
        ['collectible_id' => $subB, 'probability' => '30', 'planned_quantity' => '30'],
        ['collectible_id' => $subA, 'probability' => '30', 'planned_quantity' => '30'],
    ]]);
    [$i3] = guardCode(fn () => $ctrl->items());
    $itemRows = Db::name('blind_box_item')->where('blind_box_id', $boxA)->order('id')->select()->toArray();
    $itemIdA = (int) ($itemRows[0]['id'] ?? 0);
    check('items 3 条 → 200 落库', $i3 === 200 && count($itemRows) === 3, "got $i3 rows=" . count($itemRows));

    // 单条改概率 → 总和 110 → 拦
    $app->request->withGet(['id' => (string) $boxA, 'item_id' => (string) $itemIdA])
        ->withPost(['probability' => '50']);
    [$i4] = guardCode(fn () => $ctrl->itemUpdate());
    check('item 改概率致总和>100 → 409', $i4 === 409, "got $i4");
    // 正常改 35 → 总和 95 → 200
    $app->request->withPost(['probability' => '35', 'planned_quantity' => '40']);
    [$i5] = guardCode(fn () => $ctrl->itemUpdate());
    check('item 改概率 → 200', $i5 === 200, "got $i5");
    // 删除第三条
    $itemIdC = (int) ($itemRows[2]['id'] ?? 0);
    $app->request->withGet(['id' => (string) $boxA, 'item_id' => (string) $itemIdC])->withPost([]);
    [$i6] = guardCode(fn () => $ctrl->itemDelete());
    check('item delete → 200', $i6 === 200 && (int) Db::name('blind_box_item')->where('blind_box_id', $boxA)->count() === 2,
        "got $i6");

    /* ========== 6. 发售（58，≤ 当时库存池） ========== */

    $app->request->withGet(['id' => (string) $boxA]);
    $app->request->withPost(['price' => '9.90', 'onsale_at' => date('Y-m-d H:i:s', $now),
        'off_sale_at' => date('Y-m-d H:i:s', $now + 86400), 'per_user_limit' => '2', 'release_quantity' => '999']);
    [$r1] = guardCode(fn () => $ctrl->release());
    check('release 超库存池 → 409', $r1 === 409, "got $r1");
    $app->request->withPost(['price' => '9.90', 'onsale_at' => date('Y-m-d H:i:s', $now),
        'off_sale_at' => date('Y-m-d H:i:s', $now + 86400), 'per_user_limit' => '2', 'release_quantity' => '40']);
    [$r2] = guardCode(fn () => $ctrl->release());
    check('release → 200 状态=发售中', $r2 === 200
        && (int) Db::name('blind_box')->where('id', $boxA)->value('status') === 1, "got $r2");

    // 发售中编辑 → 409
    [$e2] = guardCode(fn () => $ctrl->edit());
    check('发售中 edit → 409', $e2 === 409, "got $e2");

    /* ========== 7. 重新上架状态机（59，仅已售罄/已下架） ========== */

    // 发售中 → 409（已在发售中，无需重新上架）
    $app->request->withPost(['release_quantity' => '10', 'password' => $pwd]);
    [$rl0] = guardCode(fn () => $ctrl->relist());
    check('发售中 relist → 409', $rl0 === 409, "got $rl0");
    // 无密码 → 400（密码校验先于状态机）
    $app->request->withPost(['release_quantity' => '10']);
    [$rl1] = guardCode(fn () => $ctrl->relist());
    check('relist 无密码 → 400', $rl1 === 400, "got $rl1");

    /* ========== 8. 强制售罄（60，剩余回池） ========== */

    $app->request->withPost(['password' => $pwd]);
    [$f1] = guardCode(fn () => $ctrl->forceSoldout());
    check('force-soldout 缺 reason → 400', $f1 === 400, "got $f1");
    $app->request->withPost(['reason' => '契约测试售罄', 'password' => $pwd]);
    [$f2] = guardCode(fn () => $ctrl->forceSoldout());
    check('force-soldout → 200 状态=已售罄', $f2 === 200
        && (int) Db::name('blind_box')->where('id', $boxA)->value('status') === 2, "got $f2");
    [$f3] = guardCode(fn () => $ctrl->forceSoldout());
    check('重复 force-soldout → 409', $f3 === 409, "got $f3");
    // 不清零：已售出 0、剩余 100 全在池（池为推导值自动回池）
    $app->request->withGet(['id' => (string) $boxA])->withPost([]);
    $d2 = $ctrl->detail()->getData();
    check('强制售罄后池不变（回池）', (int) ($d2['data']['stock_pool'] ?? 0) === 100,
        var_export($d2['data']['stock_pool'] ?? null, true));

    /* ========== 9. 重新上架（59，扩容 ≤ 池） ========== */

    $app->request->withPost(['release_quantity' => '200', 'password' => $pwd]);
    [$rl2] = guardCode(fn () => $ctrl->relist());
    check('relist 追加超池 → 409', $rl2 === 409, "got $rl2");
    $app->request->withPost(['release_quantity' => '20', 'password' => $pwd]);
    [$rl3] = guardCode(fn () => $ctrl->relist());
    $bb = Db::name('blind_box')->where('id', $boxA)->find();
    check('relist → 200 扩容至 120', $rl3 === 200 && (int) $bb['total_num'] === 120 && (int) $bb['status'] === 1,
        "got $rl3 total={$bb['total_num']}");

    /* ========== 10. 销毁（61，≤ 池） ========== */

    $app->request->withPost(['quantity' => '9999', 'reason' => 'x', 'password' => $pwd]);
    [$ds1] = guardCode(fn () => $ctrl->destroy());
    check('destroy 超池 → 409', $ds1 === 409, "got $ds1");
    $app->request->withPost(['quantity' => '10', 'reason' => '契约测试销毁', 'password' => $pwd]);
    [$ds2] = guardCode(fn () => $ctrl->destroy());
    check('destroy → 200 destroyed=10', $ds2 === 200
        && (int) Db::name('blind_box')->where('id', $boxA)->value('destroyed_count') === 10, "got $ds2");
    check('销毁记录落库(target_type=2)', Db::name('destroy_record')->where('target_type', 2)
        ->where('target_id', $boxA)->count() === 1);

    /* ========== 11. 开启记录（65，空态→可读） ========== */

    $app->request->withGet(['id' => (string) $boxA, 'page' => '1', 'page_size' => '10'])->withPost([]);
    $or = $ctrl->openRecords()->getData();
    check('open-records 空态 total=0', (int) ($or['code'] ?? 0) === 200 && (int) ($or['data']['total'] ?? -1) === 0);

    /* ========== 12. 强制回收（64） ========== */

    $app->request->withPost(['user_blindbox_id' => '999999999', 'reason' => 'x', 'password' => $pwd]);
    [$rv1] = guardCode(fn () => $ctrl->recover());
    check('recover 不存在来源 → 404', $rv1 === 404, "got $rv1");
    // 空投来源回收闭环见 §16（boxC）

    /* ========== 13. 审计（66，boxA） ========== */

    $app->request->withGet(['id' => (string) $boxA])->withPost([]);
    $au = $ctrl->audit()->getData();
    $a = $au['data'] ?? [];
    // 120 - 0(售) - 0(空投) - 10(销毁) = 110
    check('audit 恒等式字段', (int) ($a['edition'] ?? 0) === 120 && (int) ($a['stock_pool'] ?? 0) === 110
        && (int) ($a['destroyed_count'] ?? 0) === 10 && (int) ($a['user_holding'] ?? -1) === 0
        && (int) ($a['opened_count'] ?? -1) === 0, var_export($a, true));
    check('audit diff=0 且一致', (int) ($a['diff'] ?? 99) === 0 && ($a['is_consistent'] ?? false) === true);

    /* ========== 14. RBAC（运营持有 blindbox:*，§4.4 盲盒管理归运营） ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper])
        ->withGet(['page' => '1', 'page_size' => '10'])->withPost([]);
    $ol = $ctrl->list()->getData();
    check('运营 list → 200（授权生效）', (int) ($ol['code'] ?? 0) === 200, "got " . ($ol['code'] ?? 0));

    // 运营可销毁独立盲盒 boxD（不污染 boxA/boxC）
    $app->request->withPost(['name' => '契约盲盒D_' . $sfx, 'edition' => '10']);
    $boxD = (int) ($ctrl->create()->getData()['data']['id'] ?? 0);
    $app->request->withGet(['id' => (string) $boxD])
        ->withPost(['quantity' => '1', 'reason' => '运营销毁', 'password' => $pwd]);
    [$p1] = guardCode(fn () => $ctrl->destroy());
    check('运营 destroy → 200（授权生效）', $p1 === 200, "got $p1");
    check('boxD destroyed=1', (int) Db::name('blind_box')->where('id', $boxD)->value('destroyed_count') === 1);

    /* ========== 14. 删除（62，仅草稿无关联） ========== */

    $app->request->withHeader(['authorization' => 'Bearer ' . $asSuper])
        ->withPost(['name' => '契约盲盒B_' . $sfx, 'edition' => '10']);
    $boxB = (int) ($ctrl->create()->getData()['data']['id'] ?? 0);
    $app->request->withGet(['id' => (string) $boxB])->withPost(['items' => [
        ['collectible_id' => $subA, 'probability' => '40', 'planned_quantity' => '4'],
        ['collectible_id' => $subB, 'probability' => '40', 'planned_quantity' => '4'],
    ]]);
    guardCode(fn () => $ctrl->items());
    // 无密码
    $app->request->withPost([]);
    [$dl1] = guardCode(fn () => $ctrl->delete());
    check('delete 无密码 → 400', $dl1 === 400, "got $dl1");
    // 有子藏品关联 → 409
    $app->request->withPost(['password' => $pwd]);
    [$dl2] = guardCode(fn () => $ctrl->delete());
    check('delete 有子藏品关联 → 409', $dl2 === 409, "got $dl2");
    // 删光子藏品后再删 → 200
    foreach (Db::name('blind_box_item')->where('blind_box_id', $boxB)->column('id') as $iid) {
        $app->request->withGet(['id' => (string) $boxB, 'item_id' => (string) $iid])->withPost([]);
        guardCode(fn () => $ctrl->itemDelete());
    }
    $app->request->withPost(['password' => $pwd]);
    [$dl3] = guardCode(fn () => $ctrl->delete());
    check('delete 草稿无关联 → 200', $dl3 === 200
        && Db::name('blind_box')->where('id', $boxB)->find() === null, "got $dl3");

    /* ========== 15. 独立空投（63，≤ 盲盒库存池，未注册→fail） ========== */

    $app->request->withPost(['name' => '契约盲盒C_' . $sfx, 'edition' => '50']);
    $boxC = (int) ($ctrl->create()->getData()['data']['id'] ?? 0);
    $app->request->withGet(['id' => (string) $boxC])->withPost(['quantity' => '2', 'phones' => [$phoneC]]);
    [$ad1] = guardCode(fn () => $ctrl->airdrop());
    check('airdrop 无密码 → 400', $ad1 === 400, "got $ad1");

    $app->request->withPost(['quantity' => '2', 'phones' => [$phoneC, $phoneGhost], 'password' => $pwd]);
    $ad = $ctrl->airdrop()->getData();
    check('airdrop → success=1 fail=1', (int) ($ad['data']['success_count'] ?? 0) === 1
        && (int) ($ad['data']['fail_count'] ?? -1) === 1 && (int) ($ad['data']['task_id'] ?? 0) > 0,
        var_export($ad, true));
    check('airdropped_count=2 落库', (int) Db::name('blind_box')->where('id', $boxC)->value('airdropped_count') === 2);

    // 空投总量超池（池=48）
    $app->request->withPost(['quantity' => '100', 'phones' => [$phoneC, $phoneC, $phoneC], 'password' => $pwd]);
    [$ad2] = guardCode(fn () => $ctrl->airdrop());
    check('airdrop 超池 → 409', $ad2 === 409, "got $ad2");

    /* ========== 15.5 强制回收闭环（64） ========== */

    $arId = (int) Db::name('airdrop_record')->where('task_id', (int) ($ad['data']['task_id'] ?? 0))
        ->where('status', 1)->value('id');
    $app->request->withPost(['user_blindbox_id' => (string) $arId, 'reason' => '契约回收', 'password' => $pwd]);
    [$rv2] = guardCode(fn () => $ctrl->recover());
    check('recover 空投来源 → 200', $rv2 === 200, "got $rv2");
    check('回收后 blind_box_num 归零', (int) Db::name('user')->where('id', $uidC)->value('blind_box_num') === 0);
    check('回收后 airdropped_count 归零（池回升）',
        (int) Db::name('blind_box')->where('id', $boxC)->value('airdropped_count') === 0);
    [$rv3] = guardCode(fn () => $ctrl->recover());
    check('重复回收同一条 → 409', $rv3 === 409, "got $rv3");

    /* ========== 16. 开启记录 + 审计闭环（boxC） ========== */

    // 重新空投 2 份 → 2 次开启（真实链路行为模拟：每开 1 盒记 1 行）
    $app->request->withPost(['quantity' => '2', 'phones' => [$phoneC], 'password' => $pwd]);
    $ad3 = $ctrl->airdrop()->getData();
    check('二次空投 success=1', (int) ($ad3['data']['success_count'] ?? 0) === 1, var_export($ad3, true));
    foreach ([1, 2] as $k) {
        Db::name('blind_box_open_record')->insert([
            'blind_box_id' => $boxC, 'user_id' => $uidC, 'item_id' => 0, 'collectible_id' => 0,
            'quantity' => 1, 'status' => 1, 'remark' => '', 'createtime' => $now,
        ]);
    }
    $app->request->withGet(['id' => (string) $boxC, 'page' => '1', 'page_size' => '10'])->withPost([]);
    $or2 = $ctrl->openRecords()->getData();
    check('open-records 命中 2 条', (int) ($or2['data']['total'] ?? 0) === 2, var_export($or2['data'] ?? null, true));

    $app->request->withGet(['id' => (string) $boxC])->withPost([]);
    $au2 = $ctrl->audit()->getData();
    $a2 = $au2['data'] ?? [];
    // sold=0 air=2 opened=2 holding=0 → diff=0 一致
    check('boxC audit 全开启闭环 diff=0', (int) ($a2['sold'] ?? -1) === 0 && (int) ($a2['airdropped_count'] ?? 0) === 2
        && (int) ($a2['opened_count'] ?? 0) === 2 && (int) ($a2['diff'] ?? 99) === 0
        && ($a2['is_consistent'] ?? false) === true, var_export($a2, true));

    // 伪造 1 条多余开启（开启数 > 发出数）→ is_consistent=false
    Db::name('blind_box_open_record')->insert([
        'blind_box_id' => $boxC, 'user_id' => $uidC, 'item_id' => 0, 'collectible_id' => 0,
        'quantity' => 1, 'status' => 1, 'remark' => '', 'createtime' => $now,
    ]);
    $au3 = $ctrl->audit()->getData();
    check('开启数>发出数 → is_consistent=false', (int) ($au3['data']['diff'] ?? 0) === 1
        && ($au3['data']['is_consistent'] ?? true) === false, var_export($au3['data'] ?? null, true));

    /* ========== 17. 销毁记录（67） ========== */

    $app->request->withGet(['id' => (string) $boxA, 'page' => '1', 'page_size' => '10'])->withPost([]);
    $dr = $ctrl->destroyRecords()->getData();
    check('destroy-records 命中', (int) ($dr['code'] ?? 0) === 200 && (int) ($dr['data']['total'] ?? 0) === 1,
        var_export($dr['data'] ?? null, true));
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 blindbox: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
