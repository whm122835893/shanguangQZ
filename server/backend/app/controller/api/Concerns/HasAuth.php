<?php
declare(strict_types=1);

namespace app\controller\api\Concerns;

use app\common\Request;
use app\common\Response;
use app\model\User;
use app\model\UserToken;
use app\service\Audit;

trait HasAuth
{
    private function currentUser(): array
    {
        $token = Request::token();
        if ($token === '') {
            return [null, Response::unauthorized()];
        }

        $user = UserToken::verify($token);
        if ($user === null) {
            // 🔐 token 无效/过期/账号被禁 —— 一律 401，不区分原因。
            //    区分「token 过期」与「token 伪造」会给攻击者反馈信号。
            Audit::write('auth_verify', 0, ['result' => 'invalid_token'], false);
            return [null, Response::unauthorized('登录已失效，请重新登录')];
        }

        return [$user, null];
    }
    // ==================== 首页 / 公告 ====================

    /**
     * POST /index/afficheList 公告列表【DB】
     *
     * 原站实测契约：data 为分页信封 {total,per_page,current_page,last_page,data}，
     * 列表项 6 字段 {id,image,title,createtime,type,url,affiche_type}，不含 content。
     * ⚠️ type 是字符串 "1"~"5"，前端做 === '1' 判断。
     */
}
