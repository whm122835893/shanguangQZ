<?php
declare(strict_types=1);

namespace app\service;

use app\model\SmsCode;
use think\facade\Db;
use think\facade\Log;

/**
 * 短信验证码服务 —— 生成 / 发送 / 校验
 *
 * 🔴 本类取代原 MOCK 实现的全部逻辑。原 MOCK 是这样的：
 *      发送：返回 {code: '123456'}          ← 验证码公开给所有人
 *      校验：$code !== '123456' 即失败        ← 校验形同虚设
 *
 *    现在改为真实链路：随机生成 → HMAC 存储 → 一次性消费 → 带次数与频率限制。
 *
 * ==================== 四条不可让步的底线 ====================
 *
 * 1. **明文码绝不出现在 HTTP 响应体**
 *    响应只回 {mobile, event, expire}。这是与原 MOCK 最本质的区别。
 *
 * 2. **一次性消费**
 *    校验成功即置 is_used=1。同一码不能用第二次。
 *
 * 3. **校验次数上限**
 *    6 位数字码只有 10^6 空间，攻击者可以脚本穷举。
 *    同一码最多试 MAX_VERIFY(5) 次，超出即作废该码并要求重发。
 *
 * 4. **发送频率限制**
 *    同手机号 60 秒 1 次、单日 10 次。防短信轰炸（费用失控）+ 防验证码疲劳。
 *
 * @package app\service
 */
final class SmsCodeService
{
    /**
     * 发送验证码
     *
     * @return array{ok:bool, msg:string, plain:?string}
     *         plain 仅本地开发通道返回，**仅供测试读取，绝不可进响应体**
     */
    public static function send(string $mobile, string $scene, string $ip = ''): array
    {
        if (!in_array($scene, SmsCode::SCENES, true)) {
            return ['ok' => false, 'msg' => '不支持的验证场景', 'plain' => null];
        }

        // ---- 频率限制 1：60 秒内只能发一次 ----
        $last = SmsCode::where('mobile', $mobile)
            ->where('scene', $scene)
            ->order('id', 'desc')
            ->find();
        if ($last !== null && (int) $last['expiretime'] > time() - SmsCode::TTL) {
            $elapsed = time() - (int) $last['createtime'];
            if ($elapsed < SmsCode::SEND_INTERVAL) {
                $wait = SmsCode::SEND_INTERVAL - $elapsed;
                return ['ok' => false, 'msg' => "请 {$wait} 秒后再试", 'plain' => null];
            }
        }

        // ---- 频率限制 2：单日累计上限 ----
        $dayStart = strtotime('today midnight');
        $todayCount = (int) SmsCode::where('mobile', $mobile)
            ->where('createtime', '>=', $dayStart)
            ->count();
        if ($todayCount >= SmsCode::DAILY_LIMIT) {
            return ['ok' => false, 'msg' => '今日验证码发送次数已达上限', 'plain' => null];
        }

        // ---- 生成并存储 ----
        $code = SmsCode::generateCode();
        try {
            $hash = SmsCode::hash($mobile, $scene, $code);
        } catch (\RuntimeException $e) {
            // 密钥未配置 —— 明确失败，不降级
            Log::error('sms hmac key missing', ['error' => $e->getMessage()]);
            return ['ok' => false, 'msg' => '验证码服务未就绪，请稍后再试', 'plain' => null];
        }

        $now = time();
        SmsCode::create([
            'mobile'       => $mobile,
            'scene'        => $scene,
            'code_hash'    => $hash,
            'ip'           => $ip,
            'send_count'   => 1,
            'verify_count' => 0,
            'is_used'      => 0,
            'expiretime'   => $now + SmsCode::TTL,
            'createtime'   => $now,
            'updatetime'   => $now,
        ]);

        // ---- 投递 ----
        $channel = SmsChannels::for();
        $result  = $channel->send($mobile, $code, $scene);

        self::writeSendLog($mobile, $scene, $ip, $result);

        if (!$result->isOk()) {
            // 发送失败：把刚存的码作废，避免留下「发了但没送出去」的脏记录
            SmsCode::where('mobile', $mobile)
                ->where('scene', $scene)
                ->where('expiretime', '>=', $now)
                ->update(['is_used' => 1, 'updatetime' => $now]);

            return ['ok' => false, 'msg' => $result->message(), 'plain' => null];
        }

        return ['ok' => true, 'msg' => $result->message(), 'plain' => $result->plainCode()];
    }

    /**
     * 校验验证码
     *
     * @return array{ok:bool, msg:string}
     */
    public static function verify(string $mobile, string $scene, string $code): array
    {
        if (!in_array($scene, SmsCode::SCENES, true)) {
            return ['ok' => false, 'msg' => '不支持的验证场景'];
        }
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            return ['ok' => false, 'msg' => '验证码错误'];
        }

        try {
            $hash = SmsCode::hash($mobile, $scene, $code);
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'msg' => '验证码服务未就绪，请稍后再试'];
        }

        /** @var SmsCode|null $row */
        $row = SmsCode::where('mobile', $mobile)
            ->where('scene', $scene)
            ->where('is_used', 0)
            ->order('id', 'desc')
            ->find();

        if ($row === null) {
            return ['ok' => false, 'msg' => '验证码错误'];
        }

        // ---- 过期 ----
        if ((int) $row['expiretime'] < time()) {
            SmsCode::where('id', (int) $row['id'])->update(['is_used' => 1, 'updatetime' => time()]);
            return ['ok' => false, 'msg' => '验证码已过期，请重新获取'];
        }

        // ---- 校验次数超限 → 作废该码 ----
        if ((int) $row['verify_count'] >= SmsCode::MAX_VERIFY) {
            SmsCode::where('id', (int) $row['id'])->update(['is_used' => 1, 'updatetime' => time()]);
            return ['ok' => false, 'msg' => '验证码错误次数过多，请重新获取'];
        }

        // ---- 恒定时间比较 ----
        // 🔴 必须用 hash_equals。=== 会在首个不同字节短路，
        //    攻击者可通过响应时间差逐字节爆破 6 位码。
        if (!SmsCode::hashEquals((string) $row['code_hash'], $hash)) {
            // 失败计数原子自增（并发下不丢计数）
            SmsCode::where('id', (int) $row['id'])->inc('verify_count')->update(['updatetime' => time()]);
            return ['ok' => false, 'msg' => '验证码错误'];
        }

        // ---- 通过：一次性消费 ----
        SmsCode::where('id', (int) $row['id'])->update([
            'is_used'    => 1,
            'updatetime' => time(),
        ]);

        return ['ok' => true, 'msg' => '验证通过'];
    }

    /**
     * 写发送审计
     */
    private static function writeSendLog(string $mobile, string $scene, string $ip, SmsResult $result): void
    {
        try {
            Db::name('sms_send_log')->insert([
                'mobile'     => $mobile,
                'scene'      => $scene,
                'ip'         => $ip,
                'channel'    => $result->channel(),
                'success'    => $result->isOk() ? 1 : 0,
                'error'      => mb_substr($result->isOk() ? '' : $result->message(), 0, 200),
                'createtime' => time(),
            ]);
        } catch (\Throwable $e) {
            // 🔴 审计写失败**不能**阻断主流程 —— 发送成功与否才是用户关心的事。
            //    但必须留痕，否则故障时无从查证。
            Log::warning('sms send log write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 清理过期验证码
     *
     * 由定时任务调用（建议每小时一次）。返回清理条数。
     */
    public static function purgeExpired(): int
    {
        return SmsCode::where('expiretime', '<', time() - 86400)->delete();
    }
}
