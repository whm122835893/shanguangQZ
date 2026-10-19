<?php
declare(strict_types=1);

/**
 * 账号域契约用例的辅助函数（与 PayTestKit 同模式）
 *
 * 抽出来的原因与 PayTestKit 一致：加进 AccountCases 会破单文件 300 行红线。
 */

use app\service\SmsCodeService;
use think\facade\Db;

if (! function_exists('acctReset')) {
    /** 清掉某手机号的测试残留（验证码、用户、凭据、token） */
    function acctReset(string $mobile): void
    {
        $ids = Db::name('user')->where('mobile', $mobile)->column('id');
        if ($ids !== []) {
            Db::name('user_token')->whereIn('user_id', $ids)->delete();
            Db::name('user_credential')->whereIn('user_id', $ids)->delete();
            Db::name('user')->whereIn('id', $ids)->delete();
        }
        Db::name('sms_code')->where('mobile', $mobile)->delete();
        Db::name('sms_send_log')->where('mobile', $mobile)->delete();
    }
}

if (! function_exists('acctIssueCode')) {
    /**
     * 造一条可用的验证码（直接调服务层，拿到明文）
     *
     * 🔴 为什么绕过 HTTP 层：验证码明文只在服务端内部流转，
     *    HTTP 响应体里**故意不返回**。测试要拿到明文只能走服务层。
     *    这也顺带验证了「响应不泄露码」这一约束确实成立。
     */
    function acctIssueCode(string $mobile, string $scene): string
    {
        // 先清掉旧的未消费码，否则会命中 60 秒限流
        Db::name('sms_code')->where('mobile', $mobile)->where('scene', $scene)->delete();
        $r = SmsCodeService::send($mobile, $scene, '127.0.0.1');
        return (string) ($r['plain'] ?? '');
    }
}

if (! function_exists('acctBind')) {
    /** 绑定 POST body（无鉴权端点，不需要 token） */
    function acctBind(array $post): void
    {
        request()->withPost($post);
        request()->withGet([]);
    }
}

if (! function_exists('acctAssert')) {
    /**
     * 断言响应的 code 与 msg 逐字一致
     *
     * @param \think\response\Json $resp
     */
    function acctAssert(string $label, $resp, int $code, string $msg): void
    {
        global $results, $failed;
        $data = method_exists($resp, 'getData') ? $resp->getData() : [];
        $gotC = (int) ($data['code'] ?? -1);
        $gotM = (string) ($data['msg'] ?? '');
        if ($gotC === $code && $gotM === $msg) {
            $results[] = sprintf('%-30s PASS  code=%d msg="%s"', $label, $gotC, $gotM);
        } else {
            $results[] = sprintf('%-30s FAIL  期望 code=%d msg="%s"，实际 code=%d msg="%s"',
                $label, $code, $msg, $gotC, $gotM);
            $failed++;
        }
    }
}

if (! function_exists('acctExpect')) {
    /** 严格全等断言 */
    function acctExpect(string $label, $actual, $expected): void
    {
        global $results, $failed;
        if ($actual === $expected) {
            $results[] = sprintf('%-30s PASS', $label);
        } else {
            $results[] = sprintf('%-30s FAIL  期望 %s，实际 %s',
                $label, var_export($expected, true), var_export($actual, true));
            $failed++;
        }
    }
}

if (! function_exists('acctRow')) {
    /** 取用户行（不存在返回 null） */
    function acctRow(string $mobile)
    {
        return Db::name('user')->where('mobile', $mobile)->find();
    }
}

if (! function_exists('acctCodeRows')) {
    /** 取某手机号某场景的验证码记录条数 */
    function acctCodeRows(string $mobile, string $scene): int
    {
        return (int) Db::name('sms_code')->where('mobile', $mobile)->where('scene', $scene)->count();
    }
}

if (! function_exists('acctHashIsArgon')) {
    /** 判断密码哈希是否为 Argon2id */
    function acctHashIsArgon(string $hash): bool
    {
        return strpos($hash, '$argon2id$') === 0;
    }
}
