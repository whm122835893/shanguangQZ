<?php
declare(strict_types=1);

namespace app\service;

/**
 * 实名领域异常（金额/证件/姓名等业务拒绝统一走本类）
 */
class RealnameException extends \RuntimeException
{
}
