-- ============================================================
-- 16-admin-v1-extra.sql —— 司南后台 v1 补充迁移
--
-- 覆盖 §8.12 钱包（fee 列）、§8.13 CMS（协议/文物展览）、
-- §8.9 市场手续费配置、§8.11 营销配置、§8.14 系统配置 的缺口。
-- 幂等：可重复执行。
-- ============================================================

-- ------------------------------------------------------------
-- 一、artel_order 补手续费列（§8.12 fee-stats / §8.9 fee-config 的落库点）
-- ------------------------------------------------------------
ALTER TABLE `artel_order`
  ADD COLUMN IF NOT EXISTS `fee` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '平台手续费';

-- ------------------------------------------------------------
-- 二、CMS：协议 / 文物展览（§8.13）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cms_agreement` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `type`       varchar(32)  NOT NULL COMMENT 'user/service 协议 privacy 隐私 policy 规则',
  `title`      varchar(128) NOT NULL,
  `content`    longtext,
  `version`    varchar(32)  NOT NULL DEFAULT '',
  `status`     tinyint      NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `createtime` int unsigned NOT NULL DEFAULT 0,
  `updatetime` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='协议文本（每类型一份）';

CREATE TABLE IF NOT EXISTS `cms_artifact` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `name`        varchar(128) NOT NULL COMMENT '文物名称',
  `dynasty`     varchar(64)  NOT NULL DEFAULT '' COMMENT '朝代',
  `material`    varchar(64)  NOT NULL DEFAULT '' COMMENT '材质',
  `image`       varchar(500) NOT NULL DEFAULT '',
  `description` text,
  `sort`        int unsigned NOT NULL DEFAULT 0,
  `status`      tinyint      NOT NULL DEFAULT 1 COMMENT '1展示 0隐藏',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  `updatetime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文物展览';

-- 公告（§8.13 139~143）：管理端 CRUD；C 端只读 status=1 且已到发布时间
CREATE TABLE IF NOT EXISTS `cms_announcement` (
  `id`           int unsigned NOT NULL AUTO_INCREMENT,
  `title`        varchar(128) NOT NULL COMMENT '标题',
  `content`      longtext,
  `type`         tinyint      NOT NULL DEFAULT 1 COMMENT '类型 1=系统 2=活动 3=维护',
  `is_top`       tinyint(1)   NOT NULL DEFAULT 0 COMMENT '置顶 0/1',
  `status`       tinyint      NOT NULL DEFAULT 0 COMMENT '0=草稿 1=已发布 2=已下线',
  `publish_time` int unsigned NOT NULL DEFAULT 0 COMMENT '发布时间 unixtime，0=创建即发布',
  `createtime`   int unsigned NOT NULL DEFAULT 0,
  `updatetime`   int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status_top` (`status`, `is_top`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站内公告';

-- Banner 展示时间窗（§8.13 135/137：show_time_start/end 可选）
ALTER TABLE `banner`
  ADD COLUMN IF NOT EXISTS `show_time_start` int unsigned NOT NULL DEFAULT 0 COMMENT '展示开始 unixtime，0=不限',
  ADD COLUMN IF NOT EXISTS `show_time_end`   int unsigned NOT NULL DEFAULT 0 COMMENT '展示结束 unixtime，0=不限';

-- ------------------------------------------------------------
-- 三、site_config 种子（KV 配置，group_name 区分模块）
--    JSON value；管理端 GET 直接解析，PUT 覆盖写
-- ------------------------------------------------------------
INSERT IGNORE INTO `site_config` (`key`, `value`, `group_name`, `remark`, `updatetime`) VALUES
  ('market.fee_config', '{"fee_rate":"5","fee_min":"1.00","fee_max":"500.00"}', 'market', '寄售手续费率%/最低/最高', UNIX_TIMESTAMP()),
  ('system.payment',    '{"alipay":{"app_id":"","merchant_private_key":"","is_enabled":0},"wechat":{"mch_id":"","app_id":"","api_v3_key":"","is_enabled":0}}', 'system', '支付渠道配置', UNIX_TIMESTAMP()),
  ('system.security',   '{"tx_password_max_fail":5,"tx_password_lock_minutes":15,"rate_limit_qps":50,"rate_limit_ip":30,"rate_limit_user":10}', 'system', '安全策略', UNIX_TIMESTAMP()),
  ('system.cors',       '{"allowed_origins":[],"api_whitelist":[]}', 'system', 'CORS/域名白名单', UNIX_TIMESTAMP()),
  ('system.sms_oss',    '{"sms":{"driver":"aliyun","is_enabled":0},"oss":{"driver":"local","is_enabled":1}}', 'system', '短信/OSS', UNIX_TIMESTAMP()),
  ('system.global',     '{"site_name":"司南数藏","contact_phone":"","min_recharge":"1.00","order_timeout_minutes":15,"max_upload_size":10}', 'system', '全局参数', UNIX_TIMESTAMP()),
  ('cms.decoration',    '{"theme_color":"#C8102E","button_color":"#D4AF37","background_gradient":"linear-gradient(180deg,#1A0A0D 0%,#2B0F14 100%)","site_name":"司南数藏","site_logo":"","sections":[]}', 'cms', 'C 端装修配置', UNIX_TIMESTAMP()),
  ('marketing.checkin', '{"is_enabled":1,"rewards":[{"day":1,"amount":"0.50"},{"day":2,"amount":"0.50"},{"day":3,"amount":"1.00"},{"day":7,"amount":"3.00"}]}', 'marketing', '签到奖励配置', UNIX_TIMESTAMP()),
  ('marketing.invite',  '{"is_enabled":1,"tiers":[{"level":1,"reward":"2.00"},{"level":2,"reward":"1.00"}],"register_reward":"1.00"}', 'marketing', '邀请奖励配置', UNIX_TIMESTAMP()),
  ('marketing.register', '{"is_enabled":1,"reward_type":"balance","reward_config":{"amount":"1.00"}}', 'marketing', '注册福利', UNIX_TIMESTAMP());

-- ------------------------------------------------------------
-- 四、盲盒开启记录（§3.6.2 / §8.6.65）
--     C 端开启链路的写入目标（每开 1 盒记 1 行：盲盒ID/用户/命中子藏品/发放状态/时间）；
--     管理端 open-records 与库存审计(opened_count) 只读本表。
--     本平台「盲盒开启即发放子藏品、无未开启持有载体」，故无 user_blindbox 表。
-- ------------------------------------------------------------
ALTER TABLE `blind_box`
  ADD COLUMN IF NOT EXISTS `updatetime` int unsigned NOT NULL DEFAULT 0 COMMENT '更新时间';

CREATE TABLE IF NOT EXISTS `blind_box_open_record` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `blind_box_id`   int unsigned NOT NULL,
  `user_id`        int unsigned NOT NULL,
  `order_id`       int unsigned NOT NULL DEFAULT 0 COMMENT '来源盲盒订单，0=非订单来源',
  `item_id`        int unsigned NOT NULL DEFAULT 0 COMMENT '命中的 blind_box_item.id，0=空奖',
  `collectible_id` int unsigned NOT NULL DEFAULT 0 COMMENT '命中子藏品，0=空奖',
  `quantity`       int unsigned NOT NULL DEFAULT 1 COMMENT '发放数量',
  `status`         tinyint NOT NULL DEFAULT 0 COMMENT '0=处理中 1=已发放 2=发放失败(降级空奖)',
  `remark`         varchar(255) NOT NULL DEFAULT '' COMMENT '失败原因/备注',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_box` (`blind_box_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盲盒开启记录';

-- ------------------------------------------------------------
-- 五、market_goods 系统下架扩展（§8.9 delist 审计留痕）
--     真实挂单载体为 market_goods（C 端 TradeController/OrderService 在用）；
--     consignment 为旧版遗留表，管理端 v1 不使用。
-- ------------------------------------------------------------
ALTER TABLE `market_goods`
  ADD COLUMN IF NOT EXISTS `is_system_delisted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否被系统强制下架 0/1',
  ADD COLUMN IF NOT EXISTS `delist_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '强制下架原因';

-- transfer_record.status 扩展：4=已强制回退（§8.10 cancel 已完成转赠的回退终态）
ALTER TABLE `transfer_record`
  MODIFY COLUMN `status` tinyint NOT NULL DEFAULT 0 COMMENT '0=待接收 1=已完成 2=已拒绝 3=已撤销 4=已强制回退';

-- ------------------------------------------------------------
-- 六、营销活动模块（§8.11）
--     1. lottery 增加抽奖次数兑换配置（§8.11.4 create/edit 入参 exchange_config）
--     2. synthesis_plan 补目标藏品/每人限合成列（§8.11.5 create 入参）
--     3. synthesis_record 合成记录表（C 端合成链路的写入目标，管理端只读；
--        同 blind_box_open_record 先例：先建载体，C 端接入时逐笔落库）
-- ------------------------------------------------------------
ALTER TABLE `lottery`
  ADD COLUMN IF NOT EXISTS `exchange_config` json DEFAULT NULL COMMENT '抽奖次数兑换配置 JSON';

ALTER TABLE `synthesis_plan`
  ADD COLUMN IF NOT EXISTS `target_collectible_id` int unsigned NOT NULL DEFAULT 0 COMMENT '合成目标藏品',
  ADD COLUMN IF NOT EXISTS `per_user_limit` int unsigned NOT NULL DEFAULT 0 COMMENT '每人限合成次数，0=不限';

CREATE TABLE IF NOT EXISTS `synthesis_record` (
  `id`                   int unsigned NOT NULL AUTO_INCREMENT,
  `plan_id`              int unsigned NOT NULL COMMENT 'synthesis_plan.id',
  `user_id`              int unsigned NOT NULL,
  `materials`            json DEFAULT NULL COMMENT '消耗材料快照',
  `target_collectible_id` int unsigned NOT NULL DEFAULT 0,
  `uc_id`                int unsigned NOT NULL DEFAULT 0 COMMENT '产出 user_collection.id',
  `status`               tinyint NOT NULL DEFAULT 1 COMMENT '1=成功 0=失败',
  `fail_reason`          varchar(255) NOT NULL DEFAULT '',
  `createtime`           int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_plan` (`plan_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合成记录';

-- ------------------------------------------------------------
-- 七、交易密码锁定记录（§8.16 186~187）
--     C 端交易密码连续错误 ≥ system.security.tx_password_max_fail 时落一行
--     （locked_until = now + tx_password_lock_minutes*60）；管理端只读 + 手动解锁。
--     解锁语义：status→0、locked_until→0，审计走 audit_log(security_txlock_unlock)。
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tx_lock` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`       int unsigned NOT NULL,
  `fail_count`    int          NOT NULL DEFAULT 0 COMMENT '触发锁定时的连续错误次数',
  `locked_until`  int unsigned NOT NULL DEFAULT 0 COMMENT '解锁时间 unixtime',
  `status`        tinyint      NOT NULL DEFAULT 1 COMMENT '1=锁定中 0=已解锁（含手动/到期）',
  `unlock_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '手动解锁原因（管理端写入）',
  `unlocked_by`   int unsigned NOT NULL DEFAULT 0 COMMENT '手动解锁管理员，0=系统到期',
  `createtime`    int unsigned NOT NULL DEFAULT 0,
  `updatetime`    int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='交易密码锁定记录';

-- security_event 处理留痕（§8.16 185：PUT /security/events/:id）
ALTER TABLE `security_event`
  ADD COLUMN IF NOT EXISTS `handler_id` int unsigned DEFAULT NULL COMMENT '处理管理员',
  ADD COLUMN IF NOT EXISTS `handler_name` varchar(50) DEFAULT NULL COMMENT '处理管理员名',
  ADD COLUMN IF NOT EXISTS `handle_comment` varchar(255) DEFAULT NULL COMMENT '处理备注',
  ADD COLUMN IF NOT EXISTS `handled_at` int unsigned NOT NULL DEFAULT 0 COMMENT '处理时间 unixtime';

-- ------------------------------------------------------------
-- 八、平台备份（§8.19 208~209）
--     cleanup 执行前自动生成 JSON 备份文件（runtime/backups/）并落一行；
--     restore 读文件回灌（INSERT IGNORE），status→2 已恢复。
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `platform_backup` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `name`        varchar(128) NOT NULL COMMENT '备份名',
  `file_path`   varchar(500) NOT NULL COMMENT '备份文件相对路径',
  `file_size`   int unsigned NOT NULL DEFAULT 0 COMMENT '文件字节数',
  `type`        varchar(32)  NOT NULL DEFAULT 'cleanup' COMMENT 'cleanup=清库自动备份 manual=手动',
  `table_list`  text COMMENT '包含的表清单 JSON',
  `admin_id`    int unsigned NOT NULL DEFAULT 0,
  `admin_name`  varchar(50)  NOT NULL DEFAULT '',
  `status`      tinyint      NOT NULL DEFAULT 1 COMMENT '1=有效 2=已恢复',
  `restored_at` int unsigned NOT NULL DEFAULT 0,
  `restored_by` int unsigned NOT NULL DEFAULT 0,
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='平台备份记录';
