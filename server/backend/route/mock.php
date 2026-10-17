<?php
declare(strict_types=1);

use think\facade\Route;

/**
 * API 入口路由
 *
 * ⚠️ 三个踩过的坑，改动前先读：
 *
 * 1. 限流中间件不在这里挂载。ThinkPHP 8 的 Route::group() 返回 RuleGroup，
 *    该类**没有 middleware() 方法**，链式调用会中断，导致 require 的返回值(1)
 *    被当成响应体输出。
 *    全局中间件的正确位置是 config/middleware.php（它只接受类名列表）。
 *
 * 2. 业务路由表放在 route/sub/ 子目录。Http::loadRoutes() 用 glob('route/*.php')
 *    加载目录下所有文件，路由表若直接放route/ 会被自动加载一次，
 *    再被下面的 require 加载第二次，导致路由重复注册。
 *
 * 3. /api 前缀不在本文件声明，而由 route/app.php 的外层 group 提供，
 *    这里只注册 'api' 前缀下的相对路径。
 */

// ==================== 健康检查 ====================
Route::get('system/ping', function () {
    return json(['code' => 1, 'msg' => 'ok', 'time' => time(), 'data' => null]);
});

// ==================== 业务端点 ====================
require __DIR__ . '/sub/route_api.php';
