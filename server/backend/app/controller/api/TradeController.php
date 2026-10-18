<?php
declare(strict_types=1);

namespace app\controller\api;

use app\model\Collection;
use app\model\ConsignmentPriceRule;
use app\service\MarketFormatter;
use app\service\MockData;
use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;

/**
 * 交易域控制器（v2.3 从 Trade 拆分）—— 订单/市场单/寄售/藏品详情/求购单
 *
 * 已切 DB 的端点：tradeCollectionDetail / prepaymentCollectionDetail（market 域，v2.4）
 * 其余端点仍为【MOCK】。切 DB 时禁止凭端点名猜字段，必须以 recon/ 实测样本为准。
 * 🔐 鉴权走 HasAuth::currentUser()，禁用 Request::hasToken()（只判非空，是后门）。
 * ⚠️ 改动后必须跑：php tools/contract/run.php
 *
 * @package app\controller\api
 */
class TradeController
{
    use HasAuth;

    public function getMarketOrderDetails()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok(MockData::order(0));
    }

    public function saleMarketOrderDetail()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $o              = MockData::order(0);
        $o['consign']   = 1;   // 寄售标记，布尔 = int
        return Response::ok($o);
    }

    // ==================== 交易/ 寄售 ====================

    public function entrust()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $price = Request::str('price');
        $num   = Request::int('num');
        if ($price === '' || $num <= 0) {
            return Response::fail('请填写价格与数量');
        }
        return Response::ok([
            'entrust_no' => 'ENT' . date('YmdHis') . random_int(1000, 9999),
            'status'     => 1,
        ]);
    }

    public function entrustOrderList()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 2;
        $r = MockData::paginate(function ($i) {
            return [
                'id'          => $i + 1,
                'entrust_no'  => 'ENT2026' . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                'collection_id' => $i + 101,
                'name'        => '藏品 ' . ($i + 1),
                'num'         => 1,
                'price'       => '199.00',
                'status'      => 1,
                'status_text' => '寄售中',
                'createtime'  => time() - $i * 86400,
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /**
     * market/trade/collectionDetail —— 寄售藏品详情 + 地板价
     *
     * 实测结构：data = {id, name, listimg, reprice_limit, reprice_min_limit,
     *                  reprice_beg_max_limit, max_buy_price, floor_price:{11 字段}}
     * 信封 msg = 「藏品详情」
     *
     * 🔴 两个实测事实，改这里前必须知道：
     *   1. floor_price 是嵌套对象，字段来自 consignment_price_rule.scene='trade'
     *   2. 前端传参 collection_id 是**字符串**（"124"），Request::int 已兼容
     */
    public function tradeCollectionDetail()
    {
        $collectionId = Request::int('collection_id');

        $coll = $collectionId > 0 ? Collection::find($collectionId) : null;
        $rule = $collectionId > 0
            ? ConsignmentPriceRule::findForCollection($collectionId, 'trade')
            : null;

        // 原站无「藏品不存在」分支的样本。返回空数组（不是 null），
        // 前端 SubmitBegBuy.vue 有 r.data 判空保护，不会崩。
        if (!$coll || !$rule) {
            return Response::ok([], Msg::COLLECTION_DETAIL_TRADE);
        }

        return Response::ok(
            MarketFormatter::tradeCollectionDetail($coll->toArray(), $rule),
            Msg::COLLECTION_DETAIL_TRADE
        );
    }

    /**
     * market/Prepayment/collectionDetail —— 预付藏品的地板价/费率
     *
     * 实测结构：data = {11 字段}，**平铺无嵌套**（与 trade 端点最大的结构差异）
     * 信封 msg = 「相关金额」
     *
     * 🔴 前端 SubmitEntrust.vue 的说明文案取的就是这个端点的 tips
     *    （「出价下限为昨日平均成交价100%」），不是 trade 的「寄售出价下限」。
     *    所以 scene='prepay' 不能省、不能错。
     */
    public function prepaymentCollectionDetail()
    {
        $collectionId = Request::int('collection_id');

        $rule = $collectionId > 0
            ? ConsignmentPriceRule::findForCollection($collectionId, 'prepay')
            : null;

        if (!$rule) {
            return Response::ok([], Msg::COLLECTION_DETAIL_PREPAY);
        }

        return Response::ok(
            MarketFormatter::prepaymentCollectionDetail($rule),
            Msg::COLLECTION_DETAIL_PREPAY
        );
    }

    public function begBuyOrderList()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 2;
        $r = MockData::paginate(function ($i) {
            return [
                'id'         => $i + 1,
                'collection_id' => $i + 201,
                'name'       => '求购藏品 ' . ($i + 1),
                'price'      => '299.00',
                'num'        => 1,
                'status'     => 1,
                'status_text'=> '求购中',
                'createtime' => time() - $i * 3600,
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    public function begBuyOrderInfo()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok([
            'id'           => 1,
            'collection_id'=> 201,
            'name'         => '求购藏品 1',
            'main_image'   => MockData::collection(0)['main_image'],
            'price'        => '299.00',
            'num'          => 1,
            'status'       => 1,
            'status_text'  => '求购中',
            'createtime'   => time(),
        ]);
    }

    public function myOrderInfo()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $o              = MockData::order(1);
        $o['prepay']    = 1;
        return Response::ok($o);
    }

    // ==================== 我的藏品 ====================

    public function collectionDetail()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $c            = MockData::collection(0);
        $c['num']     = 1;
        $c['sell_num']= '0';
        return Response::ok($c);
    }

    public function showResaleMoney()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok([
            'resale_money'    => '1,280.00',
            'resale_num'      => 6,
            'settle_money'    => '0.00',
        ]);
    }

    // ==================== 其他 ====================
    // unionLogs 已移至 UserController（union/index/logs 属公会域，
    // 与 union/user/myunion 同域归位；v2.4 拆分，兼解本文件超 300 行）

    public function newRank()
    {
        $total = 10;
        $list  = [];
        for ($i = 1; $i <= $total; $i++) {
            $list[] = [
                'rank'       => $i,
                'user_id'     => 16797 - $i,
                'nickname'    => '用户' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'avatar'      => '/defautAvatar.png',
                'ore'         => number_format(9999 - $i * 137, 6, '.', ''),
                'is_vip'      => $i <= 3 ? 1 : 0,          // 布尔 = int
            ];
        }
        return Response::ok($list);
    }

    public function getConfig()
    {
        $key = Request::str('key');
        if ($key === '') {
            // 对齐原站：code=0 + 固定 msg，HTTP 仍 200
            return Response::fail('缺少key值');
        }

        // 防护：用参数化查询，不做字符串拼接，避免 SQL 注入
        $row = \think\facade\Db::name('site_config')
            ->where('key', $key)
            ->find();

        if (!$row) {
            return Response::fail('不合法的key值');
        }

        return Response::ok((string) $row['value']);
    }

    // ==================== 账号 ====================
    // register / resetPwd 已移至 AccountController（user/ 域，v2.4 归位）
    //
    // ==================== 内部 ====================
    // makeOrder 已抽至 Concerns\MakesOrders（v2.4）——曾因留在本类变成
    // 私有方法，导致 PayController 的 5 个 Create*Order 端点调用时 fatal
    // （实测 500 HTML 错误页，静态检查发现不了，详见 trait 注释）。
}
