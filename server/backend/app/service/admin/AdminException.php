<?php
declare(strict_types=1);

namespace app\service\admin;

/**
 * AdminException —— 管理端 v1 领域异常
 *
 * HTTP 状态码与业务 code 一一对应（Resp::fail 渲染）：
 *   400 参数/校验失败、401 未登录、403 无权限/锁定、409 冲突
 *
 * @package app\service\admin
 */
class AdminException extends \RuntimeException
{
    public function __construct(string $message, int $httpCode = Resp::BAD_REQUEST)
    {
        parent::__construct($message, $httpCode);
    }

    public function httpCode(): int
    {
        return $this->getCode();
    }
}
