<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Request;
use app\common\Response;
use app\service\PayNotify;
use app\service\PaymentException;
use app\service\SettlementService;
use think\facade\Db;

/**
 * 渠道异步回调控制器【新增】
 *
 * POST /order/pay/notify/:code —— 外部支付渠道确认收款后回调本端点：
 *   1. 按 code 取渠道，必须 is_open=1 且配置了 notify_secret
 *   2. PayNotify 验签（HMAC-SHA256，hash_equals 恒定时间比较）
 *   3. SettlementService::dispatch 按单号前缀分流结算（充值入账 / 订单结算）
 *
 * 幂等口径：同单同 pay_no 重放 → 依旧返回 success（渠道重试机制要求）；
 *          金额不符 / pay_no 不一致 → fail（伪造或渠道异常，绝不静默吞掉）。
 *
 * 🔴 这是外部资金进入系统的唯一入口，验签失败一律拒绝，绝不降级放行。
 */
class NotifyController
{
    public function notify(string $code)
    {
        $channel = Db::name('payment_channel')
            ->where('code', $code)
            ->where('is_open', 1)
            ->find();
        if ($channel === null) {
            return Response::fail('支付渠道不存在或未开通');
        }

        $payload = [
            'order_sn' => Request::str('order_sn'),
            'pay_no'   => Request::str('pay_no'),
            'amount'   => (string) request()->param('amount', ''),
            'sign'     => (string) request()->param('sign', ''),
        ];

        if (!PayNotify::verify($payload, (string) $channel['notify_secret'])) {
            // 🔐 验签失败必须留审计（资金入口的可疑流量）
            \app\service\Audit::write('pay_notify', 0, ['channel' => $code, 'result' => 'bad_sign'], false);
            return Response::fail('签名校验失败');
        }

        try {
            SettlementService::dispatch($payload['order_sn'], $payload['pay_no'], $payload['amount'], $code);
        } catch (PaymentException $e) {
            // 结算拒绝（金额不符/单据不存在/伪造重放）：带原因返回，渠道侧重试可见
            \app\service\Audit::write('pay_notify', 0, [
                'channel' => $code, 'order_sn' => $payload['order_sn'],
                'result' => 'settle_reject', 'reason' => $e->getMessage(),
            ], false);
            return Response::fail($e->getMessage());
        }

        // 渠道通用应答：收到并处理成功
        return Response::ok(null, 'success');
    }
}
