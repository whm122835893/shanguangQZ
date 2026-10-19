<?php
declare(strict_types=1);

/**
 * 抽奖 TDD 契约测试 —— LotteryService 行为级用例
 *
 * 背景：/lottery/* 4 端点原为内存假数据（ContentPlayController + MockData），
 *       lottery / lottery_record 表已建但空。本套用例锁定切换 DB 后的契约：
 *
 * 消耗货币（与原站对齐，前端 Lottery.vue crystal() 直接消费）：
 *   lottery.crystal '10'→黄水晶 '20'→紫水晶 '30'→蓝水晶，扣 user.crystal_*
 *   remaining = floor(拥有量 / 单抽消耗 rule)
 *
 * 奖池：lottery.collection_json JSON 数组
 *   [{collection_id, name, image, lottery_image, level, weight}]
 *   collection_id=0 视为「谢谢参与」；weight 加权随机，0 永不中
 *
 * 七条行为红线：
 *   1. 无进行中活动 → info 抛「暂无抽奖活动」（原站实测 code:0 同文案）
 *   2. 未开始/已结束/未开启 → draw 拒绝
 *   3. 水晶不足 → draw 拒绝「次数不足」
 *   4. 每抽原子扣水晶，连抽按次扣减，永不透支
 *   5. 中藏品 → user_collection upsert + 记录 collection_id>0；未中 → 记录 collection_id=0
 *   6. 中奖记录与发放严格一致（藏品记录数 == user_collection 获得数）
 *   7. info 的 start_time/end_time 下发 'Y-m-d H:i:s' 字符串（前端 Date.parse 消费）
 *
 * 用法：php tools/contract/lottery_test.php
 * 前置：01-schema.sql 已导入（lottery / lottery_record 表存在）。
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\LotteryException;
use app\service\LotteryService;
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

function assertThrows(string $label, callable $fn, string $msgContains = ''): ?LotteryException
{
    global $passed, $failed;
    try {
        $fn();
    } catch (LotteryException $e) {
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
    echo MARK_FAIL . $label . "（预期抛 LotteryException，实际未抛）\n";
    $failed++;
    return null;
}

/* ==================== 测试准备 ==================== */

// 测试用户：独立创建，避免污染既有演示数据
Db::name('user')->where('nickname', 'LOT_T_USER')->delete();
$uid = (int) Db::name('user')->insertGetId([
    'username'       => 'lot_t_user_' . time(),
    'nickname'       => 'LOT_T_USER',
    'inviter_code'   => 'LT' . random_int(10000000, 99999999),
    'crystal_yellow' => 10000,
    'score'          => 0,
    'createtime'     => time(),
]);

// 清理本测试建的 lottery 活动与记录
Db::name('lottery')->where('name', 'like', '测试抽奖%')->delete();
Db::name('lottery_record')->where('user_id', $uid)->delete();

// 测试藏品（奖池目标）
Db::name('collection')->where('name', '测试抽奖藏品A')->delete();
$collAId = (int) Db::name('collection')->insertGetId([
    'name'       => '测试抽奖藏品A',
    'listimg'    => '/static/oss/test/lotA.jpg',
    'createtime' => time(),
]);

$now   = time();
$pool  = json_encode([
    ['collection_id' => $collAId, 'name' => '测试抽奖藏品A', 'image' => '/static/oss/test/lotA.jpg',
     'lottery_image' => '/static/oss/test/lotA.jpg', 'level' => '一等奖', 'weight' => 100],
    ['collection_id' => 0, 'name' => '谢谢参与', 'image' => '', 'lottery_image' => '',
     'level' => '谢谢参与', 'weight' => 0],
], JSON_UNESCAPED_UNICODE);

/* ==================== 1. 无活动 ==================== */

// info(id=0) 回退到「最新进行中活动」是产品语义，库里可能有 seed 演示活动。
// 构造真空场景：临时关闭全部活动，断言后恢复原状态。
$savedSwitch = Db::name('lottery')->where('switch', 1)->column('id');
Db::name('lottery')->where('switch', 1)->update(['switch' => 0]);
try {
    assertThrows('无进行中活动时 info 抛「暂无抽奖活动」', function () use ($uid) {
        LotteryService::info($uid, 0);
    }, '暂无抽奖活动');
} finally {
    if ($savedSwitch) {
        Db::name('lottery')->where('id', 'in', $savedSwitch)->update(['switch' => 1]);
    }
}

/* ==================== 2. 管理端建活动 ==================== */

$lotId = LotteryService::adminSave([
    'name'            => '测试抽奖-进行中',
    'content'         => '<p>活动说明</p>',
    'image1'          => '/static/oss/test/lottery-bg.jpg',
    'crystal'         => '10',
    'rule'            => '10',            // 单抽 10 黄水晶
    'collection_json' => $pool,
    'start_time'      => $now - 3600,
    'end_time'        => $now + 3600,
    'switch'          => 1,
]);
check('adminSave 建活动返回 id', $lotId > 0, "got {$lotId}");

/* ==================== 3. info 契约 ==================== */

$info = LotteryService::info($uid, $lotId);
check('info.remaining = floor(拥有量/单抽消耗) = 1000', (int) ($info['remaining'] ?? 0) === 1000,
    'got ' . var_export($info['remaining'] ?? null, true));
check('info.crystal = 10（前端 crystal() 选图标）', ($info['crystal'] ?? '') === '10');
check('info.crystal_yellow = 用户黄水晶拥有量 10000', (int) ($info['crystal_yellow'] ?? 0) === 10000,
    'got ' . var_export($info['crystal_yellow'] ?? null, true));
check('info.collection_json 是数组（奖池）', is_array($info['collection_json'] ?? null));
check('info.start_time 是 Y-m-d H:i:s 字符串（前端 Date.parse 消费）',
    is_string($info['start_time'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $info['start_time']) === 1,
    'got ' . var_export($info['start_time'] ?? null, true));
check('info.lotteryList 是数组（公告滚动源）', is_array($info['lotteryList'] ?? null));

/* ==================== 4. 状态拒绝 ==================== */

$futureId = LotteryService::adminSave([
    'name' => '测试抽奖-未开始', 'crystal' => '10', 'rule' => '10',
    'collection_json' => $pool, 'start_time' => $now + 86400, 'end_time' => $now + 86400 * 2, 'switch' => 1,
]);
assertThrows('未开始的活动 draw 拒绝「暂未开始」', function () use ($uid, $futureId) {
    LotteryService::draw($uid, $futureId, 1);
}, '暂未开始');

$pastId = LotteryService::adminSave([
    'name' => '测试抽奖-已结束', 'crystal' => '10', 'rule' => '10',
    'collection_json' => $pool, 'start_time' => $now - 86400 * 2, 'end_time' => $now - 86400, 'switch' => 1,
]);
assertThrows('已结束的活动 draw 拒绝「活动已结束」', function () use ($uid, $pastId) {
    LotteryService::draw($uid, $pastId, 1);
}, '活动已结束');

$closedId = LotteryService::adminSave([
    'name' => '测试抽奖-关闭中', 'crystal' => '10', 'rule' => '10',
    'collection_json' => $pool, 'start_time' => $now - 3600, 'end_time' => $now + 3600, 'switch' => 0,
]);
assertThrows('switch=0 的活动 draw 拒绝', function () use ($uid, $closedId) {
    LotteryService::draw($uid, $closedId, 1);
}, '暂无抽奖活动');

assertThrows('number 非 1/5 拒绝', function () use ($uid, $lotId) {
    LotteryService::draw($uid, $lotId, 2);
}, '次数');

/* ==================== 5. 正常抽取与扣减 ==================== */

$wins = LotteryService::draw($uid, $lotId, 1);
check('draw 返回中奖项数组（len=1）', is_array($wins) && count($wins) === 1,
    'got ' . var_export($wins, true));
check('中奖项含 name 字段（前端 toast 消费）', isset($wins[0]['name']));

$after = (int) Db::name('user')->where('id', $uid)->value('crystal_yellow');
check('单抽扣 10 黄水晶（10000→9990）', $after === 9990, "got {$after}");

// 必中藏品（weight=100），连抽 5 次
$wins5 = LotteryService::draw($uid, $lotId, 5);
check('连抽 5 次返回 5 个中奖项', is_array($wins5) && count($wins5) === 5);
$after5 = (int) Db::name('user')->where('id', $uid)->value('crystal_yellow');
check('连抽扣 50 黄水晶（9990→9940）', $after5 === 9940, "got {$after5}");

/* ==================== 6. 发放一致性 ==================== */

$held = Db::name('user_collection')->where('user_id', $uid)->where('collection_id', $collAId)->value('num');
check('中藏品入 user_collection（num=6）', (int) $held === 6, "got " . var_export($held, true));

$recTotal  = (int) Db::name('lottery_record')->where('user_id', $uid)->count();
$recWin    = (int) Db::name('lottery_record')->where('user_id', $uid)->where('collection_id', '>', 0)->count();
check('抽 6 次 = 6 条记录', $recTotal === 6, "got {$recTotal}");
check('必中奖池下 6 条记录全是藏品记录', $recWin === 6, "got {$recWin}");

/* ==================== 7. 水晶不足 ==================== */

Db::name('user')->where('id', $uid)->update(['crystal_yellow' => 5]); // 够 0 次
$remain = LotteryService::info($uid, $lotId);
check('水晶不足时 remaining=0', (int) ($remain['remaining'] ?? -1) === 0);
assertThrows('水晶不足 draw 拒绝「次数不足」', function () use ($uid, $lotId) {
    LotteryService::draw($uid, $lotId, 1);
}, '次数不足');

// 边界：恰好够 1 次但连抽 5 次 → 一次也不应扣（原子拒绝）
Db::name('user')->where('id', $uid)->update(['crystal_yellow' => 10]);
assertThrows('连抽 5 次但只够 1 次时拒绝（不部分执行）', function () use ($uid, $lotId) {
    LotteryService::draw($uid, $lotId, 5);
}, '次数不足');
$edge = (int) Db::name('user')->where('id', $uid)->value('crystal_yellow');
check('原子拒绝后水晶分文未扣（仍 10）', $edge === 10, "got {$edge}");

/* ==================== 8. 记录契约 ==================== */

Db::name('user')->where('id', $uid)->update(['crystal_yellow' => 100]);
LotteryService::draw($uid, $lotId, 1);

$stats = LotteryService::statistics($uid);
check('statistics 返回数组（Lottery.vue 奖品区消费）', is_array($stats) && count($stats) >= 1,
    'got ' . var_export(is_array($stats) ? count($stats) : $stats, true));
check('statistics 项含 prize_name/create_time（Record.vue 消费）',
    isset($stats[0]['prize_name'], $stats[0]['create_time']));

$recs = LotteryService::records($uid, 1, 20);
check('records 返回 list 结构', isset($recs['list']) && is_array($recs['list']) && count($recs['list']) >= 1);
check('records 项含 prize_name', isset($recs['list'][0]['prize_name']));

/* ==================== 9. 公告契约 ==================== */

$info2 = LotteryService::info($uid, $lotId);
check('中奖后 lotteryList 公告含「抽中了」', (function () use ($info2) {
    foreach (($info2['lotteryList'] ?? []) as $it) {
        if (isset($it['msg']) && str_contains($it['msg'], '抽中了')) {
            return true;
        }
    }
    return false;
})());

/* ==================== 收尾 ==================== */

Db::name('lottery')->where('name', 'like', '测试抽奖%')->delete();
Db::name('lottery_record')->where('user_id', $uid)->delete();
Db::name('user_collection')->where('user_id', $uid)->delete();
Db::name('collection')->where('id', $collAId)->delete();
Db::name('user')->where('id', $uid)->delete();

echo "\n==============================\n";
echo "通过 {$passed} / " . ($passed + $failed) . " 项\n";
exit($failed > 0 ? 1 : 0);
