<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 认证凭据模型（Argon2id）
 *
 * 🔴 本表是**本地扩展**，原站响应中不存在对应字段。
 *    独立成表而非在 user 表加 password 列，是为了保住 user 表
 *    「104 列 ↔ 原站实测字段」的一一对应关系（见 sql/07 头注释）。
 *
 * ============================ 安全红线 ============================
 *
 * 1. 只存 Argon2id 哈希。绝不存明文，绝不存可逆加密（base64/对称加密都不行）。
 *
 * 2. 参数写死在本类，不读配置文件。
 *    理由：可配置的成本 = 攻击者可通过改配置把 Argon2id 降级成 MD5。
 *    OWASP 推荐的 m=65536(64MB), t=3, p=4。
 *
 * 3. 校验必须用 password_verify()。
 *    ⚠️ 禁止 md5()/sha1()/=== 比较——都是可爆破的。
 *    ⚠️ 禁止把 hash 从 DB 取出后用 == 比较。
 *
 * 4. login_fail_count 达阈值即锁定。
 *    ⚠️ 计数必须在事务里做「读取+自增+判断」，否则并发下阈值形同虚设。
 *       本类用 DB 原子操作（f() 函数）实现，避免竞态。
 *
 * 5. 错误文案必须统一「账号或密码错误」。
 *    ⚠️ 区分「账号不存在」与「密码错误」= 用户枚举漏洞，
 *       攻击者可用它批量探测哪些手机号注册过本站。
 *       login_log 表内部记录真实原因，但**返回给前端的一律是统一文案**。
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $password_hash   Argon2id
 * @property int    $locked
 * @property int    $lock_until
 * @property int    $login_fail_count
 * @property int    $pwd_status
 * @property int    $pwd_updated_at
 */
class UserCredential extends Model
{
    protected $table = 'user_credential';

    protected $autoWriteTimestamp = false;

    /**
     * Argon2id 参数 —— OWASP 推荐值，**写死不可配置**
     * m=65536 KiB(64MB)  t=3 次迭代  p=4 并行度
     */
    private const ARGON_OPTIONS = [
        'memory_cost' => 65536,
        'time_cost'   => 3,
        'threads'     => 4,
    ];

    /** 连续失败多少次锁定 */
    public const MAX_FAIL = 5;

    /** 锁定时长（秒）：30 分钟 */
    public const LOCK_SECONDS = 1800;

    /**
     * 统一错误文案 —— 防用户枚举
     *
     * ⚠️ 登录失败一律返回这句，不区分具体原因。
     */
    public const GENERIC_ERROR = '账号或密码错误';

    protected $schema = [
        'id'               => 'int',
        'user_id'          => 'int',
        'password_hash'    => 'string',
        'locked'           => 'int',
        'lock_until'       => 'int',
        'login_fail_count' => 'int',
        'pwd_status'       => 'int',
        'pwd_updated_at'   => 'int',
        'createtime'       => 'int',
        'updatetime'       => 'int',
    ];

    /**
     * 生成 Argon2id 哈希
     *
     * ⚠️ 调用方负责丢弃明文。password_hash() 自带随机盐，
     *    因此同一密码每次哈希结果都不同——这是正确行为，
     *    不要试图「让相同密码得到相同哈希」来对比。
     */
    public static function makeHash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID, self::ARGON_OPTIONS);
    }

    /**
     * 校验明文密码
     *
     * ⚠️ 恒定时间比较由 password_verify() 内部保证，不要自己先 == 再 verify。
     *
     * @return bool
     */
    public function verifyPassword(string $plain): bool
    {
        $hash = (string) $this['password_hash'];
        if ($hash === '') {
            // 没有设置密码时返回 false，但要用一次假校验消耗时间，
            // 否则「无密码账号」的响应时间明显短于「密码错误」，
            // 攻击者据此枚举出哪些账号没设密码。
            password_verify($plain, '$argon2id$v=19$m=65536,t=3,p=4$'
                . str_repeat('a', 22) . str_repeat('b', 43));
            return false;
        }
        return password_verify($plain, $hash);
    }

    /**
     * 账号是否处于锁定中
     */
    public function isLocked(): bool
    {
        if ((int) $this['locked'] !== 1) {
            return false;
        }
        $until = (int) $this['lock_until'];
        // lock_until = 0 表示永久锁定（需管理员或短信解锁）
        return $until === 0 || $until > time();
    }

    /**
     * 登录成功：清零失败计数、解除锁定
     */
    public function onSuccess(): void
    {
        // ⚠️ 同 onFail()：用 self::where() 而非 $this->save()，
        //    避免 Model 实例状态与 Query 交互的版本差异。
        self::where('id', (int) $this['id'])->update([
            'login_fail_count' => 0,
            'locked'           => 0,
            'lock_until'       => 0,
            'updatetime'       => time(),
        ]);
    }

    /**
     * 登录失败：原子自增计数，达阈值则锁定
     *
     * 🔴 这里用 DB 层的原子自增（login_fail_count = login_fail_count + 1）
     *    而不是「先 select 出来 +1 再 update」。
     *    后者在并发下会让计数丢失：10 个并发同时读到 4，都写回 5，阈值形同虚设。
     *    ThinkPHP 的 exp() 表达式会被编译为 SQL 片段，由数据库保证原子性。
     *
     * @return int  自增后的失败次数
     */
    public function onFail(): int
    {
        $id = (int) $this['id'];

        // ⚠️ 必须用 self::where() 而不是 $this->db()：
        //    $this->db() 返回的是 Query 对象，与 Model 实例的状态交互在
        //    ThinkORM 3/4 间行为不一致，会出现「自增生效但后续 save() 静默失败」。
        //    self::where() 每次都从 Model 静态入口走，行为确定。
        //
        // 🔴 inc() 编译为 SQL 片段 `login_fail_count = login_fail_count + 1`，
        //    由数据库保证原子性。这是防并发绕过限流的关键 ——
        //    若改成「先 select 出来 +1 再 update」，10 个并发同时读到 4
        //    都会写回 5，阈值形同虚设。
        self::where('id', $id)->inc('login_fail_count')->update(['updatetime' => time()]);

        $count = (int) (self::where('id', $id)->value('login_fail_count') ?? 0);

        if ($count >= self::MAX_FAIL) {
            self::where('id', $id)->update([
                'locked'     => 1,
                'lock_until' => time() + self::LOCK_SECONDS,
                'updatetime' => time(),
            ]);
        }

        return $count;
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
