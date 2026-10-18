<?php
declare(strict_types=1);

/**
 * 契约回归用例集 —— 各端点逐字段比对
 *
 * 🔴 为什么从 run.php 拆出来：
 *    run.php 一度涨到 328 行，触发单文件 ≤300 行的 P0 红线。
 *    但真正该拆的理由不是行数，而是职责——run.php 是「比对器+ 调度」，
 *    用例是「被比对的数据」。两者变化频率不同，混在一起会让
 *    加一个端点用例时反复读到 diff 引擎的实现细节。
 *
 * 本文件只负责：取本地真实出参 → 交给 check() 比对。
 * 取样本、比对算法、信封校验全在 run.php 与 ContractDiff.php。
 *
 * 🔴 加新端点用例的三步（顺序不能颠倒）：
 *   1. 确认recon/ 下有 code=1 的实测样本（没样本就别写，那是猜）
 *   2. 从样本抄下msg 原文，填进 check() 的最后一个参数
 *   3. 跑 php tools/contract/run.php，退出码必须为 0
 *
 * ⚠️ 所有用例都必须处理「本地无数据」的情况，且**计为失败**——
 *    静默跳过比失败危险得多（本项目已踩过：banner 用例因样本目录
 *    未覆盖 raw/ 而静默跳过，差点造成假通过）。
 */


/**
 * 运行时字段：每次请求都会变，与结构契约无关，比对时排除
 */
const RUNTIME_FIELDS = ['logintime', 'loginip', 'updatetime'];

/**
 * 登录端点专属运行时字段：每次登录必然不同。
 *
 * 值排除但**键必须存在** —— 缺 expiretime 前端无法判断登录态过期，
 * 本项目真实发生过（本地只回 {token,user_id} 两字段，被 login 用例抓出）。
 */
const LOGIN_RUNTIME_FIELDS = ['token', 'expiretime', 'expires_in'];

use app\model\ArtelOrder;
use app\model\Banner;
use app\model\Collection;
use app\model\ConsignmentPriceRule;
use app\model\Message;
use app\model\User;
use app\model\UserCollection;
use app\service\Formatter;
use app\service\LoginFormatter;
use app\service\MarketFormatter;
use app\service\TradeFormatter;
use app\service\UnionFormatter;
use app\service\UserFormatter;

if (!function_exists('runContractCases')) {
    /** 由 run.php 调用，追加用例结果 */
    function runContractCases(): void
    {
        // 🔴 必须显式 import 全局状态：这些变量定义在 run.php 的文件作用域，
        //    函数体内默认看不到。踩过的坑：直接用会报 Undefined variable，
        //    而 $failed 读不到默认值 0 会让所有用例「碰巧通过」。
        global $results, $failed;


    /**
     * 登录端点专属运行时字段
     *
     * 🔴 这三个字段每次登录必然不同，不参与比对，但**键的存在与类型必须校验**：
     *    缺 expiretime 前端就无法判断登录态过期（本项目真实发生过——本地只回
     *    {token,user_id} 两个字段，被本用例抓出）。
     *    所以比对时排除值，但由 check() 的字段集diff 保证键不丢。
     */

    // ---- 0. user/login（26 字段，本项目抓出缺陷最多的端点）----
    try {
        $u = User::find(16797);
        if ($u === null) {
            $results[] = 'login             [跳过]（本地无 user id=16797，请先跑 seed.php）';
            $failed++;
        } else {
            // token 明文不在样本里，用固定占位串保证字段存在；只比键与类型，不比值
            $out = LoginFormatter::login(
                $u,
                '__RUNTIME_TOKEN__',
                time() + \app\model\UserToken::TTL,
                \app\model\UserToken::TTL
            );
            check('login', '_login', $out, 'data.userinfo', LOGIN_RUNTIME_FIELDS, 0, '登录成功');
        }
    } catch (\Throwable $e) {
        $results[] = 'login             异常：' . $e->getMessage();
        $failed++;
    }

    // ---- 1. getUserInfo ----
    try {
        $u = User::find(16797);
        if ($u === null) {
            $results[] = 'getUserInfo        [跳过]（本地无 user id=16797，请先跑 seed.php）';
            $failed++;
        } else {
            check('getUserInfo', 'user_getuserinfo', UserFormatter::userInfo($u), 'data', RUNTIME_FIELDS, 0, '个人信息');
        }
    } catch (\Throwable $e) {
        $results[] = 'getUserInfo        异常：' . $e->getMessage();
        $failed++;
    }

    // ---- 2. orderList（原站样本 2 条，逐条按 id 匹配比对）----
    try {
        $sampleRows = raw('order_order_orderlist')['data']['data'] ?? [];
        if ($sampleRows === []) {
            $results[] = 'orderList          [跳过]（原站样本 total=0）';
            $failed++;
        } else {
            $n = 0;
            $bad = 0;
            foreach ($sampleRows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $rec = $id > 0 ? ArtelOrder::find($id) : null;
                if ($rec === null) {
                    $results[] = sprintf('  orderList id=%d [缺失] 本地缺该订单', $id);
                    $failed++;
                    continue;
                }
                $n++;
                check('orderList#' . $id, 'order_order_orderlist',
                    TradeFormatter::order($rec->toArray()), 'data.data', [], $id, '订单列表');
            }
            if ($n === 0) {
                $results[] = 'orderList          [跳过]（本地无对应订单）';
            }
            unset($bad);
        }
    } catch (\Throwable $e) {
        $results[] = 'orderList          异常：' . $e->getMessage();
        $failed++;
    }

    // ---- 3. antMycollection ----
    try {
        $rows = UserCollection::where('user_id', 16797)->order('id', 'asc')->select()->toArray();
        if ($rows === []) {
            $results[] = 'antMycollection    [跳过]（本地无持有记录）';
            $failed++;
        } else {
            // 必须复刻 Controller 的关联加载，否则测的不是真实链路
            // （Controller 在 Core.php 里手工建 $collMap 后作为第二参数传入）
            $cids = array_values(array_unique(array_map(
                static fn($r) => (int) $r['collection_id'],
                $rows
            )));
            $collRows = Collection::whereIn('id', $cids)->select()->toArray();
            $collMap  = [];
            foreach ($collRows as $cr) {
                $collMap[(int) $cr['id']] = $cr;
            }
            check('antMycollection', 'user_collection_user_collection_antmycollection',
                TradeFormatter::userCollection($rows[0], $collMap[(int) $rows[0]['collection_id']] ?? null),
                'data.data', [], 0, '获取成功');
        }
    } catch (\Throwable $e) {
        $results[] = 'antMycollection    异常：' . $e->getMessage();
        $failed++;
    }

    // ---- 4. newList（消息）----
    try {
        $list = Message::order('id', 'asc')->select()->toArray();
        if ($list === []) {
            $results[] = 'newList            [跳过]（本地无消息）';
            $failed++;
        } else {
            check('newList', 'user_newlist', TradeFormatter::message($list[0]), 'data.list', [], 0, '消息列表');
        }
    } catch (\Throwable $e) {
        $results[] = 'newList            异常：' . $e->getMessage();
        $failed++;
    }

    // ---- 5. myunion（公会）----
    try {
        check('myunion', 'union_user_myunion', UnionFormatter::myUnion(16797), 'data', [], 0, '成功');
    } catch (\Throwable $e) {
        $results[] = 'myunion            异常：' . $e->getMessage();
        $failed++;
    }

    // ---- 6. getBanner（首页轮播，实测 2 条 5 字段）----
    try {
        $sampleRows = raw('banner_show_getBanner')['data']['data'] ?? [];
        if ($sampleRows === []) {
            $results[] = 'getBanner          [跳过]（原站样本无数据）';
            $failed++;
        } else {
            foreach ($sampleRows as $row) {
                $id  = (int) ($row['id'] ?? 0);
                $rec = $id > 0 ? Banner::find($id) : null;
                if ($rec === null) {
                    $results[] = sprintf('  getBanner id=%d [缺失] 本地缺该记录，请先跑 seed.php', $id);
                    $failed++;
                    continue;
                }
                // 走真实出参链路（Controller 里的排序+切片也要覆盖，故查全量再排序）
                $all = Banner::where('is_show', 1)
                    ->order('sort', 'desc')->order('id', 'desc')
                    ->select()->toArray();
                $all = array_slice($all, 0, 15);
                $hit = null;
                foreach ($all as $r) {
                    if ((int) $r['id'] === $id) {
                        $hit = $r;
                        break;
                    }
                }
                check('getBanner#' . $id, 'banner_show_getBanner',
                    Formatter::bannerList($hit === null ? [] : [$hit])[0] ?? [],
                    'data.data', [], $id, 'banner列表');
            }
        }
    } catch (\Throwable $e) {
        $results[] = 'getBanner          异常：' . $e->getMessage();
        $failed++;
    }

    // ---- 7. market/trade/collectionDetail（嵌套版：顶层 5 字段 + floor_price 11 字段）----
    //
    // 🔴 本用例是两端点契约的「锚」：trade 的 floor_price 是嵌套对象，
    //    若有人「优化」成平铺（或反之），前端 data.floor_price.real_price
    //    直接变 undefined，页面静默显示空——正是最难查的 bug 形态。
    try {
        $coll = Collection::find(1);
        $rule = ConsignmentPriceRule::findForCollection(1, 'trade');
        if ($coll === null || $rule === null) {
            $results[] = 'tradeDetail        [跳过]（本地无 collection id=1 或 trade 规则，请先跑 seed.php）';
            $failed++;
        } else {
            check('tradeDetail', 'market_trade_collectiondetail',
                MarketFormatter::tradeCollectionDetail($coll->toArray(), $rule),
                'data', [], 0, '藏品详情');
        }
    } catch (\Throwable $e) {
        $results[] = 'tradeDetail         异常：' . $e->getMessage();
        $failed++;
    }

    // ---- 8. market/Prepayment/collectionDetail（平铺版：11 字段直接铺在 data 下）----
    //
    // 🔴 与用例 7 是同源数据的两个投影，唯一差别是 tips 与嵌套层级。
    //    两个用例都过 = 证明 scene 区分和结构区分同时成立。
    //    只过一个 = 说明有一半用户会看到错误文案或取不到字段。
    try {
        $rule = ConsignmentPriceRule::findForCollection(1, 'prepay');
        if ($rule === null) {
            $results[] = 'prepayDetail       [跳过]（本地无 prepay 场景规则，请先跑 seed.php）';
            $failed++;
        } else {
            check('prepayDetail', 'market_prepayment_collectiondetail',
                MarketFormatter::prepaymentCollectionDetail($rule),
                'data', [], 0, '相关金额');
        }
    } catch (\Throwable $e) {
        $results[] = 'prepayDetail        异常：' . $e->getMessage();
        $failed++;
    }

    }
}
