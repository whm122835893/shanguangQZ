<?php
declare(strict_types=1);

/**
 * 实名认证 TDD 契约测试 —— RealnameService 行为级用例（不依赖原站样本）
 *
 * 背景：全库 user.real_name / user.id_card 原为脱敏值直接落列，
 *       无任何提交端点、无加密、无一证一号约束。
 * 本套用例锁定 RealnameService 的五条红线：
 *   1. 身份证必须过校验位 + 成年校验，否则拒绝
 *   2. 落库必须三态分离：脱敏值（展示/契约）+ 密文（AES-256-GCM）+ HMAC 指纹（一证一号）
 *   3. 一证一号：不同用户不得绑定同一身份证
 *   4. 已实名用户不得换证（防实名倒卖）
 *   5. 同用户重复提交同证 = 幂等成功；密文可解回原文（reveal）
 *
 * 用法：
 *   php tools/contract/realname_test.php
 *
 * 前置：11-realname.sql 已导入（user.id_card_hash / *_enc 列存在）。
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\RealnameException;
use app\service\RealnameService;
use think\facade\Db;

const MARK_OK   = '[OK]  ';
const MARK_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function assertSame(string $label, $expected, $actual): bool
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo MARK_OK . $label . "\n";
        $passed++;
        return true;
    }
    echo MARK_FAIL . $label . "\n";
    echo '       期望: ' . var_export($expected, true) . "\n";
    echo '       实际: ' . var_export($actual, true) . "\n";
    $failed++;
    return false;
}

function assertThrows(string $label, callable $fn, string $msgContains = ''): ?RealnameException
{
    global $passed, $failed;
    try {
        $fn();
    } catch (RealnameException $e) {
        if ($msgContains !== '' && !str_contains($e->getMessage(), $msgContains)) {
            echo MARK_FAIL . $label . "（异常文案不含「{$msgContains}」: {$e->getMessage()}）\n";
            $failed++;
            return null;
        }
        echo MARK_OK . $label . "\n";
        $passed++;
        return $e;
    } catch (\Throwable $e) {
        echo MARK_FAIL . $label . "（抛了错误类型 " . get_class($e) . ": {$e->getMessage()}）\n";
        $failed++;
        return null;
    }
    echo MARK_FAIL . $label . "（预期抛 RealnameException，实际未抛）\n";
    $failed++;
    return null;
}

function userRow(int $userId): array
{
    return (array) Db::name('user')->where('id', $userId)->field(
        'real_name,id_card,id_card_hash,real_name_enc,id_card_enc,real_name_authentication,real_name_time'
    )->find();
}

echo "实名认证 TDD 契约测试\n";
echo str_repeat('=', 64) . "\n";

/* ---------- 准备：两个独立测试用户（显式唯一邀请码，见 uk_inviter_code 坑） ---------- */
$mkUser = function (string $tag): int {
    return (int) Db::name('user')->insertGetId([
        'username'     => 'rname_t_' . $tag,
        'nickname'     => '实名TDD-' . $tag,
        'mobile'       => '197' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'inviter_code' => 'R' . $tag . random_int(100, 999),
        'status'       => 'normal',
        'jointime'     => time(),
        'createtime'   => time(),
        'updatetime'   => time(),
    ]);
};
$uidA = $mkUser('a' . random_int(100, 999));
$uidB = $mkUser('b' . random_int(100, 999));

// 契约样本：脱敏格式对齐原站实测（"520******838" = 前3 + 6星 + 后3）
$VALID_ID   = '11010519491231002X'; // 校验位合法（1949 年生，成年）
$VALID_NAME = '王小明';
$BAD_CHK_ID = '11010519491231003X'; // 校验位不合法
$YOUNG_ID   = '110105201001011232'; // 校验位合法但未满 18 岁（2010 年生）

try {
    /* ========== 1. 合法提交：三态分离落库 ========== */
    RealnameService::submit($uidA, $VALID_NAME, $VALID_ID);
    $row = userRow($uidA);
    assertSame('提交后 real_name_authentication = 1', 1, (int) ($row['real_name_authentication'] ?? 0));
    assertSame('落库为脱敏姓名（契约格式）', '王*明', (string) ($row['real_name'] ?? ''));
    assertSame('落库为脱敏证件（契约格式 前3+6星+后3，对齐原站 "520******838"）', '110******02X', (string) ($row['id_card'] ?? ''));
    assertSame('id_card_hash 已落列（64 hex）', 64, strlen((string) ($row['id_card_hash'] ?? '')));
    assertSame('密文列非空', true, ($row['real_name_enc'] ?? '') !== '' && ($row['id_card_enc'] ?? '') !== '');
    assertSame('real_name_time 已记录', true, (int) ($row['real_name_time'] ?? 0) > 0);

    /* ========== 2. reveal 回读：密文必须解回原文 ========== */
    $plain = RealnameService::reveal($uidA);
    assertSame('reveal 姓名回原文', $VALID_NAME, (string) ($plain['real_name'] ?? ''));
    assertSame('reveal 证件回原文', $VALID_ID, (string) ($plain['id_card'] ?? ''));
    assertSame(
        'hash = HMAC-SHA256(标准化证件号)',
        hash_hmac('sha256', strtoupper($VALID_ID), (string) env('REALNAME.HMAC_KEY')),
        (string) ($row['id_card_hash'] ?? '')
    );

    /* ========== 3. 一证一号：他人绑定同证必须拒绝 ========== */
    assertThrows(
        '他人提交同证被拒',
        fn () => RealnameService::submit($uidB, $VALID_NAME, $VALID_ID),
        '已被绑定'
    );

    /* ========== 4. 已实名禁换证 ========== */
    assertThrows(
        '已实名用户换证被拒',
        fn () => RealnameService::submit($uidA, '李四', '11010519491231002X' === $VALID_ID ? '320112196600000000X' : ''),
        '禁止变更'
    );

    /* ========== 5. 幂等：同用户同证重复提交成功且不重复计数 ========== */
    RealnameService::submit($uidA, $VALID_NAME, $VALID_ID);
    assertSame('幂等重提后认证位不变', 1, (int) userRow($uidA)['real_name_authentication']);

    /* ========== 6. 证件入参防线 ========== */
    assertThrows('校验位错误拒绝', fn () => RealnameService::submit($uidB, $VALID_NAME, $BAD_CHK_ID));
    assertThrows('未满 18 岁拒绝', fn () => RealnameService::submit($uidB, $VALID_NAME, $YOUNG_ID), '18');
    assertThrows('格式非法拒绝',   fn () => RealnameService::submit($uidB, $VALID_NAME, '12345'));
    assertThrows('空证件拒绝',     fn () => RealnameService::submit($uidB, $VALID_NAME, ''));
    assertThrows('空姓名拒绝',     fn () => RealnameService::submit($uidB, '', $VALID_ID));
    assertThrows('单字姓名拒绝',   fn () => RealnameService::submit($uidB, '王', $VALID_ID));

    /* ========== 7. B 用户未被污染 ========== */
    $rowB = userRow($uidB);
    assertSame('B 用户认证位仍为 0', 0, (int) ($rowB['real_name_authentication'] ?? 0));
    assertSame('B 用户 hash 仍为空', '', (string) ($rowB['id_card_hash'] ?? ''));

    /* ========== 8. 未实名用户 reveal 拒绝 ========== */
    assertThrows('未实名不可解密', fn () => RealnameService::reveal($uidB), '未实名');

    echo str_repeat('=', 64) . "\n";
    echo $failed === 0 ? "全部通过（{$passed} 项断言）\n" : "失败 {$failed} 项 / 通过 {$passed} 项\n";
} catch (\Throwable $e) {
    echo MARK_FAIL . '测试执行中断: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failed++;
} finally {
    foreach ([$uidA, $uidB] as $id) {
        Db::name('user')->where('id', $id)->update([
            'real_name' => '', 'id_card' => '', 'id_card_hash' => null,
            'real_name_enc' => null, 'id_card_enc' => null,
            'real_name_authentication' => 0, 'real_name_time' => 0,
        ]);
        Db::name('user')->where('id', $id)->delete();
    }
}

exit($failed === 0 ? 0 : 1);
