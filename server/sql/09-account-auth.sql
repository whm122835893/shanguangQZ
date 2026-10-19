-- ============================================================================
-- 09-account-auth.sql  账号注册/改密/短信验证码
--
-- 背景：user/register、user/resetpwd、sms/send 三个端点原为 [MOCK]，
--   register 不落库、resetpwd 不改密、sms/send 直接把明文验证码
--   '123456' 写死在响应体里。任何一个真实用户都能看到别人的验证码。
--
-- 安全要求（不可放宽）：
--   1. 验证码只存哈希不存明文；且**不能用 MD5** ——
--      6 位数字码只有 10^6 种空间，MD5 无盐可被彩虹表秒破。
--      本表改用 HMAC-SHA256（密钥从 env 读），单空间爆破成本抬到不可行。
--   2. 验证码一次性消费（is_used），且带有效期（expiretime）。
--   3. 校验次数上限（verify_count），防爆破 6 位数字码。
--   4. 发送频率：同手机号 60 秒内只能发 1 次（send_interval），
--      单日累计上限（send_count_daily），防短信轰炸与费用失控。
--   5. 密码只存 Argon2id 哈希（复用 user_credential 表，见 07）。
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. sms_code —— 短信验证码（重构自 05-system-security.sql）
-- ---------------------------------------------------------------------------
-- 🔴 与 05 的差异（这是本迁移存在的唯一理由）：
--    05 用 code_hash char(32) 存 MD5，注释写「验证码 MD5，禁止存明文」。
--    「不存明文」是对的，但 MD5 选型是错的 —— 6 位数字码的 MD5 可以
--    离线穷举出全表，本迁移换成带密钥的 HMAC-SHA256。
DROP TABLE IF EXISTS `sms_code`;
CREATE TABLE `sms_code` (
  `id`             bigint unsigned NOT NULL AUTO_INCREMENT,

  `mobile`         varchar(32)  NOT NULL                COMMENT '手机号',
  `scene`          varchar(32)  NOT NULL                COMMENT '场景：register/resetpwd/login',

  -- HMAC-SHA256(mobile|scene|code, key) 的 hex，64 位
  -- 🔴 绝不能改回 MD5：6 位数字码空间仅 10^6，MD5 无盐可彩虹表还原
  `code_hash`      char(64)     NOT NULL                COMMENT '验证码 HMAC-SHA256，禁止存明文',

  `ip`             varchar(64)  NOT NULL DEFAULT ''     COMMENT '请求 IP，用于频率审计',

  -- 同一手机号累计发送次数（用于单日上限判定）
  `send_count`     int unsigned NOT NULL DEFAULT 0      COMMENT '同一手机号当日发送次数',

  -- 同一手机号校验失败次数（防爆破）
  `verify_count`   int unsigned NOT NULL DEFAULT 0      COMMENT '已尝试校验次数，防爆破',

  `is_used`        tinyint(1)   NOT NULL DEFAULT 0      COMMENT '是否已消费：0 否 1 是',

  `expiretime`     int unsigned NOT NULL DEFAULT 0      COMMENT '过期时间 unixtime',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `updatetime`     int unsigned NOT NULL DEFAULT 0,

  PRIMARY KEY (`id`),
  -- 校验时按 (mobile, scene) 取最新一条未消费的记录
  KEY `idx_mobile_scene` (`mobile`,`scene`,`is_used`),
  KEY `idx_expire` (`expiretime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信验证码（HMAC 存储）';

-- ---------------------------------------------------------------------------
-- 2. sms_send_log —— 短信发送审计
--
-- 为什么单独建表而不复用 audit_log：
--   audit_log 是通用业务审计（who did what），
--   短信发送需要按「手机号 + 日期」聚合统计当日发送量来判单日上限，
--   需要专门的计数索引。同时保留原始响应码便于排查通道故障。
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sms_send_log` (
  `id`             bigint unsigned NOT NULL AUTO_INCREMENT,
  `mobile`         varchar(32)  NOT NULL                COMMENT '手机号',
  `scene`          varchar(32)  NOT NULL                COMMENT '场景',
  `ip`             varchar(64)  NOT NULL DEFAULT ''     COMMENT '请求 IP',
  `channel`        varchar(32)  NOT NULL DEFAULT ''     COMMENT '实际使用的短信通道标识',
  `success`        tinyint(1)   NOT NULL DEFAULT 0      COMMENT '是否发送成功',
  `error`          varchar(255) NOT NULL DEFAULT ''     COMMENT '失败原因',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_day` (`mobile`,`createtime`),
  KEY `idx_day` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信发送审计';

-- ---------------------------------------------------------------------------
-- 3. user 表补充：注册需要的两个字段
-- ---------------------------------------------------------------------------
-- 🔴 为什么不加到 user 表：user 表的 104 列严格对齐原站实测字段
--    （见 01-schema.sql 头注释），加列会破坏「原站字段 ↔ DB 列」一一对应。
--    但这两个字段是**本地扩展的安全数据**，与 user_credential 同性质。
--
--    register_ip   —— 注册来源 IP，风控与审计用
--    pwd_set_at    —— 首次设置密码时间，与 user_credential.pwd_updated_at 区分
ALTER TABLE `user`
  ADD COLUMN `register_ip`  varchar(64) NOT NULL DEFAULT '' COMMENT '注册 IP（本地扩展）',
  ADD COLUMN `pwd_set_at`   int unsigned NOT NULL DEFAULT 0  COMMENT '密码设置时间（本地扩展）';
