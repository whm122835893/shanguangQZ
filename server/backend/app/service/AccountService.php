<?php
declare(strict_types=1);

namespace app\service;

use app\model\User;
use app\model\UserCredential;
use think\facade\Db;

/**
 * 账号服务 —— 注册 / 改密
 *
 * 🔴 为什么单独成 Service 而不是写在 Controller 里：
 *    注册要写两张表（user + user_credential），改密要写两张表
 *    （user_credential + user_token 吊销），都是多表事务。
 *    事务边界必须由 Service 持有 —— 写在 Controller 里，
 *    一旦有人加个 early return 就会漏掉 rollback。
 *
 * ==================== 三条底线 ====================
 *
 * 1. **密码只存 Argon2id 哈希**
 *    UserCredential::makeHash() 参数写死不可配置。
 *
 * 2. **改密必须吊销该用户全部 token**
 *    改密码的语义是「怀疑密码泄露」。若旧 token 仍然有效，
 *    攻击者手里的 token 可以继续用 —— 改密等于没改。
 *    这是安全设计的常识，但很容易漏，必须在这里强制。
 *
 * 3. **手机号唯一性靠数据库约束兜底**
 *    应用层先查只是快速失败，真正防并发重复注册靠唯一索引。
 *
 * @package app\service
 */
final class AccountService
{
    /**
     * 注册
     *
     * @param string $mobile      手机号
     * @param string $password    明文密码
     * @param string $code        短信验证码
     * @param string $inviterCode 邀请码（可空）
     * @param string $ip          注册 IP
     * @return array{ok:bool, msg:string, user:?User}
     */
    public static function register(
        string $mobile,
        string $password,
        string $code,
        string $inviterCode = '',
        string $ip = ''
    ): array {
        // ---- 参数校验 ----
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return ['ok' => false, 'msg' => '手机号格式错误', 'user' => null];
        }
        $pwdErr = PasswordPolicy::validate($password);
        if ($pwdErr !== '') {
            return ['ok' => false, 'msg' => $pwdErr, 'user' => null];
        }

        // ---- 验证码校验（先消耗验证码，再落库）----
        //
        // 🔴 顺序很关键：先校验验证码再查库。
        //    反过来的话，攻击者可以用「手机号是否已注册」来探测
        //    用户存在性，而验证码还没消耗就把码浪费掉了。
        $verify = SmsCodeService::verify($mobile, 'register', $code);
        if (!$verify['ok']) {
            return ['ok' => false, 'msg' => $verify['msg'], 'user' => null];
        }

        // ---- 手机号是否已注册 ----
        //
        // ⚠️ 这里的「已注册」提示是**必要的**：
        //    注册场景下用户需要知道「这个号已被用」才能去登录，
        //    与登录场景的用户枚举防护不冲突 ——
        //    登录失败才必须统一文案（见 UserCredential::GENERIC_ERROR）。
        if (self::mobileExists($mobile)) {
            return ['ok' => false, 'msg' => '该手机号已注册，请直接登录', 'user' => null];
        }

        // ---- 邀请码解析 ----
        $inviterId = 0;
        if ($inviterCode !== '') {
            $inviter = User::where('inviter_code', $inviterCode)->find();
            if ($inviter !== null) {
                $inviterId = (int) $inviter['id'];
            }
            // 邀请码不存在时静默忽略 —— 不因为邀请码无效就拒绝注册
        }

        $now = time();

        // ---- 事务落库 ----
        Db::startTrans();
        try {
            $userId = (int) Db::name('user')->insertGetId([
                'username'         => $mobile,   // 实测 username 与 mobile 同值
                'nickname'         => $mobile,   // 昵称默认取手机号（前端可改）
                'mobile'           => $mobile,
                'mobile_verified'  => 1,         // 已通过短信验证码验证
                'status'           => 'normal',
                'inviter_user_id'  => $inviterId,
                'inviter_code'     => '',        // 本人邀请码，注册后按需生成
                'register_ip'      => $ip,
                'pwd_set_at'       => $now,
                'joinip'           => $ip,
                'jointime'         => $now,
                'logintime'        => $now,
                'loginip'          => $ip,
                'createtime'       => $now,
                'updatetime'       => $now,
            ]);

            // ---- 凭据表：Argon2id 哈希 ----
            Db::name('user_credential')->insert([
                'user_id'         => $userId,
                'password_hash'   => UserCredential::makeHash($password),
                'locked'          => 0,
                'lock_until'      => 0,
                'login_fail_count'=> 0,
                'pwd_status'      => 1,          // 正常
                'pwd_updated_at'  => $now,
                'createtime'      => $now,
                'updatetime'      => $now,
            ]);

            // ---- 生成本人邀请码（Q+uid，唯一索引兜底）----
            $thisCode = 'Q' . $userId;
            Db::name('user')->where('id', $userId)->update([
                'inviter_code' => $thisCode,
                'updatetime'   => $now,
            ]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            // 🔴 唯一索引冲突 = 并发下同一手机号被注册两次
            if (PasswordPolicy::isDuplicateEntry($e)) {
                return ['ok' => false, 'msg' => '该手机号已注册，请直接登录', 'user' => null];
            }
            throw $e;
        }

        $user = User::find($userId);
        return ['ok' => true, 'msg' => '注册成功', 'user' => $user];
    }

    /**
     * 重置密码
     *
     * @return array{ok:bool, msg:string}
     */
    public static function resetPassword(string $mobile, string $code, string $password, string $repassword = ''): array
    {
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return ['ok' => false, 'msg' => '手机号格式错误'];
        }
        if ($repassword !== '' && $repassword !== $password) {
            return ['ok' => false, 'msg' => '两次输入的密码不一致'];
        }
        $pwdErr = PasswordPolicy::validate($password);
        if ($pwdErr !== '') {
            return ['ok' => false, 'msg' => $pwdErr];
        }

        // ---- 验证码校验 ----
        $verify = SmsCodeService::verify($mobile, 'resetpwd', $code);
        if (!$verify['ok']) {
            return ['ok' => false, 'msg' => $verify['msg']];
        }

        // ---- 找用户 ----
        //
        // ⚠️ 用户不存在时的文案与「验证码错误」保持区分是**有意的**：
        //    改密场景下用户已经通过短信验证码证明了自己持有该手机号，
        //    不存在用户枚举风险（攻击者无法只靠这个接口探测手机号）。
        $user = User::where('mobile', $mobile)->find();
        if ($user === null) {
            return ['ok' => false, 'msg' => '该手机号未注册'];
        }

        $userId = (int) $user['id'];
        $now = time();

        Db::startTrans();
        try {
            $cred = UserCredential::where('user_id', $userId)->find();
            if ($cred === null) {
                // 理论上不该发生（注册时必写），但若数据被外部改过，
                // 这里补建一条而不是让用户卡死。
                Db::name('user_credential')->insert([
                    'user_id'         => $userId,
                    'password_hash'   => UserCredential::makeHash($password),
                    'locked'          => 0,
                    'lock_until'      => 0,
                    'login_fail_count'=> 0,
                    'pwd_status'      => 1,
                    'pwd_updated_at'  => $now,
                    'createtime'      => $now,
                    'updatetime'      => $now,
                ]);
            } else {
                Db::name('user_credential')->where('id', (int) $cred['id'])->update([
                    'password_hash'  => UserCredential::makeHash($password),
                    // 🔴 改密后解除锁定状态：用户已证明身份，
                    //    之前的锁定（可能因爆破触发）应当解除。
                    'locked'         => 0,
                    'lock_until'     => 0,
                    'login_fail_count' => 0,
                    'pwd_status'     => 1,
                    'pwd_updated_at' => $now,
                    'updatetime'     => $now,
                ]);
            }

            // ---- 吊销该用户全部 token ----
            //
            // 🔴 这是改密的安全核心。改密的语义是「怀疑密码泄露」，
            //    若旧 token 仍有效，攻击者手里的 token 可以继续用 ——
            //    改密码等于没改。
            $revoked = Db::name('user_token')
                ->where('user_id', $userId)
                ->where('expiretime', '>', time())
                ->update(['expiretime' => $now]);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return ['ok' => true, 'msg' => '重置成功'];
    }

    /**
     * 密码强度校验
     *
     * @return string 空串表示通过，否则是错误文案
     */

    /**
     * 手机号是否已注册
     */
    private static function mobileExists(string $mobile): bool
    {
        return User::where('mobile', $mobile)->count() > 0;
    }

    /**
     * 是否唯一索引冲突
     */

    /**
     * 签发登录 token 并返回登录响应
     *
     * 注册成功后前端会跳转到登录页，因此这里**不自动登录** ——
     * 但仍下发一份符合 login 契约的 userinfo，前端若直接用它也能工作。
     */
}
