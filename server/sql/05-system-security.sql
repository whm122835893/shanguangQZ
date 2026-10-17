-- ============================================================
-- 05-system-security.sql  —— 系统与安全表
-- 这三张表不依赖原站字段（纯本地基础设施），字段由安全设计推导
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. audit_log —— 审计日志
--    要求：记录「谁/何时/对什么/做了什么/结果」
--    敏感字段（password/token/id_card/bank_card/cvv）在写入前
--    由 app/service/Audit.php 脱敏为 ***REDACTED***
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id`     int unsigned NOT NULL DEFAULT 0,
  `username`    varchar(64)  NOT NULL DEFAULT '',
  `ip`          varchar(64)  NOT NULL DEFAULT '',
  `route`       varchar(191) NOT NULL DEFAULT '' COMMENT '请求路由',
  `method`      varchar(16)  NOT NULL DEFAULT 'GET',
  `action`      varchar(64)  NOT NULL DEFAULT '' COMMENT '业务动作',
  `target_id`   varchar(64)  NOT NULL DEFAULT '' COMMENT '操作对象ID',
  `params`      text         COMMENT '已脱敏的请求参数 JSON',
  `result`      varchar(32)  NOT NULL DEFAULT '' COMMENT 'success/fail',
  `error_msg`   varchar(500) NOT NULL DEFAULT '',
  `duration_ms` int unsigned NOT NULL DEFAULT 0,
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`,`createtime`),
  KEY `idx_route_time` (`route`,`createtime`),
  KEY `idx_result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='审计日志';

-- ------------------------------------------------------------
-- 2. idempotent —— 幂等记录
--    用途：防止重复下单、重复支付、重复开盒
--    机制：唯一键 (scene, biz_key) 拦截重复提交
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `idempotent`;
CREATE TABLE `idempotent` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `scene`       varchar(64)  NOT NULL COMMENT '场景：create_order/pay/open_box',
  `biz_key`     varchar(128) NOT NULL COMMENT '业务幂等键（订单号/支付流水号）',
  `user_id`     int unsigned NOT NULL DEFAULT 0,
  `result_hash` char(32)     NOT NULL DEFAULT '' COMMENT '首次响应摘要，重复请求原样返回',
  `expiretime`  int unsigned NOT NULL DEFAULT 0 COMMENT '幂等窗口过期时间',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scene_key` (`scene`,`biz_key`),
  KEY `idx_expire` (`expiretime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='幂等记录';

-- ------------------------------------------------------------
-- 3. sms_code —— 短信验证码
--    安全要求：只存哈希不存明文；发送频率限制；验证次数限制
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sms_code`;
CREATE TABLE `sms_code` (
  `id`          bigint unsigned NOT NULL AUTO_INCREMENT,
  `mobile`      varchar(32)  NOT NULL,
  `scene`       varchar(32)  NOT NULL DEFAULT 'login' COMMENT 'login/register/reset_pay',
  `code_hash`   char(32)     NOT NULL COMMENT '验证码 MD5，禁止存明文',
  `ip`          varchar(64)  NOT NULL DEFAULT '',
  `send_count`  int unsigned NOT NULL DEFAULT 0 COMMENT '同一手机号当日发送次数',
  `verify_count` int unsigned NOT NULL DEFAULT 0 COMMENT '已尝试校验次数，防爆破',
  `is_used`     tinyint(1)   NOT NULL DEFAULT 0,
  `expiretime`  int unsigned NOT NULL DEFAULT 0,
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_mobile_scene` (`mobile`,`scene`),
  KEY `idx_expire` (`expiretime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='短信验证码';
