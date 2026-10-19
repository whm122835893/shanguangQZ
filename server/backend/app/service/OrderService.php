<?php
declare(strict_types=1);

namespace app\service;

use app\model\ArtelOrder;
use app\model\Collection;
use app\model\Series;
use think\facade\Db;

/**
 * 下单服务 —— 交易链路的真实落库层
 *
 * 🔴 为什么抽这一层（2026-10-19）：
 *    此前 `order/pay/CreateOrder`、`CreateMarketOrder` 走 trait 里的
 *    makeOrder()，订单号凭 random 生成、**不落库**，却返回 code=1 成功。
 *    结果是「下单成功但订单列表查不到」——假成功。
 *
 * 【本实现的行业最优要点】
 *   1. **服务端算价**：金额只认挂单价（market_goods.price），
 *      绝不接受前端传入的价格。用前端价等于开放价格篡改漏洞
 *      （传 0.01 买走标价 100 的藏品）。
 *   2. **幂等防重**：同一用户 + 同一商品 + 已有待付款订单 → 拒绝。
 *      原站实测文案「请勿重复提交」（raw/order_pay_createorder.json）。
 *   3. **事务**：订单写入与后续任何库存/挂单变更必须同事务，失败整体回滚。
 *   4. **状态机**：新建恒为待付款；状态推进只能由支付结果驱动。
 *
 * 【契约依据说明】
 *   - 成功响应结构 `{order_sn, order_id, time}`：取自同族两个**实测**端点
 *     createVipOrder 与 createBegBuyOrder（二者键序完全一致）。
 *     order/pay/* 整组在原站当前返回空响应，无法直接取证，
 *     属归纳而非实证，已登记 docs/api/交易链路复探清单.md。
 *   - `time` = 付款窗口秒数，取求购域实测值 300。
 *   - order_type/order_status 依 OrderEnum（原站前端逆向）order_type=2 分支。
 */
final class OrderService
{
    /** 付款窗口（秒）—— 求购域实测 300 */
    public const PAY_WINDOW = 300;

    /** 业务标识：实测订单行 artel_order 列恒为此值（二手市场单） */
    private const BIZ_TAG = 'mh_secondary_market_order';

    /** 待付款：OrderEnum order_type=2 → 1=待付款 */
    public const STATUS_UNPAID = 1;

    /**
     * 创建市场订单
     *
     * @return array{ok: bool, msg: string, order: array|null}
     */
    public static function createMarketOrder(
        int    $userId,
        int    $goodsId,
        int    $num,
        string $payWay
    ): array {
        if ($goodsId <= 0) {
            return self::fail('藏品ID不能为空');
        }
        if ($num <= 0) {
            return self::fail('数量不能为空');
        }
        if ($payWay === '') {
            return self::fail('支付方式不能为空');
        }

        // ---- 商品存在性（服务端取证，不信任前端传的商品名/图） ----
        $goods = Collection::find($goodsId);
        if ($goods === null) {
            return self::fail('藏品不存在');
        }

        // ---- 服务端算价：只认在售挂单 ----
        // 本地 market_goods 当前 0 行（账号无挂单），因此本分支恒返回
        // 「暂无在售挂单」。这是**真实结论**而非占位：没有价格来源就不定价，
        // 拿前端传的价格落库会造成资损。灌入挂单数据后本路径即真实可用。
        $listing = Db::name('market_goods')
            ->where('collection_id', $goodsId)
            ->where('status', 1)
            ->where('num', '>', 0)
            ->order('price', 'asc')
            ->find();
        if ($listing === null) {
            return self::fail('该藏品暂无在售挂单');
        }
        if ($num > (int) $listing['num']) {
            return self::fail('挂单数量不足');
        }
        $unitPrice  = (float) $listing['price'];
        $totalPrice = number_format($unitPrice * $num, 2, '.', '');

        // ---- 幂等防重：同用户同商品已有待付款单 → 拒绝 ----
        $dup = ArtelOrder::where('user_id', $userId)
            ->where('goods_id', $goodsId)
            ->where('order_status', self::STATUS_UNPAID)
            ->find();
        if ($dup !== null) {
            return self::fail('请勿重复提交');
        }

        // ---- 事务落库 ----
        $seriesName = '';
        if ((int) $goods['series_id'] > 0) {
            $series = Series::find((int) $goods['series_id']);
            $seriesName = $series !== null ? (string) $series['name'] : '';
        }

        $now = time();
        try {
            Db::startTrans();
            $orderId = Db::name('artel_order')->insertGetId([
                'order_sn'          => self::makeOrderSn(),
                'user_id'           => $userId,
                'artel_order'       => self::BIZ_TAG,
                'order_type'        => '2',          // ⚠️ string，实测形态
                'order_status'      => self::STATUS_UNPAID,
                'total_price'       => $totalPrice,
                'num'               => (string) $num, // ⚠️ string，实测形态
                'goods_id'          => $goodsId,
                'goods_type'        => 2,
                'goods_name'        => (string) $goods['name'],
                'goods_cover_image' => (string) $goods['listimg'],
                'series_name'       => $seriesName,
                'crystal_type'      => 'crystal_no',
                'crystal_number'    => '0',
                'collection_code'   => '',
                'pay_channel'       => '',
                'pay_time'          => 0,
                'createtime'        => $now,
                'updatetime'        => $now,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return self::fail('下单失败，请稍后重试');
        }

        $row = ArtelOrder::find($orderId);

        return [
            'ok'    => true,
            'msg'   => '',
            'order' => $row !== null ? $row->toArray() : null,
        ];
    }

    /**
     * 订单号：对齐实测形态 YYYYMMDDHHMMSS + 14 位随机（共 28 位）
     *
     * 实测样本：2026101515380398100505963459（28 位，artel_order 与
     * beg_buy_order 两个表的样本形态一致）。
     */
    private static function makeOrderSn(): string
    {
        return date('YmdHis') . str_pad((string) random_int(0, 99999999999999), 14, '0', STR_PAD_LEFT);
    }

    /** @return array{ok: bool, msg: string, order: null} */
    private static function fail(string $msg): array
    {
        return ['ok' => false, 'msg' => $msg, 'order' => null];
    }
}
