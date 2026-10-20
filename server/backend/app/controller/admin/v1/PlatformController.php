<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * PlatformController —— 平台运维（契约 §8.19，4 个接口）
 *
 * 一键清库（206）：四重安全确认（契约 §3.8）
 *   1. confirmation_text === '确认清空用户数据'（错 → 400）
 *   2. 管理员密码（错 → 400）
 *   3. 短信验证码（非空校验）
 *   4. 审计落库
 * 流程：全量 JSON 备份（runtime/backups/）→ platform_backup 落行 →
 *       逐表 TRUNCATE（用户业务数据，不含 admin/审计/平台表）→
 *       platform_cleanup_log 落行 → 审计。
 *
 * 恢复备份（209）：密码验证 → 读 JSON → 逐表 TRUNCATE + 分批回灌 → status→2。
 *
 * 🔴 清库不可回滚（TRUNCATE 是 DDL），仅备份可逆；全量回归最后执行。
 *
 * @package app\controller\admin\v1
 */
final class PlatformController
{
    use V1;

    /** 清库确认文本（契约 §3.8） */
    private const CONFIRM_TEXT = '确认清空用户数据';

    /** 清库范围：C 端用户业务表（不含 admin_*、audit_log、site_config、platform_*、风控审批） */
    private const CLEANUP_TABLES = [
        'user', 'user_credential', 'user_collection', 'user_collection_record',
        'user_address', 'user_payment_account', 'user_token',
        'artel_order', 'artel_order_item', 'recharge_order', 'vip_order', 'beg_buy_order',
        'wallet_transaction', 'transfer_record', 'market_goods',
        'support_ticket', 'ticket_reply', 'feedback',
        'blind_box_open_record', 'lottery_record', 'synthesis_record',
        'check_in_record', 'invite_record', 'airdrop_record',
        'blacklist', 'tx_lock', 'sms_code', 'login_log', 'content_audit',
    ];

    // ========================================================
    // 8.19.1 一键清空用户数据（206）
    // ========================================================

    /** POST /platform/cleanup */
    public function cleanup()
    {
        $admin = self::guard('platform:cleanup');

        // ① 确认文本
        if (self::str('confirmation_text') !== self::CONFIRM_TEXT) {
            throw new AdminException('确认文本错误，请输入「' . self::CONFIRM_TEXT . '」');
        }
        // ② 管理员密码
        self::verifyPassword($admin);
        // ③ 短信验证码（无短信通道时留空即拒）
        if (self::str('sms_code') === '') {
            throw new AdminException('请输入短信验证码');
        }
        // ④ 原因
        $reason = self::str('reason');
        if ($reason === '') {
            throw new AdminException('缺少必填参数: reason');
        }

        $startedAt = time();
        $usersBefore = (int) Db::name('user')->count();
        $ordersBefore = (int) Db::name('artel_order')->count()
            + (int) Db::name('recharge_order')->count();

        // 备份 → 落 platform_backup
        $backupPath = '';
        try {
            $backupPath = $this->dumpBackup((int) $admin['id'], (string) $admin['username']);
        } catch (\Throwable $e) {
            $this->logCleanup($admin, $reason, '', $usersBefore, $ordersBefore, $startedAt, 2, $e->getMessage());
            throw new AdminException('清库失败：备份生成异常 ' . $e->getMessage());
        }

        // 逐表清空（TRUNCATE 为 DDL 不可回滚，失败即中断）
        // ⚠️ 业务表间存在外键（如 recharge_order→user），全量清库先关 FK_CHECKS，结束恢复
        $error = '';
        Db::execute('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (self::CLEANUP_TABLES as $table) {
                try {
                    Db::execute("TRUNCATE TABLE `{$table}`");
                } catch (\Throwable $e) {
                    $error = '清空 ' . $table . ' 失败：' . $e->getMessage();
                    break;
                }
            }
        } finally {
            Db::execute('SET FOREIGN_KEY_CHECKS = 1');
        }

        $this->logCleanup($admin, $reason, $backupPath, $usersBefore, $ordersBefore,
            $startedAt, $error === '' ? 1 : 2, $error);
        if ($error !== '') {
            throw new AdminException('清库失败：' . $error);
        }

        Guard::audit('platform_cleanup', (int) $admin['id'], 0, [
            'reason' => $reason, 'affected_users' => $usersBefore, 'backup_path' => $backupPath,
        ]);
        return Resp::ok([
            'success'         => true,
            'backup_path'     => $backupPath,
            'affected_users'  => $usersBefore,
            'affected_orders' => $ordersBefore,
        ]);
    }

    // ========================================================
    // 8.19.2 清库记录（207）
    // ========================================================

    /** GET /platform/cleanup-logs */
    public function cleanupLogs()
    {
        self::guard('platform:log');
        [$page, $size] = self::paging();
        $q = Db::name('platform_cleanup_log')->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'              => (int) $r['id'],
            'admin_id'        => (int) $r['admin_id'],
            'admin_name'      => (string) $r['admin_name'],
            'admin_phone'     => self::maskPhone((string) $r['admin_phone']),
            'ip_address'      => (string) $r['ip_address'],
            'reason'          => (string) $r['reason'],
            'backup_path'     => (string) $r['backup_path'],
            'affected_users'  => (int) $r['affected_users'],
            'affected_orders' => (int) $r['affected_orders'],
            'execution_time'  => (int) $r['execution_time'],
            'status'          => (int) $r['status'],
            'error_message'   => (string) ($r['error_message'] ?? ''),
            'time'            => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.19.3 备份列表（208）
    // ========================================================

    /** GET /platform/backups */
    public function backups()
    {
        self::guard('platform:backup');
        [$page, $size] = self::paging();
        $q = Db::name('platform_backup')->order('id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'          => (int) $r['id'],
            'name'        => (string) $r['name'],
            'file_path'   => (string) $r['file_path'],
            'file_size'   => (int) $r['file_size'],
            'type'        => (string) $r['type'],
            'table_list'  => json_decode((string) ($r['table_list'] ?? '[]'), true) ?: [],
            'admin_id'    => (int) $r['admin_id'],
            'admin_name'  => (string) $r['admin_name'],
            'status'      => (int) $r['status'],
            'restored_at' => self::iso((int) $r['restored_at']),
            'time'        => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    // ========================================================
    // 8.19.4 恢复备份（209）—— 高风险
    // ========================================================

    /** POST /platform/backups/:id/restore */
    public function restore()
    {
        $admin = self::guard('platform:backup');
        self::verifyPassword($admin);
        $id = self::int('id');
        $backup = Db::name('platform_backup')->where('id', $id)->find();
        if ($backup === null) {
            throw new AdminException('备份不存在', Resp::NOT_FOUND);
        }
        if ((int) $backup['status'] === 2) {
            throw new AdminException('该备份已恢复过，不可重复恢复', Resp::CONFLICT);
        }
        $file = rtrim(runtime_path(), '/\\') . ltrim((string) $backup['file_path'], '/\\');
        if (!is_file($file)) {
            throw new AdminException('备份文件缺失，无法恢复', Resp::NOT_FOUND);
        }
        $payload = json_decode((string) file_get_contents($file), true);
        if (!is_array($payload) || !isset($payload['tables']) || !is_array($payload['tables'])) {
            throw new AdminException('备份文件损坏，无法恢复');
        }

        // 回灌：user 等父表行先插避免外键序；全表恢复关 FK_CHECKS 最稳
        Db::execute('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach ($payload['tables'] as $table => $rows) {
                if (!in_array($table, self::CLEANUP_TABLES, true) || !is_array($rows) || $rows === []) {
                    continue;
                }
                // 剔除生成列（如 wallet_transaction.biz_uniq）：显式插入生成列值会被 MySQL 拒绝
                $genCols = [];
                foreach (Db::query("SHOW COLUMNS FROM `{$table}`") as $col) {
                    if (stripos((string) $col['Extra'], 'GENERATED') !== false) {
                        $genCols[] = (string) $col['Field'];
                    }
                }
                if ($genCols !== []) {
                    $rows = array_map(static function (array $row) use ($genCols): array {
                        foreach ($genCols as $g) {
                            unset($row[$g]);
                        }
                        return $row;
                    }, $rows);
                }
                Db::execute("TRUNCATE TABLE `{$table}`");
                foreach (array_chunk($rows, 500) as $chunk) {
                    Db::name($table)->insertAll($chunk);
                }
            }
        } finally {
            Db::execute('SET FOREIGN_KEY_CHECKS = 1');
        }

        Db::name('platform_backup')->where('id', $id)->update([
            'status' => 2, 'restored_at' => time(), 'restored_by' => (int) $admin['id'],
        ]);
        Guard::audit('platform_restore', (int) $admin['id'], $id, ['backup' => (string) $backup['name']]);
        return Resp::ok(['success' => true]);
    }

    // ========================================================

    /**
     * 全量导出清库范围表 → runtime/backups/cleanup_*.json，落 platform_backup
     *
     * @return string 备份文件相对路径
     */
    private function dumpBackup(int $adminId, string $adminName): string
    {
        $tables = [];
        foreach (self::CLEANUP_TABLES as $table) {
            $tables[$table] = Db::name($table)->limit(100000)->select()->toArray();
        }
        $dir = rtrim(runtime_path(), '/\\') . 'backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $relPath = 'backups/cleanup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.json';
        $json = json_encode([
            'generated_at' => time(),
            'admin_id'     => $adminId,
            'admin_name'   => $adminName,
            'tables'       => $tables,
        ], JSON_UNESCAPED_UNICODE);
        file_put_contents($dir . '/' . basename($relPath), $json);

        Db::name('platform_backup')->insert([
            'name'       => '清库自动备份 ' . date('Y-m-d H:i:s'),
            'file_path'  => $relPath,
            'file_size'  => strlen((string) $json),
            'type'       => 'cleanup',
            'table_list' => json_encode(array_keys($tables)),
            'admin_id'   => $adminId,
            'admin_name' => $adminName,
            'status'     => 1,
            'createtime' => time(),
        ]);
        return $relPath;
    }

    /** 清库日志落库 */
    private function logCleanup(array $admin, string $reason, string $backupPath,
        int $users, int $orders, int $startedAt, int $status, string $error): void
    {
        Db::name('platform_cleanup_log')->insert([
            'admin_id'        => (int) $admin['id'],
            'admin_name'      => (string) $admin['username'],
            'admin_phone'     => (string) ($admin['phone'] ?? ''),
            'ip_address'      => request()->ip(),
            'reason'          => mb_substr($reason, 0, 250),
            'backup_path'     => $backupPath,
            'affected_users'  => $users,
            'affected_orders' => $orders,
            'execution_time'  => max(1, time() - $startedAt),
            'status'          => $status,
            'error_message'   => $error !== '' ? $error : null,
            'createtime'      => time(),
        ]);
    }
}
