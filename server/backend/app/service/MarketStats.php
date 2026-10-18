<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 市场域统计查询 —— market/trade/tradingInformation 与
 *                   market/trade/begBuyList 的取数层
 *
 * 🔴 为什么单独抽服务而不写在 Controller 里：
 *    契约用例（tools/contract/Cases.php）必须测**真实查询链路**。
 *    若查询逻辑留在 Controller 私有方法里，用例只能复刻一份——
 *    测的是副本，Controller 改了用例不会红，等于没测。
 *    抽成静态方法后，Controller 与用例调同一个入口。
 *
 * ⚠️ 本类的统计语义**没有非空样本可验证**（样本账号无挂单/成交，见 D-005）：
 *      - transaction_record_number：成交态判定（哪些 order_status 算成交）未知
 *      - floor_price：是否 = min(寄售价) 未知
 *      - today_amount：单位与取整规则未知（实测值 0 无法区分 int/float/string）
 *    当前实现取「结构上有依据的最小过滤」：goods_id + goods_type，
 *    不过滤 order_status——漏算是可见 bug，错算是静默错数，两害取其轻。
 *    本地数据状态下所有统计恒等于样本值（0 / null）。
 *
 * @package app\service
 */
final class MarketStats
{
    /** 藏品类订单的 goods_type（样本两条订单实测均为 2） */
    private const GOODS_TYPE_COLLECTION = 2;

    /** 寄售中（consignment.status，表注释语义） */
    private const STATUS_CONSIGNING = 1;

    /** 求购中（beg_buy_order.status，表注释语义） */
    private const STATUS_BEG_BUYING = 1;

    /**
     * 某藏品的三态行情统计
     *
     * @return array 键名与 MarketFormatter::tradingInformation 出参一致，
     *               便于直接展开传入；today_*_price 无成交时为 null
     */
    public static function tradingStats(int $collectionId): array
    {
        if ($collectionId <= 0) {
            return self::emptyStats();
        }

        // 当前求购数：该藏品求购中的单数
        $currentBuying = (int) Db::name('beg_buy_order')
            ->where('collection_id', $collectionId)
            ->where('status', self::STATUS_BEG_BUYING)
            ->count();

        // 当前寄售数：该藏品寄售中的单数
        $currentConsignment = (int) Db::name('consignment')
            ->where('collection_id', $collectionId)
            ->where('status', self::STATUS_CONSIGNING)
            ->count();

        // 地板价：当前最低寄售价；无寄售为 0（实测值）
        // ⚠️ decimal 列 min() 返回 string（如 "3.00"）或 null，两种都要处理
        $minPrice = Db::name('consignment')
            ->where('collection_id', $collectionId)
            ->where('status', self::STATUS_CONSIGNING)
            ->min('price');
        $floorPrice = $minPrice === null ? 0 : (int) $minPrice;

        // 成交记录数：该藏品的藏品类订单总数
        $transactionRecordNumber = (int) Db::name('artel_order')
            ->where('goods_id', $collectionId)
            ->where('goods_type', self::GOODS_TYPE_COLLECTION)
            ->count();

        // 今日成交：自然日边界（createtime >= 今日 0 点）
        $todayStart = strtotime(date('Y-m-d') . ' 00:00:00');
        $todayRows = Db::name('artel_order')
            ->where('goods_id', $collectionId)
            ->where('goods_type', self::GOODS_TYPE_COLLECTION)
            ->where('createtime', '>=', $todayStart)
            ->select()
            ->toArray();

        $todayAmount = 0.0;
        $todayVolume = 0;
        $todayPrices = [];
        foreach ($todayRows as $o) {
            $todayAmount += (float) $o['total_price'];
            $todayVolume += (int) $o['num'];
            $todayPrices[] = (float) $o['total_price'];
        }

        return [
            'transaction_record_number' => $transactionRecordNumber,
            'current_buying'            => $currentBuying,
            'current_consignment'       => $currentConsignment,
            'floor_price'               => $floorPrice,
            'today_amount'              => $todayAmount,
            'today_volume'              => $todayVolume,
            // 无今日成交 → null（实测契约）；有成交 → 极值（数值形态待样本校正）
            'today_highest_price'       => $todayPrices === [] ? null : max($todayPrices),
            'today_lowest_price'        => $todayPrices === [] ? null : min($todayPrices),
        ];
    }

    /** 无 goods_id 时的空统计（全 0 + 双 null，与实测空态一致） */
    public static function emptyStats(): array
    {
        return [
            'transaction_record_number' => 0,
            'current_buying'            => 0,
            'current_consignment'       => 0,
            'floor_price'               => 0,
            'today_amount'              => 0.0,
            'today_volume'              => 0,
            'today_highest_price'       => null,
            'today_lowest_price'        => null,
        ];
    }

    /**
     * 某藏品「求购中」的求购单数 —— begBuyList 的 total 来源
     *
     * 🔴 与 tradingStats() 里的 current_buying 是同一个查询口径
     *    （collection_id + status=1），两个端点必须一致：
     *    tradingInformation 显示「当前求购 N」，begBuyList 的 total
     *    也应该是 N。若一边改口径一边不改，两个页面数字对不上，
     *    而这种不一致不会有任何报错。
     *
     * ⚠️ 只返回计数不返回行：begBuyList 的 data 行结构无样本
     *    （见 MarketFormatter::begBuyList 警告），恒 []。
     *    total 走真实查询，保证「有行必见数」。
     */
    public static function begBuyOrderCount(int $collectionId): int
    {
        if ($collectionId <= 0) {
            return 0;
        }
        return (int) Db::name('beg_buy_order')
            ->where('collection_id', $collectionId)
            ->where('status', self::STATUS_BEG_BUYING)
            ->count();
    }
}
