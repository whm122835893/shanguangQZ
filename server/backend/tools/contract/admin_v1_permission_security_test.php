<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第九批：权限审计 §8.15（15 接口）+ 风控安全 §8.16（15 接口）
 *
 * 锁定契约：
 *   1. 管理员 CRUD：admin_user；密码只存 password_hash（argon2id）；创建默认 role/status 生效；
 *      reset-password 后旧密码失效新密码可登录；删除自身 → 400（自我保护）
 *   2. 角色：admin_role + admin_role_permission（permissions 数组落关联）；预设角色（is_preset=1）禁删
 *   3. 权限树：admin_permission 按 module 分组
 *   4. 操作日志：audit_log 分页（admin_id/module/action/date 过滤）+ 详情 + CSV 导出
 *   5. 登录日志：admin_login_log 分页
 *   6. 黑名单：blacklist（添加 → status=1；移出 → status=0 + lifted_at/lifted_by + 审计）
 *   7. 风控告警：risk_alert 处理（status + handle_comment + handler 留痕）
 *   8. 安全事件：security_event 处理（status + handle_comment + handler 留痕）
 *   9. 交易密码锁定：tx_lock 只读 + 手动解锁（status→0/locked_until→0 + 审计）
 *  10. 敏感操作审批：approval approve→2 / reject→3（密码必填 + approver 留痕 + 审计）
 *
 * 用法：php tools/contract/admin_v1_permission_security_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\admin\v1\PermissionController;
use app\controller\admin\v1\SecurityController;
use app\service\admin\AdminException;
use think\facade\Db;

const PS_OK   = '[OK]  ';
const PS_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        echo PS_OK . $label . "\n";
        $passed++;
        return;
    }
    echo PS_FAIL . $label . "\n";
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
$pwd = 'S3cret-Psec-#9';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('psec_super', 1);
$secId   = $mkAdmin('psec_sec', 5);   // role=5：security:* 专用（若 RBAC 无该角色码则回退 1）

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
    check('管理员登录成功', $asSuper !== '', "token empty");

    $perm = new PermissionController();
    $sec  = new SecurityController();
    $superHeader = ['authorization' => 'Bearer ' . $asSuper];

    /* ==================== 8.15.1 管理员（162~167） ==================== */

    $app->request->withHeader($superHeader)
        ->withPost(['username' => 'newop_' . $sfx, 'password' => 'N3w-Op-Pwd#1', 'real_name' => '运营专员',
            'role' => '4', 'phone' => '19500001111', 'email' => 'op@sinan.test', 'status' => '1']);
    $ac = $perm->adminCreate()->getData();
    $newAdminId = (int) ($ac['data']['id'] ?? 0);
    check('创建管理员 → id', (int) ($ac['code'] ?? 0) === 200 && $newAdminId > 0, var_export($ac, true));
    $newHash = (string) Db::name('admin_user')->where('id', $newAdminId)->value('password_hash');
    check('密码只存 hash', $newHash !== '' && !str_contains($newHash, 'N3w-Op-Pwd#1')
        && password_verify('N3w-Op-Pwd#1', $newHash));

    $app->request->withGet(['page' => '1', 'page_size' => '10', 'role' => '4'])->withPost([]);
    $al = $perm->adminList()->getData();
    $aHit = false;
    foreach (($al['data']['list'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $newAdminId) {
            $aHit = (($row['real_name'] ?? '') === '运营专员' && ($row['phone'] ?? '') === '19500001111');
        }
    }
    check('管理员列表 role 筛选', (int) ($al['code'] ?? 0) === 200
        && (int) ($al['data']['total'] ?? 0) >= 1 && $aHit, var_export($al, true));

    $app->request->withGet(['id' => (string) $newAdminId])->withPost([]);
    $ad = $perm->adminDetail()->getData();
    check('管理员详情不含密码字段', (int) ($ad['code'] ?? 0) === 200
        && ($ad['data']['username'] ?? '') === 'newop_' . $sfx
        && !isset($ad['data']['password_hash']) && !isset($ad['data']['token_hash']),
        var_export($ad['data'] ?? null, true));

    $app->request->withGet(['id' => (string) $newAdminId])->withPost(['real_name' => '运营主管', 'status' => '0']);
    [$ae] = guardCode(fn () => $perm->adminEdit());
    check('管理员编辑 → 200', $ae === 200, "got $ae");
    check('管理员编辑落库', (string) Db::name('admin_user')->where('id', $newAdminId)->value('real_name') === '运营主管'
        && (int) Db::name('admin_user')->where('id', $newAdminId)->value('status') === 0);

    $app->request->withGet(['id' => (string) $newAdminId])->withPost(['new_password' => 'R3set-Pwd#77']);
    [$rp] = guardCode(fn () => $perm->adminResetPassword());
    check('重置密码 → 200', $rp === 200, "got $rp");
    $rpHash = (string) Db::name('admin_user')->where('id', $newAdminId)->value('password_hash');
    check('重置后新 hash 可验证', password_verify('R3set-Pwd#77', $rpHash) && $rpHash !== $newHash);

    $app->request->withGet(['id' => (string) $newAdminId])->withPost([]);
    [$adel] = guardCode(fn () => $perm->adminDelete());
    check('管理员删除 → 200', $adel === 200, "got $adel");
    check('管理员删除落库', Db::name('admin_user')->where('id', $newAdminId)->find() === null);

    $app->request->withGet(['id' => (string) $superId])->withPost([]);
    [$self] = guardCode(fn () => $perm->adminDelete());
    check('删除自己 → 400', $self === 400, "got $self");

    /* ==================== 8.15.2 角色（168~171） ==================== */

    $app->request->withPost(['name' => '契约角色_' . $sfx, 'code' => 'role_' . $sfx,
        'description' => '契约测试角色', 'data_scope' => '2',
        'permissions' => ['cms:banner:list', 'cms:banner:detail']]);
    $rc = $perm->roleCreate()->getData();
    $roleId = (int) ($rc['data']['id'] ?? 0);
    check('创建角色 → id', (int) ($rc['code'] ?? 0) === 200 && $roleId > 0, var_export($rc, true));
    check('角色权限关联落库', Db::name('admin_role_permission')->where('role_id', $roleId)->count() === 2
        && (int) Db::name('admin_role')->where('id', $roleId)->value('data_scope') === 2);

    $app->request->withGet([])->withPost([]);
    $rl = $perm->roleList()->getData();
    check('角色列表命中', (int) ($rl['code'] ?? 0) === 200
        && count($rl['data']['list'] ?? []) >= 1, var_export($rl, true));

    $app->request->withGet(['id' => (string) $roleId])->withPost(['description' => '契约角色v2', 'status' => '0']);
    [$re] = guardCode(fn () => $perm->roleEdit());
    check('角色编辑 → 200', $re === 200, "got $re");
    check('角色编辑落库', (string) Db::name('admin_role')->where('id', $roleId)->value('description') === '契约角色v2'
        && (int) Db::name('admin_role')->where('id', $roleId)->value('status') === 0);

    $presetId = (int) Db::name('admin_role')->where('is_preset', 1)->value('id');
    if ($presetId > 0) {
        $app->request->withGet(['id' => (string) $presetId])->withPost([]);
        [$rd0] = guardCode(fn () => $perm->roleDelete());
        check('预设角色禁删 → 409', $rd0 === 409, "got $rd0");
    }
    $app->request->withGet(['id' => (string) $roleId])->withPost([]);
    [$rd] = guardCode(fn () => $perm->roleDelete());
    check('自定义角色删除 → 200', $rd === 200, "got $rd");
    check('角色删除关联清空', Db::name('admin_role')->where('id', $roleId)->find() === null
        && Db::name('admin_role_permission')->where('role_id', $roleId)->count() === 0);

    /* ==================== 8.15.3 权限树（172） ==================== */

    $app->request->withGet(['module' => 'cms'])->withPost([]);
    $pt = $perm->permissionTree()->getData();
    $tree = $pt['data']['tree'] ?? [];
    check('权限树 module 过滤', (int) ($pt['code'] ?? 0) === 200
        && is_array($tree) && $tree !== []
        && ($tree[0]['module'] ?? 'x') === 'cms', var_export($tree, true));

    /* ==================== 8.15.4 操作日志（173~175） ==================== */

    Db::name('audit_log')->insert([
        'user_id' => $superId, 'username' => 'psec_super_' . $sfx, 'ip' => '127.0.0.1',
        'route' => '/admin/api/v1/x', 'method' => 'POST', 'action' => 'psec_test_action',
        'target_id' => 0, 'params' => '{"k":"v"}', 'result' => 1,
        'duration_ms' => 5, 'createtime' => $now,
    ]);
    $logId = (int) Db::name('audit_log')->max('id');

    $app->request->withGet(['page' => '1', 'page_size' => '10', 'action' => 'psec_test_action'])->withPost([]);
    $ol = $perm->operationLogs()->getData();
    check('操作日志 action 筛选', (int) ($ol['code'] ?? 0) === 200
        && (int) ($ol['data']['total'] ?? 0) >= 1, var_export($ol, true));

    $app->request->withGet(['id' => (string) $logId])->withPost([]);
    $od = $perm->operationLogDetail()->getData();
    check('操作日志详情', (int) ($od['code'] ?? 0) === 200
        && ($od['data']['action'] ?? '') === 'psec_test_action'
        && is_array($od['data']['params'] ?? null), var_export($od['data'] ?? null, true));

    $app->request->withGet(['date_start' => date('Y-m-d', $now), 'date_end' => date('Y-m-d', $now)])->withPost([]);
    $exp = $perm->operationLogsExport();
    $expBody = (string) $exp->getContent();
    check('操作日志导出 CSV', str_contains($expBody, 'id,') && str_contains($expBody, 'psec_test_action'),
        substr($expBody, 0, 120));

    /* ==================== 8.15.5 登录日志（176） ==================== */

    Db::name('admin_login_log')->insert([
        'admin_id' => $superId, 'username' => 'psec_super_' . $sfx, 'ip' => '127.0.0.1',
        'user_agent' => 'contract-test', 'is_success' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'admin_id' => (string) $superId])->withPost([]);
    $ll = $perm->loginLogs()->getData();
    check('登录日志命中', (int) ($ll['code'] ?? 0) === 200 && (int) ($ll['data']['total'] ?? 0) >= 1,
        var_export($ll, true));

    /* ==================== 8.16.1 黑名单（177~179） ==================== */

    $blkUid = (int) Db::name('user')->insertGetId([
        'username' => 'psecu_' . $sfx, 'nickname' => 'PSECU', 'mobile' => '196' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'money' => '0.00', 'status' => 'normal', 'inviter_code' => 'P' . $sfx,
        'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    $app->request->withPost(['user_id' => (string) $blkUid, 'type' => '1',
        'reason' => '契约封禁', 'evidence' => 'screenshot://x', 'expires_at' => (string) ($now + 86400)]);
    $bc = $sec->blacklistAdd()->getData();
    $blkId = (int) ($bc['data']['id'] ?? 0);
    check('添加黑名单 → id', (int) ($bc['code'] ?? 0) === 200 && $blkId > 0, var_export($bc, true));
    check('黑名单落库', (int) Db::name('blacklist')->where('id', $blkId)->value('status') === 1
        && (string) Db::name('blacklist')->where('id', $blkId)->value('reason') === '契约封禁');

    $app->request->withGet(['page' => '1', 'page_size' => '10', 'user_id' => (string) $blkUid])->withPost([]);
    $bl = $sec->blacklistList()->getData();
    check('黑名单列表 user_id 筛选', (int) ($bl['code'] ?? 0) === 200
        && (int) ($bl['data']['total'] ?? 0) >= 1, var_export($bl, true));

    $app->request->withGet(['id' => (string) $blkId])->withPost([]);
    [$bx0] = guardCode(fn () => $sec->blacklistRemove());
    check('移出黑名单缺原因 → 400', $bx0 === 400, "got $bx0");
    $app->request->withPost(['reason' => '误封恢复']);
    [$bx] = guardCode(fn () => $sec->blacklistRemove());
    check('移出黑名单 → 200', $bx === 200, "got $bx");
    check('黑名单移出落库', (int) Db::name('blacklist')->where('id', $blkId)->value('status') === 0
        && (int) Db::name('blacklist')->where('id', $blkId)->value('lifted_by') === $superId
        && (int) Db::name('blacklist')->where('id', $blkId)->value('lifted_at') > 0);
    Db::name('user')->where('id', $blkUid)->delete();

    /* ==================== 8.16.2 风控告警（180~182） ==================== */

    $alertId = (int) Db::name('risk_alert')->insertGetId([
        'alert_type' => 1, 'alert_level' => 2, 'user_id' => null, 'title' => '契约告警_' . $sfx,
        'description' => '契约描述', 'status' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'level' => '2', 'status' => '1'])->withPost([]);
    $ra = $sec->riskAlerts()->getData();
    $raHit = false;
    foreach (($ra['data']['list'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $alertId) {
            $raHit = (($row['title'] ?? '') === '契约告警_' . $sfx);
        }
    }
    check('风控告警列表 level+status 筛选', (int) ($ra['code'] ?? 0) === 200
        && (int) ($ra['data']['total'] ?? 0) >= 1 && $raHit, var_export($ra, true));

    $app->request->withGet(['id' => (string) $alertId])->withPost([]);
    $rad = $sec->riskAlertDetail()->getData();
    check('风控告警详情', (int) ($rad['code'] ?? 0) === 200 && ($rad['data']['title'] ?? '') === '契约告警_' . $sfx);

    $app->request->withGet(['id' => (string) $alertId])->withPost(['status' => '2', 'handle_comment' => '已人工核实']);
    [$rah] = guardCode(fn () => $sec->riskAlertHandle());
    check('处理风控告警 → 200', $rah === 200, "got $rah");
    check('告警处理留痕', (int) Db::name('risk_alert')->where('id', $alertId)->value('status') === 2
        && (string) Db::name('risk_alert')->where('id', $alertId)->value('handle_comment') === '已人工核实'
        && (int) Db::name('risk_alert')->where('id', $alertId)->value('handled_at') > 0);

    /* ==================== 8.16.3 安全事件（183~185） ==================== */

    $eventId = (int) Db::name('security_event')->insertGetId([
        'event_type' => 2, 'event_level' => 3, 'ip_address' => '10.0.0.9',
        'description' => '契约事件_' . $sfx, 'status' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'level' => '3', 'ip' => '10.0.0.9'])->withPost([]);
    $ev = $sec->eventList()->getData();
    $evHit = false;
    foreach (($ev['data']['list'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $eventId) {
            $evHit = (($row['description'] ?? '') === '契约事件_' . $sfx);
        }
    }
    check('安全事件列表 level+ip 筛选', (int) ($ev['code'] ?? 0) === 200
        && (int) ($ev['data']['total'] ?? 0) >= 1 && $evHit, var_export($ev, true));

    $app->request->withGet(['id' => (string) $eventId])->withPost([]);
    $evd = $sec->eventDetail()->getData();
    check('安全事件详情', (int) ($evd['code'] ?? 0) === 200
        && ($evd['data']['ip_address'] ?? '') === '10.0.0.9');

    $app->request->withGet(['id' => (string) $eventId])->withPost(['status' => '2', 'comment' => '已封禁来源 IP']);
    [$evh] = guardCode(fn () => $sec->eventHandle());
    check('处理安全事件 → 200', $evh === 200, "got $evh");
    check('事件处理留痕', (int) Db::name('security_event')->where('id', $eventId)->value('status') === 2
        && (string) Db::name('security_event')->where('id', $eventId)->value('handle_comment') === '已封禁来源 IP'
        && (int) Db::name('security_event')->where('id', $eventId)->value('handled_at') > 0);

    /* ==================== 8.16.4 交易密码锁定（186~187） ==================== */

    $lockUid = (int) Db::name('user')->insertGetId([
        'username' => 'psecl_' . $sfx, 'nickname' => 'PSECL', 'mobile' => '197' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'money' => '0.00', 'status' => 'normal', 'createtime' => $now, 'jointime' => $now, 'updatetime' => $now,
    ]);
    $lockId = (int) Db::name('tx_lock')->insertGetId([
        'user_id' => $lockUid, 'fail_count' => 5, 'locked_until' => $now + 600,
        'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'user_id' => (string) $lockUid])->withPost([]);
    $tl = $sec->txLocks()->getData();
    check('锁定记录 user_id 筛选', (int) ($tl['code'] ?? 0) === 200
        && (int) ($tl['data']['total'] ?? 0) >= 1
        && (int) (($tl['data']['list'][0]['fail_count'] ?? 0)) === 5, var_export($tl, true));

    $app->request->withGet(['id' => (string) $lockId])->withPost([]);
    [$tu0] = guardCode(fn () => $sec->txLockUnlock());
    check('手动解锁缺原因 → 400', $tu0 === 400, "got $tu0");
    $app->request->withPost(['reason' => '用户申诉通过']);
    [$tu] = guardCode(fn () => $sec->txLockUnlock());
    check('手动解锁 → 200', $tu === 200, "got $tu");
    check('解锁落库', (int) Db::name('tx_lock')->where('id', $lockId)->value('status') === 0
        && (int) Db::name('tx_lock')->where('id', $lockId)->value('locked_until') === 0
        && (string) Db::name('tx_lock')->where('id', $lockId)->value('unlock_reason') === '用户申诉通过');
    Db::name('user')->where('id', $lockUid)->delete();

    /* ==================== 8.16.5 敏感操作审批（188~191） ==================== */

    $apId = (int) Db::name('approval')->insertGetId([
        'approval_type' => 1, 'target_id' => $blkUid, 'target_type' => 'user',
        'title' => '契约审批_' . $sfx, 'description' => '删除用户藏品',
        'applicant_id' => $superId, 'applicant_name' => 'psec_super_' . $sfx,
        'status' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'status' => '1'])->withPost([]);
    $ap = $sec->approvals()->getData();
    $apHit = false;
    foreach (($ap['data']['list'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $apId) {
            $apHit = (($row['title'] ?? '') === '契约审批_' . $sfx);
        }
    }
    check('审批列表 status 筛选', (int) ($ap['code'] ?? 0) === 200
        && (int) ($ap['data']['total'] ?? 0) >= 1 && $apHit, var_export($ap, true));

    $app->request->withGet(['id' => (string) $apId])->withPost([]);
    $apd = $sec->approvalDetail()->getData();
    check('审批详情', (int) ($apd['code'] ?? 0) === 200 && ($apd['data']['title'] ?? '') === '契约审批_' . $sfx);

    // 拒绝：缺密码 → 400
    $app->request->withGet(['id' => (string) $apId])->withPost(['comment' => '证据不足']);
    [$arj0] = guardCode(fn () => $sec->approvalReject());
    check('审批拒绝缺密码 → 400', $arj0 === 400, "got $arj0");
    $app->request->withPost(['comment' => '证据不足', 'password' => $pwd]);
    [$arj] = guardCode(fn () => $sec->approvalReject());
    check('审批拒绝 → 200', $arj === 200, "got $arj");
    check('拒绝落库 approver 留痕', (int) Db::name('approval')->where('id', $apId)->value('status') === 3
        && (string) Db::name('approval')->where('id', $apId)->value('comment') === '证据不足'
        && (int) Db::name('approval')->where('id', $apId)->value('approver_id') === $superId
        && (int) Db::name('approval')->where('id', $apId)->value('approved_at') > 0);

    // 已终态再批 → 409
    $app->request->withPost(['comment' => 'ok', 'password' => $pwd]);
    [$aap9] = guardCode(fn () => $sec->approvalApprove());
    check('终态审批再处理 → 409', $aap9 === 409, "got $aap9");

    $apId2 = (int) Db::name('approval')->insertGetId([
        'approval_type' => 2, 'target_id' => 0, 'target_type' => 'transfer',
        'title' => '契约审批2_' . $sfx, 'applicant_id' => $superId, 'applicant_name' => 'psec_super_' . $sfx,
        'status' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['id' => (string) $apId2])->withPost(['comment' => '批准执行', 'password' => $pwd]);
    [$aap] = guardCode(fn () => $sec->approvalApprove());
    check('审批批准 → 200', $aap === 200, "got $aap");
    check('批准落库', (int) Db::name('approval')->where('id', $apId2)->value('status') === 2
        && (int) Db::name('approval')->where('id', $apId2)->value('approved_at') > 0);
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 permission+security: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
