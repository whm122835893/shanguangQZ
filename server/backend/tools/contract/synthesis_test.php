<?php
declare(strict_types=1);

/**
 * 合成 TDD 契约测试 —— SynthesisService 行为级用例
 *
 * 背景：/synthesis/* 3 端点原为内存假数据，synthesis_theme / synthesis_plan 表已建但空。
 *       前端 Compound.vue 1:1 复刻原站，消费字段即契约。
 *
 * 契约要点：
 *   - themeinfo(thm)：返回 {name, image, play[]}；play 项时间戳为毫秒（13 位，前端
 *     statusOf/countdown 直接 Number() 比对 Date.now()）；主题不存在/未开启 → 抛
 *     「主题未开启」（控制器转 code:0，前端 catch 后 1.5s 自动回首页）
 *   - material(play_id)：返回该方案 need_items 数组；无材料返回 []（原站实测空数组合法）；
 *     方案不存在 → 抛「活动不存在」
 *   - recordList：无记录载体（本域无执行合成端点），恒真实空分页 {list:[], total:0}
 *   - status=0 的主题/方案一律不可见
 *
 * 用法：php tools/contract/synthesis_test.php
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\SynthesisException;
use app\service\SynthesisService;
use think\facade\Db;

const MARK_OK   = '[OK]  ';
const MARK_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): bool
{
    global $passed, $failed;
    if ($cond) {
        echo MARK_OK . $label . "\n";
        $passed++;
        return true;
    }
    echo MARK_FAIL . $label . "\n";
    if ($detail !== '') {
        echo '       ' . $detail . "\n";
    }
    $failed++;
    return false;
}

function assertThrows(string $label, callable $fn, string $msgContains = ''): ?SynthesisException
{
    global $passed, $failed;
    try {
        $fn();
    } catch (SynthesisException $e) {
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
    echo MARK_FAIL . $label . "（预期抛 SynthesisException，实际未抛）\n";
    $failed++;
    return null;
}

/* ==================== 测试准备 ==================== */

Db::name('synthesis_plan')->where('title', 'like', '测试合成方案%')->delete();
Db::name('synthesis_theme')->where('name', 'like', '测试合成主题%')->delete();

/* ==================== 1. 无主题 ==================== */

assertThrows('无启用主题时 themeinfo 抛「主题未开启」', function () {
    SynthesisService::themeinfo(999999);
}, '主题未开启');

/* ==================== 2. 管理端建主题+方案 ==================== */

$themeId = SynthesisService::adminSaveTheme([
    'name'  => '测试合成主题A',
    'image' => '/static/oss/test/synth.jpg',
    'types' => 1,
    'sort'  => 99,
]);
check('adminSaveTheme 返回 id', $themeId > 0);

$items = json_encode([
    ['collection_id' => 0, 'name' => '测试合成材料·碎片', 'image' => '/static/oss/test/frag.jpg', 'num' => 3],
], JSON_UNESCAPED_UNICODE);

$planId = SynthesisService::adminSavePlan([
    'theme_id'   => $themeId,
    'title'      => '测试合成方案·进行中',
    'image'      => '/static/oss/test/synth-plan.jpg',
    'start_time' => (time() - 3600) * 1000,
    'end_time'   => (time() + 3600) * 1000,
    'real_start_time' => (time() - 3600) * 1000,
    'classify'   => 'regular',
    'type'       => '10',
    'stock'      => 100,
    'need_items' => $items,
]);
check('adminSavePlan 返回 id', $planId > 0);

SynthesisService::adminSavePlan([
    'theme_id'   => $themeId,
    'title'      => '测试合成方案·已下架',
    'classify'   => 'regular',
    'type'       => '10',
    'status'     => 0,
]);

/* ==================== 3. themeinfo 契约 ==================== */

$info = SynthesisService::themeinfo($themeId);
check('info.name 透传', ($info['name'] ?? '') === '测试合成主题A');
check('info.image 透传', ($info['image'] ?? '') === '/static/oss/test/synth.jpg');
check('info.play 是数组', is_array($info['play'] ?? null));
check('status=0 的方案不可见', count($info['play']) === 1, 'got ' . count($info['play'] ?? []));

$play = $info['play'][0] ?? [];
check('play.id 正确', (int) ($play['id'] ?? 0) === $planId);
check('play 时间戳为毫秒（13 位，前端 Number() 直接比对）',
    (int) ($play['start_time'] ?? 0) > 1000000000000,
    'got ' . var_export($play['start_time'] ?? null, true));
check('play.classify/type 透传', ($play['classify'] ?? '') === 'regular' && (string) ($play['type'] ?? '') === '10');
check('play.title 透传', ($play['title'] ?? '') === '测试合成方案·进行中');

/* ==================== 4. material 契约 ==================== */

$mats = SynthesisService::material($planId);
check('material 返回数组', is_array($mats));
check('material 解析 need_items（1 项材料）', count($mats) === 1, 'got ' . count($mats));
check('material 项含 name/num', isset($mats[0]['name'], $mats[0]['num']));

assertThrows('未知 play_id 抛「活动不存在」', function () {
    SynthesisService::material(999999);
}, '活动不存在');

// 无材料的方案 → 空数组（原站实测合法，前端空数组不弹层）
$emptyPlanId = SynthesisService::adminSavePlan([
    'theme_id' => $themeId, 'title' => '测试合成方案·空材料',
    'start_time' => (time() - 3600) * 1000, 'end_time' => (time() + 3600) * 1000,
    'classify' => 'regular', 'type' => '10',
]);
check('无材料方案返回空数组', SynthesisService::material($emptyPlanId) === []);

/* ==================== 5. recordList 空态 ==================== */

$recs = SynthesisService::records(12345, 1, 10);
check('recordList 真实空分页', ($recs['list'] ?? null) === [] && ($recs['total'] ?? -1) === 0);

/* ==================== 6. status=0 主题不可见 ==================== */

Db::name('synthesis_theme')->where('id', $themeId)->update(['status' => 0]);
assertThrows('主题下架后 themeinfo 抛「主题未开启」', function () use ($themeId) {
    SynthesisService::themeinfo($themeId);
}, '主题未开启');

/* ==================== 收尾 ==================== */

Db::name('synthesis_plan')->where('title', 'like', '测试合成方案%')->delete();
Db::name('synthesis_theme')->where('name', 'like', '测试合成主题%')->delete();

echo "\n==============================\n";
echo "通过 {$passed} / " . ($passed + $failed) . " 项\n";
exit($failed > 0 ? 1 : 0);
