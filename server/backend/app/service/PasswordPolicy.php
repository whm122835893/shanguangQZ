<?php
declare(strict_types=1);

namespace app\service;

use app\model\User;
use app\model\UserToken;
use think\facade\Db;

/**
 * 密码策略与登录载荷
 *
 * 🔴 为什么从 AccountService 拆出来：
 *    AccountService 拆出后仍达 302 行，触发 ≤300 行 P0 红线。
 *    把「密码强度规则」「唯一键冲突判定」「登录载荷签发」三块
 *    与注册/改密主流程解耦，职责也更清晰。
 *
 * @package app\service
 */
final class PasswordPolicy
{
    /** 密码最小长度 */
    public const MIN_PASSWORD = 6;

    /** 密码最大长度（防超长输入拖垮 Argon2） */
    public const MAX_PASSWORD = 64;

    /**
     * 密码强度校验
     *
     * @return string 空串表示通过，否则是错误文案
     */
    public static function validate(string $password): string
    {
        $len = strlen($password);
        if ($len < self::MIN_PASSWORD) {
            return '密码至少 6 位';
        }
        if ($len > self::MAX_PASSWORD) {
            return '密码不能超过 64 位';
        }
        return '';
    }

    /**
     * 是否唯一索引冲突
     *
     * 🔴 用于识别并发下的重复注册：应用层先查只是快速失败，
     *    真正防并发的是数据库唯一索引，冲突时靠本方法转成友好文案。
     */
    public static function isDuplicateEntry(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        return strpos($msg, '1062') !== false
            || stripos($msg, 'duplicate entry') !== false
            || stripos($msg, 'Duplicate') !== false;
    }

    /**
     * 签发 token 并返回符合 login 契约的载荷
     *
     * ⚠️ 26 字段白名单由 LoginFormatter 保证，调用方不要自己拼。
     */
    public static function issueLoginPayload(?User $user, string $device = 'unknown', string $ip = ''): array
    {
        if ($user === null) {
            return [];
        }
        $t = UserToken::issue($device, $ip);
        Db::name('user_token')->insert([
            'user_id'    => (int) $user['id'],
            'token'      => $t['hash'],
            'device'     => $device,
            'ip'         => $ip,
            'expiretime' => $t['expiretime'],
            'createtime' => time(),
        ]);

        return LoginFormatter::login($user, $t['plain'], $t['expiretime'], UserToken::TTL);
    }
}
