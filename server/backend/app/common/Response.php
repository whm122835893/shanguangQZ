<?php
declare(strict_types=1);

namespace app\common;

/**
 * 统一响应契约 —— 闪光集市后端
 *
 * 铁律（改了前端就崩，来源 docs/api/BACKEND-API-SPEC.md §1.3）：
 *   1. code = 1 表示成功（反直觉约定，不是 0）
 *   2. 信封固定四字段 {code, msg, time, data}，一个不能少
 *   3. 401 必须同时体现在 HTTP 状态码与 body.code
 *
 * @package app\common
 */
final class Response
{
    /** 业务失败 */
    public const CODE_FAIL = 0;
    /** 成功 */
    public const CODE_OK = 1;
    /** 未登录 / token 失效 */
    public const CODE_UNAUTHORIZED = 401;

    /**
     * 成功
     * @param mixed $data
     */
    public static function ok($data = null, string $msg = ''): \think\response\Json
    {
        return self::make(self::CODE_OK, $msg, $data);
    }

    /**
     * 业务失败（HTTP 200 + code=0）
     * 原站 msg 是可直接展示的中文，如「账户不正确」「存在其他尚未支付的订单」
     */
    public static function fail(string $msg, $data = null): \think\response\Json
    {
        return self::make(self::CODE_FAIL, $msg, $data);
    }

    /**
     * 未登录 —— HTTP 状态码与 body.code 同时为 401
     *
     * 前端两条路径都拦（http.js:96-112）：响应拦截器看 body.code，
     * 错误拦截器看 HTTP status。只改其一会导致漏判。
     */
    public static function unauthorized(string $msg = '请登录后操作'): \think\response\Json
    {
        $json = self::make(self::CODE_UNAUTHORIZED, $msg, null);
        return $json->code(401);
    }

    /**
     * ThinkPHP 标准分页结构
     * 前端强依赖这五个字段，缺一不可。
     *
     * 🔴 $msg 不是可选装饰，是端点级契约：
     *    原站每个端点的 msg 都不同且稳定（banner列表/订单列表/公告列表…），
     *    是从 240 个实测样本里统计出来的，不是随口填的。
     *    漏传会返回空串，构成契约漂移（已真实发生过一次）。
     *    新增分页端点时务必从样本里抄原值，不要留空。
     */
    public static function page(
        array $list,
        int $total,
        int $perPage = 10,
        int $currentPage = 1,
        string $msg = ''
    ): \think\response\Json {
        $perPage   = max(1, $perPage);
        $lastPage  = (int) max(1, ceil($total / $perPage));
        $current   = max(1, $currentPage);

        return self::ok([
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $current,
            'last_page'    => $lastPage,
            'data'         => $list,
        ], $msg);
    }

    private static function make(int $code, string $msg, $data): \think\response\Json
    {
        return json([
            'code' => $code,
            'msg'  => $msg,
            'time' => time(),
            'data' => $data,
        ]);
    }
}
