<?php
declare(strict_types=1);

use think\facade\Route;
use app\controller\api\AccountController;
use app\controller\api\ContentController;
use app\controller\api\ContentPlayController;
use app\controller\api\MarketController;
use app\controller\api\OrderController;
use app\controller\api\UserController;
use app\controller\api\UserExtraController;
use app\controller\api\PayController;
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
 * 数据来源标注：
 *   [DB]   已按原站实测响应建表并灌入真实数据
 *   [MOCK] 原站需登录或已下线，暂用内存假数据（详见各控制器注释）
 *   [契约] 有实测成功样本且经复探证明恒空态，无数据载体（如 batchPayOrder）
 */

// ==================== 首页 / 公告 ====================
Route::rule('index/afficheList',        [ContentController::class, 'afficheList'],      'GET|POST'); // [DB]
Route::rule('index/affichetypelist',    [ContentController::class, 'afficheTypeList'],  'GET|POST'); // [DB]
Route::rule('index/getSeries',          [ContentController::class, 'getSeries'],        'GET|POST'); // [MOCK] 原站实测空数组
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
Route::rule('box/collection/collection_list', [ContentController::class, 'collectionList'], 'GET|POST'); // [MOCK]
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
Route::rule('user/address_list',         [UserExtraController::class, 'addressList'],      'GET|POST'); // [MOCK]
Route::rule('user/newList',              [UserController::class, 'newList'],          'GET|POST'); // [DB]
Route::rule('user_collection/user_collection/antMycollection', [UserController::class, 'myCollection'],    'GET|POST'); // [DB]
Route::rule('user_collection/user_collection/getRecord',      [UserExtraController::class, 'collectionRecord'], 'GET|POST'); // [MOCK]

// ==================== 市场 / 行情 ====================
Route::rule('market/market/getMarketGoodsListByGoodsId', [MarketController::class, 'marketGoodsListByGoodsId'], 'GET|POST'); // [MOCK]
Route::rule('market/market/marketGoodsDetail',            [MarketController::class, 'marketGoodsDetail'],        'GET|POST'); // [MOCK]
Route::rule('market/market/getMarketList',                [MarketController::class, 'marketList'],              'GET|POST'); // [MOCK]
Route::rule('market/market/marketList',                   [MarketController::class, 'marketBoxList'],           'GET|POST'); // [MOCK]
Route::rule('market/market/MarketDetail/ids/:id',         [MarketController::class, 'marketDetail'],            'GET|POST'); // [MOCK]
Route::rule('market/trade/tradingInformation',            [MarketController::class, 'tradingInformation'],      'GET|POST'); // [DB]
Route::rule('market/trade/begBuyList',                    [MarketController::class, 'begBuyList'],              'GET|POST'); // [DB]

// ==================== 交易下单 ====================
Route::rule('order/pay/fastOrderNew',     [OrderController::class, 'fastOrderNew'],     'GET|POST'); // [MOCK]
Route::rule('order/pay/doPay',            [OrderController::class, 'doPay'],             'GET|POST'); // [MOCK]

// ==================== 订单 ====================
Route::rule('order/order/orderList',                       [OrderController::class, 'orderList'],         'GET|POST'); // [MOCK]
Route::rule('order/order/orderDetail/ids/:id',             [OrderController::class, 'orderDetail'],       'GET|POST'); // [MOCK]
Route::rule('order/order/MarketOrderDetail/ids/:id',       [OrderController::class, 'marketOrderDetail'], 'GET|POST'); // [MOCK]
Route::rule('order/order/myConsignment',                   [OrderController::class, 'myConsignment'],     'GET|POST'); // [MOCK]
Route::rule('market/Prepayment/myOrderlist',               [OrderController::class, 'myOrderlist'],       'GET|POST'); // [MOCK]

// ==================== 合成 ====================
// 🔴 原站实测：synthesis/themeinfo、synthesis/material 均返回 code=401（需登录）
Route::rule('synthesis/themeinfo',       [ContentPlayController::class, 'synthesisTheme'],     'GET|POST'); // [MOCK] 原站401
Route::rule('synthesis/material',        [ContentPlayController::class, 'synthesisMaterial'],  'GET|POST'); // [MOCK] 原站401
Route::rule('synthesis/recordList',      [ContentPlayController::class, 'synthesisRecord'],    'GET|POST'); // [MOCK]

// ==================== 抽签 / 抽奖 ====================
Route::rule('lottery/info',              [ContentPlayController::class, 'lotteryInfo'],      'GET|POST'); // [MOCK]
Route::rule('lottery/statistics',        [ContentPlayController::class, 'lotteryStatistics'], 'GET|POST'); // [MOCK]
Route::rule('lottery/start',             [ContentPlayController::class, 'lotteryStart'],      'GET|POST'); // [MOCK]
Route::rule('lottery/record',            [ContentPlayController::class, 'lotteryRecord'],     'GET|POST'); // [MOCK]

// ==================== 活动 / 社区 ====================
// 🔴 原站实测：activity/index 返回分页信封但 total=0（站点无数据）
Route::rule('activity/index',            [ContentPlayController::class, 'activityIndex'],     'GET|POST'); // [MOCK] 原站 total=0
Route::rule('Activity/category',          [ContentPlayController::class, 'activityCategory'],  'GET|POST'); // [MOCK]
// 🔴 原站实测：crystal_goods/list 返回 code=401（需登录）
Route::rule('crystal_goods/list',         [ContentPlayController::class, 'crystalGoods'],     'GET|POST'); // [MOCK] 原站401
Route::rule('user/ranking',              [UserExtraController::class, 'userRank'],         'GET|POST'); // [MOCK]
Route::rule('union/user/myunion',        [UserController::class, 'myUnion'],          'GET|POST'); // [DB]

/* ========================================================================
 * 补齐端点（联调缺口回填，由 check-coverage.py 驱动）
 * 背景：比对前端 60 个调用端点，发现 27 个未覆盖。支付/下单类缺失最致命——
 *      交易链路走到 CreateOrder 就断，表现为「点了没反应」且不报错。
 * 契约：docs/api/API.md + src/api/index.js 调用注释
 * ======================================================================== */
Route::rule('order/pay/CreateOrder',                [PayController::class, 'createOrder'],              'GET|POST'); // [MOCK]
Route::rule('order/pay/CreateMarketOrder',          [PayController::class, 'createMarketOrder'],        'GET|POST'); // [MOCK]
Route::rule('order/pay/CreateMarketOrderRush',      [PayController::class, 'createMarketOrderRush'],    'GET|POST'); // [MOCK]
Route::rule('order/pay/CreateVipOrder',             [PayController::class, 'createVipOrder'],           'GET|POST'); // [DB]
Route::rule('order/pay/CreateHuifuAccountOrder',    [PayController::class, 'createHuifuAccountOrder'],  'GET|POST'); // [MOCK]
Route::rule('order/pay/batchBuy',                   [PayController::class, 'batchBuy'],                 'GET|POST'); // [MOCK]
Route::rule('order/pay/Recharge',                   [PayController::class, 'recharge'],                 'GET|POST'); // [MOCK]
Route::rule('order/pay/paySmsCheck',                [PayController::class, 'paySmsCheck'],              'GET|POST'); // [MOCK]
Route::rule('order/order/batchPayOrder',            [PayController::class, 'batchPayOrder'],            'GET|POST'); // [契约]
Route::rule('order/order/getMarketOrderDetails',    [TradeController::class, 'getMarketOrderDetails'],    'GET|POST'); // [MOCK]
Route::rule('order/order/saleMarketOrderDetail',    [TradeController::class, 'saleMarketOrderDetail'],    'GET|POST'); // [MOCK]
Route::rule('market/trade/entrust',                 [TradeController::class, 'entrust'],                  'GET|POST'); // [MOCK]
Route::rule('market/trade/entrustOrderList',        [TradeController::class, 'entrustOrderList'],         'GET|POST'); // [MOCK]
Route::rule('market/trade/collectionDetail',        [TradeController::class, 'tradeCollectionDetail'],   'GET|POST'); // [DB]
Route::rule('market/trade/begBuyOrderList',         [TradeController::class, 'begBuyOrderList'],          'GET|POST'); // [MOCK]
Route::rule('market/trade/begBuyOrderInfo',         [TradeController::class, 'begBuyOrderInfo'],          'GET|POST'); // [MOCK]
Route::rule('market/Prepayment/collectionDetail',   [TradeController::class, 'prepaymentCollectionDetail'], 'GET|POST'); // [DB]
Route::rule('market/Prepayment/myOrderInfo',        [TradeController::class, 'myOrderInfo'],              'GET|POST'); // [MOCK]
Route::rule('user_collection/user_collection/detail',            [TradeController::class, 'collectionDetail'],  'GET|POST'); // [MOCK]
Route::rule('user_collection/user_collection/show_resale_money', [TradeController::class, 'showResaleMoney'],   'GET|POST'); // [MOCK]
Route::rule('union/index/logs',                     [UserController::class, 'unionLogs'],                'GET|POST'); // [MOCK]
Route::rule('user/newrank1',                        [TradeController::class, 'newRank'],                  'GET|POST'); // [MOCK]
Route::rule('index/getConfig',                      [TradeController::class, 'getConfig'],                'GET|POST'); // [DB] 已按原站补 key 校验
Route::rule('sms/send',                             [PayController::class, 'sendSms'],                  'GET|POST'); // [MOCK]
Route::rule('user/register',                        [AccountController::class, 'register'],                 'GET|POST'); // [MOCK]
Route::rule('user/resetpwd',                        [AccountController::class, 'resetPwd'],                 'GET|POST'); // [MOCK]
Route::rule('order/pay/batchdopay',                 [PayController::class, 'batchDoPay'],               'GET|POST'); // [MOCK]