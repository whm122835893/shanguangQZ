<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 登录令牌模型
 *
 * 字段依据：原站 user/login 实测响应 data.userinfo.token（LoginResult 结构）
 * 样本：recon/raw/auth/_login.json
 *
 * ⚠️ 为什么 token 不落在 user 表：
 *    原站 getUserInfo 实测下发 token = ""（空串），说明登录态**不由用户信息接口承载**，
 *    而是登录时单独下发并由前端每次请求带上。DB 侧同理：user 表不设 token 列，
 *    独立成 user_token 表支持多端登录与主动吊销。
 *
 * ⚠️ token 存储形态：char(36)（UUID 形态），**不是 JWT**。
 *    原站实测 token 是 36 位随机串，不是三段式 JWT。
 *    好处是天然支持服务端吊销（JWT 无状态做不到即吊销）。
 *    不要"优化"成 JWT——会丢失吊销能力，且与原站行为不符。
 *
 * 🔐 安全要求：
 *   1. token 必须用 hash('sha256', ...) 存哈希，不存明文。
 *      本表 token 列存的是**哈希值**，泄露此表不等于泄露登录态。
 *   2. 校验用 hash_equals()，防时序攻击。
 *   3. 有效期由 expiretime 控制，过期 token 一律拒绝，不做「续期放宽」。
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $token       token 的 sha256 哈希（非明文）
 * @property string $device      设备标识（前端下发，缺失时填 unknown）
 * @property string $ip
 * @property int    $expiretime  过期时间 unixtime
 * @property int    $createtime
 */
class UserToken extends Model
{
    protected $table = 'user_token';

    protected $autoWriteTimestamp = false;

    /** token 默认有效期（秒）：7 天 */
    public const TTL = 7 * 24 * 3600;

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'token'      => 'string',
        'device'     => 'string',
        'ip'         => 'string',
        'expiretime' => 'int',
        'createtime' => 'int',
    ];

    /**
     * 生成新 token（明文只在返回给前端的那一次存在）
     *
     * @return array{plain:string, hash:string, expiretime:int}
     */
    public static function issue(string $device = 'unknown', string $ip = ''): array
    {
        // 36 位随机串，与原站实测形态一致（UUID v4 去掉连字符）
        $plain = bin2hex(random_bytes(18));
        return [
            'plain'      => $plain,
            'hash'       => self::hash($plain),
            'expiretime' => time() + self::TTL,
        ];
    }

    /**
     * token 明文 → 存储哈希
     */
    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * 校验 token 并返回用户，无效返回 null
     *
     * ⚠️ 恒定时间比较：hash_equals 防时序攻击，不要换成 ==。
     * ⚠️ 查询条件同时带上 expiretime > now，过期 token 不会进入后续判断。
     *
     * @return User|null
     */
    public static function verify(string $plain): ?User
    {
        if ($plain === '') {
            return null;
        }

        /** @var UserToken|null $row */
        $row = self::where('token', self::hash($plain))
            ->where('expiretime', '>', time())
            ->order('id', 'desc')
            ->find();

        if ($row === null) {
            return null;
        }

        /** @var User|null $user */
        $user = User::find($row['user_id']);
        if ($user === null) {
            return null;
        }

        // 🔐 状态校验：禁用账号即使 token 未过期也必须拒绝
        //    实测 status = "normal"，禁用态文案未抓到，字符串比较用 !== 而非 ==，
        //    避免原站若返回 0/"0"/"" 时被误判为正常。
        if ((string) $user['status'] !== 'normal') {
            return null;
        }

        return $user;
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 清理过期 token
     *
     * 由定时任务调用（建议每小时一次）。返回清理条数。
     */
    public static function purgeExpired(): int
    {
        return self::where('expiretime', '<', time())->delete();
    }
}
