<?php
declare(strict_types=1);

namespace app\common;

/**
 * 请求处理 —— 契约兼容层
 *
 * 兼容两处原站历史包袱：
 *   1. 分页入参三种写法并存：list_rows / limit / pageSize（SPEC §1.4）
 *   2. token 双通道：header + query（线上网关会剥自定义header，SPEC §1.2）
 *
 * @package app\common
 */
final class Request
{
    /**
     * 取 token —— header 与 query 双通道
     *
     * 前端拦截器两处都下发（http.js:56,59），任一通道有效即可，
     * 但为兼容网关行为，两个都读。
     */
    public static function token(): string
    {
        $header = request()->header('token');
        if (is_string($header) && $header !== '') {
            return $header;
        }
        $query = request()->get('token', '');
        return is_string($query) ? $query : '';
    }

    /** 是否已携带 token（Mock 阶段只判断存在性，不校验有效性） */
    public static function hasToken(): bool
    {
        return self::token() !== '';
    }

    /**
     * 分页参数归一化
     *
     * 原站三种写法并存，且部分接口只传 page（服务端默认 10）。
     * 统一按 list_rows → limit → pageSize → 默认 10 的顺序取。
     *
     * @return array{page:int, size:int}
     */
    public static function page(): array
    {
        $page = (int) (request()->post('page') ?? request()->get('page') ?? 1);
        $page = max(1, $page);

        $size = 0;
        foreach (['list_rows', 'limit', 'pageSize'] as $field) {
            $v = request()->post($field) ?? request()->get($field);
            if ($v !== null && (int) $v > 0) {
                $size = (int) $v;
                break;
            }
        }
        if ($size === 0) {
            $size = 10;
        }
        // 上限保护，避免 ?limit=999999 拖垮内存（Mock 阶段也保持习惯）
        $size = min($size, 100);

        return ['page' => $page, 'size' => $size];
    }

    /**
     * 读取 POST body，兼容 JSON 与表单两种提交方式
     *
     * @return array<string,mixed>
     */
    public static function body(): array
    {
        $json = request()->post();
        return is_array($json) ? $json : [];
    }

    /**
     * 安全地取一个字符串参数
     */
    public static function str(string $key, string $default = ''): string
    {
        $v = request()->post($key) ?? request()->get($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = request()->post($key) ?? request()->get($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }
}
