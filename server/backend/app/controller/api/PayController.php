<?php
declare(strict_types=1);

namespace app\controller\api;

use app\model\ArtelOrder;
use app\model\VipOrder;
use app\service\Gateways;
use app\service\OrderService;
use app\service\MockData;
use app\service\PayFormatter;
use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;

/**
 * 支付域控制器（v2.3 从 Trade 拆分，v2.4 修 makeOrder 断裂，v2.5 切两个端点）
 *
 * 下单、批量支付、充值、开户、短信验证码
 *
 * 🔴 v2.4 修复：本类 5 个 Create*Order 端点一直在调 $this->makeOrder()，
 *    但该方法实体留在 TradeController 且是 private——跨类调用必然 fatal。
 *    已实测复现（POST /api/order/pay/CreateVipOrder 返回 500 HTML 错误页），
 *    现改为：下单走 OrderService（服务端算价+幂等+事务落库），
 *    支付走 Gateways 网关抽象——两者都不再产生假成功。
 *    教训：私有方法跨类不可见，PHP 运行期才报错，静态检查与 lint 都发现不了。
 *
 * 🔴 v2.5 数据来源分三类，勿混淆：
 *   【DB  】CreateVipOrder —— 有成功样本，真写 vip_order 表
 *   【契约】batchPayOrder —— 有成功样本且经复探证明恒空态，无数据载体
 *   【MOCK】其余端点（batchBuy/batchDoPay/Create*Order/Recharge...）
 *          无成功样本（全在 C/D/F 类，见 D-002），保持内存假数据
 *
 * 🔐 鉴权统一走 HasAuth::currentUser()（sha256 + 有效期 + 账号状态），
 *    禁止用 Request::hasToken()（只判非空，是后门）。
 *
 * ⚠️ 改动后必须跑：php tools/contract/run.php
 *
 * @package app\controller\api
 */
class PayController
{
    use HasAuth;

    public function batchBuy()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $ids = Request::str('ids');
        if ($ids === '') {
            return Response::fail('请选择商品');
        }
        $arr   = array_filter(explode(',', $ids));
        $ts    = time();
        $items = [];
        foreach ($arr as $i => $id) {
            $items[] = [
                'goods_id'  => (int) $id,
                'num'       => 1,
                'price'     => '99.00',
                'order_sn'  => date('YmdHis', $ts) . '57' . (100000 + $i),
            ];
        }
        return Response::ok([
            'batch_no' => 'BATCH' . date('YmdHis', $ts),
            'total'    => count($items),
            'amount'   => '99.00',          // 金额 = string（红线）
            'items'    => $items,
        ]);
    }

    // ==================== 支付 ====================

    public function batchDoPay()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $sns = Request::str('order_sns') ?: Request::str('order_sn');
        if ($sns === '' ) {
            return Response::fail('订单号不能为空');
        }
        $payWay = Request::str('pay_way');
        if ($payWay === '') {
            return Response::fail('支付方式不能为空');
        }

        $arr    = array_filter(array_map('trim', explode(',', $sns)));
        $items  = [];
        $success = 0;

        foreach ($arr as $sn) {
            // 🔐 逐单校验归属（IDOR）：不属于当前用户按「订单不存在」处理
            $order = ArtelOrder::where('order_sn', $sn)
                ->where('user_id', $user['id'])
                ->find();

            if ($order === null) {
                $items[] = ['order_sn' => $sn, 'pay_no' => '', 'status' => 0, 'msg' => '订单不存在'];
                continue;
            }
            if ((int) $order['pay_time'] > 0) {
                $items[] = ['order_sn' => $sn, 'pay_no' => '', 'status' => 0, 'msg' => '该订单已完成支付'];
                continue;
            }

            // 与 doPay 同源：收款结果只由网关决定，通道未接入即拒绝。
            // 旧实现在此直接编造 MOCKPAY 支付号并置 status=1（假成功）。
            $result = Gateways::make($payWay)->charge($order->toArray(), $payWay);
            if (! $result->paid) {
                $items[] = ['order_sn' => $sn, 'pay_no' => '', 'status' => 0, 'msg' => $result->msg];
                continue;
            }

            $order->save([
                'pay_time'    => time(),
                'pay_channel' => $payWay,
                'updatetime'  => time(),
            ]);
            $success++;
            $items[] = ['order_sn' => $sn, 'pay_no' => $result->payNo, 'status' => 1, 'msg' => ''];
        }

        return Response::ok([
            'total'   => count($items),
            'success' => $success,
            'items'   => $items,
        ]);
    }

    /**
     * POST /order/order/batchPayOrder 批量下单列表【契约，v2.5 切】
     *
     * 实测（raw2/order_order_batchpayorder.json + 2026-10-18 复探）：
     *   data = {batch_order_id: 0, data: []}，msg="暂无订单"；无 token → 401
     *
     * 🔴 为什么恒返回空态是**真实结论**而非占位符：
     *    复探时原站账号下挂着 2 笔市场单 + 2 笔刚创建的 VIP 待付单，
     *    本端点仍返回空态 —— 证明「批量」专指 batchBuy 产生的待付批量，
     *    与普通订单、VIP 订单无关。本地 batchBuy 无持久化实现
     *    （无成功样本，D-002），批量在本地永不可能存在。
     *
     * ⚠️ batch_order_id 是 int 0 不是 "0" —— 与 CreateVipOrder 的全 string
     *    形态相反，勿「统一类型」。
     *
     * ⚠️ 非空态（有批量时）的行结构无样本，已登记 OPEN-DECISIONS D-006；
     *    本地无批量载体，该分支不可达。
     */
    public function batchPayOrder()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok(PayFormatter::noBatchOrder(), Msg::BATCH_PAY_ORDER);
    }

    /**
     * POST /order/pay/CreateVipOrder 创建 VIP 会员订单【DB，v2.5 切】
     *
     * 实测（raw2/order_pay_createviporder.json + 2026-10-18 复探）：
     *   入参：空 body 即成功（会员购买是无参数动作）
     *   出参：data = {order_sn:"...28位...", order_id:"1", time:"300"}
     *         —— 3 个字段**全是 string**，msg="下单成功"；无 token → 401
     *
     * 🔴 三个实测事实决定了实现形态：
     *   1. 空 body 成功 → 不做任何参数校验（加了就是虚构契约）
     *   2. 同账号连续下单 order_id "1"→"2" → 必须真写库，
     *      内存实现会让两笔订单共用 id="1"，是静默造假
     *   3. VIP 订单不在 artel_order（原站有 2 笔 VIP 单时 orderList total 仍为 2）
     *      → 独立 vip_order 表，不蹭统一订单表
     *
     * 🔴 出参形状走 PayFormatter::vipOrderCreated()——
     *    契约用例与Controller 共用同一入口，测的是真实代码路径。
     *    早期走 makeOrder() 返回 {order_id:int, order_sn, pay_type:4, order_type:2}
     *    （4 字段、类型全错），正是「凭端点名猜字段」的反面教材。
     */
    public function createVipOrder()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $order = VipOrder::createForUser((int) $user['id']);

        return Response::ok(
            PayFormatter::vipOrderCreated($order),
            Msg::CREATE_VIP_ORDER
        );
    }

    public function paySmsCheck()
    {
        $mobile = Request::str('mobile');
        $code   = Request::str('code');
        if ($mobile === '' || $code === '') {
            return Response::fail('请输入手机号与验证码');
        }
        if (strlen($code) !== 6) {
            return Response::fail('验证码格式错误');
        }
        return Response::ok(['verify' => 1]);
    }

    public function recharge()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $money = Request::str('money');
        if ($money === '' || (float) $money <= 0) {
            return Response::fail('充值金额不能为空');
        }
        return Response::ok([
            'order_sn'   => 'RC' . date('YmdHis') . random_int(100000, 999999),
            'money'      => number_format((float) $money, 2, '.', ''), // string
            'pay_type'   => 4,
        ]);
    }

    public function sendSms()
    {
        $mobile = Request::str('mobile');
        $event  = Request::str('event');   // register / resetpwd
        if ($mobile === '' || !preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return Response::fail('手机号格式错误');
        }
        if (!in_array($event, ['register', 'resetpwd'], true)) {
            return Response::fail('不支持的验证场景');
        }
        return Response::ok([
            'mobile'   => $mobile,
            'event'    => $event,
            'expire'   => 60,
            // Mock 阶段固定验证码，方便自动化测试
            'code'     => '123456',
        ]);
    }}
