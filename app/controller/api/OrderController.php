<?php
declare(strict_types=1);

namespace app\controller\api;

use app\model\ArtelOrder;
use app\service\MockData;
use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\service\TradeFormatter;
use app\controller\api\Concerns\HasAuth;

/**
 * 订单域控制器（v2.3 从 Core 拆分）
 *
 * 下单、支付、订单列表与详情、寄售、我的求购单
 *
 * 🔴 数据来源分两类，勿混淆：
 *   【DB  】已按原站实测响应建表并灌入真实数据
 *   【MOCK】原站需登录或已下线的端点，暂用内存假数据
 *
 * 🔐 鉴权统一走 HasAuth::currentUser()，禁止只判断 token 非空。
 *
 * ⚠️ 改动本文件后必须跑契约回归：php tools/contract/run.php
 *    退出码非 0 表示与原站实测契约不一致，禁止提交。
 *
 * @package app\controller\api
 */
class OrderController
{
    use HasAuth;

    public function fastOrderNew()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }


        $goodsId = Request::str('goods_id');
        $key     = Request::str('key');
        $payWay  = Request::str('pay_way');

        if ($goodsId === '' || $key === '') {
            return Response::fail('未找到合适商品');
        }
        if ($payWay === '') {
            return Response::fail('请选择支付方式');
        }
        // key 格式校验：Mock 约定mock-key-{goodsId}
        if ($key !== 'mock-key-' . $goodsId) {
            return Response::fail('未找到合适商品');
        }

        $ts = time();
        return Response::ok([
            'order_id'   => 19001,
            'order_sn'   => date('YmdHis', $ts) . '57101499336911',
            'pay_type'   => 4,
            'order_type' => 2,
        ]);
    }

    /** POST /order/pay/doPay 发起支付 */

    public function doPay()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $orderSn = Request::str('order_sn');
        if ($orderSn === '') {
            return Response::fail('订单号不能为空');
        }
        return Response::ok(['pay_no' => 'MOCKPAY' . random_int(100000, 999999)]);
    }

    // ==================== 订单 ====================

    /** POST /order/order/orderList 我的订单【DB】 */

    public function orderList()
    {
        [$user, $deny] = $this->currentUser();
        if ($deny !== null) {
            return $deny;
        }

        ['page' => $p, 'size' => $s] = Request::page();

        // 🔐 强制带 user_id —— IDOR 防护：不加此条件可枚举全站订单
        $query = ArtelOrder::where('user_id', $user['id']);

        // 原站支持按状态筛选（实测 status 参数名未确认，前端传的是 order_status 语义）
        // ⚠️ 这里只透传 status 一个入口；原站是否还支持 order_sn / 时间区间未实测，
        //    不做无依据的扩展。
        $status = Request::int('status', 0);
        if ($status > 0) {
            $query->where('order_status', $status);
        }

        $total = (int) $query->count();
        $rows  = $query->order('createtime', 'desc')
            ->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        // ⚠️ 此端点是标准五字段分页（与 newList 的 {count,list} 不同）
        return Response::page(
            array_map(static fn($r) => TradeFormatter::order($r), $rows),
            $total, $s, $p, Msg::ORDER_LIST
        );
    }

    /** POST /order/order/orderDetail/ids/{id} */

    public function orderDetail($id)
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok(MockData::order((int) $id, 2, 1));
    }

    /** POST /order/order/MarketDetail/ids/{id} 市场订单详情 */

    public function marketOrderDetail($id)
    {
        return $this->orderDetail($id);
    }

    /** POST /order/order/myConsignment 我的寄售 */

    public function myConsignment()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(fn($i) => MockData::order(18800 + $i, 8, 1), $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /market/Prepayment/myOrderlist 我的预付订单 */

    public function myOrderlist()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 2;
        $r = MockData::paginate(fn($i) => MockData::order(18900 + $i, 4, 1), $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 合成 /盲盒 ====================

    /** POST /synthesis/themeinfo 合成主题（🔴 必须 POST，GET 无效） */}
