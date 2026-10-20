<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\common\Request;
use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;

/**
 * V1 基础工具：分页参数 / 请求读取 / 时间格式化
 *
 * 契约（§1.2）：分页 page 默认 1、page_size 默认 20 最大 100；
 * 时间输出 ISO 8601（YYYY-MM-DDTHH:mm:ss+08:00 本地时区）；
 * 金额字符串两位小数。
 *
 * @package app\controller\admin\v1
 */
trait V1
{
    /** @return array{0:int,1:int} [page, pageSize] */
    private static function paging(): array
    {
        $page = (int) Request::str('page', '1');
        $size = (int) Request::str('page_size', '20');
        return [max(1, $page), min(100, max(1, $size))];
    }

    /**
     * 实时取参：route → post → get
     *
     * 🔴 不用 request()->param()：TP8 的 param() 首次调用后缓存合并结果，
     *    且 withGet/withPost 不重置缓存（只有 setRoute 会），直调控制器的
     *    契约测试会读到脏数据。route 优先保证 /users/:id 这类路径参数可用。
     */
    private static function param(string $key, $default = null)
    {
        $r = request();
        $v = $r->route($key);
        if ($v === null || $v === '') {
            $v = $r->post($key);
        }
        if ($v === null || $v === '') {
            $v = $r->get($key, $default);
        }
        return $v;
    }

    /** int 参数（允许 0/缺省） */
    private static function int(string $key, int $default = 0): int
    {
        $v = self::param($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    /** string 参数 */
    private static function str(string $key, string $default = ''): string
    {
        $v = self::param($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    /** 时间范围 [start, end]（ISO 字符串 → unixtime，空 → 0） */
    private static function dateRange(string $startKey = 'date_start', string $endKey = 'date_end'): array
    {
        $s = self::str($startKey);
        $e = self::str($endKey);
        return [
            $s !== '' ? (int) strtotime($s) : 0,
            $e !== '' ? (int) strtotime($e) : 0,
        ];
    }

    /** unixtime → ISO 8601（0 → 空串） */
    private static function iso(int $ts): string
    {
        return $ts > 0 ? date('Y-m-d\TH:i:s+08:00', $ts) : '';
    }

    /** 金额输出：string 两位小数（Decimal.js 契约） */
    private static function money($v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    /** 手机号脱敏：138****8888（§3.7） */
    private static function maskPhone(string $phone): string
    {
        if (mb_strlen($phone) < 7) {
            return $phone === '' ? '' : '****';
        }
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }

    /**
     * 姓名脱敏：王*明（张*丰 风格）
     *
     * 🔴 与 RealnameService::maskName 保持同一口径：保留首尾、遮中段；
     *    两字名 王*。两端实现若不一致，实名模块与用户模块会互相打架。
     */
    private static function maskName(string $name): string
    {
        if ($name === '') {
            return '';
        }
        $len = mb_strlen($name);
        if ($len === 1) {
            return $name;
        }
        if ($len === 2) {
            return mb_substr($name, 0, 1) . '*';
        }
        return mb_substr($name, 0, 1) . str_repeat('*', $len - 2) . mb_substr($name, -1);
    }

    /** 证件号脱敏：110***********234 */
    private static function maskIdCard(string $id): string
    {
        if (mb_strlen($id) < 8) {
            return $id === '' ? '' : '******';
        }
        return substr($id, 0, 3) . str_repeat('*', 11) . substr($id, -3);
    }

    /** 要求管理员登录 + 权限码（无权限抛 AdminException → 401/403 JSON） */
    private static function guard(string $permission): array
    {
        return Guard::require($permission);
    }

    /** 高风险操作密码验证 */
    private static function verifyPassword(array $admin): void
    {
        Guard::verifyPassword($admin, Request::str('password'));
    }

    /** 统一业务失败（HTTP 400） */
    private static function fail(string $msg, int $code = Resp::BAD_REQUEST): \think\response\Json
    {
        return Resp::fail($msg, $code);
    }

    /** 必填校验：缺一个抛 400 */
    private static function requireFields(array $keys): void
    {
        foreach ($keys as $key) {
            $v = self::param($key, '');
            if ((is_string($v) && trim($v) === '') || $v === null || $v === []) {
                throw new AdminException('缺少必填参数: ' . $key);
            }
        }
    }
}
