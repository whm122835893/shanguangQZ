<?php
declare(strict_types=1);

namespace app\controller\api;

use app\service\MockData;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;

/**
 * 市场域控制器（v2.3 从 Core 拆分）
 *
 * 挂单查询、藏品详情、行情、求购列表
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
class MarketController
{
    use HasAuth;

    public function marketGoodsListByGoodsId()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 4;
        $r = MockData::paginate(function ($i) {
            $g = MockData::marketGoods(155 + $i);
            $g['key'] = 'mock-key-' . (155 + $i);
            return $g;
        }, $total, $p, $s);

        return json([
            'code' => 1, 'msg' => '', 'time' => time(),
            'data' => ['list' => $r['list'], 'total' => $r['total']],
        ]);
    }

    /**
     * POST /market/market/marketGoodsDetail
     * 🔴 detail.key 是快捷下单必需（SPEC §4.2 检查清单第 1 项）
     */

    public function marketGoodsDetail()
    {
        $id   = Request::int('id', 155);
        $type = Request::int('type', 2);
        $g    = MockData::marketGoods($id);

        return Response::ok([
            'id'      => $g['id'],
            'name'    => $g['name'],
            'type'    => $type,
            'series_name' => $g['series_name'],
            'listimg' => $g['listimg'],
            'main_image' => $g['main_image'],
            'detail' => [
                'key'     => 'mock-key-' . $id,
                'price'   => '100.00',
                'num'     => 1,
                'goods_id'=> $id,
            ],
            'priceList' => [
                ['price' => '100.00', 'num' => 1],
            ],
        ]);
    }

    /** POST /market/market/getMarketList 行情主列表 */

    public function marketList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 10;
        $r = MockData::paginate(function ($i) {
            $c = MockData::collection($i + 1);
            return [
                'id'    => $c['id'],
                'name'  => $c['name'],
                'image' => $c['main_image'],
                'price' => (string)(100 + $i * 10) . '.00',
                'change_rate' => (string)round(($i - 2) * 1.5, 2) . '%',
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /market/market/marketList 幸运盒行情 */

    public function marketBoxList()
    {
        return $this->marketList();
    }

    /** POST /market/market/MarketDetail/ids/{id} */

    public function marketDetail($id)
    {
        $g = MockData::marketGoods((int) $id);
        return Response::ok($g);
    }

    /** POST /market/trade/tradingInformation 委托挂单列表 */

    public function tradingInformation()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(fn($i) => MockData::order(18600 + $i, 2, 1), $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /market/trade/begBuyList 求购列表 */

    public function begBuyList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 5;
        $r = MockData::paginate(function ($i) {
            $o = MockData::order(18700 + $i, 11, 1);
            $o['number']      = 3;
            $o['sale_number'] = $i;
            return $o;
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 交易下单 ====================

    /**
     * POST /order/pay/fastOrderNew 快捷下单（核心交易接口）
     *
     * 🔴 三条硬规则（SPEC §4.2）：
     *   1. 端点名是 fastOrderNew，不是 fastCreateOrder
     *   2. key 必须来自 marketGoodsDetail 的 detail.key
     *   3. 同用户存在未支付订单时返回明确业务错误
     */}
