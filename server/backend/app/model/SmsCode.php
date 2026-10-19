<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 短信验证码模型
 *
 * 🔴 为什么用 HMAC-SHA256 而不是 MD5：
 *    6 位数字验证码只有 10^6 种可能。MD5 无盐，攻击者可以离线穷举
 *    出「码 → MD5」的完整反查表，拿到库里任意一条记录的 hash
 *    就能秒解出明文验证码。因此必须用**带密钥**的 HMAC：
 *    没有密钥就无法预计算反查表。
 *
 * ⚠️ 密钥来源：env('SMS_HMAC_KEY')。生产环境必须配置且不得进版本库。
 *    密钥缺失时本类拒绝工作（抛异常），绝不降级成无盐哈希。
 *
 * @property int    $id
 * @property string $mobile
 * @property string $scene
 * @property string $code_hash  HMAC-SHA256 hex
 * @property string $ip
 * @property int    $send_count
 * @property int    $verify_count
 * @property int    $is_used
 * @property int    $expiretime
 * @property int    $createtime
 */
class SmsCode extends Model
{
    protected $table = 'sms_code';

    protected $autoWriteTimestamp = false;

    /** 验证码有效期（秒） */
    public const TTL = 300;

    /** 同一手机号两次发送的最小间隔（秒） */
    public const SEND_INTERVAL = 60;

    /** 同一手机号单日发送上限 */
    public const DAILY_LIMIT = 10;

    /** 同一验证码最大校验次数（超过即作废，防爆破） */
    public const MAX_VERIFY = 5;

    /** 允许的场景白名单 */
    public const SCENES = ['register', 'resetpwd'];

    protected $schema = [
        'id'           => 'int',
        'mobile'       => 'string',
        'scene'        => 'string',
        'code_hash'    => 'string',
        'ip'           => 'string',
        'send_count'   => 'int',
        'verify_count' => 'int',
        'is_used'      => 'int',
        'expiretime'   => 'int',
        'createtime'   => 'int',
        'updatetime'   => 'int',
    ];

    /**
     * 计算验证码哈希
     *
     * 格式：HMAC-SHA256(手机号|场景|明文码, 密钥)
     *
     * 🔴 三个要素缺一不可：
     *    手机号 —— 防止同一码跨手机号复用
     *    场景   —— 防止注册码拿去改密
     *    密钥   —— 防止离线反查
     *
     * @throws \RuntimeException 密钥未配置时
     */
    public static function hash(string $mobile, string $scene, string $code): string
    {
        $key = self::key();
        return hash_hmac('sha256', $mobile . '|' . $scene . '|' . $code, $key);
    }

    /**
     * 取 HMAC 密钥
     *
     * ⚠️ 密钥缺失必须失败而不是降级。降级成无盐哈希等于把验证码
     *    明文可还原，安全上比不做验证码更糟 —— 它会给人虚假的安全感。
     *
     * @throws \RuntimeException
     */
    private static function key(): string
    {
        $key = (string) env('SMS_HMAC_KEY', '');
        if ($key === '') {
            throw new \RuntimeException('SMS_HMAC_KEY 未配置，拒绝以不安全方式存储验证码');
        }
        return $key;
    }

    /**
     * 生成 6 位数字验证码
     *
     * 🔴 用 random_int 而不是 rand()/mt_rand()：
     *    后两者是线性同余伪随机，种子可猜，输出可预测。
     *    random_int 是密码学安全的，且会拒绝越界参数而不是静默回绕。
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 恒定时间比较两个哈希
     *
     * ⚠️ 绝不能用 === 比较哈希：字符串比较会在第一个不同字节处短路，
     *    攻击者可通过响应时间差逐字节爆破。
     */
    public static function hashEquals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }
}
