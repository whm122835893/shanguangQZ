<?php
declare(strict_types=1);

/**
 * 用户域契约用例：login / getUserInfo
 *
 * 两个用例共用本地 user id=16797（seed.php 灌入的实测账号，
 * 与原站样本账号同 id，逐字段比对才有意义）。
 */

use app\model\User;
use app\model\UserToken;
use app\service\LoginFormatter;
use app\service\UserFormatter;

if (!function_exists('runUserCases')) {
    /**
     * @global array $results 用例结果行（run.php 注入）
     * @global int   $failed  失败计数（run.php 注入）
     */
    function runUserCases(): void
    {
        global $results, $failed;

        // ---- 0. user/login（26 字段，本项目抓出缺陷最多的端点）----
        //
        // 🔴 token / expiretime / expires_in 三个键每次登录必然不同，
        //    值排除但键必须在校（见 LOGIN_RUNTIME_FIELDS 注释）——
        //    缺 expiretime 前端就无法判断登录态过期，本项目真实发生过。
        //    token 明文不在样本里，用固定占位串保证字段存在；只比键与类型。
        try {
            $u = User::find(16797);
            if ($u === null) {
                $results[] = 'login             [跳过]（本地无 user id=16797，请先跑 seed.php）';
                $failed++;
            } else {
                $out = LoginFormatter::login(
                    $u,
                    '__RUNTIME_TOKEN__',
                    time() + UserToken::TTL,
                    UserToken::TTL
                );
                check('login', '_login', $out, 'data.userinfo', LOGIN_RUNTIME_FIELDS, 0, '登录成功');
            }
        } catch (\Throwable $e) {
            $results[] = 'login             异常：' . $e->getMessage();
            $failed++;
        }

        // ---- 1. getUserInfo ----
        try {
            $u = User::find(16797);
            if ($u === null) {
                $results[] = 'getUserInfo        [跳过]（本地无 user id=16797，请先跑 seed.php）';
                $failed++;
            } else {
                check('getUserInfo', 'user_getuserinfo', UserFormatter::userInfo($u), 'data', RUNTIME_FIELDS, 0, '个人信息');
            }
        } catch (\Throwable $e) {
            $results[] = 'getUserInfo        异常：' . $e->getMessage();
            $failed++;
        }
    }
}
