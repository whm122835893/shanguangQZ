<?php
declare(strict_types=1);

namespace app\service;

/**
 * 登录出参契约 —— 对齐 user/login 实测 26 字段
 *
 * 实测结构（recon/raw/_login.json）：
 *   data = {userinfo: {26 字段}}
 *
 * 🔴 为什么不能直接复用 UserFormatter::userInfo()（那是对的 106 字段）：
 *    登录下发的是**精简子集**——26 字段，只有 getUserInfo 的其中 22个，
 *    另外 80 个字段登录时不下发。反过来 getUserInfo 也不下发登录独有的
 *    expires_in / expiretime / is_exist / user_id。两个端点字段集不同，
 *    共用一个 Formatter 必然导致 80 个字段的差异。
 *
 * 🔴 独有 4 字段的语义：
 *    token       明文，只在登录这���下发一次
 *    expiretime  过期绝对时间戳（int）
 *    expires_in  有效期秒数（int），原站实测 2592000 = 30 天
 *    is_exist    实测 1，语义未确认（疑为「是否已完善资料」），照抄实测值
 *    user_id     字符串型用户 id —— 实测是 "16797" 带引号，与 id 的 int 16797 并存
 *
 * ⚠️ id 与 user_id 同时存在且类型不同（int vs string），
 *    这是原站既有事实，不要「顺手统一」成同类型。
 *
 * @package app\service
 */
final class LoginFormatter
{
    /**
     * 实测 26 字段白名单（严格顺序，与原站 JSON 键序一致）
     *
     * 🔴 白名单而非 toArray()：库里 100+ 列，多下发一个前端就可能踩未定义分支。
     */
    private const FIELDS = [
        'id', 'username', 'nickname', 'mobile', 'avatar', 'score',
        'inviter_code', 'yao_count', 'huifu_service_charge',
        'sandpay_service_charge', 'yibao_status', 'sandpay_status',
        'user_wx', 'user_qq', 'user_phone', 'user_ore', 'team_ore',
        'person_ore', 'invited_user_buy_rabbit_collection_person',
        'transfer_permission', 'transfer_permission_level',
        'token', 'user_id', 'expiretime', 'expires_in', 'is_exist',
    ];

    /**
     * 整型字段（必须转int，不能下发字符串）
     */
    private const INT_FIELDS = [
        'id', 'score', 'yao_count', 'huifu_service_charge',
        'sandpay_service_charge', 'yibao_status', 'sandpay_status',
        'invited_user_buy_rabbit_collection_person',
        'transfer_permission', 'transfer_permission_level',
        'expiretime', 'expires_in', 'is_exist',
    ];

    /**
     * 高精度字符串字段（实测是 "0.000000"，绝不能转 float）
     *
     * 🔴 转float 会得到 0.0，丢精度且类型变掉，前端 toFixed 行为不同。
     */
    private const RAW_STRING_FIELDS = ['user_ore', 'team_ore', 'person_ore'];

    /**
     * 构造登录出参
     *
     * @param \think\Model|array $row       user 行
     * @param string             $plainToken 明文 token（只在这一次出现）
     * @param int                $expiretime 过期时间戳
     * @param int                $ttl        有效期秒数
     */
    public static function login($row, string $plainToken, int $expiretime, int $ttl): array
    {
        $s = is_array($row) ? $row : $row->toArray();

        $out = [];
        foreach (self::FIELDS as $k) {
            if ($k === 'token') {
                $out[$k] = $plainToken;
                continue;
            }
            if ($k === 'expiretime') {
                $out[$k] = $expiretime;
                continue;
            }
            if ($k === 'expires_in') {
                $out[$k] = $ttl;
                continue;
            }
            if ($k === 'user_id') {
                // 🔴 实测 user_id 是字符串 "16797"，而 id 是 int 16797
                $out[$k] = (string) ($s['id'] ?? '');
                continue;
            }
            $out[$k] = $s[$k] ?? null;
        }

        foreach (self::INT_FIELDS as $k) {
            $out[$k] = (int) $out[$k];
        }

        foreach (self::RAW_STRING_FIELDS as $k) {
            // null 必须保持 null —— 实测 ore 可能为 null，压成 '0.000000' 是契约破坏
            $out[$k] = $out[$k] === null ? null : (string) $out[$k];
        }

        // 其余字符串字段兜底：null 保持 null（实测 user_wx/user_qq/user_phone 为 null）
        return $out;
    }
}