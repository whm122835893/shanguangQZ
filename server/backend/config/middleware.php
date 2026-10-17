<?php
declare(strict_types=1);

// 中间件配置（config 层：仅别名与优先级，不含全局队列）
//
// ⚠️🔴 关键区别（这个坑真实踩过两次）：
//    - app/middleware.php    → **全局中间件队列**，Http::loadMiddleware() 会
//                              import() 它返回的数组，数组每一项都是中间件类名
//    - config/middleware.php → **只读 alias 与 priority** 两个键
//
//    在 config/middleware.php 里写全局队列是无效的——它压根不被当队列读取；
//    而它一旦被误当成队列 include 回来，就会把本文件源码吐进 HTTP 响应。
//
//    限流中间件的正确挂载位置见 app/middleware.php。
return [
    // 别名或分组
    'alias'    => [
        'ratelimit' => \app\middleware\RateLimit::class,
    ],

    // 优先级设置（数组值，非队列）
    'priority' => [
        \app\middleware\RateLimit::class,
    ],
];