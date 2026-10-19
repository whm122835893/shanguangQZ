<?php
declare(strict_types=1);

use think\facade\Route;
use app\controller\admin\AdminController;

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
