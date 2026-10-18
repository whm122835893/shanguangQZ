<?php
declare(strict_types=1);

namespace app\service;

use app\model\VipOrder;

/**
 * 支付域出参契约层
 *
 * 与 MarketFormatter / TradeFormatter 同一定位：Controller 与契约用例
 * 共用同一入口，用例测的是真实代码路径而非复刻副本。
 *
 * 🔴 本文件两个方法对应的端点都**无前端页面消费者**（Svip.vue 的「立即开通」
 *    只弹 toast「原站未开通会员入口」， TradeList 不调批量下单列表），
 *    但两者当前都在返回**结构错误的假数据**，修复形状是契约义务：
 *      - createVipOrder 原返回 {order_id:int, order_sn, pay_type:4, order_type:2}
 *        （4 字段、order_id 是 int、多两个字段）——实测是 3 字段全 string
 *      - batchPayOrder 原返回 MockData 假造的 3 条待支付订单
 *        ——实测是 {batch_order_id:0, data:[]}
 *
 * @package app\service
 */
final class PayFormatter
{
    /**
     * VIP 下单成功出参
     *
     * 实测（raw2/order_pay_createviporder.json + 2026-10-18 复探）：
     *   {order_sn:"2026101720203010157519849484", order_id:"1", time:"300"}
     *
     * 🔴 三个字段全是 string，一个 int 都没有：
     *    order_id 不是自增 int 的直出（"1" 是带引号的）
     *    time 不是秒数 int，是 "300" —— 支付倒计时，实测两次采样恒为 300
     *
     * @param VipOrder $order 已持久化的订单（id 为真实自增主键）
     */
    public static function vipOrderCreated(VipOrder $order): array
    {
        return [
            'order_sn' => (string) $order['order_sn'],
            // 🔴 (string) 强转是契约的一部分：给 int 前端 typeof 判断会走错分支
            'order_id' => (string) $order['id'],
            'time'     => (string) self::VIP_PAY_TIMEOUT,
        ];
    }

    /**
     * 批量下单列表 —— 无待付批量时的空态
     *
     * 实测（raw2/order_order_batchpayorder.json + 2026-10-18 复探）：
     *   {batch_order_id:0, data:[]}，msg="暂无订单"
     *
     * 🔴 复探证据：原站账号下有 2 笔市场单 + 2 笔 VIP 待付单时，
     *    本端点仍返回空态 —— 「批量」专指 batchBuy 产生的待付批量，
     *    与普通订单、VIP 订单无关。本地 batchBuy 无持久化实现
     *    （无成功样本，见 OPEN-DECISIONS D-002），批量恒不存在，
     *    故此空态是真实查询结论，不是占位符。
     *
     * 🔴 batch_order_id 是 int 0（不是 "0"）——与 CreateVipOrder 的
     *    全 string 形态不同，同文件内两种端点类型相反，勿「统一」
     */
    public static function noBatchOrder(): array
    {
        return [
            'batch_order_id' => 0,
            'data'           => [],
        ];
    }

    /** VIP 订单支付倒计时（秒）—— 实测两次采样恒为 "300" */
    public const VIP_PAY_TIMEOUT = 300;
}
