<?php
declare(strict_types=1);

/**
 * 遗留 MOCK 端点清理 TDD 契约测试
 *
 * 背景（v3.1 三分类收尾后的漏网之鱼，共 4 个 [MOCK]）：
 *   - order/pay/paySmsCheck  无条件返回 verify=1（假成功 = 支付校验后门）→ 必须真校验
 *   - order/pay/batchBuy     编造 99.00 假订单                          → fail-closed 明确拒绝
 *   - union/index/logs       假造 20 条战力流水（union_log 无载体）      → 真实空分页
 *   - user/newrank1          假造 10 条矿石榜（无数据载体）              → 真实空榜
 *
 * 锁定的红线：
 *   1. paySmsCheck 对「未发送过/错误」的验证码绝不返回 verify=1（fail-closed）
 *   2. 参数缺失明确报错，不静默通过
 *
 * batchBuy / unionLogs / newRank 返回常量结构，且鉴权前置无独立逻辑，
 * 不值得为此搭 token 脚手架——由路由冒烟与人工审查覆盖。
 *
 * 用法：php tools/contract/mock_endpoints_test.php
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\api\PayController;

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

/* ==================== 1. paySmsCheck fail-closed ==================== */

// 场景 A：从没给该手机号发过验证码 → 绝不能 verify=1
$app->request->withPost(['mobile' => '19900000001', 'code' => '123456']);
$resp = (new PayController())->paySmsCheck();
$body = method_exists($resp, 'getData') ? $resp->getData() : [];
check('未发送过验证码 → 业务失败（code!=1）', (int) ($body['code'] ?? 0) !== 1,
    'got ' . var_export($body, true));
check('响应体不含 verify=1（假成功后门锁死）',
    (int) ($body['data']['verify'] ?? 0) !== 1,
    'got ' . var_export($body, true));

// 场景 B：参数缺失 → 明确报错
$app->request->withPost(['mobile' => '', 'code' => '']);
$resp = (new PayController())->paySmsCheck();
$body = method_exists($resp, 'getData') ? $resp->getData() : [];
check('参数缺失 → 业务失败', (int) ($body['code'] ?? 0) !== 1,
    'got ' . var_export($body, true));

/* ==================== 汇总 ==================== */

echo "\n==============================\n";
echo "通过 {$passed} / " . ($passed + $failed) . " 项\n";
exit($failed > 0 ? 1 : 0);
