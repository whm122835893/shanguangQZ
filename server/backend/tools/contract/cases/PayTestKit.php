<?php
declare(strict_types=1);

/**
 * 交易链路契约用例的辅助函数（与 BegBuyTestKit 同模式）
 *
 * 抽出来的原因与 BegBuyTestKit 一致：加进 PayCases 会破单文件 300 行红线。
 */

use app\model\UserToken;
use think\facade\Db;

if (! function_exists('payTempToken')) {
    /**
     * 事务内签发一个临时 token（随事务回滚，不污染库）
     *
     * 与 BegBuyTestKit 同做法：直接落 user_token 表 + 插哈希，
     * 而不是走登录接口（登录有限流，用例连打会 401）。
     */
    function payTempToken(int $userId): string
    {
        // issue() 签名是 (device, ip) 返回 ['plain','hash','expiretime']，
        // 不接收 user_id —— 归属靠下面这条 INSERT 的 user_id 列建立。
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
}

if (! function_exists('payBind')) {
    /** 绑定请求（token 走 query 通道，业务参走 POST body） */
    function payBind(string $token, array $post): void
    {
        request()->withGet(['token' => $token]);
        request()->withPost($post);
    }
}

if (! function_exists('payAssert')) {
    /**
     * 断言响应的 code 与 msg 逐字一致
     *
     * @param \think\response\Json $resp
     */
    function payAssert(string $label, $resp, int $code, string $msg): void
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

if (! function_exists('payExpect')) {
    /** 严格全等断言 */
    function payExpect(string $label, $actual, $expected): void
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

if (! function_exists('payMakeListing')) {
    /**
     * 事务内造一条在售挂单（供「服务端算价」用例使用）
     *
     * 🔴 为什么必须自己造：原站账号无挂单（market_market_getMarketList
     *    样本 data 为空列表），market_goods 表恒 0 行，算价分支本地不可达。
     *    造数据只为验证「金额按挂单价算、不认前端价」这条逻辑，
     *    事务回滚后不留痕。
     */
    function payMakeListing(int $collectionId, string $price, int $num = 5): int
    {
        return (int) Db::name('market_goods')->insertGetId([
            'user_id'        => 16797,
            'collection_id'  => $collectionId,
            'collection_code'=> '',
            'price'          => $price,
            'num'            => $num,
            'sold_num'       => 0,
            'status'         => 1,
            'createtime'     => time(),
            'updatetime'     => time(),
        ]);
    }
}
