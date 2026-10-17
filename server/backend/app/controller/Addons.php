<?php
declare(strict_types=1);

namespace app\controller;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * 二维码生成 —— 原站 /addons/qrcode 的本地替代实现
 *
 * 【为什么必须自己实现】
 * 原站这条接口在 api.gandart.shop/addons/qrcode/index/build，不带 /api 前缀，
 * 且返回的是 PNG 二维码图而非 JSON。只要前端还调它，就仍然依赖原站。
 * 本控制器用纯 PHP 库 endroid/qr-code 重写，彻底去掉这最后一条外部依赖。
 *
 * 【为什么输出 SVG 而不是 PNG】
 * 沙箱 PHP 没有 GD 扩展，PNG/GIF/ImageMagick Writer 全部不可用。
 * SvgWriter 是纯 PHP 实现，无需任何图像扩展，浏览器 <img> 直接能渲染。
 * 注意：原站返回 PNG，若前端有硬编码 content-type 判断需留意（本项目只当 img src 用，无影响）。
 *
 * 【契约对齐】原站实测请求参数：
 *   /addons/qrcode/index/build
 *     ?text_type=h5
 *     &text=Q28116797          <- 邀请码（后端 getUserInfo 的 inviter_code 字段）
 *     &t=1758...                <- 时间戳，防缓存
 *     &url=%2F%23%2Fpages%2F... <- 二维码指向的页面路径
 */
class Addons
{
    /**
     * GET|POST /addons/qrcode/index/build
     * 返回 SVG 二维码（Content-Type: image/svg+xml）
     */
    public function qrcode()
    {
        $text = (string) request()->get('text', '');
        if ($text === '') {
            return response('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 400)
                ->contentType('image/svg+xml');
        }

        /* 原站语义：text_type=h5 时，扫出来的是 url 参数指向的本项目页面，
           text（邀请码）只是附带参数。真实实现要还原这个拼接规则。 */
        $textType = (string) request()->get('text_type', '');
        $path     = (string) request()->get('url', '');
        $origin   = $this->guessOrigin($path);

        $content = ($textType === 'h5' && $path !== '')
            ? $origin . '/' . ltrim($path, '/')
            : $text;

        try {
            /* endroid/qr-code v6 已移除 Builder::create()，改为构造器注入：
               new Builder(writer: ..., data: ..., size: ..., margin: ...)。
               下方用 named arguments，跨小版本升级时不易错位。 */
            $builder = new Builder(
                writer:  new SvgWriter(),
                data:    $content,
                size:    320,
                margin:  10,
            );
            $result = $builder->build();

            return response($result->getString(), 200)
                ->contentType('image/svg+xml')
                ->header([
                    // 防缓存参数由前端 t= 时间戳控制，这里给一个短缓存即可
                    'Cache-Control' => 'public, max-age=300',
                ]);
        } catch (\Throwable $e) {
            // 生成失败时返回占位 SVG，而不是 500 —— img 标签会显示破图但不崩页面
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320">'
                 . '<rect width="320" height="320" fill="#f5f5f5"/>'
                 . '<text x="160" y="165" font-size="13" fill="#999" text-anchor="middle">'
                 . '二维码生成失败</text></svg>';
            return response($svg, 200)->contentType('image/svg+xml');
        }
    }

    /**
     * 从 url 参数推断站点根地址。
     * 原站该参数是站内相对路径（如 /#/pages/invitation/invitation），
     * 扫码后要能在当前部署的域名下打开，所以这里用请求自身的 Host。
     */
    private function guessOrigin(string $path): string
    {
        // 支持前端传绝对地址（如 http://192.168.x.x:5173/#/xxx）
        if (preg_match('#^(https?://[^/]+)#i', $path, $m)) {
            return $m[1];
        }
        $scheme = request()->isSsl() ? 'https' : 'http';
        $host   = request()->host();
        $port   = request()->port();
        // 标准端口不写入，保持 URL 干净
        $default = $scheme === 'https' ? 443 : 80;
        return $port && $port !== $default ? "{$scheme}://{$host}:{$port}" : "{$scheme}://{$host}";
    }
}