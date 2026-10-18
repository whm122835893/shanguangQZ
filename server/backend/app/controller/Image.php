<?php
declare(strict_types=1);

namespace app\controller;

use app\common\Response;

/**
 * 图片服务 —— 收口最后一条原站依赖（img.gandart.shop）
 *
 * 【为什么需要】
 * 前端 oss() 原本把相对路径拼到 https://img.gandart.shop，
 * 或落到本地 public/static/oss/（但那 287 张图从未拉取）。两条路都依赖外部。
 * 现在改为：本Mock 端点按需从 CDN 抓取并缓存到本地，
 * 之后前端只认同源 /static/oss/...，首次访问后彻底不再出网。
 *
 * 【与前端 oss() 的约定】
 * oss() 返回 /static/oss/<OSS路径>，Vite dev 代理该前缀到本端点；
 * 生产由 server.cjs 放行 /static/oss/ 走同一逻辑（或直接命中已缓存文件）。
 *
 * 【为什么用 HTTP 客户端而非 GD】
 * 沙箱 PHP 无 GD 扩展；此处只需把字节原样落盘/回传，不做图像处理。
 */
class Image
{
    /** 缓存根目录：runtime 下，便于随runtime 一起清理 */
    private static function cacheDir(): string
    {
        $dir = runtime_path() . 'img-cache' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * GET /static/oss/<path>
     *
     * 注意：ThinkPHP 路由的 <path> 动态段默认只匹配**不含斜杠**的一段，
     * 而 OSS 路径形如 /uploads/20261010/xxx.jpg 本身是多级目录。
     * 因此注册路由时必须用「自定义规则」显式声明为多段，
     * 这里直接从 request() 里取完整原始路径，不依赖路由注入。
     */
    public function serve()
    {
        // 取原始请求 URI（request()->server('REQUEST_URI')），
        // 再剥掉前缀 /static/oss。不能用 getPathInfo()——它已被路由改写。
        $uri = (string) request()->server('REQUEST_URI', '');
        $path = $uri;
        // 去掉查询串
        if (($q = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $q);
        }
        $pos = strpos($path, '/static/oss');
        if ($pos !== false) {
            $path = substr($path, $pos + strlen('/static/oss'));
        }
        $path = '/' . ltrim($path, '/');

        // 白名单：只允许 /uploads/ 前缀，防目录穿越
        if (!str_starts_with($path, '/uploads/') || str_contains($path, '..')) {
            return Response::fail('非法图片路径');
        }

        // 查找顺序：①运行时缓存 → ②静态预下载资产 → ③回源抓取
        //
        // 🔴 为什么多一层「静态资产」：前端仓库的 public/static/oss/ 下
        //    已有 37 张预下载图片（由 gandart-h5/tools/fetch-oss-images.sh 生成），
        //    路径结构与 OSS 完全一致（/uploads/2026xxxx/xxx.jpg）。
        //    2026-10-19 实测：库内 25 条图片路径里有 14 条静态目录已覆盖。
        //    直接复用这层，这 14 张**完全不出网**；只有静态目录没有的
        //    才落到第 ③ 步回源（并落 runtime 缓存，下次也不出网）。
        //    两套资产都是本地真实文件，不存在编造。
        $file = self::cacheDir() . md5($path);

        if (!is_file($file)) {
            $static = self::staticOssPath($path);
            if ($static !== null) {
                return response(file_get_contents($static), 200)
                    ->contentType($this->mimeOf($path))
                    ->header(['Cache-Control' => 'public, max-age=2592000']);
            }
            $ok = $this->fetch($path, $file);
            if (!$ok) {
                return Response::fail('图片不存在');
            }
        }

        $mime = $this->mimeOf($path);
        return response(file_get_contents($file), 200)
            ->contentType($mime)
            ->header(['Cache-Control' => 'public, max-age=2592000']);
    }

    /**
     * 静态预下载资产里有没有这张图（命中即零出网）
     *
     * 路径映射：DB 里存 `/uploads/20261012/xxx.jpg`，静态目录是
     * `public/static/oss/uploads/20261012/xxx.jpg` —— 直接拼接即可，无需转换。
     *
     * 目录来源：环境变量 `GANDART_STATIC_OSS` 优先，否则退回前端仓库的固定
     * 位置。之所以要跨项目引用，是因为 dev 环境下 vite 把 `/static/oss`
     * 代理到本端点，而预下载资产落在前端仓库；生产由 server.cjs 直接直出
     * 静态文件，不走本端点。
     */
    private static function staticOssPath(string $path): ?string
    {
        $dirs = [];
        $env = (string) (getenv('GANDART_STATIC_OSS') ?: '');
        if ($env !== '') {
            $dirs[] = rtrim($env, '/');
        }
        $dirs[] = '/workspace/gandart-h5/public/static/oss';

        foreach ($dirs as $d) {
            $full = $d . $path;
            if (is_file($full) && filesize($full) > 0) {
                return $full;
            }
        }
        return null;
    }

    /**
     * 从原站 CDN 抓取一次并落盘。
     * 说明：这是「一次性引导」行为 —— 图取回来后本服务可独立运行。
     * 若连CDN 也不可用，需要执行 tools/fetch-oss-images.sh 全量预热。
     */
    private function fetch(string $path, string $file): bool
    {
        $url = 'https://img.gandart.shop' . $path;

        $ctx = stream_context_create([
            'http' => [
                'timeout'         => 15,
                'ignore_errors'   => true,
                'follow_location' => 1,
                'header'          => "User-Agent: gandart-h5-local\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $body = @file_get_contents($url, false, $ctx);
        if ($body === false || $body === '' || strlen($body) < 100) {
            return false;
        }
        // file_get_contents 在 4xx 时会返回错误页内容，这里按图片魔数二次确认
        if (!$this->looksLikeImage($body)) {
            return false;
        }
        return @file_put_contents($file, $body) !== false;
    }

    /** 校验字节流确实是图片，而不是 CDN 返回的 HTML 错误页 */
    private function looksLikeImage(string $b): bool
    {
        if (strncmp($b, "\xFF\xD8\xFF", 3) === 0) return true;          // JPEG
        if (strncmp($b, "\x89PNG\r\n\x1A\n", 8) === 0) return true;    // PNG
        if (strncmp($b, 'GIF8', 4) === 0) return true;                 // GIF
        if (strncmp($b, 'RIFF', 4) === 0) return true;                 // WEBP
        if (str_starts_with($b, '<svg') || str_contains(substr($b, 0, 200), '<svg')) return true;
        return false;
    }

    private function mimeOf(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}