<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Request;
use app\common\Response;
use app\service\MockData;

/**
 * 补齐端点控制器 —— 联调缺口回填
 *
 * 背景：check-coverage.py 比对前端 60 个调用端点与 Mock 路由，发现 27 个缺口。
 *      其中支付/下单类缺失最致命：交易链路走到CreateOrder 就断，前端表现为
 *      「点了没反应」，不报任何错。
 *
 * 契约依据：docs/api/API.md + 前端 src/api/index.js 的调用注释
 * 红线：code=1 成功 · 金额用 string · 布尔用 int · token 双通道
 */
class Trade
{
    // ==================== 下单 ====================

    /**
     * POST /order/pay/CreateOrder 通用创建订单
     * 前端调用：createOrder(data)，data 含 goods_id / num / pay_way
     */
    public function createOrder()
    {
        return $this->makeOrder();
    }

    /** POST /order/pay/CreateMarketOrder 市场订单 */
    public function createMarketOrder()
    {
        return $this->makeOrder();
    }

    /** POST /order/pay/CreateMarketOrderRush 市场订单抢购 */
    public function createMarketOrderRush()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return $this->makeOrder();
    }

    /** POST /order/pay/CreateVipOrder VIP 订单 */
    public function createVipOrder()
    {
        return $this->makeOrder();
    }

    /** POST /order/pay/CreateHuifuAccountOrder 汇付账户开通订单 */
    public function createHuifuAccountOrder()
    {
        return $this->makeOrder();
    }

    /** POST /order/pay/batchBuy 批量购买 */
    public function batchBuy()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /**
     * POST /order/pay/Recharge 充值
     * 前端调用：recharge(data)，data 含 money / pay_way
     */
    public function recharge()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /** POST /order/pay/paySmsCheck 支付短信校验 */
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

    /**
     * POST /order/pay/batchdopay 批量支付
     * 注意端点名是全小写 do（与 /order/pay/doPay 不同），原站就是这个写法，
     * 不能"顺手改成驼峰"，否则 404。
     */
    public function batchDoPay()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /** POST /order/order/batchPayOrder 批量下单列表 */
    public function batchPayOrder()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /** POST /order/order/getMarketOrderDetails 市场订单详情 */
    public function getMarketOrderDetails()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return Response::ok(MockData::order(0));
    }

    /** POST /order/order/saleMarketOrderDetail 出售市场订单详情 */
    public function saleMarketOrderDetail()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        $o              = MockData::order(0);
        $o['consign']   = 1;   // 寄售标记，布尔 = int
        return Response::ok($o);
    }

    // ==================== 交易/ 寄售 ====================

    /** POST /market/trade/entrust 提交寄售委托 */
    public function entrust()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /** POST /market/trade/entrustOrderList 寄售委托列表 */
    public function entrustOrderList()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /** POST /market/trade/collectionDetail 藏品详情（交易态） */
    public function tradeCollectionDetail()
    {
        return Response::ok(MockData::collection(0));
    }

    /** POST /market/Prepayment/collectionDetail 预付藏品详情 */
    public function prepaymentCollectionDetail()
    {
        return Response::ok(MockData::collection(1));
    }

    /** POST /market/trade/begBuyOrderList 求购订单列表 */
    public function begBuyOrderList()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /** POST /market/trade/begBuyOrderInfo 求购订单详情 */
    public function begBuyOrderInfo()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /** POST /market/Prepayment/myOrderInfo 预付订单详情 */
    public function myOrderInfo()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        $o              = MockData::order(1);
        $o['prepay']    = 1;
        return Response::ok($o);
    }

    // ==================== 我的藏品 ====================

    /** POST /user_collection/user_collection/detail 藏品详情 */
    public function collectionDetail()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        $c            = MockData::collection(0);
        $c['num']     = 1;
        $c['sell_num']= '0';
        return Response::ok($c);
    }

    /** POST /user_collection/user_collection/show_resale_money 待售金额 */
    public function showResaleMoney()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return Response::ok([
            'resale_money'    => '1,280.00',
            'resale_num'      => 6,
            'settle_money'    => '0.00',
        ]);
    }

    // ==================== 其他 ====================

    /** POST /union/index/logs 联合战力流水 */
    public function unionLogs()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
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

    /** POST /user/newrank1 新版排行榜 */
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

    /**
     * POST /index/getConfig 全局配置【DB】
     *
     * 🔴 原站实测行为（recon/raw/index_getConfig.json）：
     *    不传 key 时返回 {"code":0,"msg":"缺少key值","data":null}
     *    —— code 为 0 而非 1，且 msg 是固定文案。
     *    之前的 Mock 漏了这个校验，导致前端拿到配置后行为与原站不一致。
     *
     * 数据来源：site_config 表（key-value 结构）。
     * ⚠️ key 不存在时返回「不合法的key值」而非 null ——
     *    避免前端拿到 null 后走 undefined 分支。
     */
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

    /** POST /sms/send 发送短信验证码 */
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
    }

    /** POST /user/register 注册 */
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

    /** POST /user/resetpwd 重置密码 */
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
        if (!Request::hasToken()) {
            return Response::unauthorized();
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