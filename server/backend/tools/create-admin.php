<?php
declare(strict_types=1);

/**
 * 创建管理端账号 —— 唯一入口
 *
 * 设计红线：库内不允许出现默认弱口令账号，初始 admin_user 为空表，
 * 第一个管理员必须由本脚本显式创建（密码 Argon2id 哈希后入库）。
 *
 * 用法：
 *   php tools/create-admin.php <username> <password>
 *   密码强度：>= 10 位且同时含字母与数字，否则拒绝。
 */

require __DIR__ . '/../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use think\facade\Db;

if ($argc < 3) {
    fwrite(STDERR, "用法: php tools/create-admin.php <username> <password>\n");
    exit(1);
}

[, $username, $password] = $argv;

$username = trim($username);
if (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $username)) {
    fwrite(STDERR, "用户名限 3-32 位字母/数字/下划线\n");
    exit(1);
}
if (strlen($password) < 10 || !preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password)) {
    fwrite(STDERR, "密码至少 10 位且同时含字母与数字（防默认弱口令）\n");
    exit(1);
}

$exists = Db::name('admin_user')->where('username', $username)->find();
if ($exists !== null) {
    fwrite(STDERR, "用户名已存在: {$username}\n");
    exit(1);
}

Db::name('admin_user')->insert([
    'username'      => $username,
    'password_hash' => password_hash($password, PASSWORD_ARGON2ID),
    'status'        => 1,
    'createtime'    => time(),
    'updatetime'    => time(),
]);

echo "管理员已创建: {$username}\n";
