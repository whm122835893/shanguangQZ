<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;
use app\controller\Addons;
use app\controller\Image;

// Mock 路由：/api 前缀组，契约见 docs/api/BACKEND-API-SPEC.md
Route::group('api', function () {
    require __DIR__ . '/mock.php';
})->allowCrossDomain();

/* 管理端路由：无 /api 前缀（P0 最小集），鉴权走 AdminAuthService。
   同样需要跨域放行 —— 管理前端与 API 不同源部署。 */
Route::group(function () {
    require __DIR__ . '/sub/route_admin.php';
})->allowCrossDomain();

/* 管理后台 v1（司南重建版）：/admin/api/v1，JWT + RBAC。
   见 route_admin_v1.php 顶部契约注释。 */
Route::group(function () {
    require __DIR__ . '/sub/route_admin_v1.php';
})->allowCrossDomain();

/* 原站 addons 插件路由：**不带 /api 前缀**（其余业务接口都带）。
   目前只有二维码生成一处，Invitation 页用它渲染邀请二维码。
   已在本地用 endroid/qr-code 重写，去掉最后一条原站依赖。
   注意路径是 /addons/qrcode/index/build —— 三段都在，不要简化。 */
Route::rule('addons/qrcode/index/build', [Addons::class, 'qrcode'], 'GET|POST');

/* 图片服务：收口 img.gandart.shop 依赖。
   首次访问从 CDN 抓取并缓存到 runtime/img-cache，之后只读本地不再出网。
   路径同前端 oss() 的输出：/static/oss/<OSS路径>。
   用 Route::rule + pattern(['path' => '.+'])，让 <path> 能匹配含斜杠的多级路径
   （OSS 路径形如 /uploads/20261010/xxx.jpg，默认动态段只吃一段会漏掉日期目录）。 */
Route::rule('static/oss/<path>', [Image::class, 'serve'], 'GET')
    ->pattern(['path' => '.+']);

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});
