<?php
declare(strict_types=1);

namespace app\service;

/**
 * 抽奖域异常 —— 与其他业务域异常分离，控制器统一转 code:0
 *
 * @package app\service
 */
final class LotteryException extends \RuntimeException
{
}
