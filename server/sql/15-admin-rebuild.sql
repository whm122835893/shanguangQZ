-- ============================================================
-- 15-admin-rebuild.sql —— 管理后台全量重建 migration
--
-- 依据：《司南后台提示词 v3.1》（/workspace/.uploads/082967AA-E8D0-4DF0-A49B-804B9B4BFB30_司南后台提示词！！！.txt）
-- 映射原则：
--   1. 文档使用 nft_ 前缀；本库既有 42 张表全部无前缀 → 新表同样无前缀
--   2. 不修改既有字段语义，只 ADD COLUMN（MariaDB 10.6 支持 IF NOT EXISTS，可重复执行）
--   3. 时间戳沿用本库惯例 int unsigned unixtime（API 层再转 ISO 8601）
--   4. JSON 用 MariaDB JSON 别名（LONGTEXT）
--
-- 表名映射（文档 → 本库）：
--   nft_collectibles            → collection（扩展）
--   nft_blind_boxes             → blind_box（扩展）
--   nft_orders                  → artel_order（扩展 source）
--   nft_users                   → user（扩展黑名单）
--   nft_resale_listings         → consignment（扩展系统下架）
--   nft_admin_users             → admin_user（扩展 RBAC/JWT）
--   nft_priority_sale_whitelists→ priority_sale_whitelist（新建，附 activity）
--   其余 19 张新表见下方 CREATE
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ############################################################
-- 一、既有表扩展字段
-- ############################################################

-- ------------------------------------------------------------
-- collection（藏品主表）← nft_collectibles 扩展
-- 库存恒等式：total_num(发行总量) = 库存池 + reserved_count(已配配额)
--             + sold_count(已售出发售) + airdropped_count(已独立空投) + destroyed_count(已销毁)
-- 库存池 = total_num - reserved_count - sold_count - airdropped_count - destroyed_count
-- ------------------------------------------------------------
ALTER TABLE `collection`
  ADD COLUMN IF NOT EXISTS `sale_status` tinyint NOT NULL DEFAULT 0 COMMENT '发售状态 0=草稿 1=发售中 2=已售罄 3=已下架',
  ADD COLUMN IF NOT EXISTS `sale_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '发售价格（元）',
  ADD COLUMN IF NOT EXISTS `onsale_at` int unsigned NOT NULL DEFAULT 0 COMMENT '发售开始 unixtime',
  ADD COLUMN IF NOT EXISTS `off_sale_at` int unsigned NOT NULL DEFAULT 0 COMMENT '发售结束 unixtime，0=不限',
  ADD COLUMN IF NOT EXISTS `per_user_limit` int unsigned NOT NULL DEFAULT 0 COMMENT '每人限购，0=不限',
  ADD COLUMN IF NOT EXISTS `release_quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '发售数量（<= 当时库存池）',
  ADD COLUMN IF NOT EXISTS `sold_count` int unsigned NOT NULL DEFAULT 0 COMMENT '已售出发售数量',
  ADD COLUMN IF NOT EXISTS `reserved_count` int unsigned NOT NULL DEFAULT 0 COMMENT '已配置配额预留总数',
  ADD COLUMN IF NOT EXISTS `airdropped_count` int unsigned NOT NULL DEFAULT 0 COMMENT '已独立空投数量',
  ADD COLUMN IF NOT EXISTS `destroyed_count` int unsigned NOT NULL DEFAULT 0 COMMENT '已销毁数量',
  ADD COLUMN IF NOT EXISTS `is_transferable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '转赠开关 0=禁止 1=允许（独立于寄售开关）',
  ADD COLUMN IF NOT EXISTS `is_resaleable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '寄售开关 0=禁止 1=允许',
  ADD COLUMN IF NOT EXISTS `resale_price_min` decimal(10,2) DEFAULT NULL COMMENT '寄售限价下限（限价模式生效）',
  ADD COLUMN IF NOT EXISTS `resale_price_max` decimal(10,2) DEFAULT NULL COMMENT '寄售限价上限（限价模式生效）',
  ADD COLUMN IF NOT EXISTS `resale_price_mode` tinyint NOT NULL DEFAULT 0 COMMENT '价格管控 0=不限价 1=限价',
  ADD COLUMN IF NOT EXISTS `is_qualification_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '资格购开关 0=关闭 1=开启（独立于优先购）',
  ADD COLUMN IF NOT EXISTS `qualification_config_id` int unsigned DEFAULT NULL COMMENT '关联 qualification_config.id',
  ADD COLUMN IF NOT EXISTS `story` text COMMENT '藏品故事/描述',
  ADD COLUMN IF NOT EXISTS `creator` varchar(64) NOT NULL DEFAULT '' COMMENT '创作者',
  ADD COLUMN IF NOT EXISTS `royalty_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '版税比例 %';

-- ------------------------------------------------------------
-- blind_box（盲盒表）扩展
-- 盲盒恒等式：total_num(发行总量) = 盲盒库存池 + sale_number(已售出发售)
--             + airdropped_count(已独立空投) + destroyed_count(已销毁)
-- 盲盒库存池 = total_num - sale_number - airdropped_count - destroyed_count
-- ------------------------------------------------------------
ALTER TABLE `blind_box`
  ADD COLUMN IF NOT EXISTS `status` tinyint NOT NULL DEFAULT 0 COMMENT '状态 0=草稿 1=发售中 2=已售罄 3=已下架',
  ADD COLUMN IF NOT EXISTS `per_user_limit` int unsigned NOT NULL DEFAULT 0 COMMENT '每人限购，0=不限',
  ADD COLUMN IF NOT EXISTS `release_quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '发售数量（<= 当时盲盒库存池）',
  ADD COLUMN IF NOT EXISTS `destroyed_count` int unsigned NOT NULL DEFAULT 0 COMMENT '已销毁数量',
  ADD COLUMN IF NOT EXISTS `airdropped_count` int unsigned NOT NULL DEFAULT 0 COMMENT '已独立空投数量',
  ADD COLUMN IF NOT EXISTS `is_transferable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '转赠开关 0=禁止 1=允许',
  ADD COLUMN IF NOT EXISTS `is_resaleable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '寄售开关 0=禁止 1=允许',
  ADD COLUMN IF NOT EXISTS `resale_price_min` decimal(10,2) DEFAULT NULL COMMENT '寄售限价下限',
  ADD COLUMN IF NOT EXISTS `resale_price_max` decimal(10,2) DEFAULT NULL COMMENT '寄售限价上限',
  ADD COLUMN IF NOT EXISTS `resale_price_mode` tinyint NOT NULL DEFAULT 0 COMMENT '价格管控 0=不限价 1=限价',
  ADD COLUMN IF NOT EXISTS `description` varchar(1000) NOT NULL DEFAULT '' COMMENT '盲盒描述';

-- ------------------------------------------------------------
-- artel_order（订单表）← nft_orders.source 扩展
-- ------------------------------------------------------------
ALTER TABLE `artel_order`
  ADD COLUMN IF NOT EXISTS `source` varchar(16) NOT NULL DEFAULT 'release' COMMENT '订单来源 release公售/market市场/priority优先购/eligibility资格购/blindbox盲盒';

-- ------------------------------------------------------------
-- user（用户表）← 黑名单扩展
-- ------------------------------------------------------------
ALTER TABLE `user`
  ADD COLUMN IF NOT EXISTS `is_blacklisted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否黑名单 0=正常 1=黑名单',
  ADD COLUMN IF NOT EXISTS `blacklist_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '拉黑原因',
  ADD COLUMN IF NOT EXISTS `blacklist_at` int unsigned NOT NULL DEFAULT 0 COMMENT '拉黑时间',
  ADD COLUMN IF NOT EXISTS `blacklist_by` int unsigned NOT NULL DEFAULT 0 COMMENT '拉黑操作管理员ID';

-- ------------------------------------------------------------
-- consignment（寄售挂单）← 系统下架扩展
-- ------------------------------------------------------------
ALTER TABLE `consignment`
  ADD COLUMN IF NOT EXISTS `is_system_delisted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否被系统强制下架 0/1',
  ADD COLUMN IF NOT EXISTS `system_delisted_at` int unsigned NOT NULL DEFAULT 0 COMMENT '系统下架时间',
  ADD COLUMN IF NOT EXISTS `delist_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '下架原因';

-- ------------------------------------------------------------
-- admin_user（管理员）← RBAC + JWT 扩展
-- role: 1=超级管理员 2=运营 3=财务 4=风控 5=客服（对应 admin_role 种子 id）
-- ------------------------------------------------------------
ALTER TABLE `admin_user`
  ADD COLUMN IF NOT EXISTS `role` tinyint NOT NULL DEFAULT 1 COMMENT '角色 1=超管 2=运营 3=财务 4=风控 5=客服',
  ADD COLUMN IF NOT EXISTS `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '绑定手机号',
  ADD COLUMN IF NOT EXISTS `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  ADD COLUMN IF NOT EXISTS `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像',
  ADD COLUMN IF NOT EXISTS `real_name` varchar(50) NOT NULL DEFAULT '' COMMENT '真实姓名',
  ADD COLUMN IF NOT EXISTS `twofa_secret` varchar(64) NOT NULL DEFAULT '' COMMENT '2FA TOTP 密钥',
  ADD COLUMN IF NOT EXISTS `ip_whitelist` varchar(500) NOT NULL DEFAULT '' COMMENT 'IP白名单，逗号分隔，空=不限',
  ADD COLUMN IF NOT EXISTS `last_action_at` int unsigned NOT NULL DEFAULT 0 COMMENT '最后操作时间',
  ADD COLUMN IF NOT EXISTS `login_fail_count` int NOT NULL DEFAULT 0 COMMENT '连续登录失败次数',
  ADD COLUMN IF NOT EXISTS `locked_until` int unsigned NOT NULL DEFAULT 0 COMMENT '账号锁定截止 unixtime，0=未锁',
  ADD COLUMN IF NOT EXISTS `refresh_token_hash` char(64) DEFAULT NULL COMMENT '当前 refresh_token sha256',
  ADD COLUMN IF NOT EXISTS `refresh_expire` int unsigned NOT NULL DEFAULT 0 COMMENT 'refresh_token 过期 unixtime',
  ADD COLUMN IF NOT EXISTS `twofa_secret_draft` varchar(64) NOT NULL DEFAULT '' COMMENT '2FA 待确认密钥';

-- ############################################################
-- 二、RBAC 三表
-- ############################################################

CREATE TABLE IF NOT EXISTS `admin_role` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `name`        varchar(50)  NOT NULL COMMENT '角色名称',
  `code`        varchar(50)  NOT NULL COMMENT '角色标识',
  `description` varchar(255) NOT NULL DEFAULT '',
  `data_scope`  tinyint NOT NULL DEFAULT 1 COMMENT '数据权限 1=全部 2=本部门 3=本人',
  `is_preset`   tinyint(1) NOT NULL DEFAULT 0 COMMENT '预置角色不可删除',
  `status`      tinyint NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  `updatetime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员角色';

CREATE TABLE IF NOT EXISTS `admin_permission` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `name`       varchar(100) NOT NULL COMMENT '权限名',
  `code`       varchar(100) NOT NULL COMMENT '权限码 如 user:list',
  `module`     varchar(50)  NOT NULL COMMENT '所属模块',
  `type`       tinyint NOT NULL DEFAULT 1 COMMENT '1=菜单 2=按钮 3=接口',
  `parent_id`  int unsigned NOT NULL DEFAULT 0,
  `sort_order` int NOT NULL DEFAULT 0,
  `status`     tinyint NOT NULL DEFAULT 1,
  `createtime` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限表';

CREATE TABLE IF NOT EXISTS `admin_role_permission` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT,
  `role_id`       int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  `createtime`    int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
  KEY `idx_role` (`role_id`),
  KEY `idx_permission` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色权限关联';

-- ------------------------------------------------------------
-- 角色种子（五角色，id 与 admin_user.role 对齐）
-- ------------------------------------------------------------
INSERT IGNORE INTO `admin_role` (`id`, `name`, `code`, `description`, `is_preset`, `createtime`, `updatetime`) VALUES
  (1, '超级管理员', 'superadmin', '全部功能，含平台清库与完整实名查看', 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (2, '运营',       'operator',   '藏品/盲盒/活动/CMS/基础用户管理',    1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (3, '财务',       'finance',    '订单/钱包/报表/导出',                1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (4, '风控',       'risk',       '黑名单/告警/安全事件/实名完整查看',  1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
  (5, '客服',       'support',    '工单处理/脱敏用户查询',              1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ------------------------------------------------------------
-- 权限码种子（模块:动作，覆盖 17 模块全部按钮/接口）
-- ------------------------------------------------------------
INSERT IGNORE INTO `admin_permission` (`code`, `name`, `module`, `type`, `createtime`) VALUES
  ('dashboard:view',            '仪表盘查看',        'dashboard', 1, UNIX_TIMESTAMP()),
  ('user:list',                 '用户列表',          'user', 1, UNIX_TIMESTAMP()),
  ('user:detail',               '用户详情',          'user', 2, UNIX_TIMESTAMP()),
  ('user:freeze',               '冻结/解冻',         'user', 2, UNIX_TIMESTAMP()),
  ('user:manage',               '用户管理操作',      'user', 2, UNIX_TIMESTAMP()),
  ('user:blacklist',            '黑名单操作',        'user', 2, UNIX_TIMESTAMP()),
  ('user:recover',              '强制回收',          'user', 2, UNIX_TIMESTAMP()),
  ('realname:list',             '实名列表',          'realname', 1, UNIX_TIMESTAMP()),
  ('realname:view',             '实名脱敏查看',      'realname', 2, UNIX_TIMESTAMP()),
  ('realname:full',             '实名完整查看',      'realname', 2, UNIX_TIMESTAMP()),
  ('realname:audit',            '实名审计日志',      'realname', 2, UNIX_TIMESTAMP()),
  ('collectible:list',          '藏品列表',          'collectible', 1, UNIX_TIMESTAMP()),
  ('collectible:create',        '创建藏品',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:detail',        '藏品详情',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:edit',          '编辑藏品',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:release',       '发售配置',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:quota',         '配额配置',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:relist',        '重新上架',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:manage',        '藏品管理操作',      'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:destroy',       '销毁库存',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:delete',        '删除藏品',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:airdrop',       '独立空投',          'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:market',        '寄售/价格管控',     'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:qualification', '资格购配置',        'collectible', 2, UNIX_TIMESTAMP()),
  ('collectible:audit',         '库存审计',          'collectible', 2, UNIX_TIMESTAMP()),
  ('blindbox:list',             '盲盒列表',          'blindbox', 1, UNIX_TIMESTAMP()),
  ('blindbox:create',           '创建盲盒',          'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:detail',           '盲盒详情',          'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:edit',             '编辑盲盒',          'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:config',           '子藏品配置',        'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:release',          '盲盒发售配置',      'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:relist',           '盲盒重新上架',      'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:manage',           '盲盒管理操作',      'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:destroy',          '盲盒销毁',          'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:delete',           '删除盲盒',          'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:airdrop',          '盲盒空投',          'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:recover',          '盲盒强制回收',      'blindbox', 2, UNIX_TIMESTAMP()),
  ('blindbox:audit',            '盲盒库存审计',      'blindbox', 2, UNIX_TIMESTAMP()),
  ('order:list',                '订单列表',          'order', 1, UNIX_TIMESTAMP()),
  ('order:detail',              '订单详情',          'order', 2, UNIX_TIMESTAMP()),
  ('order:manage',              '订单管理操作',      'order', 2, UNIX_TIMESTAMP()),
  ('order:refund',              '申请退款',          'order', 2, UNIX_TIMESTAMP()),
  ('order:audit',               '异常订单',          'order', 2, UNIX_TIMESTAMP()),
  ('order:export',              '导出订单',          'order', 2, UNIX_TIMESTAMP()),
  ('refund:list',               '退款列表',          'refund', 1, UNIX_TIMESTAMP()),
  ('refund:detail',             '退款详情',          'refund', 2, UNIX_TIMESTAMP()),
  ('refund:approve',            '退款审批',          'refund', 2, UNIX_TIMESTAMP()),
  ('market:list',               '挂单列表',          'market', 1, UNIX_TIMESTAMP()),
  ('market:manage',             '市场管理操作',      'market', 2, UNIX_TIMESTAMP()),
  ('market:monitor',            '市场监控',          'market', 2, UNIX_TIMESTAMP()),
  ('market:config',             '手续费配置',        'market', 2, UNIX_TIMESTAMP()),
  ('transfer:list',             '转赠列表',          'transfer', 1, UNIX_TIMESTAMP()),
  ('transfer:detail',           '转赠详情',          'transfer', 2, UNIX_TIMESTAMP()),
  ('transfer:manage',           '转赠管理操作',      'transfer', 2, UNIX_TIMESTAMP()),
  ('transfer:stats',            '转赠统计',          'transfer', 2, UNIX_TIMESTAMP()),
  ('transfer:monitor',          '异常转赠监控',      'transfer', 2, UNIX_TIMESTAMP()),
  ('marketing:priority:list',   '优先购列表',        'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:priority:detail', '优先购详情',        'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:priority:create', '创建优先购活动',    'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:priority:edit',   '编辑优先购活动',    'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:priority:whitelist', '优先购白名单',   'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:priority:manage', '优先购管理',        'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:checkin:config',  '签到配置',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:checkin:list',    '签到记录',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:invite:config',   '邀请配置',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:invite:list',     '邀请记录',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:lucky:list',      '抽奖列表',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:lucky:detail',    '抽奖详情',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:lucky:create',    '创建抽奖活动',      'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:lucky:edit',      '编辑抽奖活动',      'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:lucky:prize',     '奖项管理',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:synthesis:list',  '合成列表',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:synthesis:detail','合成详情',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:synthesis:create','创建合成活动',      'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:synthesis:edit',  '编辑合成活动',      'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:airdrop',         '活动空投',          'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:register:config', '注册福利配置',      'marketing', 2, UNIX_TIMESTAMP()),
  ('marketing:blindbox:stats',  '盲盒数据统计',      'marketing', 2, UNIX_TIMESTAMP()),
  ('wallet:recharge',           '充值记录',          'wallet', 1, UNIX_TIMESTAMP()),
  ('wallet:transaction',        '资金流水',          'wallet', 2, UNIX_TIMESTAMP()),
  ('wallet:fee',                '手续费统计',        'wallet', 2, UNIX_TIMESTAMP()),
  ('wallet:audit',              '资金守恒校验',      'wallet', 2, UNIX_TIMESTAMP()),
  ('wallet:monitor',            '异常资金监控',      'wallet', 2, UNIX_TIMESTAMP()),
  ('wallet:export',             '导出财务数据',      'wallet', 2, UNIX_TIMESTAMP()),
  ('cms:banner:list',           'Banner列表',        'cms', 1, UNIX_TIMESTAMP()),
  ('cms:banner:detail',         'Banner详情',        'cms', 2, UNIX_TIMESTAMP()),
  ('cms:banner:create',         '创建Banner',        'cms', 2, UNIX_TIMESTAMP()),
  ('cms:banner:edit',           '编辑Banner',        'cms', 2, UNIX_TIMESTAMP()),
  ('cms:banner:delete',         '删除Banner',        'cms', 2, UNIX_TIMESTAMP()),
  ('cms:announcement:list',     '公告列表',          'cms', 1, UNIX_TIMESTAMP()),
  ('cms:announcement:detail',   '公告详情',          'cms', 2, UNIX_TIMESTAMP()),
  ('cms:announcement:create',   '创建公告',          'cms', 2, UNIX_TIMESTAMP()),
  ('cms:announcement:edit',     '编辑公告',          'cms', 2, UNIX_TIMESTAMP()),
  ('cms:announcement:delete',   '删除公告',          'cms', 2, UNIX_TIMESTAMP()),
  ('cms:agreement:list',        '协议列表',          'cms', 1, UNIX_TIMESTAMP()),
  ('cms:agreement:edit',        '编辑协议',          'cms', 2, UNIX_TIMESTAMP()),
  ('cms:artifact:list',         '文物列表',          'cms', 1, UNIX_TIMESTAMP()),
  ('cms:artifact:create',       '创建文物',          'cms', 2, UNIX_TIMESTAMP()),
  ('cms:artifact:edit',         '编辑文物',          'cms', 2, UNIX_TIMESTAMP()),
  ('cms:artifact:delete',       '删除文物',          'cms', 2, UNIX_TIMESTAMP()),
  ('cms:decoration:view',       '装修配置查看',      'cms', 2, UNIX_TIMESTAMP()),
  ('cms:decoration:edit',       '装修配置编辑',      'cms', 2, UNIX_TIMESTAMP()),
  ('system:config',             '系统配置',          'system', 1, UNIX_TIMESTAMP()),
  ('system:security',           '安全策略',          'system', 2, UNIX_TIMESTAMP()),
  ('permission:admin:list',     '管理员列表',        'permission', 1, UNIX_TIMESTAMP()),
  ('permission:admin:create',   '创建管理员',        'permission', 2, UNIX_TIMESTAMP()),
  ('permission:admin:detail',   '管理员详情',        'permission', 2, UNIX_TIMESTAMP()),
  ('permission:admin:edit',     '编辑管理员',        'permission', 2, UNIX_TIMESTAMP()),
  ('permission:admin:delete',   '删除管理员',        'permission', 2, UNIX_TIMESTAMP()),
  ('permission:admin:manage',   '管理员管理操作',    'permission', 2, UNIX_TIMESTAMP()),
  ('permission:role:list',      '角色列表',          'permission', 1, UNIX_TIMESTAMP()),
  ('permission:role:create',    '创建角色',          'permission', 2, UNIX_TIMESTAMP()),
  ('permission:role:edit',      '编辑角色',          'permission', 2, UNIX_TIMESTAMP()),
  ('permission:role:delete',    '删除角色',          'permission', 2, UNIX_TIMESTAMP()),
  ('permission:permission:list','权限树',            'permission', 2, UNIX_TIMESTAMP()),
  ('permission:log:list',       '操作日志',          'permission', 1, UNIX_TIMESTAMP()),
  ('permission:log:detail',     '日志详情',          'permission', 2, UNIX_TIMESTAMP()),
  ('permission:log:export',     '导出日志',          'permission', 2, UNIX_TIMESTAMP()),
  ('permission:log:login',      '登录日志',          'permission', 2, UNIX_TIMESTAMP()),
  ('security:blacklist:list',   '黑名单列表',        'security', 1, UNIX_TIMESTAMP()),
  ('security:blacklist:create', '添加黑名单',        'security', 2, UNIX_TIMESTAMP()),
  ('security:blacklist:delete', '移出黑名单',        'security', 2, UNIX_TIMESTAMP()),
  ('security:alert:list',       '风控告警列表',      'security', 1, UNIX_TIMESTAMP()),
  ('security:alert:detail',     '告警详情',          'security', 2, UNIX_TIMESTAMP()),
  ('security:alert:handle',     '处理告警',          'security', 2, UNIX_TIMESTAMP()),
  ('security:event:list',       '安全事件列表',      'security', 1, UNIX_TIMESTAMP()),
  ('security:event:detail',     '事件详情',          'security', 2, UNIX_TIMESTAMP()),
  ('security:event:handle',     '处理事件',          'security', 2, UNIX_TIMESTAMP()),
  ('security:txlock:list',      '交易密码锁定记录',  'security', 1, UNIX_TIMESTAMP()),
  ('security:txlock:manage',    '手动解锁交易密码',  'security', 2, UNIX_TIMESTAMP()),
  ('security:approval:list',    '审批列表',          'security', 1, UNIX_TIMESTAMP()),
  ('security:approval:detail',  '审批详情',          'security', 2, UNIX_TIMESTAMP()),
  ('security:approval:handle',  '审批处理',          'security', 2, UNIX_TIMESTAMP()),
  ('ticket:list',               '工单列表',          'ticket', 1, UNIX_TIMESTAMP()),
  ('ticket:detail',             '工单详情',          'ticket', 2, UNIX_TIMESTAMP()),
  ('ticket:assign',             '分配工单',          'ticket', 2, UNIX_TIMESTAMP()),
  ('ticket:reply',              '回复工单',          'ticket', 2, UNIX_TIMESTAMP()),
  ('ticket:manage',             '工单管理操作',      'ticket', 2, UNIX_TIMESTAMP()),
  ('ticket:feedback',           '用户反馈',          'ticket', 2, UNIX_TIMESTAMP()),
  ('ticket:compensate',         '快捷补偿',          'ticket', 2, UNIX_TIMESTAMP()),
  ('report:sales',              '销售报表',          'report', 1, UNIX_TIMESTAMP()),
  ('report:user',               '用户报表',          'report', 2, UNIX_TIMESTAMP()),
  ('report:collectible',        '藏品报表',          'report', 2, UNIX_TIMESTAMP()),
  ('report:blindbox',           '盲盒报表',          'report', 2, UNIX_TIMESTAMP()),
  ('report:finance',            '财务对账',          'report', 2, UNIX_TIMESTAMP()),
  ('report:export',             '自定义导出',        'report', 2, UNIX_TIMESTAMP()),
  ('platform:cleanup',          '平台清库',          'platform', 1, UNIX_TIMESTAMP()),
  ('platform:log',              '清库记录',          'platform', 2, UNIX_TIMESTAMP()),
  ('platform:backup',           '备份管理',          'platform', 2, UNIX_TIMESTAMP());

-- ------------------------------------------------------------
-- 角色 → 权限 映射种子（超管 role=1 代码层直通全量，不落关联）
-- ------------------------------------------------------------
-- 运营(2)
INSERT IGNORE INTO `admin_role_permission` (`role_id`, `permission_id`, `createtime`)
SELECT 2, p.id, UNIX_TIMESTAMP() FROM `admin_permission` p
WHERE p.code LIKE 'collectible:%' OR p.code LIKE 'blindbox:%' OR p.code LIKE 'marketing:%'
   OR p.code LIKE 'cms:%' OR p.code LIKE 'transfer:%'
   OR p.code IN ('dashboard:view','user:list','user:detail','user:freeze','order:list','order:detail','market:list');
-- 财务(3)
INSERT IGNORE INTO `admin_role_permission` (`role_id`, `permission_id`, `createtime`)
SELECT 3, p.id, UNIX_TIMESTAMP() FROM `admin_permission` p
WHERE p.code LIKE 'order:%' OR p.code LIKE 'refund:%' OR p.code LIKE 'wallet:%'
   OR p.code IN ('dashboard:view','market:list','market:config','report:sales','report:finance','report:export');
-- 风控(4)
INSERT IGNORE INTO `admin_role_permission` (`role_id`, `permission_id`, `createtime`)
SELECT 4, p.id, UNIX_TIMESTAMP() FROM `admin_permission` p
WHERE p.code LIKE 'security:%' OR p.code LIKE 'realname:%'
   OR p.code IN ('dashboard:view','user:list','user:detail','user:blacklist');
-- 客服(5)
INSERT IGNORE INTO `admin_role_permission` (`role_id`, `permission_id`, `createtime`)
SELECT 5, p.id, UNIX_TIMESTAMP() FROM `admin_permission` p
WHERE p.code LIKE 'ticket:%'
   OR p.code IN ('dashboard:view','user:list','user:detail');

-- ############################################################
-- 三、业务新表
-- ############################################################

-- ------------------------------------------------------------
-- 资格购配置（1 藏品 : 1 配置）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `qualification_config` (
  `id`                       int unsigned NOT NULL AUTO_INCREMENT,
  `collectible_id`           int unsigned NOT NULL COMMENT '目标藏品',
  `is_enabled`               tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=关闭 1=开启',
  `required_collectible_ids` json DEFAULT NULL COMMENT '资格藏品ID数组',
  `required_checkin_days`    int unsigned NOT NULL DEFAULT 0 COMMENT '要求累计签到天数，0=不启用',
  `required_invite_count`    int unsigned NOT NULL DEFAULT 0 COMMENT '要求累计邀请人数，0=不启用',
  `condition_type`           tinyint NOT NULL DEFAULT 1 COMMENT '1=满足任一 2=满足全部',
  `valid_start_at`           int unsigned NOT NULL DEFAULT 0 COMMENT '资格有效期起，0=不限',
  `valid_end_at`             int unsigned NOT NULL DEFAULT 0 COMMENT '资格有效期止，0=不限',
  `createtime`               int unsigned NOT NULL DEFAULT 0,
  `updatetime`               int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collectible` (`collectible_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资格购配置';

CREATE TABLE IF NOT EXISTS `qualification_whitelist` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `config_id`   int unsigned NOT NULL COMMENT '关联 qualification_config.id',
  `user_id`     int unsigned NOT NULL,
  `phone`       varchar(20) NOT NULL,
  `status`      tinyint NOT NULL DEFAULT 1 COMMENT '1=有效 0=停用',
  `expires_at`  int unsigned NOT NULL DEFAULT 0 COMMENT '过期时间，0=不限',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  `updatetime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_user` (`config_id`, `user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='资格购白名单（额外资格手机号）';

-- ------------------------------------------------------------
-- 库存配额预留
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_quota` (
  `id`               int unsigned NOT NULL AUTO_INCREMENT,
  `collectible_id`   int unsigned NOT NULL,
  `quota_type`       tinyint NOT NULL COMMENT '1=优先购 2=活动空投 3=签到 4=注册 5=邀请 6=抽奖 7=其他',
  `quota_name`       varchar(100) NOT NULL DEFAULT '',
  `planned_quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '计划数量（占库存池）',
  `used_quantity`    int unsigned NOT NULL DEFAULT 0 COMMENT '已使用（不可减少部分）',
  `status`           tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用（停用释放回库存池）',
  `activity_id`      int unsigned DEFAULT NULL,
  `activity_type`    varchar(50) NOT NULL DEFAULT '',
  `remark`           varchar(255) NOT NULL DEFAULT '',
  `created_by`       int unsigned NOT NULL DEFAULT 0 COMMENT '创建管理员',
  `createtime`       int unsigned NOT NULL DEFAULT 0,
  `updatetime`       int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_collectible` (`collectible_id`),
  KEY `idx_type` (`quota_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='库存配额预留';

-- ------------------------------------------------------------
-- 优先购活动 + 白名单
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `priority_sale_activity` (
  `id`                    int unsigned NOT NULL AUTO_INCREMENT,
  `collectible_id`        int unsigned NOT NULL,
  `start_time`            int unsigned NOT NULL DEFAULT 0,
  `end_time`              int unsigned NOT NULL DEFAULT 0,
  `max_quantity_per_user` int unsigned NOT NULL DEFAULT 0 COMMENT '每人最大购买量，0=不限',
  `status`                tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `createtime`            int unsigned NOT NULL DEFAULT 0,
  `updatetime`            int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_collectible` (`collectible_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优先购活动';

CREATE TABLE IF NOT EXISTS `priority_sale_whitelist` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT,
  `activity_id`   int unsigned NOT NULL,
  `collectible_id` int unsigned NOT NULL DEFAULT 0 COMMENT '冗余，便于按藏品校验',
  `user_id`       int unsigned NOT NULL,
  `phone`         varchar(20) NOT NULL DEFAULT '',
  `max_quantity`  int unsigned NOT NULL DEFAULT 1 COMMENT '最大购买量',
  `used_quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '已用配额',
  `expires_at`    int unsigned NOT NULL DEFAULT 0 COMMENT '资格有效期，0=不限',
  `status`        tinyint NOT NULL DEFAULT 1 COMMENT '1=有效 0=停用 2=已清理',
  `createtime`    int unsigned NOT NULL DEFAULT 0,
  `updatetime`    int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_activity` (`activity_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_collectible` (`collectible_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='优先购白名单';

-- ------------------------------------------------------------
-- 销毁记录
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `destroy_record` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `target_type` tinyint NOT NULL COMMENT '1=藏品 2=盲盒',
  `target_id`   int unsigned NOT NULL,
  `target_name` varchar(128) NOT NULL DEFAULT '',
  `quantity`    int unsigned NOT NULL DEFAULT 0,
  `reason`      varchar(255) NOT NULL DEFAULT '',
  `admin_id`    int unsigned NOT NULL DEFAULT 0,
  `admin_name`  varchar(50) NOT NULL DEFAULT '',
  `ip_address`  varchar(45) NOT NULL DEFAULT '',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_target` (`target_type`, `target_id`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销毁记录';

-- ------------------------------------------------------------
-- 退款
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `refund` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `order_id`       int unsigned NOT NULL COMMENT 'artel_order.id',
  `order_sn`       varchar(64) NOT NULL DEFAULT '',
  `user_id`        int unsigned NOT NULL,
  `amount`         decimal(12,2) NOT NULL DEFAULT 0.00,
  `reason`         varchar(255) NOT NULL DEFAULT '',
  `status`         tinyint NOT NULL DEFAULT 1 COMMENT '1=待审批 2=已批准 3=已拒绝 4=已退款',
  `approver_id`    int unsigned DEFAULT NULL,
  `approved_at`    int unsigned NOT NULL DEFAULT 0,
  `refunded_at`    int unsigned NOT NULL DEFAULT 0,
  `refund_no`      varchar(64) NOT NULL DEFAULT '',
  `admin_id`       int unsigned NOT NULL DEFAULT 0 COMMENT '发起管理员',
  `ip_address`     varchar(45) NOT NULL DEFAULT '',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `updatetime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='退款记录';

-- ------------------------------------------------------------
-- 敏感操作审批工作流
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `approval` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `approval_type`  tinyint NOT NULL COMMENT '1=大额退款 2=强制修改资产 3=修改支付配置 4=平台清库 5=其他',
  `target_id`      int unsigned NOT NULL DEFAULT 0,
  `target_type`    varchar(50) NOT NULL DEFAULT '',
  `title`          varchar(200) NOT NULL,
  `description`    text DEFAULT NULL,
  `request_data`   json DEFAULT NULL,
  `applicant_id`   int unsigned NOT NULL,
  `applicant_name` varchar(50) NOT NULL DEFAULT '',
  `approver_id`    int unsigned DEFAULT NULL,
  `approver_name`  varchar(50) DEFAULT NULL,
  `status`         tinyint NOT NULL DEFAULT 1 COMMENT '1=待审批 2=已批准 3=已拒绝',
  `comment`        varchar(255) DEFAULT NULL,
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `approved_at`    int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`approval_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='敏感操作审批工作流';

-- ------------------------------------------------------------
-- 黑名单
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blacklist` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`        int unsigned NOT NULL DEFAULT 0,
  `blacklist_type` tinyint NOT NULL DEFAULT 1 COMMENT '1=用户级 2=IP级 3=设备级',
  `target_value`   varchar(255) NOT NULL COMMENT '用户ID/IP/设备号',
  `reason`         varchar(255) NOT NULL,
  `evidence`       text DEFAULT NULL,
  `admin_id`       int unsigned NOT NULL DEFAULT 0,
  `admin_name`     varchar(50) NOT NULL DEFAULT '',
  `status`         tinyint NOT NULL DEFAULT 1 COMMENT '1=生效 0=已解除',
  `lifted_at`      int unsigned NOT NULL DEFAULT 0,
  `lifted_by`      int unsigned NOT NULL DEFAULT 0,
  `expires_at`     int unsigned NOT NULL DEFAULT 0 COMMENT '0=永久',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `updatetime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`blacklist_type`),
  KEY `idx_target` (`target_value`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='黑名单';

-- ------------------------------------------------------------
-- 风控告警
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `risk_alert` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `alert_type`     tinyint NOT NULL COMMENT '1=大额充值 2=频繁小额 3=余额突变 4=高频API 5=异常时间 6=异地登录 7=批量注册 8=异常价格 9=其他',
  `alert_level`    tinyint NOT NULL DEFAULT 1 COMMENT '1=低 2=中 3=高 4=紧急',
  `user_id`        int unsigned DEFAULT NULL,
  `target_id`      int unsigned DEFAULT NULL,
  `target_type`    varchar(50) DEFAULT NULL,
  `title`          varchar(200) NOT NULL,
  `description`    text DEFAULT NULL,
  `evidence`       json DEFAULT NULL,
  `status`         tinyint NOT NULL DEFAULT 1 COMMENT '1=未处理 2=处理中 3=已处理 4=已忽略',
  `handler_id`     int unsigned DEFAULT NULL,
  `handler_name`   varchar(50) DEFAULT NULL,
  `handled_at`     int unsigned NOT NULL DEFAULT 0,
  `handle_comment` varchar(255) DEFAULT NULL,
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`alert_type`),
  KEY `idx_status` (`status`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='风控告警';

-- ------------------------------------------------------------
-- 安全事件
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `security_event` (
  `id`              int unsigned NOT NULL AUTO_INCREMENT,
  `event_type`      tinyint NOT NULL COMMENT '1=IDOR 2=越权 3=回调篡改 4=Token异常 5=暴力破解 6=SQLi 7=XSS 8=其他',
  `event_level`     tinyint NOT NULL DEFAULT 1 COMMENT '1=低 2=中 3=高 4=紧急',
  `user_id`         int unsigned DEFAULT NULL,
  `admin_id`        int unsigned DEFAULT NULL,
  `ip_address`      varchar(45) DEFAULT NULL,
  `user_agent`      varchar(500) DEFAULT NULL,
  `request_path`    varchar(255) DEFAULT NULL,
  `request_method`  varchar(10) DEFAULT NULL,
  `request_params`  text DEFAULT NULL,
  `response_status` int DEFAULT NULL,
  `description`     text DEFAULT NULL,
  `status`          tinyint NOT NULL DEFAULT 1 COMMENT '1=未处理 2=处理中 3=已处理',
  `createtime`      int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`event_type`),
  KEY `idx_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='安全事件审计';

-- ------------------------------------------------------------
-- 客服工单
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `support_ticket` (
  `id`                     int unsigned NOT NULL AUTO_INCREMENT,
  `ticket_no`              varchar(32) NOT NULL COMMENT 'TK+日期+序号',
  `user_id`                int unsigned NOT NULL,
  `user_phone`             varchar(20) NOT NULL DEFAULT '',
  `ticket_type`            tinyint NOT NULL COMMENT '1=支付异常 2=藏品丢失 3=盲盒问题 4=转赠纠纷 5=账号问题 6=其他',
  `priority`               tinyint NOT NULL DEFAULT 2 COMMENT '1=紧急 2=高 3=中 4=低',
  `title`                  varchar(200) NOT NULL,
  `description`            text DEFAULT NULL,
  `related_order_id`       int unsigned DEFAULT NULL,
  `related_collectible_id` int unsigned DEFAULT NULL,
  `related_blindbox_id`    int unsigned DEFAULT NULL,
  `status`                 tinyint NOT NULL DEFAULT 1 COMMENT '1=待处理 2=处理中 3=待用户确认 4=已解决 5=已关闭',
  `assignee_id`            int unsigned DEFAULT NULL,
  `assignee_name`          varchar(50) DEFAULT NULL,
  `solved_at`              int unsigned NOT NULL DEFAULT 0,
  `closed_at`              int unsigned NOT NULL DEFAULT 0,
  `createtime`             int unsigned NOT NULL DEFAULT 0,
  `updatetime`             int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_no` (`ticket_no`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_assignee` (`assignee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='客服工单';

CREATE TABLE IF NOT EXISTS `ticket_reply` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id`   int unsigned NOT NULL,
  `sender_type` tinyint NOT NULL COMMENT '1=用户 2=客服 3=系统',
  `sender_id`   int unsigned NOT NULL DEFAULT 0,
  `sender_name` varchar(50) NOT NULL DEFAULT '',
  `content`     text NOT NULL,
  `attachments` json DEFAULT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0 COMMENT '内部备注 0/1',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ticket` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='工单回复';

CREATE TABLE IF NOT EXISTS `feedback` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`    int unsigned NOT NULL DEFAULT 0,
  `type`       tinyint NOT NULL DEFAULT 1 COMMENT '1=反馈 2=举报 3=建议',
  `content`    text NOT NULL,
  `images`     json DEFAULT NULL,
  `contact`    varchar(64) NOT NULL DEFAULT '',
  `status`     tinyint NOT NULL DEFAULT 1 COMMENT '1=待处理 2=已处理',
  `reply`      varchar(500) NOT NULL DEFAULT '',
  `admin_id`   int unsigned NOT NULL DEFAULT 0,
  `createtime` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户反馈';

-- ------------------------------------------------------------
-- 平台清库日志
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `platform_cleanup_log` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `admin_id`       int unsigned NOT NULL,
  `admin_name`     varchar(50) NOT NULL,
  `admin_phone`    varchar(20) NOT NULL DEFAULT '',
  `ip_address`     varchar(45) NOT NULL DEFAULT '',
  `reason`         varchar(255) NOT NULL DEFAULT '',
  `backup_path`    varchar(500) NOT NULL DEFAULT '',
  `affected_users` int unsigned NOT NULL DEFAULT 0,
  `affected_orders` int unsigned NOT NULL DEFAULT 0,
  `execution_time` int unsigned NOT NULL DEFAULT 0 COMMENT '耗时秒',
  `status`         tinyint NOT NULL DEFAULT 1 COMMENT '1=成功 2=失败',
  `error_message`  text DEFAULT NULL,
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='平台清库日志';

-- ------------------------------------------------------------
-- 活动奖励统一配置
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_reward` (
  `id`               int unsigned NOT NULL AUTO_INCREMENT,
  `activity_id`      int unsigned NOT NULL,
  `activity_type`    varchar(50) NOT NULL COMMENT 'checkin/invite/register/lucky/airdrop',
  `reward_type`      tinyint NOT NULL COMMENT '1=藏品 2=优先购资格 3=资格购资格 4=抽奖次数 5=盲盒',
  `reward_config`    json NOT NULL,
  `planned_quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '计划上限（发放时动态校验配额）',
  `used_quantity`    int unsigned NOT NULL DEFAULT 0,
  `status`           tinyint NOT NULL DEFAULT 1,
  `createtime`       int unsigned NOT NULL DEFAULT 0,
  `updatetime`       int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_activity` (`activity_id`, `activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='活动奖励统一配置';

-- ------------------------------------------------------------
-- 区块链
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chain_contract` (
  `id`               int unsigned NOT NULL AUTO_INCREMENT,
  `chain_type`       varchar(20) NOT NULL COMMENT 'ETH/POLYGON/BSC',
  `contract_address` varchar(100) NOT NULL,
  `contract_name`    varchar(100) NOT NULL,
  `abi`              json DEFAULT NULL,
  `status`           tinyint NOT NULL DEFAULT 1,
  `createtime`       int unsigned NOT NULL DEFAULT 0,
  `updatetime`       int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_chain` (`chain_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='智能合约';

CREATE TABLE IF NOT EXISTS `chain_transaction` (
  `id`              int unsigned NOT NULL AUTO_INCREMENT,
  `chain_type`      varchar(20) NOT NULL,
  `contract_address` varchar(100) NOT NULL,
  `tx_hash`         varchar(100) NOT NULL,
  `event_type`      varchar(50) NOT NULL COMMENT 'Mint/Transfer/Sale',
  `from_address`    varchar(100) DEFAULT NULL,
  `to_address`      varchar(100) DEFAULT NULL,
  `token_id`        varchar(100) DEFAULT NULL,
  `amount`          decimal(20,8) DEFAULT NULL,
  `gas_used`        bigint unsigned DEFAULT NULL,
  `status`          tinyint NOT NULL DEFAULT 1 COMMENT '1=待确认 2=已确认 3=失败',
  `block_number`    bigint unsigned DEFAULT NULL,
  `createtime`      int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tx` (`tx_hash`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='链上交易';

-- ------------------------------------------------------------
-- 内容审核
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `content_audit` (
  `id`               int unsigned NOT NULL AUTO_INCREMENT,
  `content_type`     tinyint NOT NULL COMMENT '1=藏品 2=帖子 3=评论',
  `target_id`        int unsigned NOT NULL,
  `user_id`          int unsigned NOT NULL,
  `content_snapshot` text DEFAULT NULL,
  `images`           json DEFAULT NULL,
  `status`           tinyint NOT NULL DEFAULT 1 COMMENT '1=待审核 2=通过 3=驳回',
  `auditor_id`       int unsigned DEFAULT NULL,
  `audit_comment`    varchar(255) DEFAULT NULL,
  `createtime`       int unsigned NOT NULL DEFAULT 0,
  `audited_at`       int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户内容审核';

-- ------------------------------------------------------------
-- 抽奖奖项池（配合既有 lottery 活动表）
-- 概率闭环：非空奖概率之和 <= 100；空奖概率 = 100 - 非空奖总和
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lottery_prize` (
  `id`               int unsigned NOT NULL AUTO_INCREMENT,
  `lottery_id`       int unsigned NOT NULL COMMENT '关联 lottery.id',
  `name`             varchar(100) NOT NULL DEFAULT '',
  `prize_type`       tinyint NOT NULL DEFAULT 1 COMMENT '1=藏品 2=优先购资格 3=资格购资格 4=抽奖次数 5=盲盒 6=空奖',
  `prize_config`     json DEFAULT NULL COMMENT '{collectible_id/blind_box_id/draw_count/...}',
  `probability`      decimal(7,4) NOT NULL DEFAULT 0.0000 COMMENT '概率%，4位小数',
  `planned_quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '奖项数量，0=不限',
  `sent_quantity`    int unsigned NOT NULL DEFAULT 0,
  `is_empty_prize`   tinyint(1) NOT NULL DEFAULT 0,
  `status`           tinyint NOT NULL DEFAULT 1,
  `createtime`       int unsigned NOT NULL DEFAULT 0,
  `updatetime`       int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_lottery` (`lottery_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖奖项池';

-- ------------------------------------------------------------
-- 盲盒子藏品（奖品池）
-- 概率闭环（§3.6.2）：概率之和 < 100 差额视为空奖；> 100 拦截
-- 子藏品库存不预冻结，开启时实时校验该子藏品库存池/配额
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blind_box_item` (
  `id`               int unsigned NOT NULL AUTO_INCREMENT,
  `blind_box_id`     int unsigned NOT NULL,
  `collectible_id`   int unsigned NOT NULL,
  `probability`      decimal(7,4) NOT NULL DEFAULT 0.0000 COMMENT '概率%，4位小数',
  `planned_quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '计划数量',
  `status`           tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `createtime`       int unsigned NOT NULL DEFAULT 0,
  `updatetime`       int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_box` (`blind_box_id`),
  KEY `idx_collectible` (`collectible_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盲盒子藏品';

-- ------------------------------------------------------------
-- 转赠记录（C 端转赠落此表；管理端读此表做撤销/统计）
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `transfer_record` (
  `id`           int unsigned NOT NULL AUTO_INCREMENT,
  `transfer_no`  varchar(64) NOT NULL DEFAULT '',
  `type`         tinyint NOT NULL COMMENT '1=藏品 2=盲盒',
  `collectible_id` int unsigned NOT NULL DEFAULT 0,
  `blind_box_id` int unsigned NOT NULL DEFAULT 0,
  `uc_id`        int unsigned NOT NULL DEFAULT 0 COMMENT 'user_collection.id',
  `sender_id`    int unsigned NOT NULL,
  `receiver_id`  int unsigned NOT NULL,
  `status`       tinyint NOT NULL DEFAULT 0 COMMENT '0=待接收 1=已完成 2=已拒绝 3=已撤销',
  `revoke_by`    int unsigned NOT NULL DEFAULT 0 COMMENT '撤销操作管理员',
  `revoke_reason` varchar(255) NOT NULL DEFAULT '',
  `createtime`   int unsigned NOT NULL DEFAULT 0,
  `confirm_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_receiver` (`receiver_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='转赠记录';

-- ------------------------------------------------------------
-- 营销记录载体：签到 / 邀请 / 空投
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `check_in_record` (
  `id`           int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`      int unsigned NOT NULL,
  `checkin_date` date NOT NULL,
  `successions`  int unsigned NOT NULL DEFAULT 0 COMMENT '当时连签天数',
  `reward_type`  varchar(32) NOT NULL DEFAULT 'none' COMMENT 'none/collectible/points/draw_chance/priority_qualification/eligibility_qualification/blindbox',
  `reward_config` json DEFAULT NULL,
  `reward_status` tinyint NOT NULL DEFAULT 0 COMMENT '0=无奖励 1=已发 2=发放失败',
  `createtime`   int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`, `checkin_date`),
  KEY `idx_date` (`checkin_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='签到记录';

CREATE TABLE IF NOT EXISTS `invite_record` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT,
  `inviter_id`    int unsigned NOT NULL,
  `invitee_id`    int unsigned NOT NULL,
  `invitee_phone` varchar(20) NOT NULL DEFAULT '' COMMENT '脱敏存储',
  `reward_type`   varchar(32) DEFAULT NULL COMMENT 'collectible/points/draw_chance/priority_qualification/eligibility_qualification/blindbox',
  `reward_config` json DEFAULT NULL,
  `status`        tinyint NOT NULL DEFAULT 1 COMMENT '1=已发 0=未发',
  `createtime`    int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_inviter` (`inviter_id`),
  KEY `idx_invitee` (`invitee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='邀请记录';

CREATE TABLE IF NOT EXISTS `airdrop_task` (
  `id`           int unsigned NOT NULL AUTO_INCREMENT,
  `target_type`  tinyint NOT NULL COMMENT '1=藏品 2=盲盒',
  `target_id`    int unsigned NOT NULL,
  `target_name`  varchar(128) NOT NULL DEFAULT '',
  `quantity`     int unsigned NOT NULL DEFAULT 0 COMMENT '每人发放数量',
  `user_count`   int unsigned NOT NULL DEFAULT 0,
  `success_count` int unsigned NOT NULL DEFAULT 0,
  `fail_count`   int unsigned NOT NULL DEFAULT 0,
  `source`       varchar(20) NOT NULL DEFAULT 'standalone' COMMENT 'standalone独立空投/activity活动空投',
  `activity_type` varchar(50) NOT NULL DEFAULT '' COMMENT 'checkin/invite/register/lucky',
  `activity_id`  int unsigned NOT NULL DEFAULT 0,
  `reward_type`  tinyint NOT NULL DEFAULT 1 COMMENT 'activity_reward.reward_type 同义',
  `admin_id`     int unsigned NOT NULL DEFAULT 0,
  `admin_name`   varchar(50) NOT NULL DEFAULT '',
  `createtime`   int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='空投任务';

CREATE TABLE IF NOT EXISTS `airdrop_record` (
  `id`           int unsigned NOT NULL AUTO_INCREMENT,
  `task_id`      int unsigned NOT NULL,
  `user_id`      int unsigned NOT NULL,
  `phone`        varchar(20) NOT NULL DEFAULT '' COMMENT '脱敏存储',
  `status`       tinyint NOT NULL DEFAULT 0 COMMENT '1=成功 0=失败',
  `fail_reason`  varchar(255) NOT NULL DEFAULT '',
  `createtime`   int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_task` (`task_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='空投发放明细';

-- ------------------------------------------------------------
-- 管理员登录日志
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_login_log` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `admin_id`    int unsigned NOT NULL DEFAULT 0,
  `username`    varchar(32) NOT NULL DEFAULT '',
  `ip`          varchar(45) NOT NULL DEFAULT '',
  `user_agent`  varchar(500) NOT NULL DEFAULT '',
  `is_success`  tinyint(1) NOT NULL DEFAULT 0,
  `fail_reason` varchar(255) NOT NULL DEFAULT '',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_time` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='管理员登录日志';

SET FOREIGN_KEY_CHECKS = 1;
