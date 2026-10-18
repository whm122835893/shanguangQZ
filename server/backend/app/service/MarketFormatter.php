<?php
declare(strict_types=1);

namespace app\service;

use app\model\ConsignmentPriceRule;

/**
 * 市场域出参契约 —— 对齐 market/trade/collectionDetail 与
 *                    market/Prepayment/collectionDetail 实测
 *                    及 market/trade/begBuyList 分页信封
 *
 * 两个端点的实测结构：
 *   trade   data = {id, name, listimg, reprice_limit, reprice_min_limit,
 *                  reprice_beg_max_limit, max_buy_price, floor_price:{11 字段}}
 *   prepay data = {11 字段}（floor_price 的内容**平铺**到顶层）
 *
 * 🔴🔴 两端点最重要的实测事实：11 个字段里 10 个**完全相同**，
 *     只有 tips 差两个字：
 *       trade : 「...寄售出价下限为昨日平均成交价100%...」
 *       prepay: 「...出价下限为昨日平均成交价100%...」
 *     所以 tips **必须按端点取**，不能共用一条记录。
 *     实现上用 consignment_price_rule.scene 区分（trade / prepay）。
 *
 * 字段类型（实测，逐一核对过）：
 *   int 组  ：minprice / price_pro / low_price         —— 20 / 1 / 20
 *   string组：price_price_ratio / pay_platform_service_rate / share_proportion_rate
 *             / platform_service_price / share_proportion_price / real_price
 *                                                   —— "100" / "0.07" / "0.00" ...
 *   string  ：tips / low_price_text                    —— 中文文案
 *
 * ⚠️ string组绝不能转数字：原站返回 "0.07" 这种带前导零的形态，
 *    转 float 会变 0.07 且类型从 string 变 number，前端 toFixed 行为不同。
 *
 * @package app\service
 */
final class MarketFormatter
{
    /**
     * floor_price 的 11 字段白名单（严格顺序，与实测键序一致）
     */
    private const FLOOR_PRICE_FIELDS = [
        'minprice', 'price_pro', 'tips', 'price_price_ratio',
        'pay_platform_service_rate', 'share_proportion_rate',
        'platform_service_price', 'share_proportion_price',
        'real_price', 'low_price', 'low_price_text',
    ];

    /** int 字段（必须转 int，实测是数字而非字符串） */
    private const FLOOR_PRICE_INT_FIELDS = ['minprice', 'price_pro', 'low_price'];

    /**
     * market/trade/collectionDetail —— 顶层 5 字段 + 嵌套 floor_price
     *
     * @param array $collection collection 行（id/name/listimg/reprice_*）
     * @param ConsignmentPriceRule $rule trade 场景的规则
     */
    public static function tradeCollectionDetail(array $collection, ConsignmentPriceRule $rule): array
    {
        return [
            'id'                   => (int) ($collection['id'] ?? 0),
            'name'                 => (string) ($collection['name'] ?? ''),
            'listimg'              => (string) ($collection['listimg'] ?? ''),
            'reprice_limit'        => (int) ($collection['reprice_limit'] ?? 0),
            'reprice_min_limit'    => (string) ($collection['reprice_min_limit'] ?? '0.00'),
            'reprice_beg_max_limit'=> (string) ($collection['reprice_beg_max_limit'] ?? '0.00'),
            'max_buy_price'        => (int) ($collection['max_buy_price'] ?? 0),
            'floor_price'          => self::floorPrice($rule),
        ];
    }

    /**
     * market/Prepayment/collectionDetail —— floor_price 11 字段**平铺**到顶层
     *
     * 🔴 不是嵌套！实测 data 直接就是 11 个字段，没有 floor_price 这一层。
     *    早期误以为两端点结构相同，给 prepay 也套了一层 floor_price，
     *    前端 data.real_price 取不到值——静默 undefined，最难查的一类 bug。
     */
    public static function prepaymentCollectionDetail(ConsignmentPriceRule $rule): array
    {
        return self::floorPrice($rule);
    }

    /**
     * market/trade/tradingInformation —— 藏品成交/求购/寄售三态行情
     *
     * 实测结构（recon/raw2/market_trade_tradinginformation.json，样本账号无挂单故全空）：
     *   data = {
     *     list: [],                          ← 空数组（行结构无样本，见下方警告）
     *     transaction_record_number: 0,      ← int
     *     current_buying: 0,                 ← int
     *     current_consignment: 0,            ← int
     *     floor_price: 0,                    ← int
     *     today_amount: 0,                   ← int
     *     today_volume: 0,                   ← int
     *     today_highest_price: null,         ← 🔴 null，不是 0
     *     today_lowest_price: null           ← 🔴 null，不是 0
     *   }
     *
     * 🔴🔴 本端点最重要的契约事实：**空数组 + 齐全信封 = 结构可信，缺的是行**。
     *     样本账号在原站无挂单/成交数据，9 个键的**类型契约**完整可依：
     *       6 个 int 统计 + 2 个 null 极值 + 1 个空 list。
     *
     * 🔴 today_highest_price / today_lowest_price 必须是 null：
     *    原站无成交时给 null，前端据此走「无数据」分支；
     *    若"优化"成 0，页面会显示「今日最高 ￥0」——暗示存在一笔 0 元成交，
     *    与真实语义相反。这类静默语义反转比报错更难查。
     *
     * ⚠️ list 行结构无样本（前端 SellInfo.vue 复刻注释只列出
     *    {price, collection_code, total_num, time, ...} 且带省略号，不完整），
     *    本地 consignment / beg_buy_order / artel_order 对该藏品均无行。
     *    行结构待抓到非空样本后补齐，**在那之前禁止用别的端点的行结构凑数**。
     *
     * @param array $stats 统计值（来自 DB 聚合查询，键名与本方法出参键一致）
     * @param array $list  list 行（无行结构样本前恒为 []）
     */
    public static function tradingInformation(array $stats, array $list = []): array
    {
        return [
            'list' => $list,
            'transaction_record_number' => (int) ($stats['transaction_record_number'] ?? 0),
            'current_buying'            => (int) ($stats['current_buying'] ?? 0),
            'current_consignment'       => (int) ($stats['current_consignment'] ?? 0),
            'floor_price'               => (int) ($stats['floor_price'] ?? 0),
            'today_amount'              => (int) ($stats['today_amount'] ?? 0),
            'today_volume'              => (int) ($stats['today_volume'] ?? 0),
            // null 保持 null：仅在确有成交时才可能是数字
            'today_highest_price'       => $stats['today_highest_price'] ?? null,
            'today_lowest_price'        => $stats['today_lowest_price'] ?? null,
        ];
    }

    /**
     * market/trade/begBuyList —— 求购列表分页信封（双分支契约）
     *
     * 🔴🔴 本端点有**两套信封**，按藏品是否可求购分流（2026-10-18 矩阵探测实证，
     *      recon/raw6/ 共 10 个样本）：
     *
     *    分支 A —— 不可求购藏品（reprice_beg_max_limit ≤ 0，如 1/8/124/161/165）：
     *      data = {
     *        current_page: 1,     ← int，空态恒 1（请求 page=2 也不回显，实测）
     *        data: [],            ← 行结构无样本
     *        last_page: 0,        ← int，空态是 0 不是 1
     *        per_page: 0,         ← 🔴 0！请求带了 list_rows=10/15 也不回显
     *        total: 0             ← int
     *      }
     *      该分支结构性恒空态：不可求购藏品在建单时就被「该藏品不可求购」拦住，
     *      永远不可能有求购单（除非建单后改配置，边界不计）。
     *
     *    分支 B —— 可求购藏品（reprice_beg_max_limit > 0，如 162）：
     *      data = {
     *        total: 0,            ← 🔴 total 开头！与分支 A 不同序
     *        per_page: 50,        ← 🔴 恒 50，请求带 list_rows=10 也不理（实测）
     *        current_page: 1,     ← 空态恒 1（page=2 不回显，实测）
     *        last_page: 0,
     *        data: []
     *      }
     *      total > 0 的行结构与分页值无样本（本账号 money=0 无法支付求购单，
     *      D-006）：per_page 恒 50 / last_page=ceil(total/50) / current_page=page
     *      是**按分支 B 已实证部分外推的最小假设**，拿到非空样本后须校正。
     *
     * 🔴 错误链两分支共享（实测）：无 token → 401；collection_id 空 → 藏品ID不能为空；
     *    sort 非 asc/desc → 排序方式错误；藏品不存在 → 藏品不存在。
     *    （raw4/market_trade_begBuyList.json 是缺参错误样本，_161.json 是分支 A 成功样本）
     *
     * ⚠️ 为什么不能合并成一个信封：「同 5 键不同序 + 不同值」正是两个端点的
     *    区分点，compare() 只比字段集不比顺序，键序漂移不会被自动抓到——
     *    两个分支的键序断言在 cases/MarketCases.php。
     *
     * @param int  $total     真实计数（MarketStats::begBuyOrderCount）
     * @param int  $page      当前页（Controller 已 clamp 到 ≥1）
     * @param int  $size      请求的每页条数（分支 A 忽略；分支 B 恒 50）
     * @param bool $begBuyable 藏品是否可求购（collection.reprice_beg_max_limit > 0）
     */
    public static function begBuyList(int $total, int $page, int $size, bool $begBuyable = false): array
    {
        if (!$begBuyable) {
            // 分支 A：current_page 开头，per_page 恒 0（请求 list_rows 不回显）
            return [
                'current_page' => $total > 0 ? $page : 1,
                'data'         => [],
                'last_page'    => $total > 0 ? (int) ceil($total / $size) : 0,
                'per_page'     => 0,
                'total'        => $total,
            ];
        }

        // 分支 B：total 开头，per_page 恒 50（list_rows 被忽略）
        return [
            'total'        => $total,
            'per_page'     => 50,
            'current_page' => $total > 0 ? $page : 1,
            'last_page'    => $total > 0 ? (int) ceil($total / 50) : 0,
            'data'         => [],
        ];
    }

    /**
     * floor_price 11 字段还原
     *
     * @return array 严格 11 字段，键序与实测一致
     */
    private static function floorPrice(ConsignmentPriceRule $rule): array
    {
        $s = $rule->toArray();
        $out = [];
        foreach (self::FLOOR_PRICE_FIELDS as $k) {
            $out[$k] = $s[$k] ?? null;
        }
        foreach (self::FLOOR_PRICE_INT_FIELDS as $k) {
            $out[$k] = (int) $out[$k];
        }
        foreach ($out as $k => $v) {
            if (!in_array($k, self::FLOOR_PRICE_INT_FIELDS, true)) {
                // string 组：null 保持 null，其余转 string
                $out[$k] = $v === null ? null : (string) $v;
            }
        }
        return $out;
    }
}