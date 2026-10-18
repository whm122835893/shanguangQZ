<?php
declare(strict_types=1);

namespace app\model;

use think\facade\Db;
use think\Model;

/**
 * 求购单模型
 *
 * 字段依据：recon/raw5/ 原站实测非空样本（2026-10-18，账号 16797 完整生命周期）：
 *   createBegBuyOrder 成功 → {order_sn:"2026101818223910253101711678", order_id:"76", time:"300"}
 *   本表 27 列（id + 26 实测列）；出参字段数：
 *     begBuyOrderList 每行 28 字段 = 27 列 + surplus_time_text（计算列）
 *     begBuyOrderInfo  27 字段 = 26 列（无 sale_number）+ surplus_time_text
 *   （ BegBuyFormatter 逐字段抄录，键序与样本一致 ）
 *
 * 🔴 端点名陷阱（pitfalls: recon/probe-live-site-beats-reading-samples 同族）：
 *    原站前端 submitBegBuy/submitEntrust 指向 /market/trade/begBuy、/market/trade/entrust，
 *    这两个路由原站不存在（404）。真实写端点是 createBegBuyOrder / sell。
 *    本模型只服务 createBegBuyOrder / cancelBegBuyOrder / begBuyOrderList / begBuyOrderInfo。
 *
 * 🔴 status 语义（OrderEnum 原站 order-list.js 逆向实证，非猜测）：
 *    1=待付款（创建后 300 秒倒计时）2=求购中（支付后）3=已成交 4=已取消
 *    实测只出现过 1 与 4：手动取消 → 4（canceltime 留 null、updatetime 置撤销时刻）；
 *    自动过期 → 4（canceltime 落值、updatetime 不动）。
 *
 * 🔴 insertGetId 铁律（本环境实测，同 VipOrder）：
 *    ThinkPHP 8 的 Model::save() 在本环境不回写自增 id（getLastInsID 返回 0），
 *    必须走 Db::name()->insertGetId()。
 *
 * @property int    $id
 * @property string $order_sn   28 位：YmdHis(14) + 随机(14)
 * @property int    $user_id
 * @property int    $goods_id           求购藏品（响应字段名 goods_id = collection_id）
 * @property int    $order_status       1/2/3/4 见类注释
 * @property int    $number             求购数量（响应 int）
 * @property string $price              单价 decimal → 响应 string
 * @property string $total_price
 * @property string $platform_service_rate    百分数，如 "6.00" = 6%
 * @property string $platform_service_price
 * @property string $single_platform_service_price
 * @property string $goods_name         快照
 * @property string $goods_image        快照
 * @property int    $series_id          快照
 * @property string $series_name        快照
 * @property string $pay_scene          实测 ""
 * @property string $pay_way            huifu
 * @property int|null $pay_time
 * @property string|null $pay_type
 * @property int    $is_refund
 * @property string|null $real_trans_amt
 * @property string|null $huifu_pay_type
 * @property int|null $canceltime       手动取消为 null；自动过期落值
 * @property int    $order_type         11
 * @property int    $sale_number
 * @property int    $createtime
 * @property int    $updatetime
 */
class BegBuyOrder extends Model
{
    protected $table = 'beg_buy_order';

    protected $autoWriteTimestamp = false;

    /** 待付款（创建后 300 秒支付倒计时） */
    public const STATUS_UNPAID = 1;

    /** 求购中（支付成功后公开展示——begBuyList 只可能见这一态） */
    public const STATUS_BUYING = 2;

    /** 已成交（有卖家接单） */
    public const STATUS_SOLD = 3;

    /** 已取消（手动取消 / 倒计时归零自动过期） */
    public const STATUS_CANCELLED = 4;

    /** 支付倒计时秒数 —— 实测创建响应 data.time = "300" */
    public const PAY_WINDOW = 300;

    /** 求购平台服务费率（百分数）—— 实测 "6.00"，源配置无端点暴露 */
    public const SERVICE_RATE = 6.00;

    /** 求购订单类型 —— OrderEnum 实证 */
    public const ORDER_TYPE = 11;

    /** 实测唯一可用支付通道（yibao 报「未开通易宝支付」，sandpay 等报「收款方式错误」） */
    public const PAY_WAY = 'huifu';

    protected $schema = [
        'id'                           => 'int',
        'order_sn'                     => 'string',
        'user_id'                      => 'int',
        'goods_id'                     => 'int',
        'order_status'                 => 'int',
        'number'                       => 'int',
        'price'                        => 'string',
        'total_price'                  => 'string',
        'platform_service_rate'        => 'string',
        'platform_service_price'       => 'string',
        'single_platform_service_price'=> 'string',
        'goods_name'                   => 'string',
        'goods_image'                  => 'string',
        'series_id'                    => 'int',
        'series_name'                  => 'string',
        'pay_scene'                    => 'string',
        'pay_way'                      => 'string',
        'pay_time'                     => 'int',
        'pay_type'                     => 'string',
        'is_refund'                    => 'int',
        'real_trans_amt'               => 'string',
        'huifu_pay_type'               => 'string',
        'canceltime'                   => 'int',
        'order_type'                   => 'int',
        'sale_number'                  => 'int',
        'createtime'                   => 'int',
        'updatetime'                   => 'int',
    ];

    /**
     * 创建求购单（真写库）
     *
     * 🔴 为什么必须落库：原站同账号连续创建，order_id 76 → 77 递增，
     *    只有持久化才能复现序列；内存实现会让每次调用都返回同一个 id。
     *
     * 冗余快照列（goods_name/goods_image/series_*）在调用方备好传入——
     * 本方法只认数据，不自己去 join（保持单一职责：写库）。
     *
     * @param array $snapshot 藏品快照：goods_name/goods_image/series_id/series_name
     * @return self 已持久化（id 为真实自增主键）
     */
    public static function createForUser(
        int $userId,
        int $collectionId,
        string $price,
        int $number,
        string $payWay,
        array $snapshot
    ): self {
        $priceNum   = (float) $price;
        $totalNum   = round($priceNum * $number, 2);
        $serviceAmt = round($totalNum * self::SERVICE_RATE / 100, 2);

        $data = [
            'user_id'                    => $userId,
            'goods_id'                   => $collectionId,
            'order_status'               => self::STATUS_UNPAID,
            'number'                     => $number,
            'price'                      => $price,
            'total_price'                => number_format($totalNum, 2, '.', ''),
            'platform_service_rate'      => number_format(self::SERVICE_RATE, 2, '.', ''),
            'platform_service_price'     => number_format($serviceAmt, 2, '.', ''),
            'single_platform_service_price' => number_format(round($priceNum * self::SERVICE_RATE / 100, 2), 2, '.', ''),
            'goods_name'                 => (string) ($snapshot['goods_name'] ?? ''),
            'goods_image'                => (string) ($snapshot['goods_image'] ?? ''),
            'series_id'                  => (int) ($snapshot['series_id'] ?? 0),
            'series_name'                => (string) ($snapshot['series_name'] ?? ''),
            'pay_scene'                  => '',
            'pay_way'                    => $payWay,
            'order_type'                 => self::ORDER_TYPE,
            'sale_number'                => 0,
            'is_refund'                  => 0,
            'createtime'                 => time(),
            'updatetime'                 => time(),
        ];
        $data['order_sn'] = self::generateOrderSn();
        $data['id']       = (int) Db::name((new self())->getTable())->insertGetId($data);

        return new self($data);
    }

    /**
     * 取消求购单（带归属校验——防 IDOR）
     *
     * 🔴 实测语义：手动取消后 order_status=4、updatetime=撤销时刻，
     *    canceltime 保持 null（自动过期才落 canceltime）。两种取消路径
     *    在 DB 层的差异就是这一列，抄错会把「手动取消」显示成「已过期」。
     *
     * @return bool 是否取消成功（不存在 / 不属于该用户 / 非可取消态 → false）
     */
    public function cancelForUser(int $userId): bool
    {
        if ((int) $this['user_id'] !== $userId) {
            return false;
        }
        // 只有待付款态可取消（实测未覆盖其他态的前置条件，取最小白名单）
        if ((int) $this['order_status'] !== self::STATUS_UNPAID) {
            return false;
        }
        $this->save([
            'order_status' => self::STATUS_CANCELLED,
            'updatetime'   => time(),
        ]);
        return true;
    }

    /**
     * 生成订单号 —— 实测形态：YmdHis(14) + 14 位随机（与 vip_order 同构）
     */
    public static function generateOrderSn(): string
    {
        return date('YmdHis') . (string) random_int(10000000000000, 99999999999999);
    }

    /**
     * 支付剩余秒数 —— 实测响应 surplus_time_text
     *
     * 🔴 与 order_status **无关**（v2.6 修正，旧版加了「非待付款恒 0」
     *    的状态分支，被样本直接推翻）：
     *      - 77 号单手动取消后（status=4）仍在倒计时：186 → 196 递减
     *        （raw5/market_trade_begBuyOrderInfo_77_cancelled.json 与
     *         market_trade_begBuyOrderList_after_cancel.json 双样本实证）
     *      - 76 号单自动过期（status=4）显示 0 —— 因为 createtime+300
     *        早已过去，不是因为状态
     *    即纯时间数学：max(0, 300 - (now - createtime))。
     *    原站倒计时只认创建时刻，取消/过期不冻结它。
     */
    public function surplusSeconds(): int
    {
        return max(0, self::PAY_WINDOW - (time() - (int) $this['createtime']));
    }
}
