<?php
declare(strict_types=1);

use think\facade\Route;
use app\controller\admin\AdminController;
use app\controller\admin\StatsController;
use app\controller\admin\UserController;
use app\controller\admin\OrderController;
use app\controller\admin\WalletController;
use app\controller\admin\RealnameController;

/**
 * 管理端路由（P0 最小集）—— 无 /api 前缀，与 C 端完全隔离
 *
 * 载入方式：route/app.php require 本文件（route/ 顶层会被 glob 自动加载，
 * route/sub/ 下的文件必须显式 require，与 route_api.php 同一套路数）。
 *
 * 鉴权：除 login 外全部要求 header X-Admin-Token（或 query admin_token），
 * 校验走 AdminAuthService::verify —— 12 小时有效、单会话、禁用即失效。
 *
 * ⚠️ 改动后必须跑：php tools/contract/admin_test.php + admin_e2e.sh
 */

// ==================== 会话 ====================
Route::rule('admin/auth/login',   [AdminController::class, 'login'],   'POST');
Route::rule('admin/auth/logout',  [AdminController::class, 'logout'],  'POST');
Route::rule('admin/auth/profile', [AdminController::class, 'profile'], 'GET|POST');

// ==================== banner 运营 ====================
Route::rule('admin/banner/list',   [AdminController::class, 'bannerList'],    'GET|POST');
Route::rule('admin/banner/save',   [AdminController::class, 'bannerSave'],    'POST');
Route::rule('admin/banner/toggle', [AdminController::class, 'bannerToggle'],  'POST');

// ==================== 公告运营 ====================
Route::rule('admin/affiche/list',   [AdminController::class, 'afficheList'],    'GET|POST');
Route::rule('admin/affiche/save',   [AdminController::class, 'afficheSave'],    'POST');
Route::rule('admin/affiche/toggle', [AdminController::class, 'afficheToggle'],  'POST');

// ==================== 藏品上下架 ====================
Route::rule('admin/collection/list',   [AdminController::class, 'collectionList'],    'GET|POST');
Route::rule('admin/collection/toggle', [AdminController::class, 'collectionToggle'],  'POST');

// ==================== 抽奖运营 ====================
Route::rule('admin/lottery/list',   [AdminController::class, 'lotteryList'],    'GET|POST');
Route::rule('admin/lottery/save',   [AdminController::class, 'lotterySave'],    'POST');
Route::rule('admin/lottery/toggle', [AdminController::class, 'lotteryToggle'],  'POST');

// ==================== 合成运营 ====================
Route::rule('admin/synthesis/theme/list', [AdminController::class, 'synthesisThemeList'],  'GET|POST');
Route::rule('admin/synthesis/theme/save', [AdminController::class, 'synthesisThemeSave'],  'POST');
Route::rule('admin/synthesis/plan/save',  [AdminController::class, 'synthesisPlanSave'],  'POST');

// ==================== 仪表盘（v3.1 P1） ====================
Route::rule('admin/dashboard', [StatsController::class, 'dashboard'], 'GET|POST');

// ==================== 用户治理（v3.1 P1） ====================
Route::rule('admin/user/list',   [UserController::class, 'list'],    'GET|POST');
Route::rule('admin/user/detail', [UserController::class, 'detail'],  'GET|POST');
Route::rule('admin/user/freeze', [UserController::class, 'freeze'],  'POST');

// ==================== 订单查询（v3.1 P1，只读） ====================
Route::rule('admin/order/list', [OrderController::class, 'list'], 'GET|POST');

// ==================== 钱包核对（v3.1 P1，只读） ====================
Route::rule('admin/wallet/transactions', [WalletController::class, 'transactions'], 'GET|POST');
Route::rule('admin/wallet/summary',      [WalletController::class, 'summary'],      'GET|POST');

// ==================== 实名复核（v3.1 P1，只读） ====================
Route::rule('admin/realname/list', [RealnameController::class, 'list'], 'GET|POST');
