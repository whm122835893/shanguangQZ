<?php
declare(strict_types=1);

/**
 * 契约回归测试 —— 已切 DB/契约驱动的端点逐字段比对
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
use app\model\Banner;
use app\model\Collection;
use app\model\Message;
use app\model\User;
use app\model\UserCollection;
use app\service\Formatter;
use app\service\LoginFormatter;
use app\service\TradeFormatter;
use app\service\UnionFormatter;
use app\service\UserFormatter;
use tools\contract\ContractDiff;

// 样本目录：默认为 /workspace/recon/raw2，可用环境变量覆盖
// 🔴 四批样本目录都要扫：不同端点散落在不同批次。
//    banner 的样本在 raw/（第一批），早期只读 raw2 会导致该用例静默跳过——
//    静默跳过比失败更危险，所以 check() 里「跳过」也计为失败。
//    raw4 是第四轮重抓（20 个 401 + 2 个 500），begBuyList 的成功样本在里面。
$SAMPLE_DIR  = getenv('GANDART_SAMPLE_DIR') ?: '/workspace/recon/raw2';
$EXTRA_DIR   = getenv('GANDART_SAMPLE_DIR3') ?: '/workspace/recon/raw3';
$SAMPLE_DIR1 = getenv('GANDART_SAMPLE_DIR1') ?: '/workspace/recon/raw';
$SAMPLE_DIR4 = getenv('GANDART_SAMPLE_DIR4') ?: '/workspace/recon/raw4';

if (!is_dir($SAMPLE_DIR)) {
    fwrite(STDERR, "样本目录不存在：{$SAMPLE_DIR}\n");
    fwrite(STDERR, "可用 GANDART_SAMPLE_DIR=/path/to/raw2 覆盖\n");
    exit(2);
}

/** 读原站样本的 data 部分 */
function raw(string $name): array
{
    global $SAMPLE_DIR, $EXTRA_DIR, $SAMPLE_DIR1, $SAMPLE_DIR4;
    foreach ([$SAMPLE_DIR, $SAMPLE_DIR1, $EXTRA_DIR, $SAMPLE_DIR4] as $dir) {
        $f = $dir . '/' . $name . '.json';
        if (is_file($f)) {
            $j = json_decode((string) file_get_contents($f), true);
            return is_array($j) ? $j : [];
        }
    }
    return [];
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
    int $matchId = 0,
    ?string $localMsg = null
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
        $results[] = sprintf('%-18s [跳过]（原站样本无成功数据）', $label);
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
            $results[] = sprintf('%-18s [跳过]（原站样本无 id=%d 的记录）', $label, $matchId);
            $failed++;
            return;
        }
    } else {
        $first = $node;
    }

    $d = new ContractDiff();
    $d->compare($first, $local, $ignoreFields);
    $fieldCount = count($first);

    /**
     * 🔴 信封 msg 也必须一致 —— 这是本用例新增的护栏。
     *
     * 踩过的坑：banner 端点 data 层 5 字段全对，但原站 msg="banner列表"，
     * 本地返回空串。data 层比对完全查不出来，因为 msg 在信封层。
     * 而前端 axios 拦截器在 code===0 时会把 msg 直接 toast 给用户，
     * 成功路径虽不弹窗，但两端不一致就是契约漂移，必须归零。
     *
     * @var string|null $rawMsg 原站实测 msg
     */
    $rawMsg = (string) ($sample['msg'] ?? '');
    $msgBad = $localMsg !== null && $localMsg !== $rawMsg;

    if ($d->passed() && !$msgBad) {
        $results[] = sprintf(
            '%-18s PASS  字段 %d/%d 全部一致  msg="%s"',
            $label, $fieldCount, $fieldCount, $rawMsg
        );
    } else {
        $failed++;
        $results[] = $d->report($label, $fieldCount) ;
        if ($msgBad) {
            // 文本标记不用 emoji —— CLI 输出约定见 pitfalls gate/emoji-in-cli-console-output
            // （seed.php 当年清过一轮，这处 ↳❌ 是漏网的，本轮补上）
            $results[] = sprintf(
                '  -> [FAIL] 信封 msg 不一致：原站="%s" 本地="%s"',
                $rawMsg, $localMsg
            );
        }
    }
}

echo "契约回归测试 —— 对照原站实测样本\n";
echo str_repeat('=', 64) . "\n";

/**
 * 运行时字段：每次请求都会变，与契约无关，比对时应排除
 *
 * logintime / loginip / login_count —— 登录动作改写，不是「结构契约」
 */require __DIR__ . '/Cases.php';
runContractCases();

echo implode("\n", $results) . "\n";
echo str_repeat('=', 64) . "\n";
echo $failed === 0 ? "全部通过\n" : "发现 {$failed} 个端点存在契约差异\n";

exit($failed === 0 ? 0 : 1);
