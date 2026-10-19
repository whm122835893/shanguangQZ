<?php
declare(strict_types=1);

namespace app\service;

/**
 * 管理端领域异常（登录拒绝 / token 无效等统一走本类）
 */
class AdminAuthException extends \RuntimeException
{
}
