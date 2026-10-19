<?php
declare(strict_types=1);

namespace app\service;

/**
 * 网关工厂：按支付方式取实现
 *
 * ⚠️ 当前 payment_channel 表为空（无任何渠道数据），因此所有 pay_way
 *    都落到 NullGateway —— 这不是降级，而是如实反映「没有收单能力」。
 *
 * 接入真实通道时在此按 code 分发即可，调用方（Controller）无需改动：
 *    if ($ch = Db::name('payment_channel')->where('code', $payWay)
 *        ->where('is_open', 1)->find()) {
 *        return new HuifuGateway($ch);
 *    }
 */
final class Gateways
{
    public static function make(string $payWay): PaymentGateway
    {
        return new NullGateway();
    }
}
