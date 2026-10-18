<?php
declare(strict_types=1);

namespace app\service;

use app\model\BegBuyOrder;

/**
 * 求购单出参格式化 —— begBuyOrderList / begBuyOrderInfo 专用
 *
 * 契约依据：recon/raw5/ 原站实测（2026-10-18，账号 16797）：
 *   begBuyOrderList 每行 28 字段；begBuyOrderInfo 27 字段（无 sale_number）。
 *   字段数与键序均逐键抄自样本（键序断言见 cases/BegBuyCases.php）。
 *
 * 🔴 类型铁律（逐字段抄自实测，不许「顺手统一」）：
 *    string : order_sn / price / total_price / platform_service_rate /
 *             platform_service_price / single_platform_service_price /
 *             goods_name / goods_image / series_name / pay_scene / pay_way
 *    int    : id / user_id / goods_id / order_status / number / series_id /
 *             order_type / sale_number / surplus_time_text / is_refund
 *    null   : pay_time / canceltime(手动取消时) / pay_type / real_trans_amt / huifu_pay_type
 *
 * 🔴 三个不变量（前端强依赖，漂移即崩）：
 *    1. 金额两位小数字符串（decimal 列直出会是 "20000.00"，但 rate 是 "6.00"
 *       不是 6 —— 统一 number_format 到 2 位）
 *    2. surplus_time_text 是**剩余秒数**不是时间戳，且与 order_status 无关：
 *       max(0, 300-(now-createtime)) —— 77 号单取消后仍递减（186/196 实证），
 *       76 号单过期归 0（时间过窗，非状态置零）
 *    3. 列表按 id 倒序（实测 77 在 76 前）
 *    4. sale_number 位于 order_type 之后、surplus_time_text 之前——
 *       不是行尾（实测键序，别顺手挪）
 *
 * @package app\service
 */
final class BegBuyFormatter
{
    /**
     * 单行出参（begBuyOrderInfo 用）
     *
     * @param bool $withSaleNumber true=列表行（28 字段）/ false=详情（27 字段）
     */
    public static function row(BegBuyOrder $order, bool $withSaleNumber = false): array
    {
        $row = [
            'id'                            => (int) $order['id'],
            'order_sn'                      => (string) $order['order_sn'],
            'user_id'                       => (int) $order['user_id'],
            'goods_id'                      => (int) $order['goods_id'],
            'order_status'                  => (int) $order['order_status'],
            'number'                        => (int) $order['number'],
            'price'                         => self::money($order['price']),
            'total_price'                   => self::money($order['total_price']),
            'platform_service_rate'         => self::money($order['platform_service_rate']),
            'platform_service_price'        => self::money($order['platform_service_price']),
            'single_platform_service_price' => self::money($order['single_platform_service_price']),
            'createtime'                    => (int) $order['createtime'],
            'goods_name'                    => (string) $order['goods_name'],
            'goods_image'                   => (string) $order['goods_image'],
            'pay_scene'                     => (string) $order['pay_scene'],
            'pay_way'                       => (string) $order['pay_way'],
            'pay_time'                      => self::nullableInt($order['pay_time']),
            'is_refund'                     => (int) $order['is_refund'],
            'canceltime'                    => self::nullableInt($order['canceltime']),
            'pay_type'                      => self::nullableStr($order['pay_type']),
            'updatetime'                    => (int) $order['updatetime'],
            'series_id'                     => (int) $order['series_id'],
            'series_name'                   => (string) $order['series_name'],
            'real_trans_amt'                => self::nullableMoney($order['real_trans_amt']),
            'huifu_pay_type'                => self::nullableStr($order['huifu_pay_type']),
            'order_type'                    => (int) $order['order_type'],
        ];

        if ($withSaleNumber) {
            // 🔴 键序锚点：实测 sale_number 位于 order_type 之后、
            //    surplus_time_text 之前（列表样本 28 键逐位核对过）
            $row['sale_number'] = (int) $order['sale_number'];
        }

        $row['surplus_time_text'] = $order->surplusSeconds();

        return $row;
    }

    /**
     * 列表出参（begBuyOrderList 用）—— 每行 28 字段
     *
     * @param BegBuyOrder[] $orders
     * @return array<int,array>
     */
    public static function rows(array $orders): array
    {
        return array_map(fn (BegBuyOrder $o): array => self::row($o, true), $orders);
    }

    /** 金额 → 两位小数字符串；0 值也保持 "0.00"（实测空态为 "0.00" 不是 0） */
    private static function money($value): string
    {
        return number_format((float) (string) $value, 2, '.', '');
    }

    /** 可空金额：null 原样返回，不转 "0.00"（实测 real_trans_amt 为 null） */
    private static function nullableMoney($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return self::money($value);
    }

    /** 可空 int：DB 0 与 null 是两回事——pay_time 未支付时必须为 null */
    private static function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    /** 可空字符串 */
    private static function nullableStr($value): ?string
    {
        if ($value === null) {
            return null;
        }
        return (string) $value;
    }
}
