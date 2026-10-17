<?php
declare(strict_types=1);

use think\facade\Route;
use app\controller\api\Core;
use app\controller\api\Trade;

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
 */

// ==================== 首页 / 公告 ====================
Route::rule('index/afficheList',        [Core::class, 'afficheList'],      'GET|POST'); // [DB]
Route::rule('index/affichetypelist',    [Core::class, 'afficheTypeList'],  'GET|POST'); // [DB]
Route::rule('index/getSeries',          [Core::class, 'getSeries'],        'GET|POST'); // [MOCK] 原站实测空数组
Route::rule('banner/show/getBanner',      [Core::class, 'getBanner'],        'GET|POST'); // [MOCK]
Route::rule('index/afficheDetail/ids/:id', [Core::class, 'afficheDetail'],  'GET|POST'); // [DB]

// ==================== 藏品 / 盲盒 ====================
Route::rule('box/blind_box/list',         [Core::class, 'blindBoxList'],     'GET|POST'); // [DB]

/**
 * 🔴 藏品系列列表
 * 正确端点是下划线 collection_list。
 * 原站前端误写为驼峰 CollectionList（404 死路由，SPEC 缺陷清单 #1），
 * 这里同时注册旧名并返回明确错误，避免再次踩坑。
 */
Route::rule('box/collection/collection_list', [Core::class, 'collectionList'], 'GET|POST'); // [MOCK]
Route::rule('box/collection/CollectionList', function () {
    return json([
        'code' => 0,
        'msg'  => '端点已废弃，请改用 /box/collection/collection_list（下划线）',
        'time' => time(),
        'data' => null,
    ]);
}, 'GET|POST');

// ==================== 用户 ====================
Route::rule('user/login',                [Core::class, 'login'],            'GET|POST'); // [DB]
Route::rule('user/getUserInfo',          [Core::class, 'getUserInfo'],      'GET|POST'); // [DB]
Route::rule('user/address_list',         [Core::class, 'addressList'],      'GET|POST'); // [MOCK]
Route::rule('user/newList',              [Core::class, 'newList'],          'GET|POST'); // [DB]
Route::rule('user_collection/user_collection/antMycollection', [Core::class, 'myCollection'],    'GET|POST'); // [DB]
Route::rule('user_collection/user_collection/getRecord',      [Core::class, 'collectionRecord'], 'GET|POST'); // [MOCK]

// ==================== 市场 / 行情 ====================
Route::rule('market/market/getMarketGoodsListByGoodsId', [Core::class, 'marketGoodsListByGoodsId'], 'GET|POST'); // [MOCK]
Route::rule('market/market/marketGoodsDetail',            [Core::class, 'marketGoodsDetail'],        'GET|POST'); // [MOCK]
Route::rule('market/market/getMarketList',                [Core::class, 'marketList'],              'GET|POST'); // [MOCK]
Route::rule('market/market/marketList',                   [Core::class, 'marketBoxList'],           'GET|POST'); // [MOCK]
Route::rule('market/market/MarketDetail/ids/:id',         [Core::class, 'marketDetail'],            'GET|POST'); // [MOCK]
Route::rule('market/trade/tradingInformation',            [Core::class, 'tradingInformation'],      'GET|POST'); // [MOCK]
Route::rule('market/trade/begBuyList',                    [Core::class, 'begBuyList'],              'GET|POST'); // [MOCK]

// ==================== 交易下单 ====================
Route::rule('order/pay/fastOrderNew',     [Core::class, 'fastOrderNew'],     'GET|POST'); // [MOCK]
Route::rule('order/pay/doPay',            [Core::class, 'doPay'],             'GET|POST'); // [MOCK]

// ==================== 订单 ====================
Route::rule('order/order/orderList',                       [Core::class, 'orderList'],         'GET|POST'); // [MOCK]
Route::rule('order/order/orderDetail/ids/:id',             [Core::class, 'orderDetail'],       'GET|POST'); // [MOCK]
Route::rule('order/order/MarketOrderDetail/ids/:id',       [Core::class, 'marketOrderDetail'], 'GET|POST'); // [MOCK]
Route::rule('order/order/myConsignment',                   [Core::class, 'myConsignment'],     'GET|POST'); // [MOCK]
Route::rule('market/Prepayment/myOrderlist',               [Core::class, 'myOrderlist'],       'GET|POST'); // [MOCK]

// ==================== 合成 ====================
// 🔴 原站实测：synthesis/themeinfo、synthesis/material 均返回 code=401（需登录）
Route::rule('synthesis/themeinfo',       [Core::class, 'synthesisTheme'],     'GET|POST'); // [MOCK] 原站401
Route::rule('synthesis/material',        [Core::class, 'synthesisMaterial'],  'GET|POST'); // [MOCK] 原站401
Route::rule('synthesis/recordList',      [Core::class, 'synthesisRecord'],    'GET|POST'); // [MOCK]

// ==================== 抽签 / 抽奖 ====================
Route::rule('lottery/info',              [Core::class, 'lotteryInfo'],      'GET|POST'); // [MOCK]
Route::rule('lottery/statistics',        [Core::class, 'lotteryStatistics'], 'GET|POST'); // [MOCK]
Route::rule('lottery/start',             [Core::class, 'lotteryStart'],      'GET|POST'); // [MOCK]
Route::rule('lottery/record',            [Core::class, 'lotteryRecord'],     'GET|POST'); // [MOCK]

// ==================== 活动 / 社区 ====================
// 🔴 原站实测：activity/index 返回分页信封但 total=0（站点无数据）
Route::rule('activity/index',            [Core::class, 'activityIndex'],     'GET|POST'); // [MOCK] 原站 total=0
Route::rule('Activity/category',          [Core::class, 'activityCategory'],  'GET|POST'); // [MOCK]
// 🔴 原站实测：crystal_goods/list 返回 code=401（需登录）
Route::rule('crystal_goods/list',         [Core::class, 'crystalGoods'],     'GET|POST'); // [MOCK] 原站401
Route::rule('user/ranking',              [Core::class, 'userRank'],         'GET|POST'); // [MOCK]
Route::rule('union/user/myunion',        [Core::class, 'myUnion'],          'GET|POST'); // [MOCK]

/* ========================================================================
 * 补齐端点（联调缺口回填，由 check-coverage.py 驱动）
 * 背景：比对前端 60 个调用端点，发现 27 个未覆盖。支付/下单类缺失最致命——
 *      交易链路走到 CreateOrder 就断，表现为「点了没反应」且不报错。
 * 契约：docs/api/API.md + src/api/index.js 调用注释
 * ======================================================================== */
Route::rule('order/pay/CreateOrder',                [Trade::class, 'createOrder'],              'GET|POST'); // [MOCK]
Route::rule('order/pay/CreateMarketOrder',          [Trade::class, 'createMarketOrder'],        'GET|POST'); // [MOCK]
Route::rule('order/pay/CreateMarketOrderRush',      [Trade::class, 'createMarketOrderRush'],    'GET|POST'); // [MOCK]
Route::rule('order/pay/CreateVipOrder',             [Trade::class, 'createVipOrder'],           'GET|POST'); // [MOCK]
Route::rule('order/pay/CreateHuifuAccountOrder',    [Trade::class, 'createHuifuAccountOrder'],  'GET|POST'); // [MOCK]
Route::rule('order/pay/batchBuy',                   [Trade::class, 'batchBuy'],                 'GET|POST'); // [MOCK]
Route::rule('order/pay/Recharge',                   [Trade::class, 'recharge'],                 'GET|POST'); // [MOCK]
Route::rule('order/pay/paySmsCheck',                [Trade::class, 'paySmsCheck'],              'GET|POST'); // [MOCK]
Route::rule('order/order/batchPayOrder',            [Trade::class, 'batchPayOrder'],            'GET|POST'); // [MOCK]
Route::rule('order/order/getMarketOrderDetails',    [Trade::class, 'getMarketOrderDetails'],    'GET|POST'); // [MOCK]
Route::rule('order/order/saleMarketOrderDetail',    [Trade::class, 'saleMarketOrderDetail'],    'GET|POST'); // [MOCK]
Route::rule('market/trade/entrust',                 [Trade::class, 'entrust'],                  'GET|POST'); // [MOCK]
Route::rule('market/trade/entrustOrderList',        [Trade::class, 'entrustOrderList'],         'GET|POST'); // [MOCK]
Route::rule('market/trade/collectionDetail',        [Trade::class, 'tradeCollectionDetail'],   'GET|POST'); // [MOCK]
Route::rule('market/trade/begBuyOrderList',         [Trade::class, 'begBuyOrderList'],          'GET|POST'); // [MOCK]
Route::rule('market/trade/begBuyOrderInfo',         [Trade::class, 'begBuyOrderInfo'],          'GET|POST'); // [MOCK]
Route::rule('market/Prepayment/collectionDetail',   [Trade::class, 'prepaymentCollectionDetail'], 'GET|POST'); // [MOCK]
Route::rule('market/Prepayment/myOrderInfo',        [Trade::class, 'myOrderInfo'],              'GET|POST'); // [MOCK]
Route::rule('user_collection/user_collection/detail',            [Trade::class, 'collectionDetail'],  'GET|POST'); // [MOCK]
Route::rule('user_collection/user_collection/show_resale_money', [Trade::class, 'showResaleMoney'],   'GET|POST'); // [MOCK]
Route::rule('union/index/logs',                     [Trade::class, 'unionLogs'],                'GET|POST'); // [MOCK]
Route::rule('user/newrank1',                        [Trade::class, 'newRank'],                  'GET|POST'); // [MOCK]
Route::rule('index/getConfig',                      [Trade::class, 'getConfig'],                'GET|POST'); // [DB] 已按原站补 key 校验
Route::rule('sms/send',                             [Trade::class, 'sendSms'],                  'GET|POST'); // [MOCK]
Route::rule('user/register',                        [Trade::class, 'register'],                 'GET|POST'); // [MOCK]
Route::rule('user/resetpwd',                        [Trade::class, 'resetPwd'],                 'GET|POST'); // [MOCK]
Route::rule('order/pay/batchdopay',                 [Trade::class, 'batchDoPay'],               'GET|POST'); // [MOCK]