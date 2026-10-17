<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Log;

/**
 * 审计日志服务
 *
 * 只记录「谁、在什么时候、对什么资源、做了什么、结果如何」，
 * 不记录密码 / token / 身份证等敏感值本身。
 *
 * 用途：安全事件事后追溯（如越权尝试、登录爆破）。
 * 这是安全编码 skill 的强制项——没有审计日志的鉴权系统等于没有鉴权。
 *
 * @package app\service
 */
final class Audit
{
    /**
     * 敏感字段黑名单：写日志前先剔除，防止把密码写进日志文件
     */
    private const SENSITIVE = [
        'password', 'password_hash', 'old_password', 'new_password',
        'confirm_password', 'token', 'access_token', 'refresh_token',
        'idcard', 'id_card', 'bank_card', 'cvv',
    ];

    /**
     * 记录一条审计条目
     *
     * @param string $action   动作名，如 login / create_order / update_profile
     * @param int    $userId   操作者 ID，未登录填 0
     * @param array  $detail   附加信息，会自动剔除敏感字段
     * @param bool   $success  操作是否成功
     */
    public static function write(
        string $action,
        int $userId = 0,
        array $detail = [],
        bool $success = true
    ): void {
        try {
            Log::write([
                'audit'    => 1,
                'action'   => $action,
                'user_id'  => $userId,
                'ip'       => request()->ip(),
                'uri'      => request()->server('REQUEST_URI', ''),
                'method'   => request()->method(),
                'agent'    => substr((string) request()->header('user-agent'), 0, 200),
                'detail'   => self::sanitize($detail),
                'success'  => $success ? 1 : 0,
                'time'     => time(),
                'ip_chain' => request()->server('HTTP_X_FORWARDED_FOR', ''),
            ], 'audit');
        } catch (\Throwable $e) {
            // 审计写失败不能打断业务，但需要留下痕迹
            Log::error('[Audit] 审计日志写入失败: ' . $e->getMessage());
        }
    }

    /**
     * 记录一次鉴权失败（含失败原因），这是排查爆破的主要线索
     *
     * @param string       $action  动作名
     * @param string       $reason  失败原因
     * @param array        $context 附加上下文（账号、失败次数等），会自动脱敏
     * @param int          $userId  已识别到的用户 ID，未知填 0
     */
    public static function authFail(
        string $action,
        string $reason,
        array $context = [],
        int $userId = 0
    ): void {
        self::write($action, $userId, ['reason' => $reason] + $context, false);
    }

    /**
     * 剔除敏感字段
     */
    private static function sanitize(array $data): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string) $k), self::SENSITIVE, true)) {
                $out[$k] = '***REDACTED***';
                continue;
            }
            if (is_array($v)) {
                $out[$k] = self::sanitize($v);
                continue;
            }
            $out[$k] = is_string($v) ? substr($v, 0, 500) : $v;
        }
        return $out;
    }
}