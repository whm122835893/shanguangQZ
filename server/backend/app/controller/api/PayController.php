<?php
declare(strict_types=1);

namespace app\controller\api;

use app\service\MockData;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;
use app\controller\api\Concerns\MakesOrders;

/**
 * 支付域控制器（v2.3 从 Trade 拆分，v2.4 修 makeOrder 断裂）
 *
 * 下单、批量支付、充值、开户、短信验证码
 *
 * 🔴 v2.4 修复：本类 5 个 Create*Order 端点一直在调 $this->makeOrder()，
 *    但该方法实体留在 TradeController 且是 private——跨类调用必然 fatal。
 *    已实测复现（POST /api/order/pay/CreateVipOrder 返回 500 HTML 错误页），
 *    现改为共用 Concerns\MakesOrders trait。
 *    教训：私有方法跨类不可见，PHP 运行期才报错，静态检查与 lint 都发现不了。
 *
 * 🔴 本文件全部端点当前为【MOCK】——用内存假数据补齐联调缺口，
 *    待拿到原站成功响应样本后逐个切换为 DB 驱动。
 *    切DB 时禁止凭端点名猜字段，必须以 recon/raw2/ 的实测样本为准。
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
    use HasAuth, MakesOrders;

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
        if ($sns === '') {
            return Response::fail('订单号不能为空');
        }
        $arr = array_filter(array_map('trim', explode(',', $sns)));
        $ok  = [];
        foreach ($arr as $sn) {
            $ok[] = [
                'order_sn' => $sn,
                'pay_no'   => 'MOCKPAY' . random_int(100000, 999999),
                'status'   => 1,               // 布尔 = int
            ];
        }
        return Response::ok([
            'total'   => count($ok),
            'success' => count($ok),
            'items'   => $ok,
        ]);
    }

    public function batchPayOrder()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(function ($i) {
            $o         = MockData::order($i);
            $o['state'] = '待支付';
            return $o;
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 订单详情 ====================

    public function createHuifuAccountOrder()
    {
        return $this->makeOrder();
    }

    public function createMarketOrder()
    {
        return $this->makeOrder();
    }

    public function createMarketOrderRush()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return $this->makeOrder();
    }

    public function createOrder()
    {
        return $this->makeOrder();
    }

    public function createVipOrder()
    {
        return $this->makeOrder();
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
