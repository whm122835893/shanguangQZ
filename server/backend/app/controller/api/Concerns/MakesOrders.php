<?php
declare(strict_types=1);

namespace app\controller\api\Concerns;

use app\common\Response;

/**
 * 下单统一实现 —— 供 PayController / TradeController 复用
 *
 * 🔴 为什么抽成 trait：
 *    v2.3 拆分时把 makeOrder 留在 TradeController 变成私有方法，
 *    而 PayController 的 5 个 Create*Order 端点仍在调 $this->makeOrder()。
 *    两个类互不继承，调用必然 fatal —— 已实测复现：
 *     POST /api/order/pay/CreateVipOrder 返回 500 HTML 错误页。
 *    私有方法跨类调用 PHP 运行期才解析，静态检查发现不了，
 *    只有真实 HTTP 请求能暴露。
 *
 * ⚠️ 约定：宿主 Controller 必须自行 use HasAuth（提供 currentUser()）。
 *    本 trait 刻意不再 use HasAuth——两个 trait 同时提供同名私有方法
 *    会触发 PHP trait 冲突 fatal。
 *
 * ⚠️ 本方法是 MOCK 实现：字段与数值均为占位，待原站成功样本到位后替换。
 */
trait MakesOrders
{
    /** 下单统一实现：返回订单三元组，供各 Create*Order 复用 */
    protected function makeOrder(): \think\response\Json
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $ts = time();
        return Response::ok([
            'order_id'   => random_int(20000, 29999),
            'order_sn'   => date('YmdHis', $ts) . random_int(10000000000000, 99999999999999),
            'pay_type'   => 4,
            'order_type' => 2,
        ]);
    }
}
