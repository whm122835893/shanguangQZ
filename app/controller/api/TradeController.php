<?php
declare(strict_types=1);

namespace app\controller\api;

use app\service\MockData;
use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;

/**
 * 交易域控制器（v2.3 从 Trade 拆分）—— 订单/市场单/寄售/藏品详情/求购单
 *
 * 🔴 全部端点当前为【MOCK】。切 DB 时禁止凭端点名猜字段，必须以 recon/ 实测样本为准。
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

    public function tradeCollectionDetail()
    {
        return Response::ok(MockData::collection(0));
    }

    public function prepaymentCollectionDetail()
    {
        return Response::ok(MockData::collection(1));
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

    public function unionLogs()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        // 原站此端点用 pageSize 分页（API.md §2.3 三种写法之一）
        $p     = max(1, Request::int('page', 1));
        $size  = max(1, min(100, Request::int('pageSize', 15)));
        $total = 20;
        $list  = [];
        for ($i = ($p - 1) * $size; $i < min($p * $size, $total); $i++) {
            $list[] = [
                'id'         => $i + 1,
                'type'       => $i % 2 === 0 ? 1 : 2,      // 1 获得 2 消耗（int）
                'title'      => ($i % 2 === 0 ? '联合战力+' : '联合战力-') . ($i + 1),
                'num'        => (string) (($i % 3) + 1),  // 原站此字段为 string
                'createtime' => time() - $i * 600,
            ];
        }
        return Response::page($list, $total, $size, $p);
    }

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

    public function register()
    {
        $mobile   = Request::str('mobile');
        $password = Request::str('password');
        $code     = Request::str('code');

        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return Response::fail('手机号格式错误');
        }
        if (strlen($password) < 6) {
            return Response::fail('密码至少 6 位');
        }
        if ($code !== '123456') {
            return Response::fail('验证码错误');
        }
        return Response::ok(['userinfo' => MockData::loginResult()]);
    }

    public function resetPwd()
    {
        $mobile = Request::str('mobile');
        $code   = Request::str('code');
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return Response::fail('手机号格式错误');
        }
        if ($code !== '123456') {
            return Response::fail('验证码错误');
        }
        return Response::ok(['mobile' => $mobile]);
    }

    // ==================== 内部 ====================

    /** 下单统一实现：返回订单三元组，供各Create*Order 复用 */
    private function makeOrder(): \think\response\Json
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
    }}
