<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 用户主表模型
 *
 * 字段依据：原站 user/getUserInfo 实测响应（106 叶子字段 / 100 标量 + 2 嵌套对象）
 * 样本：recon/raw2/user_getuserinfo.json（uid 16797）
 *
 * ============================ 字段映射契约 ============================
 * 实测 106 叶子字段 与 本表 104 列 的关系（已双向核验，零遗漏零冗余）：
 *
 *   API 100 标量
 *     − token  → 不落库（登录态由 user_token 表承载，见 UserToken）
 *     − url    → 不落库（计算字段 "/u/{id}"，由 Formatter 实时拼）
 *     = 98 落库标量
 *   + verification.{email,mobile}  → 拆为 email_verified / mobile_verified
 *   + statistics.{invited_count,buy_count,sale_count,join_day} → 拆为 4 个独立列
 *   = 104 列  ✅
 *
 * ============================ 六个类型陷阱 ============================
 *
 * ⚠️ 陷阱 1：ore 类是 6 位小数，金额类是 2 位小数，同一接口内并存
 *    实测 user_ore = "0.000000"（6 位） / money = "0.00"（2 位）
 *    统一用 number_format($v, 6) 处理会毁掉 money 的 "0.00" 形态，
 *    统一用 2 位会毁掉 ore。必须按字段分别格式化，见 Formatter::money6() / money2()。
 *
 * ⚠️ 陷阱 2：布尔语义字段一律 int 0/1，不可输出 true/false
 *    实测 pay_password=0 / huifu_account=1 / has_bank 是 int。
 *    其中 pay_password 只是「是否已设置」的标志位，绝不可下发密码本身。
 *
 * ⚠️ 陷阱 3：status 是字符串枚举，不是 int
 *    实测 "normal"。禁用态原站用 "disable"（未实测到，仅按命名推断，实现时须复核）。
 *
 * ⚠️ 陷阱 4：nullable 字段的空值形态是 JSON null，不是空串
 *    实测 birthday / private_key / ntf_url / nft_url / ali_openid /
 *    user_wx / user_qq / user_phone / id_card_front / id_card_back 全为 null。
 *    DB 默认值是空串，出参前必须 null 归一，否则前端拿到 "" 与 null 行为不同。
 *
 * ⚠️ 陷阱 5：id_card / real_name 是脱敏值，原样透传
 *    实测 id_card="520******838"、real_name="王*绵"——原站已脱敏，
 *    我们不要"帮忙"还原，也不要再叠加一层脱敏（会变成双重打码）。
 *
 * ⚠️ 陷阱 6：username 与 mobile 实测同值（都是手机号），但语义不同
 *    username 是登录名，mobile 是手机号。分开存储，不要合并成一个字段。
 *
 * @property int    $id
 * @property string $username       登录名（实测为手机号，但与 mobile 语义不同）
 * @property string $mobile         手机号
 * @property string $money          余额，实测 "0.00"（2 位小数字符串）
 * @property string $user_ore       矿石，实测 "0.000000"（6 位小数字符串）
 * @property int    $pay_password   是否已设支付密码（0/1，非密码本身）
 * @property int    $invited_count  statistics.invited_count 拆列
 * @property int    $buy_count      statistics.buy_count 拆列
 * @property int    $sale_count     statistics.sale_count 拆列
 * @property int    $join_day       statistics.join_day 拆列
 * @property int    $email_verified verification.email 拆列
 * @property int    $mobile_verified verification.mobile 拆列
 */
class User extends Model
{
    protected $table = 'user';

    protected $autoWriteTimestamp = false;

    /**
     * 字段白名单 —— 与 information_schema 实测列严格一一对应（104 列）
     *
     * ⚠️ 这里的类型只影响 ORM 取值时的类型转换，不影响出参形态。
     *    出参的 string/int 形态由 Formatter 负责，两处职责不要混。
     */
    protected $schema = [
        'id'                                       => 'int',
        'group_id'                                 => 'int',
        'username'                                 => 'string',
        'nickname'                                 => 'string',
        'email'                                    => 'string',
        'mobile'                                   => 'string',
        'avatar'                                   => 'string',
        'level'                                    => 'int',
        'gender'                                   => 'int',
        'birthday'                                 => 'int',
        'bio'                                      => 'string',
        'money'                                    => 'decimal',
        'agent_money'                              => 'decimal',
        'score'                                    => 'int',
        'successions'                              => 'int',
        'maxsuccessions'                           => 'int',
        'prevtime'                                 => 'int',
        'logintime'                                => 'int',
        'loginip'                                  => 'string',
        'loginfailure'                             => 'int',
        'joinip'                                   => 'string',
        'jointime'                                 => 'int',
        'createtime'                               => 'int',
        'updatetime'                               => 'int',
        'status'                                   => 'string',
        // ↓ verification.{email,mobile} 拆列
        'email_verified'                           => 'int',
        'mobile_verified'                          => 'int',
        'team_id'                                  => 'int',
        'inviter_user_id'                          => 'int',
        'inviter_code'                             => 'string',
        'new_inviter_user_id'                      => 'int',
        'sq_user_id'                               => 'int',
        'yao_count'                                => 'int',
        'yao_count_uptime'                         => 'int',
        'real_name_authentication'                 => 'int',
        'real_name'                                => 'string',
        'id_card'                                  => 'string',
        'id_card_front'                            => 'string',
        'id_card_back'                             => 'string',
        'real_name_time'                           => 'int',
        'first_buy_time'                           => 'int',
        'collection_total_price'                   => 'decimal',
        'collection_num'                           => 'int',
        'blind_box_num'                            => 'int',
        'buy_collection_total_price'               => 'decimal',
        'freeze'                                   => 'int',
        'ali_openid'                               => 'string',
        'option_pass'                              => 'int',
        'is_white'                                 => 'int',
        'service_price'                            => 'decimal',
        'recharge_price'                           => 'decimal',
        'xin_count'                                => 'int',
        'pay_password'                             => 'int',
        'inviter_status'                           => 'int',
        'll_id'                                    => 'string',
        'virtual'                                  => 'int',
        'market_open'                              => 'int',
        'person_intro'                             => 'string',
        'has_bank'                                 => 'int',
        'yttime'                                   => 'int',
        'crystal_yellow'                           => 'int',
        'crystal_purple'                           => 'int',
        'crystal_blue'                             => 'int',
        'share_status'                             => 'int',
        'share_success_time'                       => 'int',
        'huifu_service_charge'                     => 'int',
        'sandpay_service_charge'                   => 'int',
        'yifutong_service_charge'                  => 'int',
        'ntf_url'                                  => 'string',
        'invite_type'                              => 'int',
        'yibao_status'                             => 'int',
        'tipay_status'                             => 'int',
        'sandpay_status'                           => 'int',
        'yifutong_status'                          => 'int',
        'show_wallet'                              => 'int',
        'nft_url'                                  => 'string',
        'private_key'                              => 'string',
        'random_name'                              => 'int',
        'is_show_rank'                             => 'int',
        'user_wx'                                  => 'string',
        'user_qq'                                  => 'string',
        'user_phone'                               => 'string',
        'account'                                  => 'string',
        'huifu_account'                            => 'int',
        'user_ore'                                 => 'decimal',
        'team_ore'                                 => 'decimal',
        'person_ore'                               => 'decimal',
        'help_invited_user_product_ore_number'     => 'decimal',
        'rabbit_team_status'                       => 'int',
        'rabbit_person_status'                     => 'int',
        'invited_user_buy_rabbit_collection_person' => 'int',
        'is_first_one_market_buy_rabbit'           => 'int',
        'begin_team_rabbit_time'                   => 'int',
        'medals_number'                            => 'decimal',
        'transfer_permission'                      => 'int',
        'transfer_permission_level'                => 'int',
        'vip_time'                                 => 'int',
        'sync_time'                                => 'int',
        'r_status'                                 => 'int',
        'is_vip'                                   => 'int',
        // ↓ statistics.{invited_count,buy_count,sale_count,join_day} 拆列
        'invited_count'                            => 'int',
        'buy_count'                                => 'int',
        'sale_count'                               => 'int',
        'join_day'                                 => 'int',
    ];

    /**
     * 密码哈希（不在 $schema 白名单内，避免被 toArray() 泄漏）
     *
     * ⚠️ 登录校验必须用 password_verify()，禁止 == / === 比较。
     * ⚠️ 本表不存明文密码，也不存可逆加密密码。
     *
     * @var string|null
     */
    protected $passwordHash = null;

    /**
     * 关联登录令牌
     */
    public function tokens()
    {
        return $this->hasMany(UserToken::class, 'user_id', 'id');
    }

    /**
     * 关联持有藏品
     */
    public function collections()
    {
        return $this->hasMany(UserCollection::class, 'user_id', 'id');
    }

    /**
     * 关联订单
     */
    public function orders()
    {
        return $this->hasMany(ArtelOrder::class, 'user_id', 'id');
    }

    /**
     * 取当前有效的登录令牌（未过期）
     *
     * ⚠️ 多端登录：原站未实测是否允许同时多端在线。
     *    当前实现取最新一条未过期令牌，若原站是「单点互斥」需改为踢掉旧令牌。
     *    抓到一个有效 token 后可反查确认（原站若返回 401 即为互斥）。
     */
    public function activeToken(): ?UserToken
    {
        /** @var UserToken|null */
        return $this->tokens()
            ->where('expiretime', '>', time())
            ->order('id', 'desc')
            ->find();
    }
}
