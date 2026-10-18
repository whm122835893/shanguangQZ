<?php
declare(strict_types=1);

namespace app\controller\api;

use app\service\MockData;
use app\common\Request;
use app\common\Response;

/**
 * 账号域控制器 —— 注册 / 找回密码
 *
 * 🔴 为什么从 TradeController 拆出来：
 *    这两个端点挂在 user/ 路径下（user/register、user/resetpwd），
 *    属于账号生命周期，与交易域无关。v2.3 大拆分时被顺手留在了
 *    TradeController，导致该文件涨到 354 行触发 ≤300 行 P0 红线。
 *    按域归位的同时解决行数问题。
 *
 * ⚠️ 两端点当前为【MOCK】：register 不真正落库（返回假 userinfo），
 *    resetPwd 不真正改密。原站成功样本未抓到（需真实短信验证码），
 *    抓到后按样本切换 DB 驱动。切 DB 时禁止凭端点名猜字段。
 *
 * @package app\controller\api
 */
class AccountController
{
    public function register()
    {
        $mobile   = Request::str('mobile');
        $password = Request::str('password');
        $code     = Request::str('code');

        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return Response::fail('手机号格式错误');
        }
        if (strlen($password) < 6) {
            return Response::fail('密码至少 6 位');
        }
        if ($code !== '123456') {
            return Response::fail('验证码错误');
        }
        return Response::ok(['userinfo' => MockData::loginResult()]);
    }

    public function resetPwd()
    {
        $mobile   = Request::str('mobile');
        $code     = Request::str('code');
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return Response::fail('手机号格式错误');
        }
        if ($code !== '123456') {
            return Response::fail('验证码错误');
        }
        return Response::ok(['mobile' => $mobile]);
    }
}
