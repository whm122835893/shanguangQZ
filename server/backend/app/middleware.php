<?php
declare(strict_types=1);

/**
 * 全局中间件队列
 *
 * 🔴 这是全局中间件的唯一正确位置。
 * Http::loadMiddleware() 读取本文件并 import() 返回的数组，
 * 数组每一项都会被当作中间件类名实例化。
 *
 * ⚠️ 踩过的坑：在 config/middleware.php 里挂全局队列是**无效**的
 *    （该文件只读 alias / priority 两个键），改错文件会导致限流静默失效——
 *    表现为「配了限流但连打 70 次都不拦」，属于典型的假安全，必须实测验证。
 */
return [
    // 接口限流：60 次 / 60 秒，按 ip + 路由计数
    // 中间件内部对 system/ping 放行，健康检查不受影响
    \app\middleware\RateLimit::class,
];