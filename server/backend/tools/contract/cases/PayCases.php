<?php
declare(strict_types=1);

/**
 * 交易链路契约用例：doPay / batchDoPay / CreateOrder / CreateHuifuAccountOrder
 *
 * 🔴 本组的头号断言是**安全红线**：
 *    2026-10-19 之前，doPay 与 batchDoPay 直接返回 code=1 +
 *    `pay_no = 'MOCKPAY' . random_int(...)`，即伪造支付成功；而
 *    server.cjs 的 API_UPSTREAM 默认指向本地 Mock，按现状发布就是
 *    「线上用户付款看到成功、但没订单没资金流」的资损事故。
 *    因此本组第一条断言就是：**任何参数组合下支付端点都不得返回成功**。
 *
 * 用例全部走 Controller 直连 + 事务包住回滚：
 *   - 直连是为了断言失败路径的文案（check() 只比成功响应的 data 节点）
 *   - 事务是因为「服务端算价」需要造一条在售挂单（原站账号无挂单，
 *     market_goods 恒 0 行，算价分支本地不可达）
 */

require_once __DIR__ . '/PayTestKit.php';

use app\controller\api\OrderController;
use app\controller\api\PayController;
use app\controller\api\PayOrderController;
use app\model\ArtelOrder;
use app\service\OrderService;
use think\facade\Db;

if (! function_exists('runPayCases')) {
    function runPayCases(): void
    {
        global $results, $failed;

        Db::startTrans();
        try {
            $token = payTempToken(16797);

            // ==================== 1. doPay 安全红线 ====================
            //
            // 关键：真实订单 + 合法支付方式 这组参数，旧实现会返回 code=1
            // 假成功。现在必须返回 code=0 且 msg 明确说明通道未接入。
            payBind($token, []);
            payAssert('doPay:空参', (new OrderController())->doPay(), 0, '订单号不能为空');

            payBind($token, ['order_sn' => 'SN_NOT_EXIST_1']);
            payAssert('doPay:缺支付方式', (new OrderController())->doPay(), 0, '支付方式不能为空');

            payBind($token, ['order_sn' => 'SN_NOT_EXIST_1', 'pay_way' => 'huifu']);
            payAssert('doPay:订单不存在', (new OrderController())->doPay(), 0, '订单不存在');

            // IDOR：别人的订单按「不存在」处理（最小披露，不泄露订单空间）
            $otherOrder = ArtelOrder::where('user_id', '<>', 16797)->find();
            if ($otherOrder === null) {
                // 库里没有他人订单时，临时造一条（事务回滚）
                $otherId = (int) Db::name('artel_order')->insertGetId([
                    'order_sn'    => 'SN_OTHER_USER_0001',
                    'user_id'     => 99991,
                    'artel_order' => 'mh_secondary_market_order',
                    'order_type'  => '2',
                    'order_status'=> 1,
                    'total_price' => '10.00',
                    'num'         => '1',
                    'goods_id'    => 8,
                    'goods_type'  => 2,
                    'goods_name'  => '他人订单',
                    'createtime'  => time(),
                    'updatetime'  => time(),
                ]);
                $otherSn = 'SN_OTHER_USER_0001';
            } else {
                $otherSn = (string) $otherOrder['order_sn'];
            }
            payBind($token, ['order_sn' => $otherSn, 'pay_way' => 'huifu']);
            payAssert('doPay:IDOR 他人订单', (new OrderController())->doPay(), 0, '订单不存在');

            // 真实属于自己的订单 —— 核心安全断言
            $mine = ArtelOrder::where('user_id', 16797)->find();
            if ($mine === null) {
                $results[] = 'doPay:真实订单            [跳过]（库内无本用户订单，先跑 seed.php）';
                $failed++;
            } else {
                payBind($token, ['order_sn' => (string) $mine['order_sn'], 'pay_way' => 'huifu']);
                $resp = (new OrderController())->doPay();
                payAssert('doPay:真实订单不假成功', $resp, 0, '支付通道未接入，当前无法完成支付');

                // 副作用断言：不得写入 pay_time（假成功会留下脏数据）
                $after = ArtelOrder::find((int) $mine['id']);
                payExpect('doPay:pay_time 未被写入', (int) $after['pay_time'], (int) $mine['pay_time']);
            }

            // ==================== 2. batchDoPay 不谎报成功 ====================
            if ($mine !== null) {
                payBind($token, ['order_sn' => (string) $mine['order_sn'], 'pay_way' => 'huifu']);
                $b = (new PayController())->batchDoPay()->getData()['data'];
                payExpect('batchDoPay:total=1', $b['total'], 1);
                payExpect('batchDoPay:success=0（不谎报）', $b['success'], 0);
                payExpect('batchDoPay:status=0', $b['items'][0]['status'], 0);
                payExpect('batchDoPay:pay_no 为空', $b['items'][0]['pay_no'], '');

                // 混合：真实单 + 不存在的单
                payBind($token, [
                    'order_sn' => $mine['order_sn'] . ',SN_NOT_EXIST_9',
                    'pay_way'  => 'huifu',
                ]);
                $b2 = (new PayController())->batchDoPay()->getData()['data'];
                payExpect('batchDoPay:混合 total=2', $b2['total'], 2);
                payExpect('batchDoPay:混合 success=0', $b2['success'], 0);
                payExpect('batchDoPay:不存在单 msg', $b2['items'][1]['msg'], '订单不存在');
            }

            // ==================== 3. CreateOrder 服务端算价 ====================
            payBind($token, []);
            payAssert('createOrder:空参', (new PayOrderController())->createOrder(), 0, '藏品ID不能为空');

            payBind($token, ['goods_id' => 8]);
            payAssert('createOrder:缺数量', (new PayOrderController())->createOrder(), 0, '数量不能为空');

            payBind($token, ['goods_id' => 8, 'num' => 1]);
            payAssert('createOrder:缺支付方式', (new PayOrderController())->createOrder(), 0, '支付方式不能为空');

            payBind($token, ['goods_id' => 99999, 'num' => 1, 'pay_way' => 'huifu']);
            payAssert('createOrder:藏品不存在', (new PayOrderController())->createOrder(), 0, '藏品不存在');

            // 无挂单 → 无价格来源 → 拒绝定价（不用前端传的价）
            payBind($token, ['goods_id' => 8, 'num' => 1, 'pay_way' => 'huifu']);
            payAssert('createOrder:无挂单不定价', (new PayOrderController())->createOrder(), 0, '该藏品暂无在售挂单');

            // 造一条在售挂单（88.00 × 5），验证算价与幂等
            payMakeListing(8, '88.00', 5);
            payBind($token, [
                'goods_id' => 8,
                'num'      => 2,
                'pay_way'  => 'huifu',
                'price'    => '0.01',   // 模拟前端篡改价格
            ]);
            $created = (new PayOrderController())->createOrder();
            payAssert('createOrder:有挂单即成功', $created, 1, '下单成功');

            $d = $created->getData()['data'] ?? [];
            payExpect('createOrder:响应键序', array_keys($d), ['order_sn', 'order_id', 'time']);
            payExpect('createOrder:time=300', $d['time'] ?? null, (string) OrderService::PAY_WINDOW);
            payExpect('createOrder:order_id 是 string', is_string($d['order_id'] ?? null), true);

            // 🔴 服务端算价核心断言：88.00 × 2 = 176.00，不是前端传的 0.01
            $row = ArtelOrder::find((int) ($d['order_id'] ?? 0));
            if ($row === null) {
                $results[] = 'createOrder:落库            [FAIL] 订单未落库';
                $failed++;
            } else {
                payExpect('createOrder:total_price=176.00（服务端算价）',
                    (string) $row['total_price'], '176.00');
                payExpect('createOrder:order_status=1（待付款）', (int) $row['order_status'], 1);
                payExpect('createOrder:order_type="2" string', $row['order_type'], '2');
                payExpect('createOrder:num="2" string', $row['num'], '2');
                payExpect('createOrder:pay_time=0', (int) $row['pay_time'], 0);
                payExpect('createOrder:goods_name 取服务端', (string) $row['goods_name'], '思维粒子');
            }

            // 幂等：同用户同商品已有待付款单
            payBind($token, ['goods_id' => 8, 'num' => 1, 'pay_way' => 'huifu']);
            payAssert('createOrder:幂等防重', (new PayOrderController())->createOrder(), 0, '请勿重复提交');

            // 超量：挂单只有 5 件，要 6 件。
            // 注意顺序——数量校验排在幂等校验之前（先判断买不买得起，
            // 再判断是否重复提交），因此这里返回「挂单数量不足」而非防重文案。
            payBind($token, ['goods_id' => 8, 'num' => 6, 'pay_way' => 'huifu']);
            payAssert('createOrder:挂单数量不足', (new PayOrderController())->createOrder(), 0, '挂单数量不足');

            // ==================== 4. 开户单返回实测错误 ====================
            payBind($token, []);
            payAssert('createHuifu:已付过开户费', (new PayOrderController())->createHuifuAccountOrder(),
                0, '您已经支付开户手续费，请勿重复操作');
        } finally {
            Db::rollback();
            request()->withPost([]);
            request()->withGet([]);
        }
    }
}
