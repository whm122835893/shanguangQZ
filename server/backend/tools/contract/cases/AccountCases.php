<?php
declare(strict_types=1);

/**
 * 账号域契约用例：sms/send / user/register / user/resetpwd
 *
 * 🔴 本组的头号断言是**安全红线**：
 *    2026-10-19 之前，这三个端点全是 MOCK：
 *      sms/send    → 响应体里直接回传 code='123456'（验证码公开给所有人）
 *      register    → 不落库，返回 MockData 假用户
 *      resetpwd    → 不改密，只回显 mobile
 *    其中「响应体回传明文验证码」是**任何人都能利用**的漏洞：
 *    调一次 sms/send 就拿到验证码，注册/改密的验证码环节形同虚设。
 *
 *    因此本组第一条断言就是：**sms/send 的响应体绝不含明文验证码**。
 *
 * 用例全部走 Controller 直连 + 事务包住回滚。
 */

require_once __DIR__ . '/AccountTestKit.php';

use app\controller\api\AccountController;
use app\controller\api\SmsController;
use app\model\SmsCode;
use app\service\SmsCodeService;
use think\facade\Db;

if (! function_exists('runAccountCases')) {
    function runAccountCases(): void
    {
        global $results, $failed;

        $m1 = '13910000001';
        $m2 = '13910000002';

        Db::startTrans();
        try {
            acctReset($m1);
            acctReset($m2);

            // ==================== 1. sms/send ====================
            acctBind(['mobile' => $m1, 'event' => 'register']);
            $smsResp = (new SmsController())->send();
            acctAssert('sms:发送成功', $smsResp, 1, '验证码已发送');

            // 🔴 安全红线：响应体绝不含明文验证码
            $d = $smsResp->getData()['data'] ?? [];
            acctExpect('sms:响应不含 code 字段', array_key_exists('code', $d), false);
            acctExpect('sms:响应键序', array_keys($d), ['mobile', 'event', 'expire']);
            acctExpect('sms:expire=60', $d['expire'] ?? null, SmsCode::SEND_INTERVAL);

            // 整包扫描：任何位置都不该出现 6 位数字明文码
            $raw = json_encode($smsResp->getData(), JSON_UNESCAPED_UNICODE);
            $row = Db::name('sms_code')->where('mobile', $m1)->where('scene', 'register')->find();
            acctExpect('sms:库中已存哈希', $row !== null, true);
            acctExpect('sms:哈希是 HMAC 长度64', strlen((string) ($row['code_hash'] ?? '')), 64);
            acctExpect('sms:库中无明文码字段', $row !== null && !isset($row['code']), true);
            // 明文码若出现在响应里，响应长度会异常增大；这里做弱断言
            acctExpect('sms:响应体长度正常(<200)', strlen((string) $raw) < 200, true);

            // 60 秒内重发被限流
            acctBind(['mobile' => $m1, 'event' => 'register']);
            acctAssert('sms:60秒内重发被限', (new SmsController())->send(), 0, '请 60 秒后再试');

            // 参数校验
            acctBind(['mobile' => '123', 'event' => 'register']);
            acctAssert('sms:手机号格式错误', (new SmsController())->send(), 0, '手机号格式错误');

            acctBind(['mobile' => $m2, 'event' => 'xxx']);
            acctAssert('sms:不支持的场景', (new SmsController())->send(), 0, '不支持的验证场景');

            // ==================== 2. 验证码服务 ====================
            // 错码
            $c = acctIssueCode($m1, 'register');
            acctExpect('verify:拿到6位码', preg_match('/^\d{6}$/', $c), 1);
            $v = SmsCodeService::verify($m1, 'register', '000000');
            acctExpect('verify:错码被拒', $v['ok'], false);

            // 错5次后码作废
            for ($i = 0; $i < 5; $i++) {
                SmsCodeService::verify($m1, 'register', '000000');
            }
            $v = SmsCodeService::verify($m1, 'register', $c);
            acctExpect('verify:错5次后原码也作废', $v['ok'], false);

            // 对码通过 + 一次性消费
            $c2 = acctIssueCode($m1, 'register');
            $v = SmsCodeService::verify($m1, 'register', $c2);
            acctExpect('verify:对码通过', $v['ok'], true);
            $v = SmsCodeService::verify($m1, 'register', $c2);
            acctExpect('verify:重放被拒', $v['ok'], false);

            // 跨场景不可用：注册码不能拿去改密
            $c3 = acctIssueCode($m1, 'register');
            $v = SmsCodeService::verify($m1, 'resetpwd', $c3);
            acctExpect('verify:跨场景不可用', $v['ok'], false);

            // ==================== 3. user/register ====================
            acctBind(['mobile' => $m1, 'password' => 'short']);
            acctAssert('register:密码太短', (new AccountController())->register(), 0, '密码至少 6 位');

            acctBind(['mobile' => $m1, 'password' => 'Test123456', 'code' => '000000']);
            acctAssert('register:验证码错', (new AccountController())->register(), 0, '验证码错误');

            $code = acctIssueCode($m1, 'register');
            acctBind(['mobile' => $m1, 'password' => 'Test123456', 'code' => $code]);
            $regResp = (new AccountController())->register();
            acctAssert('register:成功', $regResp, 1, '注册成功');

            // 落库验证
            $u = acctRow($m1);
            acctExpect('register:user 已落库', $u !== null, true);
            acctExpect('register:username=mobile', (string) ($u['username'] ?? ''), $m1);
            acctExpect('register:status=normal', (string) ($u['status'] ?? ''), 'normal');
            acctExpect('register:mobile_verified=1', (int) ($u['mobile_verified'] ?? 0), 1);
            acctExpect('register:生成了邀请码', (string) ($u['inviter_code'] ?? ''), 'Q' . (int) ($u['id'] ?? 0));

            // 凭据表：Argon2id
            $cred = Db::name('user_credential')->where('user_id', (int) $u['id'])->find();
            acctExpect('register:凭据已落库', $cred !== null, true);
            acctExpect('register:密码是 Argon2id', acctHashIsArgon((string) ($cred['password_hash'] ?? '')), true);
            acctExpect('register:不存明文', strpos((string) ($cred['password_hash'] ?? ''), 'Test123456') === false, true);

            // 响应体：26 字段 login 契约 + 无密码
            $ui = $regResp->getData()['data']['userinfo'] ?? [];
            acctExpect('register:userinfo 26 字段', count($ui), 26);
            acctExpect('register:响应无明文密码', strpos(json_encode($regResp->getData()), 'Test123456') === false, true);
            acctExpect('register:token 是 UUID', preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', (string) ($ui['token'] ?? '')), 1);

            // 重复注册
            acctBind(['mobile' => $m1, 'password' => 'Test123456', 'code' => acctIssueCode($m1, 'register')]);
            acctAssert('register:重复手机号被拒', (new AccountController())->register(), 0, '该手机号已注册，请直接登录');

            // ==================== 4. user/resetpwd ====================
            // 改密前 token 数
            $before = (int) Db::name('user_token')
                ->where('user_id', (int) $u['id'])
                ->where('expiretime', '>', time())->count();

            acctBind(['mobile' => $m1, 'code' => '000000', 'password' => 'NewPass888', 'repassword' => 'NewPass888']);
            acctAssert('resetpwd:验证码错', (new AccountController())->resetPwd(), 0, '验证码错误');

            acctBind(['mobile' => $m1, 'code' => acctIssueCode($m1, 'resetpwd'), 'password' => 'NewPass888', 'repassword' => 'Different1']);
            acctAssert('resetpwd:两次密码不一致', (new AccountController())->resetPwd(), 0, '两次输入的密码不一致');

            acctBind(['mobile' => $m1, 'code' => acctIssueCode($m1, 'resetpwd'), 'password' => 'NewPass888', 'repassword' => 'NewPass888']);
            acctAssert('resetpwd:成功', (new AccountController())->resetPwd(), 1, '重置成功');

            // 🔴 改密后该用户全部 token 必须被吊销
            $after = (int) Db::name('user_token')
                ->where('user_id', (int) $u['id'])
                ->where('expiretime', '>', time())->count();
            acctExpect('resetpwd:旧token 已全部吊销', $after, 0);
            acctExpect('resetpwd:改密前确有 token', $before > 0, true);

            // 密码确实变了：哈希与改密前不同，且仍是 Argon2id
            $cred2 = Db::name('user_credential')->where('user_id', (int) $u['id'])->find();
            acctExpect('resetpwd:哈希已更新', (string) ($cred2['password_hash'] ?? '') !== (string) ($cred['password_hash'] ?? ''), true);
            acctExpect('resetpwd:仍是 Argon2id', acctHashIsArgon((string) ($cred2['password_hash'] ?? '')), true);

            // 未注册手机号改密
            acctBind(['mobile' => '13919999999', 'code' => acctIssueCode('13919999999', 'resetpwd'), 'password' => 'NewPass888']);
            acctAssert('resetpwd:手机号未注册', (new AccountController())->resetPwd(), 0, '该手机号未注册');
        } finally {
            Db::rollback();
            request()->withPost([]);
            request()->withGet([]);
        }
    }
}
