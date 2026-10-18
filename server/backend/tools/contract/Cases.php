<?php
declare(strict_types=1);

/**
 * 契约回归用例集 —— 注册中心（按领域分组，实现在 cases/ 下）
 *
 * 🔴 两次拆分的历史，防止有人再合并回去：
 *   第一次（run.php vs 本文件）：拆「比对器」与「被比对的数据」——
 *     run.php 一度 328 行触发红线，且加用例时要反复读 diff 引擎细节。
 *   第二次（2026-10-18，本文件 361 行再次触线）：拆「用例之间」——
 *     13 个端点用例按领域分 4 组，各组独立文件、独立 use：
 *       cases/UserCases.php     登录/个人信息（2 例）
 *       cases/ContentCases.php  轮播/消息/公会/我的持有（4 例）
 *       cases/MarketCases.php   藏品详情/交易信息/求购列表（4 例）
 *       cases/OrderCases.php    订单列表/批量下单/VIP 下单（3 例）
 *     以后加新端点只动对应领域文件，注册中心追加一行调用即可。
 *
 * 本文件只负责：共享常量 + 分组调度。
 * 取样本、比对算法、信封校验在 run.php 与 ContractDiff.php。
 *
 * 🔴 加新端点用例的三步（顺序不能颠倒）：
 *   1. 确认 recon/ 下有 code=1 的实测样本（没样本就别写，那是猜）
 *   2. 从样本抄下 msg 原文，填进 check() 的最后一个参数
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

require __DIR__ . '/cases/UserCases.php';
require __DIR__ . '/cases/ContentCases.php';
require __DIR__ . '/cases/MarketCases.php';
require __DIR__ . '/cases/OrderCases.php';

if (!function_exists('runContractCases')) {
    /** 由 run.php 调用，按领域顺序执行全部用例 */
    function runContractCases(): void
    {
        runUserCases();     // login / getUserInfo
        runContentCases();  // antMycollection / newList / myunion / getBanner
        runMarketCases();   // tradeDetail / prepayDetail / tradingInfo / begBuyList
        runOrderCases();    // orderList / batchPayOrder / createVipOrder
    }
}
