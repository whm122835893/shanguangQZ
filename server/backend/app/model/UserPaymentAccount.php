<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 用户支付开户模型
 *
 * 字段依据：原站 wallet/huifu/wallet 的 data.info（含 openstatus + 各渠道标识）
 * 表设计要点：一个用户在不同渠道各有一份开户信息，
 *              故唯一键是 (user_id, channel_code) 而非单 user_id。
 *
 * 🔴🔴 openstatus 的正确归属（v2.1 建表时踩过这个坑）
 * ================================================================
 *   首版误把 openstatus 放进 payment_channel 表，
 *   结果「渠道启用状态」与「用户开户状态」两个正交概念被混为一谈。
 *
 *   正确定位：
 *     PaymentChannel.is_open       → 渠道维度：平台是否启用该渠道
 *     UserPaymentAccount.openstatus → 用户维度：该用户是否已在此渠道开户
 *
 *   支付前置条件 = is_open AND openstatus，缺一不可。
 *   把 openstatus 当渠道开关用，会让「渠道正常但用户没开户」的场景
 *   错误地显示为「渠道已关闭」，支付入口凭空消失。
 *
 * ⚠️ 响应码语义未确认：
 *   实测样本中除 mer_cust_id 外几乎全为空字符串（该账号仅在汇付开户，
 *   其他渠道未开户）。resp_code / resp_desc 是第三方回调原样透传，
 *   其取值含义**未抓到样本**，不可凭字段名推断成功/失败码。
 *   实现回调处理前必须先拿到真实回调样本。
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $channel_code  渠道编码
 * @property int    $openstatus    ⚠️ **用户**是否已开户（不是渠道开关）
 * @property string $mer_cust_id   商户号
 * @property string $user_cust_id  用户在渠道侧的客户号
 */
class UserPaymentAccount extends Model
{
    protected $table = 'user_payment_account';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'             => 'int',
        'user_id'        => 'int',
        'channel_code'   => 'string',
        'openstatus'     => 'int',   // ⚠️ 用户维度开户状态
        'mer_cust_id'    => 'string',
        'user_cust_id'   => 'string',
        'acct_id'        => 'string',
        'sys_id'         => 'string',
        'biz_product_id' => 'string',
        'resp_code'      => 'string',
        'resp_desc'      => 'string',
        'openuid'        => 'int',
        'createtime'     => 'int',
        'updatetime'     => 'int',
    ];

    /**
     * 取用户在某渠道的开户记录
     *
     * @return self|null
     */
    public static function findByUserChannel(int $userId, string $channelCode): ?self
    {
        /** @var self|null */
        return self::where('user_id', $userId)
            ->where('channel_code', $channelCode)
            ->find();
    }

    /**
     * 该用户是否已在某渠道开户
     */
    public static function isOpened(int $userId, string $channelCode): bool
    {
        return self::where('user_id', $userId)
            ->where('channel_code', $channelCode)
            ->where('openstatus', 1)
            ->count() > 0;
    }

    /**
     * 列出用户可用的支付渠道
     *
     * 🔴 双条件过滤，缺一不可：
     *   ① payment_channel.is_open = 1  渠道启用
     *   ② user_payment_account.openstatus = 1  用户已开户
     *
     * 这是本表与 PaymentChannel 关系的核心用法 ——
     * 单独查任何一张表都会得出错误结论。
     *
     * @return array<int,array{code:string,name:string,openstatus:int}> 可用渠道
     */
    public static function availableChannels(int $userId): array
    {
        // 显式 JOIN 两张表实现「双条件」，不用两次查询再手动合并 ——
        // 两次查询之间状态可能变化，且 N+1 不可接受。
        return self::alias('a')
            ->join('payment_channel c', 'c.code = a.channel_code')
            ->where('a.user_id', $userId)
            ->where('a.openstatus', 1)
            ->where('c.is_open', 1)
            ->where('c.status', 1)
            ->order('c.sort', 'asc')
            ->field('a.channel_code as code, c.name as name, a.openstatus as openstatus')
            ->select()
            ->toArray();
    }

    /**
     * 该用户已开户的渠道编码列表
     *
     * @return array<int,string>
     */
    public static function openedChannels(int $userId): array
    {
        return array_values(array_map(
            static fn($r) => (string) $r['channel_code'],
            self::where('user_id', $userId)
                ->where('openstatus', 1)
                ->select()
                ->toArray()
        ));
    }

    /**
     * 标记某渠道已开户
     *
     * ⚠️ resp_code / resp_desc 是第三方回调透传，
     *    此处不自行编造取值 —— 调用方必须把回调原文传进来。
     *    没有真实回调样本前，宁可留空也不要填猜测值。
     */
    public function markOpened(
        string $userCustId = '',
        string $respCode = '',
        string $respDesc = ''
    ): void {
        $this->save([
            'openstatus'   => 1,
            'user_cust_id' => $userCustId !== '' ? $userCustId : $this['user_cust_id'],
            'resp_code'    => $respCode,   // ⚠️ 传原文，不编造
            'resp_desc'    => $respDesc,
            'updatetime'   => time(),
        ]);
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 关联渠道
     */
    public function channel()
    {
        return $this->belongsTo(PaymentChannel::class, 'channel_code', 'code');
    }
}
