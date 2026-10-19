-- ============================================================
-- 13-admin.sql —— 管理端最小集：admin_user 表
--
-- 背景：本项目此前没有管理端，运营动作（banner/公告/藏品上下架）
--       只能直接改数据库。本迁移建立管理端账号体系的最小载体。
--
-- 范围界定（P0 最小集，RBAC/审批流/工单见 P2）：
--   - 单角色（超管）；token 单会话（新登录顶掉旧 token）
--   - 操作审计复用 audit_log（event='admin_*'）
-- 创建管理员：php tools/create-admin.php <username> <password>
-- 执行：mysql gandart < 13-admin.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `admin_user` (
  `id`              int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username`        varchar(32)      NOT NULL COMMENT '登录名',
  `password_hash`   varchar(255)     NOT NULL COMMENT 'Argon2id 哈希',
  `token_hash`      char(64)         NULL DEFAULT NULL COMMENT '当前会话 token 的 sha256（单会话，新登录顶掉旧值）',
  `token_expire`    int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'token 过期 unixtime',
  `last_login_time` int(10) unsigned NOT NULL DEFAULT 0,
  `last_login_ip`   varchar(45)      NOT NULL DEFAULT '' COMMENT 'IPv4/IPv6 最长 45',
  `status`          tinyint(1)       NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `createtime`      int(10) unsigned NOT NULL DEFAULT 0,
  `updatetime`      int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理端账号（单角色最小集，RBAC 见 P2）';

-- 自检：表存在且无默认弱口令账号（password_hash 列非空的行必须经 create-admin.php 创建）
-- SELECT COUNT(*) FROM admin_user;  -- 初始应为 0
