<?php
declare(strict_types=1);

namespace app\controller\admin\Concerns;

use app\common\Request;
use app\common\Response;
use app\service\AdminAuthService;

/**
 * 管理端鉴权 trait（v3.1 从 AdminController 抽出，多管理控制器复用）
 *
 * 🔐 鉴权模型：
 *   - 登录换 token（明文只下发一次，12 小时有效）
 *   - 后续请求带 header X-Admin-Token: <token>（兼容 query: admin_token）
 *   - 所有 mutation 写 audit_log（event='admin_*'），运营动作可审计
 *
 * @package app\controller\admin\Concerns
 */
trait HasAdminAuth
{
    /** token 提取：header X-Admin-Token 优先，兼容 query admin_token */
    private static function bearer(): string
    {
        $header = request()->header('x-admin-token', '');
        if (!is_string($header) || $header === '') {
            return Request::str('admin_token');
        }
        return trim($header);
    }

    /** @return array{0:array|null, 1:\think\response\Json|null} [admin, errorResponse] */
    private static function requireAdmin(): array
    {
        $admin = AdminAuthService::verify(self::bearer());
        if ($admin === null) {
            return [null, Response::unauthorized('登录已失效，请重新登录')];
        }
        return [$admin, null];
    }

    private static function ip(): string
    {
        $ip = request()->ip();
        return is_string($ip) ? $ip : '0.0.0.0';
    }
}
