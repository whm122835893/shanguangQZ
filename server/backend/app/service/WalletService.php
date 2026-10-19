<?php
declare(strict_types=1);

namespace app\service;

use think\db\exception\PDOException;
use think\facade\Db;

/**
 * WalletService —— 钱包资金唯一入口
 *
 * 🔴 为什么必须有这个类：
 *   此前 user.money 是裸字段，全代码库没有任何一处会更新它（支付走
 *   外部网关 + NullGateway fail-closed），也就没有任何流水可言。
 *   一旦后续接入真实支付/消费，绕开本类直接 UPDATE money 的写法
 *   会立刻造成「有余额没流水」的账实分裂——恒等式校验将永久红灯。
 *
 * 四条红线（tools/contract/wallet_test.php 逐条锁定）：
 *   1. 每笔变动必落 wallet_transaction 流水（含 before/after 快照）
 *   2. 借记余额不足拒绝（fail-closed），且零副作用
 *   3. 同一 (biz_type, biz_id) 幂等，重复入账拒绝（防回调重放双入账）
 *   4. 恒等式 money == SUM(收入-支出) 可随时校验，篡改必被发现
 *
 * 金额口径：全部走 bcmath、两位小数字符串（"10.50"），禁止 float 参与运算。
 * 并发口径：事务内 SELECT ... FOR UPDATE 锁用户行，同用户动账串行化。
 */
final class WalletService
{
    public const DIRECTION_CREDIT = 1;
    public const DIRECTION_DEBIT  = 2;

    /** 贷记（收入）。$amount 两位小数字符串，> 0。返回流水 id */
    public static function credit(int $userId, string $amount, string $bizType, string $bizId = '', string $remark = ''): int
    {
        return self::move($userId, $amount, self::DIRECTION_CREDIT, $bizType, $bizId, $remark);
    }

    /** 借记（支出）。余额不足抛 WalletException，不落任何行 */
    public static function debit(int $userId, string $amount, string $bizType, string $bizId = '', string $remark = ''): int
    {
        return self::move($userId, $amount, self::DIRECTION_DEBIT, $bizType, $bizId, $remark);
    }

    /**
     * 资金恒等式校验：user.money 与流水合计是否一致
     *
     * 返回 ['ok' => bool, 'balance' => 余额, 'ledger' => 流水合计]
     */
    public static function verifyInvariant(int $userId): array
    {
        $balance = (string) Db::name('user')->where('id', $userId)->value('money');
        $sum = Db::query(
            'SELECT COALESCE(SUM(CASE direction WHEN 1 THEN amount ELSE -amount END), 0) AS s'
            . ' FROM wallet_transaction WHERE user_id = ?',
            [$userId]
        );
        $ledger = (string) ($sum[0]['s'] ?? '0');

        return [
            'ok'      => bccomp($balance, $ledger, 2) === 0,
            'balance' => $balance,
            'ledger'  => $ledger,
        ];
    }

    /** 动账唯一实现：事务 + 行锁 + 快照 + 流水，任何一步失败整体回滚 */
    private static function move(int $userId, string $amount, int $direction, string $bizType, string $bizId, string $remark): int
    {
        $amount = self::normalizeAmount($amount);
        if ($bizType === '') {
            throw new WalletException('业务类型不能为空');
        }

        try {
            return Db::transaction(static function () use ($userId, $amount, $direction, $bizType, $bizId, $remark) {
                // 行锁读余额：同用户并发动账在此串行化
                $rows = Db::query('SELECT money FROM `user` WHERE id = ? FOR UPDATE', [$userId]);
                if (!isset($rows[0]['money'])) {
                    throw new WalletException('用户不存在');
                }
                $before = (string) $rows[0]['money'];
                $after  = $direction === self::DIRECTION_CREDIT
                    ? bcadd($before, $amount, 2)
                    : bcsub($before, $amount, 2);

                if ($direction === self::DIRECTION_DEBIT && bccomp($after, '0', 2) < 0) {
                    // fail-closed：拒绝即回滚，不落行不改余额
                    throw new WalletException('余额不足');
                }

                Db::name('user')->where('id', $userId)->update(['money' => $after]);

                return (int) Db::name('wallet_transaction')->insertGetId([
                    'user_id'        => $userId,
                    'direction'      => $direction,
                    'amount'         => $amount,
                    'balance_before' => $before,
                    'balance_after'  => $after,
                    'biz_type'       => $bizType,
                    'biz_id'         => $bizId,
                    'remark'         => $remark,
                    'createtime'     => time(),
                ]);
            });
        } catch (PDOException $e) {
            // 幂等键冲突（uniq_biz_uniq 1062）转领域异常；其余基础设施错误原样上抛
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new WalletException('同一业务键已入账，拒绝重复动账');
            }
            throw $e;
        }
    }

    /** 金额入参防线：仅接受正数、至多两位小数；返回归一化两位小数字符串 */
    private static function normalizeAmount(string $amount): string
    {
        $amount = trim($amount);
        if ($amount === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new WalletException('金额格式非法');
        }
        $normalized = bcadd($amount, '0', 2);
        if (bccomp($normalized, '0', 2) <= 0) {
            throw new WalletException('金额必须大于 0');
        }

        return $normalized;
    }
}
