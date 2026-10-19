<?php
declare(strict_types=1);

use think\facade\Route;
use app\controller\api\AccountController;
use app\controller\api\BegBuyController;
use app\controller\api\ContentController;
use app\controller\api\ContentPlayController;
use app\controller\api\MarketController;
use app\controller\api\OrderController;
use app\controller\api\UserController;
use app\controller\api\UserExtraController;
use app\controller\api\PayController;
use app\controller\api\PayOrderController;
use app\controller\api\SmsController;
use app\controller\api\TradeController;

/**
 * 业务端点路由表 —— 对齐前端 60 个实际调用端点
 *
 * 由 route/mock.php 以分组形式载入，统一挂载限流中间件。
 *
 * 契约（docs/api/BACKEND-API-SPEC.md §1）：
 *   BaseURL /api · token 双通道（header + query）
 *
 * 关于 HTTP 方法：原站 221 个端点实测全为 POST，但本项目前端 src/api/index.js
 * 混用 http.get 与 http.post（各 9 / 51 处），且 src/api/http.js 的请求拦截器
 * 不改写 method。因此所有业务端点统一注册为 'GET|POST'，两侧都能命中。
 *
 * 路径参数命名必须是 ids（前端硬编码拼接，SPEC §6 红线第 7 条）
 *
 * 数据来源标注（v3.1 起 [MOCK] 已清零，全仓无内存假数据）：
 *   [DB]   已按原站实测响应建表并灌入真实数据
 *   [空态] 无前端调用且无数据载体，真实空返回——绝不编造数据
 *   [拒绝] 功能未接入，fail-closed 明确拒绝——绝不假成功
 *   [契约] 有实测成功样本且经复探证明恒空态，无数据载体（如 batchPayOrder）
 */

// ==================== 首页 / 公告 ====================
Route::rule('index/afficheList',        [ContentController::class, 'afficheList'],      'GET|POST'); // [DB]
Route::rule('index/affichetypelist',    [ContentController::class, 'afficheTypeList'],  'GET|POST'); // [DB]
Route::rule('index/getSeries',          [ContentController::class, 'getSeries'],        'GET|POST'); // [DB] series 表
Route::rule('banner/show/getBanner',      [ContentController::class, 'getBanner'],        'GET|POST'); // [DB]
Route::rule('index/afficheDetail/ids/:id', [ContentController::class, 'afficheDetail'],  'GET|POST'); // [DB]

// ==================== 藏品 / 盲盒 ====================
Route::rule('box/blind_box/list',         [ContentController::class, 'blindBoxList'],     'GET|POST'); // [DB]

/**
 * 🔴 藏品系列列表
 * 正确端点是下划线 collection_list。
 * 原站前端误写为驼峰 CollectionList（404 死路由，SPEC 缺陷清单 #1），
 * 这里同时注册旧名并返回明确错误，避免再次踩坑。
 */
Route::rule('box/collection/collection_list', [ContentController::class, 'collectionList'], 'GET|POST'); // [DB] collection 表分页
Route::rule('box/collection/CollectionList', function () {
    return json([
        'code' => 0,
        'msg'  => '端点已废弃，请改用 /box/collection/collection_list（下划线）',
        'time' => time(),
        'data' => null,
    ]);
}, 'GET|POST');

// ==================== 用户 ====================
Route::rule('user/login',                [UserController::class, 'login'],            'GET|POST'); // [DB]
Route::rule('user/getUserInfo',          [UserController::class, 'getUserInfo'],      'GET|POST'); // [DB]
// 🔴 新增端点（原站实名在 App 内完成）：三态分离（脱敏/密文/指纹），红线全在 RealnameService
Route::rule('user/realname/submit',      [UserController::class, 'realname'],         'GET|POST'); // [新增]
Route::rule('user/address_list',         [UserExtraController::class, 'addressList'],      'GET|POST'); // [DB] AddressService，14-user-extra.sql
Route::rule('user/newList',              [UserController::class, 'newList'],          'GET|POST'); // [DB]
Route::rule('user_collection/user_collection/antMycollection', [UserController::class, 'myCollection'],    'GET|POST'); // [DB]
Route::rule('user_collection/user_collection/getRecord',      [UserExtraController::class, 'collectionRecord'], 'GET|POST'); // [空态] 无前端调用/无载体

// ==================== 市场 / 行情 ====================
Route::rule('market/market/getMarketGoodsListByGoodsId', [MarketController::class, 'marketGoodsListByGoodsId'], 'GET|POST'); // [DB]
Route::rule('market/market/marketGoodsDetail',            [MarketController::class, 'marketGoodsDetail'],        'GET|POST'); // [DB]
Route::rule('market/market/getMarketList',                [MarketController::class, 'marketList'],              'GET|POST'); // [DB]
Route::rule('market/market/marketList',                   [MarketController::class, 'marketBoxList'],           'GET|POST'); // [DB]
Route::rule('market/market/MarketDetail/ids/:id',         [MarketController::class, 'marketDetail'],            'GET|POST'); // [DB]
Route::rule('market/trade/tradingInformation',            [MarketController::class, 'tradingInformation'],      'GET|POST'); // [DB]
Route::rule('market/trade/begBuyList',                    [MarketController::class, 'begBuyList'],              'GET|POST'); // [DB]
// 🔴 求购域（v2.6 切 DB）：真实端点名是 createBegBuyOrder/cancelBegBuyOrder，
//    前端 api/index.js 的 begBuy/entrust 是死代码（原站无此路由，404）
Route::rule('market/trade/createBegBuyOrder',             [BegBuyController::class, 'createBegBuyOrder'],   'GET|POST'); // [DB]
Route::rule('market/trade/cancelBegBuyOrder',             [BegBuyController::class, 'cancelBegBuyOrder'],   'GET|POST'); // [DB]
Route::rule('market/trade/begBuyOrderList',               [BegBuyController::class, 'begBuyOrderList'],     'GET|POST'); // [DB]
Route::rule('market/trade/begBuyOrderInfo',               [BegBuyController::class, 'begBuyOrderInfo'],     'GET|POST'); // [DB]

// ==================== 交易下单 ====================
Route::rule('order/pay/fastOrderNew',     [OrderController::class, 'fastOrderNew'],     'GET|POST'); // [DB] 事务落 artel_order，key 与 marketGoodsDetail 同源
Route::rule('order/pay/doPay',            [OrderController::class, 'doPay'],             'GET|POST'); // [DB] 网关抽象：通道未接入即拒绝，绝不假成功

// ==================== 订单 ====================
Route::rule('order/order/orderList',                       [OrderController::class, 'orderList'],         'GET|POST'); // [DB]
Route::rule('order/order/orderDetail/ids/:id',             [OrderController::class, 'orderDetail'],       'GET|POST'); // [DB]
Route::rule('order/order/MarketOrderDetail/ids/:id',       [OrderController::class, 'marketOrderDetail'], 'GET|POST'); // [DB]
Route::rule('order/order/myConsignment',                   [OrderController::class, 'myConsignment'],     'GET|POST'); // [DB] order_type=8
Route::rule('market/Prepayment/myOrderlist',               [OrderController::class, 'myOrderlist'],       'GET|POST'); // [DB] order_type=4 + status 筛选

// ==================== 合成 ====================
Route::rule('synthesis/themeinfo',       [ContentPlayController::class, 'synthesisTheme'],     'GET|POST'); // [DB] SynthesisService，表已建
Route::rule('synthesis/material',        [ContentPlayController::class, 'synthesisMaterial'],  'GET|POST'); // [DB] need_items 真实解析
Route::rule('synthesis/recordList',      [ContentPlayController::class, 'synthesisRecord'],    'GET|POST'); // [空态] 无执行端点/无载体

// ==================== 抽签 / 抽奖 ====================
Route::rule('lottery/info',              [ContentPlayController::class, 'lotteryInfo'],      'GET|POST'); // [DB] LotteryService
Route::rule('lottery/statistics',        [ContentPlayController::class, 'lotteryStatistics'], 'GET|POST'); // [DB] 用户抽奖记录
Route::rule('lottery/start',             [ContentPlayController::class, 'lotteryStart'],      'GET|POST'); // [DB] 原子扣水晶+发放
Route::rule('lottery/record',            [ContentPlayController::class, 'lotteryRecord'],     'GET|POST'); // [DB] 中奖记录

// ==================== 活动 / 社区 ====================
// 🔴 原站实测：activity/index 返回分页信封但 total=0（站点无数据）
Route::rule('activity/index',            [ContentPlayController::class, 'activityIndex'],     'GET|POST'); // [空态] 原站 total=0
Route::rule('Activity/category',          [ContentPlayController::class, 'activityCategory'],  'GET|POST'); // [空态] 无前端调用
// 🔴 原站实测：crystal_goods/list 原站实测 total:0 data:[]（Shop.vue 注释）
Route::rule('crystal_goods/list',         [ContentPlayController::class, 'crystalGoods'],     'GET|POST'); // [空态] 原站恒空
Route::rule('user/ranking',              [UserExtraController::class, 'userRank'],         'GET|POST'); // [DB] RankService 实时聚合
Route::rule('union/user/myunion',        [UserController::class, 'myUnion'],          'GET|POST'); // [DB]

/* ========================================================================
 * 补齐端点（联调缺口回填，由 check-coverage.py 驱动）
 * 背景：比对前端 60 个调用端点，发现 27 个未覆盖。支付/下单类缺失最致命——
 *      交易链路走到 CreateOrder 就断，表现为「点了没反应」且不报错。
 * 契约：docs/api/API.md + src/api/index.js 调用注释
 * ======================================================================== */
Route::rule('order/pay/CreateOrder',                [PayOrderController::class, 'createOrder'],              'GET|POST'); // [DB] 服务端算价+幂等+事务落库
Route::rule('order/pay/CreateMarketOrder',          [PayOrderController::class, 'createMarketOrder'],        'GET|POST'); // [DB] 同上
Route::rule('order/pay/CreateMarketOrderRush',      [PayOrderController::class, 'createMarketOrderRush'],    'GET|POST'); // [DB] 同上
Route::rule('order/pay/CreateVipOrder',             [PayController::class, 'createVipOrder'],           'GET|POST'); // [DB]
Route::rule('order/pay/CreateHuifuAccountOrder',    [PayOrderController::class, 'createHuifuAccountOrder'],  'GET|POST'); // [契约] 原站实测：本账号已付开户费
Route::rule('order/pay/batchBuy',                   [PayController::class, 'batchBuy'],                 'GET|POST'); // [拒绝] 未接入 fail-closed
Route::rule('order/pay/Recharge',                   [PayController::class, 'recharge'],                 'GET|POST'); // [DB] v3.0 真落 recharge_order，入账走回调
// 🔴 新增端点：渠道异步回调（外部资金唯一入口，HMAC 验签 + 幂等结算）
Route::rule('order/pay/notify/:code',               [\app\controller\api\NotifyController::class, 'notify'], 'POST'); // [新增]
Route::rule('order/pay/paySmsCheck',                [PayController::class, 'paySmsCheck'],              'GET|POST'); // [DB] SmsCodeService 真校验
Route::rule('order/order/batchPayOrder',            [PayController::class, 'batchPayOrder'],            'GET|POST'); // [契约]
Route::rule('order/order/getMarketOrderDetails',    [TradeController::class, 'getMarketOrderDetails'],    'GET|POST'); // [DB] IDOR 归属校验
Route::rule('order/order/saleMarketOrderDetail',    [TradeController::class, 'saleMarketOrderDetail'],    'GET|POST'); // [DB] IDOR + consign=1
Route::rule('market/trade/entrust',                 [TradeController::class, 'entrust'],                  'GET|POST'); // [DB] 事务：market_goods + artel_order(4) + sell_num 占用
Route::rule('market/trade/entrustOrderList',        [TradeController::class, 'entrustOrderList'],         'GET|POST'); // [DB]
Route::rule('market/trade/collectionDetail',        [TradeController::class, 'tradeCollectionDetail'],   'GET|POST'); // [DB]
Route::rule('market/Prepayment/collectionDetail',   [TradeController::class, 'prepaymentCollectionDetail'], 'GET|POST'); // [DB]
Route::rule('market/Prepayment/myOrderInfo',        [TradeController::class, 'myOrderInfo'],              'GET|POST'); // [DB]
Route::rule('user_collection/user_collection/detail',            [TradeController::class, 'collectionDetail'],  'GET|POST'); // [DB]
Route::rule('user_collection/user_collection/show_resale_money', [TradeController::class, 'showResaleMoney'],   'GET|POST'); // [DB]
Route::rule('union/index/logs',                     [UserController::class, 'unionLogs'],                'GET|POST'); // [空态] 无 union_log 载体
Route::rule('user/newrank1',                        [TradeController::class, 'newRank'],                  'GET|POST'); // [空态] 无数据载体
Route::rule('index/getConfig',                      [TradeController::class, 'getConfig'],                'GET|POST'); // [DB] 已按原站补 key 校验
Route::rule('sms/send',                             [SmsController::class, 'send'],                    'GET|POST'); // [DB] 随机码+HMAC存储+频率限制，响应绝不含明文码
Route::rule('user/register',                        [AccountController::class, 'register'],                 'GET|POST'); // [DB] 真落库 user+user_credential(Argon2id)，验证码一次性消费
Route::rule('user/resetpwd',                        [AccountController::class, 'resetPwd'],                 'GET|POST'); // [DB] 真改密+吊销该用户全部 token
Route::rule('order/pay/batchdopay',                 [PayController::class, 'batchDoPay'],               'GET|POST'); // [DB] 逐单归属校验+网关，success 如实统计