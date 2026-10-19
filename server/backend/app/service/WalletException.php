<?php
declare(strict_types=1);

namespace app\service;

/**
 * 钱包领域异常
 *
 * 语义：凡由 WalletService 抛出的业务拒绝（金额非法 / 余额不足 /
 * 幂等键冲突 / 用户不存在）统一用本类，调用方据此转用户可读文案；
 * 基础设施错误（PDO 断连等）原样上抛，不得吞并成业务错误。
 */
class WalletException extends \RuntimeException
{
}
