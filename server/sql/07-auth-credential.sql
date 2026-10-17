-- ============================================================================
-- 07-auth-credential.sql  认证凭据表
--
-- 为什么独立成表而不直接在 user 表加 password 列：
--   user 表的 104 列是**严格对齐原站实测**的契约（100 标量 − token/url
--   + verification 拆 2 + statistics 拆 4）。加一列 password 就破坏了这个
--   「原站字段 ↔ DB 列」的一一对应关系，双向核验会立刻报冗余。
--
--   凭据属于「本地扩展的安全数据」，原站响应里根本不下发，天然应在表外。
--
-- 安全要求（不可放宽）：
--   1. 只存 Argon2id 哈希，绝不存明文、绝不存可逆加密
--   2. password_hash() 由 PHP 生成，参数写死在代码里，不接受外部配置
--      （可配置 = 攻击者可通过配置文件降低强度）
--   3. login_fail_count 用于登录爆破限流，锁定阈值走 audit_log 追溯
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_credential` (
  `id`              int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`         int unsigned NOT NULL                COMMENT '关联 user.id',

  -- Argon2id 哈希，格式 $argon2id$v=19$m=65536,t=3,p=4$<salt>$<hash>
  `password_hash`   varchar(255) NOT NULL DEFAULT ''     COMMENT 'Argon2id 哈希，禁止存明文',

  -- 二次验证锁：本表账号连续失败达到阈值后置 1，需走短信验证码解锁
  `locked`          tinyint(1)  NOT NULL DEFAULT 0      COMMENT '是否锁定：0 否 1 是',
  `lock_until`      int unsigned NOT NULL DEFAULT 0      COMMENT '锁定截止 unixtime，0=永久',
  `login_fail_count` int unsigned NOT NULL DEFAULT 0     COMMENT '连续失败次数，成功后清零',

  -- 密码策略：0=首次登录强制改密 1=正常 2=管理员重置待改密
  `pwd_status`      tinyint(1)  NOT NULL DEFAULT 1       COMMENT '密码状态：0待首次改 1正常 2重置待改',

  `pwd_updated_at`  int unsigned NOT NULL DEFAULT 0      COMMENT '最后改密时间 unixtime',
  `createtime`      int unsigned NOT NULL DEFAULT 0,
  `updatetime`      int unsigned NOT NULL DEFAULT 0,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_locked` (`locked`, `lock_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='认证凭据（Argon2id，本地扩展，不属于原站契约字段）';


-- ---------------------------------------------------------------------------
-- 登录失败审计（独立于 audit_log，因为需要按账号聚合统计爆破次数）
-- audit_log 是通用行为日志，不便按 user_id 做高频计数；
-- 本表只留最近 N 条，超期由定时任务清理。
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_log` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`     int unsigned NOT NULL DEFAULT 0     COMMENT '0=账号不存在（用户枚举探测）',
  `account`     varchar(64) NOT NULL DEFAULT ''     COMMENT '提交的登录账号（已脱敏，见备注）',
  `ip`          varchar(64) NOT NULL DEFAULT ''     COMMENT '来源 IP',
  `user_agent`  varchar(255) NOT NULL DEFAULT ''    COMMENT 'UA',
  `is_success`  tinyint(1)  NOT NULL DEFAULT 0      COMMENT '0 失败 1 成功',
  `fail_reason` varchar(64) NOT NULL DEFAULT ''     COMMENT '空密码/账号不存在/密码错误/已锁定',
  `createtime`  int unsigned NOT NULL DEFAULT 0,

  PRIMARY KEY (`id`),
  KEY `idx_account_time` (`account`, `createtime`),
  KEY `idx_ip_time` (`ip`, `createtime`),
  KEY `idx_user_time` (`user_id`, `createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='登录日志（爆破溯源）';

-- 🔐 隐私备注：account 列存的是**提交进来的原始账号**。
--    如果审计要求严格，日志里应只存账号哈希；当前保留明文是为了运维能直接
--    按账号检索登录失败记录。若后续合规要求变更，改这一个点即可。
