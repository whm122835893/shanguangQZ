<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 支付渠道配置模型
 *
 * 字段依据：原站 wallet/huifu/wallet 的 data.info 对象（10 字段）
 * 样本：recon/raw2/wallet_huifu_wallet.json（uid 16797）
 *
 * 实测样本中 info 里**多数字段为空字符串**（该账号在汇付未开户），
 * 仅 mer_cust_id 有值 "6666000247745831"（汇付分配的商户号）。
 *
 * 🔴🔴 陷阱：openstatus **不属于本表**，属于 UserPaymentAccount
 * ================================================================
 *   openstatus 实测含义是「**当前用户**是否已在该渠道开户」，
 *   是**用户维度**的状态，不是渠道维度的开关。
 *   渠道自己是否启用，是本表的 is_open（本地主数据）。
 *
 *   混淆后果很具体：渠道明明已启用（is_open=1），
 *   但用户没开户（openstatus=0），若把 openstatus 当渠道开关，
 *   前端会把「可用渠道」显示成「已关闭」——支付入口直接消失。
 *
 *   正确用法见 UserPaymentAccount::openstatus 与本表 is_open 的分工。
 *
 * ⚠️ 本表 19 列中只有 10 列来自原站实测，其余 9 列
 *    （code/name/is_open/service_charge/sort/status/...）是**本地主数据**：
 *    原站把这些配置放在服务端配置文件里，不通过接口下发，
 *    我们为了可维护而落成表。区分清楚，不要以为都是实测字段。
 *
 * @property int    $id
 * @property string $code          渠道编码（本地主数据，如 huifu / sandpay / yibao）
 * @property string $name          渠道名（本地主数据）
 * @property int    $is_open       渠道是否启用（本地主数据，**不是** openstatus）
 * @property string $mer_cust_id   汇付商户号，实测 "6666000247745831"
 * @property string $acct_id       账户号
 * @property string $sys_id        系统号
 * @property string $biz_product_id 产品号
 * @property string $redirect_url  回调地址
 */
class PaymentChannel extends Model
{
    protected $table = 'payment_channel';

    protected $autoWriteTimestamp = false;

    /**
     * 渠道编码常量 —— 本地主数据
     *
     * ⚠️ 这些编码是我们自己定的（原站没通过接口暴露编码表），
     *    但 user 表的 yibao_status / tipay_status / sandpay_status /
     *    yifutong_status 四个字段暗示原站至少有这四个渠道。
     *    对应关系见 channelForUserStatus()。
     */
    public const CHANNEL_HUIFU     = 'huifu';      // 汇付（实测唯一有开户信息的）
    public const CHANNEL_SANDPAY   = 'sandpay';    // 三方支付
    public const CHANNEL_YIBAO     = 'yibao';      // 易宝
    public const CHANNEL_TIPAY     = 'tipay';      // 提付
    public const CHANNEL_YIFUTONG  = 'yifutong';   // 一付通

    /** 实测过的渠道（未实测的不要写进注释当结论） */
    public const MEASURED_CHANNELS = [self::CHANNEL_HUIFU];

    protected $schema = [
        'id'             => 'int',
        // ↓ 以下 6 列为本地主数据，原站不通过接口下发
        'code'           => 'string',
        'name'           => 'string',
        'is_open'        => 'int',
        'service_charge' => 'int',
        'sort'           => 'int',
        'status'         => 'int',
        // ↓ 以下为原站实测字段（wallet/huifu/wallet 的 info 对象）
        'mer_cust_id'    => 'string',   // ⚠️ 商户标识，敏感，日志中需脱敏
        'acct_id'        => 'string',
        'sys_id'         => 'string',
        'user_cust_id'   => 'string',
        'biz_product_id' => 'string',
        'redirect_url'   => 'string',
        'mer_priv'       => 'string',
        'extension'      => 'string',
        'resp_code'      => 'string',
        'resp_desc'      => 'string',
        'createtime'     => 'int',
        'updatetime'     => 'int',
    ];

    /**
     * 取启用中的渠道列表（按 sort 排序）
     *
     * 🔴 注意：这里返回的是「渠道可用」，
     *    前端还要结合 UserPaymentAccount.openstatus 判断「用户是否已开户」。
     *    两者都满足才能发起支付。缺一即支付失败。
     *
     * @return array<int,array> 渠道数组
     */
    public static function enabledList(): array
    {
        return self::where('is_open', 1)
            ->where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 按渠道编码取渠道
     *
     * @return self|null
     */
    public static function findByCode(string $code): ?self
    {
        /** @var self|null */
        return self::where('code', $code)->find();
    }

    /**
     * 该渠道下已开户的用户数（运营统计用）
     */
    public function openedUserCount(): int
    {
        return (int) UserPaymentAccount::where('channel_code', $this['code'])
            ->where('openstatus', 1)
            ->count();
    }

    /**
     * 🔐 敏感字段脱敏（写日志 / 返回前端前调用）
     *
     * ⚠️ mer_cust_id / acct_id / user_cust_id 是第三方支付的商户与账户标识，
     *    属敏感商业信息。审计日志中必须脱敏，
     *    完整返回只允许出现在**该用户自己**的支付页面。
     *
     * @return array 脱敏后的副本
     */
    public function sanitized(): array
    {
        $r = $this->toArray();
        foreach (['mer_cust_id', 'acct_id', 'user_cust_id', 'mer_priv'] as $k) {
            if (!empty($r[$k])) {
                $r[$k] = substr((string) $r[$k], 0, 4) . '****';
            }
        }
        return $r;
    }
}
