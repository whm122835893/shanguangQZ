<?php
declare(strict_types=1);

namespace app\service\admin;

/**
 * 管理端 v1 统一响应信封（契约：司南后台提示词 v3.1 §1.2）
 *
 *   { code: number, data: any, message: string }，code === 200 为成功
 *
 * 与 C 端 {code:1,msg,time,data} 信封刻意隔离：
 *   管理前端 axios 拦截器只认 200/401/403/409，互不影响。
 *
 * @package app\service\admin
 */
final class Resp
{
    public const OK          = 200;
    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN   = 403;
    public const NOT_FOUND   = 404;
    public const CONFLICT    = 409;
    public const SERVER_ERROR = 500;

    /** @param mixed $data */
    public static function ok($data = null, string $message = 'ok'): \think\response\Json
    {
        return json(['code' => self::OK, 'data' => $data, 'message' => $message]);
    }

    /**
     * 业务失败：HTTP 状态码同时落地，前端 axios 双通道拦截
     * business code 与 HTTP status 一一对应，避免「HTTP 200 却是错误」的歧义
     */
    public static function fail(string $message, int $code = self::BAD_REQUEST): \think\response\Json
    {
        return json(['code' => $code, 'data' => null, 'message' => $message])->code($code);
    }

    /** 分页信封：list + total（契约 §1.2 分页参数 page/page_size） */
    public static function page(array $list, int $total, int $page, int $pageSize): \think\response\Json
    {
        return self::ok(['list' => $list, 'total' => $total, 'page' => $page, 'page_size' => $pageSize]);
    }
}
