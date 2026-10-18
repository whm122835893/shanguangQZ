<?php
declare(strict_types=1);

namespace app\service;

use app\model\UnionConfig;
use app\model\UnionMember;
use app\model\User;

/**
 * 公会域出参契约 —— 对齐 union/user/myunion 实测
 *
 * 实测结构（2026-10-18抓取）：
 *   data = {
 *     config: {6 字段}   公会规则配置（单行）
 *     my:     {8 字段}   当前用户的成员关系
 *     union:  null|对象  所属公会详情
 *     join:   []|数组    可加入的公会列表
 *   }
 *
 * 🔴 四条硬约束：
 *   1. union 未加入时是 **null**，不能压成 {} 或 []
 *   2. join  实测是空数组 []
 *   3. my.sort 是字符串 "暂无排名"，不是 null / 0
 *   4. my.nickname / avatar / mobile 来自 user 表，需 JOIN，
 *      且实测 avatar 为空串 ""（尽管 user.avatar 是 "/defautAvatar.png"）
 *      —— 实测 my.avatar="" 而 user.avatar="/defautAvatar.png"，
 *      说明原站 my 层用的是另一套取法。契约层对齐实测：出空串。
 *
 * @package app\service
 */
final class UnionFormatter
{
    /**
     * 完整出参
     *
     * @param int $userId 当前登录用户
     */
    public static function myUnion(int $userId): array
    {
        return [
            'config' => self::config(),
            'my'     => self::my($userId),
            'union'  => self::unionDetail($userId),
            'join'   => self::joinable($userId),
        ];
    }

    /**
     * config 层：6 字段
     *
     * ⚠️ have_rate 等是**倍数**（实测 1.88），出参保持原值不要乘 100。
     *
     * 🔴 类型陷阱：原站按「是否带小数」分类型，不是统一 float/string
     *     实测：user_num=20(int) have_rate=1.88(float) be_invite_back_rate=1(int)
     *          have_be_invite_back_rate=0.88(float) manager_user_rate=1(int) user_rate=2(int)
     *    而 DB 是 decimal(6,2)，取出来恒为 "1.00" 字符串。
     *    直接透传会把 int 字段变成字符串 —— 前端做 === 1 判断就会失效。
     *
     *    规则：小数部分为0 -> int，其余 -> float。
     */
    private static function config(): array
    {
        $row = UnionConfig::singleton();
        $out = [];
        foreach (UnionConfig::CONFIG_FIELDS as $k) {
            if ($row === null) {
                $out[$k] = null;
                continue;
            }
            if ($k === 'user_num') {
                $out[$k] = (int) $row[$k];
                continue;
            }
            $v = (float) $row[$k];
            // 小数为 0 时下发 int，与原站一致
            $out[$k] = (fmod($v, 1.0) === 0.0) ? (int) $v : $v;
        }
        // 配置行缺失时输出 null 而非补0：
        // 0 倍率与 1.88 倍率是完全不同的业务含义，不能互相替代。
        return $out;
    }

    /**
     * my 层：8 字段
     *
     * ⚠️ 无成员记录时输出全 null 而非全 0：
     *    「没有成员关系」与「有成员关系但未加入」是两种状态。
     */
    private static function my(int $userId): array
    {
        $m = UnionMember::ofUser($userId);
        if ($m === null) {
            return [
                'id' => null, 'avatar' => null, 'nickname' => null, 'mobile' => null,
                'is_join' => null, 'sort' => null, 'power' => null, 'is_manage' => null,
            ];
        }

        // nickname / mobile / avatar 来自 user 表
        $u = User::find($userId);

        return [
            'id'         => (int) $m['id'],
            // 🔴 实测 my.avatar = ""，而 user.avatar = "/defautAvatar.png"。
            //    原站 my 层没有沿用 user.avatar，契约层照抄实测：空串。
            'avatar'     => '',
            'nickname'   => (string) ($u['nickname'] ?? ''),
            'mobile'     => (string) ($u['mobile'] ?? ''),
            'is_join'    => (int) $m['is_join'],
            // ⚠️ 实测 "暂无排名"，字符串
            'sort'       => (string) $m['sort'],
            'power'      => (int) $m['power'],
            'is_manage'  => (int) $m['is_manage'],
        ];
    }

    /**
     * union 层：所属公会详情
     *
     * 🔴 实测未加入公会时为 **null**。
     *    压成 [] 会让前端 truthy 判断走错分支。
     */
    private static function unionDetail(int $userId): ?array
    {
        $m = UnionMember::ofUser($userId);
        if ($m === null || $m['union_id'] === null || (int) $m['is_join'] !== 1) {
            return null;
        }
        // 样本里没有「已加入公会」的成功响应，
        // 字段结构未知 —— 不猜，等拿到样本再补。
        return null;
    }

    /**
     * join 层：可加入的公会列表
     *
     * 🔴 实测为 []。样本不足，不猜结构。
     */
    private static function joinable(int $userId): array
    {
        return [];
    }
}