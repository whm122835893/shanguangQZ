<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第十批：工单 §8.17（8）+ 报表 §8.18（6）+ 运维 §8.19（4）
 *
 * 锁定契约：
 *   1. 工单：support_ticket + ticket_reply；assign 反查管理员名；status 3=已解决落 solved_at；
 *      回复 sender_type=2；补偿（高风险密码）余额路径 → user.money + wallet_transaction(biz_type=ticket_compensate)
 *   2. 反馈：feedback 分页 + 详情
 *   3. 报表：sales（订单聚合+按日）、users（新增/实名率/DAU/MAU）、collectibles（持有分布/转赠数）、
 *      blindboxes（开启/空奖率）、finance（充值/手续费/退款/余额 + 对账恒等式）、custom-export CSV
 *      （table/fields 白名单防注入）
 *   4. 运维：cleanup 三重确认（confirmation_text 错→400 / 密码错→400 / 通过→200 + JSON 备份 +
 *      platform_backup + platform_cleanup_log + 用户业务表清空）；cleanup-logs；backups；restore 回灌
 *
 * 🔴 本批含真实清库：必须放在全量回归最后执行。
 *
 * 用法：php tools/contract/admin_v1_ticket_report_platform_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\admin\v1\PlatformController;
use app\controller\admin\v1\ReportController;
use app\controller\admin\v1\TicketController;
use app\service\admin\AdminException;
use think\facade\Db;

const TRP_OK   = '[OK]  ';
const TRP_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        echo TRP_OK . $label . "\n";
        $passed++;
        return;
    }
    echo TRP_FAIL . $label . "\n";
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
$pwd = 'S3cret-Trp-#10';
$today = date('Y-m-d', $now);

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('trp_super', 1);
$agentId = $mkAdmin('trp_agent', 2);

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
    check('管理员登录成功', $asSuper !== '', 'token empty');

    $ticket = new TicketController();
    $report = new ReportController();
    $platform = new PlatformController();
    $superHeader = ['authorization' => 'Bearer ' . $asSuper];

    // 基础用户 fixture
    $mkUser = static function (string $tag) use ($now, $sfx): array {
        $mobile = '198' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        $uid = (int) Db::name('user')->insertGetId([
            'username' => $tag . '_' . $sfx, 'nickname' => strtoupper($tag), 'mobile' => $mobile,
            'money' => '0.00', 'blind_box_num' => 0, 'status' => 'normal',
            'inviter_code' => 'R' . $sfx . strtoupper($tag), 'real_name_authentication' => 1,
            'createtime' => $now, 'jointime' => $now, 'logintime' => $now, 'updatetime' => $now,
        ]);
        return [$uid, $mobile];
    };
    [$uidA, $phA] = $mkUser('trpa');
    [$uidB, $phB] = $mkUser('trpb');

    /* ==================== 8.17 工单（192~199） ==================== */

    $ticketId = (int) Db::name('support_ticket')->insertGetId([
        'ticket_no' => 'TK' . $sfx . '001', 'user_id' => $uidA, 'user_phone' => $phA,
        'ticket_type' => 1, 'priority' => 2, 'title' => '契约工单_' . $sfx,
        'description' => '契约描述', 'status' => 1,
        'createtime' => $now, 'updatetime' => $now,
    ]);

    $app->request->withHeader($superHeader)
        ->withGet(['page' => '1', 'page_size' => '10', 'status' => '1', 'user_id' => (string) $uidA])->withPost([]);
    $tl = $ticket->list()->getData();
    $tHit = false;
    foreach (($tl['data']['list'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $ticketId) {
            $tHit = !str_contains(json_encode($row, JSON_UNESCAPED_UNICODE), $phA)
                && str_contains(json_encode($row, JSON_UNESCAPED_UNICODE), substr($phA, 0, 3) . '****' . substr($phA, -4));
        }
    }
    check('工单列表筛选 + 手机号脱敏', (int) ($tl['code'] ?? 0) === 200
        && (int) ($tl['data']['total'] ?? 0) >= 1 && $tHit, var_export($tl, true));

    $app->request->withGet(['id' => (string) $ticketId])->withPost([]);
    $td = $ticket->detail()->getData();
    check('工单详情', (int) ($td['code'] ?? 0) === 200 && ($td['data']['title'] ?? '') === '契约工单_' . $sfx
        && is_array($td['data']['replies'] ?? null));

    $app->request->withGet(['id' => (string) $ticketId])->withPost(['assignee_id' => (string) $agentId]);
    [$ta] = guardCode(fn () => $ticket->assign());
    check('分配工单 → 200', $ta === 200, "got $ta");
    check('分配落库反查姓名', (int) Db::name('support_ticket')->where('id', $ticketId)->value('assignee_id') === $agentId
        && (string) Db::name('support_ticket')->where('id', $ticketId)->value('assignee_name')
            === 'trp_agent_' . $sfx);

    $app->request->withGet(['id' => (string) $ticketId])
        ->withPost(['content' => '契约回复', 'is_internal' => '1']);
    [$tr] = guardCode(fn () => $ticket->reply());
    check('回复工单 → 200', $tr === 200, "got $tr");
    check('回复落库 sender_type=2', (int) Db::name('ticket_reply')->where('ticket_id', $ticketId)->value('sender_type') === 2
        && (int) Db::name('ticket_reply')->where('ticket_id', $ticketId)->value('is_internal') === 1
        && (string) Db::name('ticket_reply')->where('ticket_id', $ticketId)->value('content') === '契约回复');

    $app->request->withGet(['id' => (string) $ticketId])->withPost(['status' => '3', 'comment' => '已解决']);
    [$ts] = guardCode(fn () => $ticket->updateStatus());
    check('更新工单状态 → 200', $ts === 200, "got $ts");
    check('已解决落 solved_at', (int) Db::name('support_ticket')->where('id', $ticketId)->value('status') === 3
        && (int) Db::name('support_ticket')->where('id', $ticketId)->value('solved_at') > 0);

    // 反馈
    $fbId = (int) Db::name('feedback')->insertGetId([
        'user_id' => $uidB, 'type' => 2, 'content' => '契约反馈_' . $sfx,
        'contact' => $phB, 'status' => 1, 'createtime' => $now,
    ]);
    $app->request->withGet(['page' => '1', 'page_size' => '10', 'status' => '1'])->withPost([]);
    $fl = $ticket->feedbacks()->getData();
    check('反馈列表命中', (int) ($fl['code'] ?? 0) === 200 && (int) ($fl['data']['total'] ?? 0) >= 1,
        var_export($fl, true));
    $app->request->withGet(['id' => (string) $fbId])->withPost([]);
    $fd = $ticket->feedbackDetail()->getData();
    check('反馈详情命中且脱敏', (int) ($fd['code'] ?? 0) === 200
        && ($fd['data']['content'] ?? '') === '契约反馈_' . $sfx
        && !str_contains(json_encode($fd, JSON_UNESCAPED_UNICODE), $phB));

    // 快捷补偿：余额路径（高风险）
    $app->request->withGet(['id' => (string) $ticketId])
        ->withPost(['compensate_type' => '1', 'compensate_config' => ['amount' => '2.50'], 'reason' => '体验补偿']);
    [$tc0] = guardCode(fn () => $ticket->compensate());
    check('补偿缺密码 → 400', $tc0 === 400, "got $tc0");
    $app->request->withPost(['compensate_config' => ['amount' => '2.50'], 'reason' => '体验补偿', 'password' => $pwd]);
    [$tc] = guardCode(fn () => $ticket->compensate());
    check('余额补偿 → 200', $tc === 200, "got $tc");
    check('补偿落库（money+流水）', (string) Db::name('user')->where('id', $uidA)->value('money') === '2.50'
        && (string) Db::name('wallet_transaction')->where('user_id', $uidA)->where('biz_type', 'ticket_compensate')->value('amount') === '2.50');

    /* ==================== 8.18 报表（200~205） ==================== */

    // 订单/充值/流水 fixture
    Db::name('artel_order')->insert([
        'order_sn' => 'RP' . $sfx . '01', 'user_id' => $uidA, 'order_type' => 1, 'order_status' => 2,
        'total_price' => '88.00', 'num' => 1, 'goods_id' => 0, 'goods_type' => 1,
        'pay_channel' => 'alipay', 'pay_time' => $now, 'source' => 'sale',
        'fee' => '4.40', 'createtime' => $now, 'updatetime' => $now,
    ]);
    Db::name('recharge_order')->insert([
        'order_sn' => 'RC' . $sfx . '01', 'user_id' => $uidA, 'amount' => '100.00', 'status' => 2,
        'pay_way' => 'alipay', 'pay_time' => $now, 'createtime' => $now, 'updatetime' => $now,
    ]);
    Db::name('wallet_transaction')->insert([
        'user_id' => $uidA, 'direction' => 1, 'amount' => '100.00',
        'balance_before' => '0.00', 'balance_after' => '100.00',
        'biz_type' => 'recharge', 'biz_id' => 'RC' . $sfx . '01', 'remark' => '', 'createtime' => $now,
    ]);
    // 🔴 增量加钱而非覆写：本批前面已有 ticket_compensate 流水，覆写会破坏资金恒等式
    Db::name('user')->where('id', $uidA)->inc('money', 100)->update(['updatetime' => $now]);
    Db::name('user_collection')->insert([
        'user_id' => $uidA, 'collection_id' => 0, 'num' => 1, 'sell_num' => 0, 'lock_num' => 0,
        'is_collection' => 0, 'can_compound' => 1, 'utime' => $now, 'createtime' => $now,
    ]);

    $app->request->withGet(['date_start' => $today, 'date_end' => $today, 'group_by' => 'day'])->withPost([]);
    $rs = $report->sales()->getData();
    check('销售报表 stats+chart', (int) ($rs['code'] ?? 0) === 200
        && (float) ($rs['data']['stats']['total_amount'] ?? 0) >= 88.00
        && is_array($rs['data']['chart_data'] ?? null), var_export($rs, true));

    $app->request->withGet(['date_start' => $today, 'date_end' => $today])->withPost([]);
    $ru = $report->users()->getData();
    check('用户报表结构', (int) ($ru['code'] ?? 0) === 200
        && isset($ru['data']['new_users'], $ru['data']['realname_rate'], $ru['data']['dau'], $ru['data']['mau'], $ru['data']['chart_data'])
        && (int) ($ru['data']['new_users'] ?? 0) >= 2, var_export($ru, true));

    $app->request->withGet(['date_start' => $today, 'date_end' => $today])->withPost([]);
    $rc = $report->collectibles()->getData();
    check('藏品报表结构', (int) ($rc['code'] ?? 0) === 200
        && isset($rc['data']['holding_distribution'], $rc['data']['transfer_count'], $rc['data']['top_list'])
        && is_array($rc['data']['holding_distribution'] ?? null), var_export($rc, true));

    $app->request->withGet(['date_start' => $today, 'date_end' => $today])->withPost([]);
    $rb = $report->blindboxes()->getData();
    check('盲盒报表结构', (int) ($rb['code'] ?? 0) === 200
        && isset($rb['data']['sales'], $rb['data']['open_rate'], $rb['data']['empty_rate'], $rb['data']['chart_data']),
        var_export($rb, true));

    $app->request->withGet(['date_start' => $today, 'date_end' => $today])->withPost([]);
    $rf = $report->finance()->getData();
    $fin = $rf['data'] ?? [];
    check('财务对账结构', (int) ($rf['code'] ?? 0) === 200
        && isset($fin['recharge'], $fin['fee'], $fin['refund'], $fin['balance'], $fin['reconciliation'])
        && (float) ($fin['recharge']['total'] ?? 0) >= 100.00
        && (float) ($fin['fee']['total'] ?? 0) >= 4.40
        && ($fin['reconciliation']['balanced'] ?? false) === true,
        var_export($fin, true));

    // 自定义导出：白名单表
    $app->request->withPost(['table' => 'user', 'fields' => ['id', 'username', 'mobile'],
        'filters' => [], 'format' => 'csv']);
    $ex = $report->customExport();
    $exBody = (string) $ex->getContent();
    check('自定义导出 CSV', str_contains($exBody, 'id,username,mobile')
        && str_contains($exBody, 'trpa_' . $sfx), substr($exBody, 0, 120));

    // 非法表 → 400
    $app->request->withPost(['table' => 'user; DROP TABLE user', 'fields' => ['id'], 'format' => 'csv']);
    [$exx] = guardCode(fn () => $report->customExport());
    check('导出非法表 → 400', $exx === 400, "got $exx");

    /* ==================== 8.19 平台运维（206~209） ==================== */

    // 清库前用户数（本批 fixture 至少 2）
    $usersBefore = (int) Db::name('user')->count();
    check('清库前有用户', $usersBefore >= 2, 'count=' . $usersBefore);

    $app->request->withPost(['confirmation_text' => '错的确认文本', 'password' => $pwd, 'sms_code' => '123456', 'reason' => '测试']);
    [$pc0] = guardCode(fn () => $platform->cleanup());
    check('cleanup 确认文本错 → 400', $pc0 === 400, "got $pc0");

    $app->request->withPost(['confirmation_text' => '确认清空用户数据', 'password' => 'Wrong#Pwd1', 'sms_code' => '123456', 'reason' => '测试']);
    [$pc1] = guardCode(fn () => $platform->cleanup());
    check('cleanup 密码错 → 400', $pc1 === 400, "got $pc1");

    $app->request->withPost(['confirmation_text' => '确认清空用户数据', 'password' => $pwd,
        'sms_code' => '123456', 'reason' => '契约清库测试']);
    $pc = $platform->cleanup()->getData();
    check('cleanup → 200 + 统计', (int) ($pc['code'] ?? 0) === 200
        && (int) ($pc['data']['affected_users'] ?? 0) === $usersBefore
        && ($pc['data']['backup_path'] ?? '') !== '', var_export($pc, true));
    check('cleanup 用户业务表清空', (int) Db::name('user')->count() === 0
        && (int) Db::name('artel_order')->count() === 0
        && (int) Db::name('wallet_transaction')->count() === 0);
    check('cleanup 日志落库', Db::name('platform_cleanup_log')->where('reason', '契约清库测试')->count() >= 1
        && (int) Db::name('platform_cleanup_log')->max('id') > 0);

    $app->request->withHeader($superHeader)->withGet(['page' => '1', 'page_size' => '10'])->withPost([]);
    $cl = $platform->cleanupLogs()->getData();
    check('清库记录列表', (int) ($cl['code'] ?? 0) === 200 && (int) ($cl['data']['total'] ?? 0) >= 1,
        var_export($cl, true));

    $app->request->withGet(['page' => '1', 'page_size' => '10'])->withPost([]);
    $bk = $platform->backups()->getData();
    $backupId = (int) ($bk['data']['list'][0]['id'] ?? 0);
    check('备份列表', (int) ($bk['code'] ?? 0) === 200 && (int) ($bk['data']['total'] ?? 0) >= 1
        && $backupId > 0, var_export($bk, true));

    // restore：缺密码 400 → 正确回灌
    $app->request->withGet(['id' => (string) $backupId])->withPost([]);
    [$pr0] = guardCode(fn () => $platform->restore());
    check('restore 缺密码 → 400', $pr0 === 400, "got $pr0");
    $app->request->withPost(['password' => $pwd]);
    [$pr] = guardCode(fn () => $platform->restore());
    check('restore → 200', $pr === 200, "got $pr");
    check('restore 用户回灌', (int) Db::name('user')->count() === $usersBefore
        && Db::name('user')->where('id', $uidA)->find() !== null);
    check('restore 备份状态→2', (int) Db::name('platform_backup')->where('id', $backupId)->value('status') === 2);
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 ticket+report+platform: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
