<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;
use app\model\BegBuyOrder;
use app\model\Collection;
use app\model\ConsignmentPriceRule;
use app\service\BegBuyFormatter;

/**
 * 求购域控制器（v2.6 新建）—— createBegBuyOrder / cancelBegBuyOrder /
 *                            begBuyOrderList / begBuyOrderInfo
 *
 * 🔴🔴 端点名陷阱（本项目第三次踩中，已入 pitfalls）：
 *     原站前端 api/index.js 的 submitBegBuy/submitEntrust 指向
 *     /market/trade/begBuy、/market/trade/entrust —— 原站无此路由（404）。
 *     真实端点：创建=createBegBuyOrder、取消=cancelBegBuyOrder（API.md 端点表）。
 *     原站「发起求购/发起委托」页面是未接线空壳（.assets-archive 逆向证实：
 *     两个页面的提交按钮均无 onClick，只加载详情）。
 *
 * 契约依据：recon/raw5/ 原站实测（2026-10-18，账号 16797 完整生命周期）：
 *   创建成功 → {order_sn, order_id:"76", time:"300"}，msg=下单成功
 *   列表/详情 → 28/27 字段（BegBuyFormatter 逐字段抄录）
 *   取消成功 → data=null，msg=取消成功
 *
 * 🔴 创建校验链（实测顺序，错一步换一句，不许调换）：
 *   1. collection_id 空        → 藏品ID不能为空
 *   2. number 非正整数         → 数量不能为空（0/"abc" 同此消息，实测）
 *   3. pay_way 非 huifu        → 支付方式不能为空（无效值同消息，实测）
 *   4. 藏品不存在              → 藏品不存在
 *   5. price 空/非数字         → 请输入挂单价格
 *   6. reprice_beg_max_limit≤0 → 该藏品不可求购
 *   7. price < 地板价          → 寄售价区间提示（floor_price.tips 同款文案）
 *   8. price > 求购上限        → 限制最大求购金额{N}（N=int(上限)）
 *
 * 🔐 全部端点走 HasAuth::currentUser()；取消/详情带 user_id 归属校验（防 IDOR）。
 * ⚠️ 改动后必须跑：php tools/contract/run.php
 *
 * @package app\controller\api
 */
class BegBuyController
{
    use HasAuth;

    /**
     * market/trade/createBegBuyOrder —— 创建求购单
     *
     * 实测：{collection_id, price, number, pay_way:"huifu"} → 下单成功
     */
    public function createBegBuyOrder()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        // 1. 藏品ID
        $collectionId = Request::int('collection_id');
        if ($collectionId <= 0) {
            return Response::fail('藏品ID不能为空');
        }

        // 2. 数量 —— 非正整数一律按空处理（实测 "0"/"abc" 均回「数量不能为空」）
        $number = Request::int('number');
        if ($number <= 0) {
            return Response::fail('数量不能为空');
        }

        // 3. 支付方式 —— 实测仅 huifu 可用（yibao 有专门消息，其余归一）
        $payWay = Request::str('pay_way');
        if ($payWay !== BegBuyOrder::PAY_WAY) {
            return Response::fail('支付方式不能为空');
        }

        // 4. 藏品存在性
        $collection = Collection::find($collectionId);
        if (!$collection) {
            return Response::fail('藏品不存在');
        }

        // 5. 价格格式 —— 空串/非数字同消息（实测）
        $price = Request::str('price');
        if (!is_numeric($price) || (float) $price <= 0) {
            return Response::fail('请输入挂单价格');
        }
        $priceNum = (float) $price;

        // 6. 可求购性 —— 求购上限 ≤ 0 视为不可求购（实测 8/124/161/165/166 均此消息）
        $begMax = (float) (string) $collection['reprice_beg_max_limit'];
        if ($begMax <= 0) {
            return Response::fail('该藏品不可求购');
        }

        // 7. 地板价下限 —— 文案与 floor_price.tips 同源（实测逐字一致）
        $rule = ConsignmentPriceRule::findForCollection($collectionId, 'trade');
        $floor = $rule !== null ? (float) $rule['low_price'] : 0.0;
        if ($floor > 0 && $priceNum < $floor) {
            return Response::fail(self::RANGE_MSG);
        }

        // 8. 求购金额上限 —— 消息取整数值（实测 1000.00 → 「限制最大求购金额1000」）
        if ($priceNum > $begMax) {
            return Response::fail('限制最大求购金额' . (int) $begMax);
        }

        // 9. 落库（冗余快照：藏品名/图/系列在创建时刻定格）
        //    series_name 来自 series 表（collection 只有 series_id，无名字列——
        //    快照必须落名字，藏品改名/系列改名都不能改写历史订单的展示）
        $seriesName = '';
        $seriesId   = (int) $collection['series_id'];
        if ($seriesId > 0) {
            $series = \app\model\Series::find($seriesId);
            $seriesName = $series !== null ? (string) $series['name'] : '';
        }

        $order = BegBuyOrder::createForUser(
            (int) $user['id'],
            $collectionId,
            number_format($priceNum, 2, '.', ''),
            $number,
            $payWay,
            [
                'goods_name'  => (string) $collection['name'],
                'goods_image' => (string) $collection['listimg'],
                'series_id'   => $seriesId,
                'series_name' => $seriesName,
            ]
        );

        return Response::ok([
            'order_sn' => (string) $order['order_sn'],
            'order_id' => (string) $order['id'],   // 🔴 string 不是 int（实测）
            'time'     => (string) BegBuyOrder::PAY_WINDOW,
        ], Msg::CREATE_BEG_BUY_ORDER);
    }

    /**
     * market/trade/cancelBegBuyOrder —— 取消求购单
     *
     * 实测：{order_id} → "取消成功"（data=null）
     * 🔴 IDOR 防护：order_id 必须属于当前用户，否则按不存在处理
     *   （实测未覆盖他人订单的消息，取最小披露：与「订单不存在」同消息）
     */
    public function cancelBegBuyOrder()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $orderId = Request::int('order_id');
        if ($orderId <= 0) {
            return Response::fail('订单ID不能为空');
        }

        $order = BegBuyOrder::find($orderId);
        if (!$order || !$order->cancelForUser((int) $user['id'])) {
            return Response::fail('订单不存在');
        }

        return Response::ok(null, Msg::CANCEL_BEG_BUY_ORDER);
    }

    /**
     * market/trade/begBuyOrderList —— 我的求购单
     *
     * 实测：标准分页信封（total 开头 5 键），per_page 默认 15（区别于
     * begBuyList 的 50 与 orderList 的 10——三个端点三个默认值）。
     * 行按 id 倒序（实测 77 在 76 前）；不按状态过滤（实测取消单仍在列表）。
     */
    public function begBuyOrderList()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        if ($s === 10) {
            $s = self::PER_PAGE;   // 本端点默认 15 而非助手类的 10
        }

        $query = BegBuyOrder::where('user_id', (int) $user['id']);
        $total = $query->count();
        $rows  = $query->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->all();

        return Response::page(
            BegBuyFormatter::rows($rows),
            $total,
            $s,
            $p,
            Msg::BEG_BUY_ORDER_LIST
        );
    }

    /**
     * market/trade/begBuyOrderInfo —— 求购单详情
     *
     * 实测：26 字段（列表行 27 字段去掉 sale_number）。
     * 🔴 IDOR 防护：user_id 不匹配按不存在处理（实测未覆盖，最小披露）。
     */
    public function begBuyOrderInfo()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $orderId = Request::int('order_id');
        if ($orderId <= 0) {
            return Response::fail('订单ID不能为空');
        }

        $order = BegBuyOrder::where('id', $orderId)
            ->where('user_id', (int) $user['id'])
            ->find();
        if (!$order) {
            return Response::fail('订单不存在');
        }

        return Response::ok(
            BegBuyFormatter::row($order, false),
            Msg::BEG_BUY_ORDER_INFO
        );
    }

    /** 本端点实测分页默认值（begBuyOrderList 样本 per_page=15） */
    private const PER_PAGE = 15;

    /** 地板价区间提示 —— 与 collectionDetail.floor_price.tips 逐字一致（实测） */
    private const RANGE_MSG = '为保护寄售市场整体用户权益，寄售出价下限为昨日平均成交价100%，若低于最低限价或高于最高限价藏品将无法寄售，敬请知悉！';
}
