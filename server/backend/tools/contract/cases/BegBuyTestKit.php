<?php
declare(strict_types=1);

/**
 * 求购域契约测试辅助（ BegBuyCases.php 专用，被其 require_once 引入 ）
 *
 * 为什么单独拆：BegBuyCases 连用例带辅助一度 309 行，越过单文件 300 红线。
 * 按 Cases.php 的拆分先例（「用例之间」拆领域文件），辅助函数是共用设施，
 * 独立成文件后两边都在红线内。
 *
 * 四个辅助分别解决：
 *   begBuyTempToken  事务内签发临时 token（避免在 user_token 留测试垃圾）
 *   begBuyBind       进程内绑定请求参数（token 走 query、业务参数走 POST，
 *                    与线上网关剥自定义 header 的行为一致）
 *   begBuyAssert     信封断言（code + msg 逐字）
 *   begBuyExpect     值断言（严格 ===，null 与 "" 是两回事）
 */

use app\model\UserToken;
use think\facade\Db;

if (!function_exists('begBuyTempToken')) {
    /** 事务内给指定用户签一个临时 token（明文返回，哈希落库） */
    function begBuyTempToken(int $userId): string
    {
        $t = UserToken::issue('contract-test', '127.0.0.1');
        Db::name('user_token')->insert([
            'user_id'    => $userId,
            'token'      => $t['hash'],
            'device'     => 'contract-test',
            'ip'         => '127.0.0.1',
            'expiretime' => $t['expiretime'],
            'createtime' => time(),
        ]);
        return $t['plain'];
    }

    /** 绑定请求参数：token 走 query 通道（与线上网关行为一致），业务参数走 POST */
    function begBuyBind(string $token, array $post): void
    {
        request()->withGet($token === '' ? [] : ['token' => $token]);
        request()->withPost($post);
    }

    /** 信封断言：code + msg 逐字（msg 是端点级契约，漂移即红） */
    function begBuyAssert(string $label, $json, int $expectCode, string $expectMsg): void
    {
        global $results, $failed;
        $data = $json->getData();
        $code = (int) ($data['code'] ?? -1);
        $msg  = (string) ($data['msg'] ?? '');
        if ($code === $expectCode && $msg === $expectMsg) {
            $results[] = sprintf('%-24s PASS  code=%d msg="%s"', $label, $code, $msg);
        } else {
            $failed++;
            $results[] = sprintf(
                '%-24s FAIL  期望 code=%d msg="%s"，实际 code=%d msg="%s"',
                $label, $expectCode, $expectMsg, $code, $msg
            );
        }
    }

    /** 值断言：严格 ===（类型也参与，null 与 "" 是两回事） */
    function begBuyExpect(string $label, $actual, $expected): void
    {
        global $results, $failed;
        if ($actual === $expected) {
            $results[] = sprintf('%-24s PASS', $label);
        } else {
            $failed++;
            $results[] = sprintf(
                '%-24s FAIL  期望 %s，实际 %s',
                $label,
                var_export($expected, true),
                var_export($actual, true)
            );
        }
    }
}
