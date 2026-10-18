<?php
declare(strict_types=1);

/**
 * 契约回归测试 —— 四个已切 DB 驱动的端点
 *
 * 用途：重构 Controller / 改 Formatter 之后，一条命令证明「行为没变」。
 * 这是防止静默漂移的唯一护栏 —— 本项目已抓到过 3 次（漏字段、
 * null 被压成 0、id 被自增覆盖），全靠人工比对才发现。
 *
 * 用法：
 *   php tools/contract/run.php
 *
 * 退出码：0 = 全通过，1 = 有契约差异（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/ContractDiff.php';

// 启动 ThinkPHP（DB 门面依赖应用初始化，否则报 Undefined db config）
$app = new think\App();
$app->initialize();

use app\model\ArtelOrder;
use app\model\Collection;
use app\model\Message;
use app\model\User;
use app\model\UserCollection;
use app\service\TradeFormatter;
use app\service\UserFormatter;
use tools\contract\ContractDiff;

// 样本目录：默认为 /workspace/recon/raw2，可用环境变量覆盖
$SAMPLE_DIR = getenv('GANDART_SAMPLE_DIR') ?: '/workspace/recon/raw2';

if (!is_dir($SAMPLE_DIR)) {
    fwrite(STDERR, "样本目录不存在：{$SAMPLE_DIR}\n");
    fwrite(STDERR, "可用 GANDART_SAMPLE_DIR=/path/to/raw2 覆盖\n");
    exit(2);
}

/** 读原站样本的 data 部分 */
function raw(string $name): array
{
    global $SAMPLE_DIR;
    $j = json_decode((string) file_get_contents($SAMPLE_DIR . '/' . $name . '.json'), true);
    return is_array($j) ? $j : [];
}

$results = [];
$failed  = 0;

/** 执行一个端点的比对 */
function check(
    string $label,
    string $sampleFile,
    array $local,
    string $envelopePath,
    array $ignoreFields = [],
    int $matchId = 0
): void {
    global $results, $failed;

    $sample = raw($sampleFile);
    // 路径从信封顶层算起（如 'data' / 'data.data' / 'data.list'）
    $node = $sample;
    foreach (explode('.', $envelopePath) as $seg) {
        $node = is_array($node) && array_key_exists($seg, $node) ? $node[$seg] : null;
    }
    if (!is_array($node) || $node === []) {
        // 无成功样本可比 —— 显式标红，不可静默跳过
        $results[] = sprintf('%-18s ⚠ 跳过（原站样本无成功数据）', $label);
        $failed++;
        return;
    }

    // 两种形态：
    //   列表型（orderList / antMycollection / newList）→ 定位到目标记录
    //   扁平型（getUserInfo）→ 整个 data 就是一条记录
    if (array_is_list($node)) {
        // matchId > 0 时按 id 匹配，避免「取首条」比到别的记录上
        $first = null;
        if ($matchId > 0) {
            foreach ($node as $row) {
                if (is_array($row) && (int) ($row['id'] ?? 0) === $matchId) {
                    $first = $row;
                    break;
                }
            }
        } else {
            $first = $node[0];
        }
        if (!is_array($first)) {
            $results[] = sprintf('%-18s ⚠ 跳过（原站样本无 id=%d 的记录）', $label, $matchId);
            $failed++;
            return;
        }
    } else {
        $first = $node;
    }

    $d = new ContractDiff();
    $d->compare($first, $local, $ignoreFields);
    $fieldCount = count($first);
    if ($d->passed()) {
        $results[] = sprintf('%-18s PASS  字段 %d/%d 全部一致', $label, $fieldCount, $fieldCount);
    } else {
        $failed++;
        $results[] = $d->report($label, $fieldCount);
    }
}

echo "契约回归测试 —— 对照原站实测样本\n";
echo str_repeat('=', 64) . "\n";

/**
 * 运行时字段：每次请求都会变，与契约无关，比对时应排除
 *
 * logintime / loginip / login_count —— 登录动作改写，不是「结构契约」
 */
const RUNTIME_FIELDS = ['logintime', 'loginip', 'updatetime'];

// ---- 1. getUserInfo ----
try {
    $u = User::find(16797);
    if ($u === null) {
        $results[] = 'getUserInfo        ⚠ 跳过（本地无 user id=16797，请先跑 seed.php）';
        $failed++;
    } else {
        check('getUserInfo', 'user_getuserinfo', UserFormatter::userInfo($u), 'data', RUNTIME_FIELDS);
    }
} catch (\Throwable $e) {
    $results[] = 'getUserInfo        异常：' . $e->getMessage();
    $failed++;
}

// ---- 2. orderList（原站样本 2 条，逐条按 id 匹配比对）----
try {
    $sampleRows = raw('order_order_orderlist')['data']['data'] ?? [];
    if ($sampleRows === []) {
        $results[] = 'orderList          ⚠ 跳过（原站样本 total=0）';
        $failed++;
    } else {
        $n = 0;
        $bad = 0;
        foreach ($sampleRows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $rec = $id > 0 ? ArtelOrder::find($id) : null;
            if ($rec === null) {
                $results[] = sprintf('  orderList id=%d ⚠ 本地缺该订单', $id);
                $failed++;
                continue;
            }
            $n++;
            check('orderList#' . $id, 'order_order_orderlist',
                TradeFormatter::order($rec->toArray()), 'data.data', [], $id);
        }
        if ($n === 0) {
            $results[] = 'orderList          ⚠ 跳过（本地无对应订单）';
        }
        unset($bad);
    }
} catch (\Throwable $e) {
    $results[] = 'orderList          异常：' . $e->getMessage();
    $failed++;
}

// ---- 3. antMycollection ----
try {
    $rows = UserCollection::where('user_id', 16797)->order('id', 'asc')->select()->toArray();
    if ($rows === []) {
        $results[] = 'antMycollection    ⚠ 跳过（本地无持有记录）';
        $failed++;
    } else {
        // 必须复刻 Controller 的关联加载，否则测的不是真实链路
        // （Controller 在 Core.php 里手工建 $collMap 后作为第二参数传入）
        $cids = array_values(array_unique(array_map(
            static fn($r) => (int) $r['collection_id'],
            $rows
        )));
        $collRows = Collection::whereIn('id', $cids)->select()->toArray();
        $collMap  = [];
        foreach ($collRows as $cr) {
            $collMap[(int) $cr['id']] = $cr;
        }
        check('antMycollection', 'user_collection_user_collection_antmycollection',
            TradeFormatter::userCollection($rows[0], $collMap[(int) $rows[0]['collection_id']] ?? null),
            'data.data');
    }
} catch (\Throwable $e) {
    $results[] = 'antMycollection    异常：' . $e->getMessage();
    $failed++;
}

// ---- 4. newList（消息）----
try {
    $list = Message::order('id', 'asc')->select()->toArray();
    if ($list === []) {
        $results[] = 'newList            ⚠ 跳过（本地无消息）';
        $failed++;
    } else {
        check('newList', 'user_newlist', TradeFormatter::message($list[0]), 'data.list');
    }
} catch (\Throwable $e) {
    $results[] = 'newList            异常：' . $e->getMessage();
    $failed++;
}

echo implode("\n", $results) . "\n";
echo str_repeat('=', 64) . "\n";
echo $failed === 0 ? "全部通过\n" : "发现 {$failed} 个端点存在契约差异\n";

exit($failed === 0 ? 0 : 1);
