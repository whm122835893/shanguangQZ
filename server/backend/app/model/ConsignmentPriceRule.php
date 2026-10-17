<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 寄售限价规则模型
 *
 * 字段依据：原站 market/trade/collectionDetail 的 data.floor_price 嵌套对象（11 字段）
 *          + market/Prepayment/collectionDetail 的 data 顶层（同样 11 字段）
 * 交叉验证：两端点字段集完全一致，互为佐证。
 *
 * 🔴🔴 本表最重要的价值不是字段，而是把三个语义陷阱固化成代码护栏。
 *     这三个坑一旦踩中，金额就会算错，且**不会报错、只会静默算错**。
 *
 * ================================================================
 * 陷阱 1：pay_platform_service_rate 与 platform_service_price 数值相同、语义不同
 * ================================================================
 *   实测两个字段的值**都是 "0.07"**：
 *     pay_platform_service_rate = "0.07"  ← 费率，0.07 意思是 7%
 *     platform_service_price   = "0.07"  ← 金额，实际收 0.07 元
 *
 *   数值巧合完全相同，合并成一列或搞混含义 = 手续费算错百倍。
 *   见 serviceRate()（返回比率）与 servicePrice()（返回金额）两个方法，
 *   命名上就强制区分，禁止用一个方法同时干两件事。
 *
 * ================================================================
 * 陷阱 2：int 与 varchar 混用，同一组语义里两种类型
 * ================================================================
 *   int 组：    minprice / price_pro / low_price
 *   varchar 组：price / price_ratio / *_rate / *_price / real_price
 *
 *   为什么 ratio/rate/price 这几个"看起来该是数字"的是 varchar？
 *   原站就是字符串，且可能返回 "0.07" / "7%" / "" 等非纯数字形态。
 *   转 int 会得到 0，**费率直接归零**。
 *
 * ================================================================
 * 陷阱 3：price 与 price_pro 不是一回事
 * ================================================================
 *   price     = 卖家挂牌价（售价）
 *   price_pro = 溢价/成本价（平台视角的基准）
 *   real_price = 实付价（= price - platform_service_price）
 *   三个字段参与不同计算，混用会导致对账错位。
 *
 * @property int    $id
 * @property int    $collection_id
 * @property int    $minprice    int，最低价门槛
 * @property int    $price_pro   int，溢价基准价
 * @property int    $low_price   int，当前最低价
 * @property string $price       varchar，卖家挂牌价
 * @property string $real_price  varchar，实付价
 * @property string $price_price_ratio  varchar，价格比率（**非纯数字，不可转 int**）
 * @property string $pay_platform_service_rate varchar，**费率** 0.07=7%
 * @property string $platform_service_price   varchar，**金额** 0.07 元
 * @property string $share_proportion_rate    varchar，分成费率
 * @property string $share_proportion_price   varchar，分成金额
 */
class ConsignmentPriceRule extends Model
{
    protected $table = 'consignment_price_rule';

    protected $autoWriteTimestamp = false;

    /** 费率转金额时的默认基数（保留 4 位，PHP float 只有 ~7 位有效数字） */
    private const RATE_SCALE = 4;

    protected $schema = [
        'id'                       => 'int',
        'collection_id'            => 'int',
        'minprice'                 => 'int',     // ⚠️ int
        'price_pro'                => 'int',     // ⚠️ int
        'price_price_ratio'        => 'string',  // ⚠️ 不可转 int
        'tips'                     => 'string',
        'pay_platform_service_rate'=> 'string',  // ⚠️ 费率，非金额
        'share_proportion_rate'    => 'string',  // ⚠️ 费率
        'platform_service_price'   => 'string',  // ⚠️ 金额，非费率
        'share_proportion_price'   => 'string',  // ⚠️ 金额
        'real_price'               => 'string',
        'low_price'                => 'int',     // ⚠️ int
        'low_price_text'           => 'string',
        'status'                   => 'int',
        'createtime'               => 'int',
        'updatetime'               => 'int',
    ];

    // ========================================================================
    // 费率 / 金额的分离访问器
    //
    // 设计意图：让「搞混」这件事在代码层面不可能发生。
    // 用 serviceRate() 拿费率，用 servicePrice() 拿金额，
    // 名字不同、返回不同、注释不同。想混用时 review 就能看出来。
    // ========================================================================

    /**
     * 平台支付服务费率（比率）
     *
     * ⚠️ 返回的是**比率**，0.07 表示 7%，不是 0.07 元。
     *    要算金额请用 servicePrice()，或用 rateToAmount() 做乘法。
     *
     * @return float 0.07 = 7%
     */
    public function serviceRate(): float
    {
        // ⚠️ 不能用 (float) 直接转然后当金额用——这正是陷阱本身
        return (float) (string) $this['pay_platform_service_rate'];
    }

    /**
     * 平台服务费金额
     *
     * ⚠️ 返回的是**金额**（元），不是比率。
     *
     * @return float 单位：元
     */
    public function servicePrice(): float
    {
        return (float) (string) $this['platform_service_price'];
    }

    /**
     * 分成费率（比率）
     *
     * @return float 0.07 = 7%
     */
    public function shareRate(): float
    {
        return (float) (string) $this['share_proportion_rate'];
    }

    /**
     * 分成金额（元）
     */
    public function sharePrice(): float
    {
        return (float) (string) $this['share_proportion_price'];
    }

    /**
     * 费率 × 基数 = 金额
     *
     * ⚠️ 唯一允许「把费率当金额用」的地方，且函数名强制表达这层转换。
     *
     * @param float $rate    比率，如 0.07
     * @param float $base    基数，如订单金额 100.00
     * @return float 7.0（= 100.00 × 7%）
     */
    public static function rateToAmount(float $rate, float $base): float
    {
        return round($rate * $base, self::RATE_SCALE);
    }

    /**
     * 按挂牌价计算应付金额
     *
     * ⚠️ 这里用 servicePrice()（金额）+ real_price（实付价），
     *    绝不能用 serviceRate()（费率）参与金额运算。
     *
     * @return float 实付金额（元）
     */
    public function payableAmount(): float
    {
        $price = (float) (string) $this['price'];
        // 优先用原站给的 real_price；没有则用 price - service_price 兜底
        $real = (float) (string) $this['real_price'];
        return $real > 0 ? $real : round($price - $this->servicePrice(), self::RATE_SCALE);
    }

    /**
     * 取某藏品的有效规则（status=1，取最新一条）
     *
     * @return self|null
     */
    public static function findForCollection(int $collectionId): ?self
    {
        /** @var self|null */
        return self::where('collection_id', $collectionId)
            ->where('status', 1)
            ->order('id', 'desc')
            ->find();
    }

    /**
     * 关联藏品
     */
    public function collection()
    {
        return $this->belongsTo(Collection::class, 'collection_id', 'id');
    }
}
