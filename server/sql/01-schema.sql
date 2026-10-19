-- ============================================================
-- 闪光集市 H5 · 数据库结构 v1
--
-- 设计依据：原站 h5.gandart.shop 实测响应（/workspace/recon/raw/*.json）
-- 契约红线（docs/api/BACKEND-API-SPEC.md §1.5）：
--   1. 金额一律 decimal，API 层输出 string
--   2. 布尔一律 tinyint(1)，API 层输出 int 0/1
--   3. 「字符串包数字」字段（goods_type/batch_id/issuer_id/affiche.type）
--      必须用 varchar 存储，否则前端的 === '2' 判断会失效
--   4. 时间戳一律 int unsigned（unixtime），不用 datetime
--      —— 原站 createtime/release_time/batch.start_time 都是秒级整数
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 公告
-- 样本：index_afficheList.json  实测字段 6 个
--   id int / image / title / createtime int / type str / url / affiche_type
-- 注意：type 是 "1"~"5" 的字符串，不是整数
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `affiche`;
CREATE TABLE `affiche` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `title`       varchar(160) NOT NULL DEFAULT ''                COMMENT '公告标题',
  `image`       varchar(255) NOT NULL DEFAULT ''                COMMENT '封面图 OSS 相对路径',
  `type`        varchar(8)  NOT NULL DEFAULT ''                 COMMENT '公告类型ID，字符串型1-5',
  `affiche_type` varchar(32) NOT NULL DEFAULT ''                COMMENT '公告类型中文名（冗余，原站直接下发）',
  `url`         varchar(512) NOT NULL DEFAULT ''                COMMENT '跳转URL，空串表示站内公告',
  `content`     mediumtext   DEFAULT NULL                       COMMENT '详情HTML，仅详情接口返回',
  `publish_time` int unsigned NOT NULL DEFAULT '0'              COMMENT '发布时间戳',
  `createtime`  int unsigned NOT NULL DEFAULT '0'                COMMENT '创建时间戳',
  `diysort`     int          NOT NULL DEFAULT '0'               COMMENT '后台排序权重，越大越靠前',
  `is_show`     tinyint(1)   NOT NULL DEFAULT '1'               COMMENT '是否展示 0/1',
  PRIMARY KEY (`id`),
  KEY `idx_type`     (`type`),
  KEY `idx_createtime` (`createtime`),
  KEY `idx_show_sort` (`is_show`, `diysort`, `createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告';

-- ------------------------------------------------------------
-- 公告类型字典
-- 样本：index_affichetypelist.json 实测 5 条
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `affiche_type`;
CREATE TABLE `affiche_type` (
  `id`   tinyint unsigned NOT NULL,
  `name` varchar(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公告类型字典';

-- 运营字典：原站样本遗失（实测为 5 条），此处为默认字典；
-- 前端仅依赖 {id,name} 通用结构，可在库内增删。
INSERT INTO `affiche_type` (`id`, `name`) VALUES
  (1, '平台公告'),
  (2, '活动福利'),
  (3, '发售上新'),
  (4, '新手教程'),
  (5, '系统维护');

-- ------------------------------------------------------------
-- 发行方（盲盒嵌套对象 publisher 拆表）
-- 实测：{"name":"闪光","company_logo":"/uploads/20240118/xxx.jpg"}
-- 拆表原因：多个盲盒共享同一发行方，避免重复存储
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `publisher`;
CREATE TABLE `publisher` (
  `id`           int unsigned NOT NULL AUTO_INCREMENT,
  `name`         varchar(64)  NOT NULL DEFAULT '',
  `company_logo` varchar(255) NOT NULL DEFAULT '' COMMENT 'LOGO OSS 相对路径',
  `createtime`   int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='藏品发行方';

-- ------------------------------------------------------------
-- 批次（盲盒嵌套对象 batch 拆表，一对一）
-- 实测：{"start_time":1792224000,"end_time":1792231200,
--        "total_num":100000,"has_num":1}
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `blind_box_batch`;
CREATE TABLE `blind_box_batch` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `start_time` int unsigned NOT NULL DEFAULT '0'  COMMENT '开售时间戳',
  `end_time`   int unsigned NOT NULL DEFAULT '0'  COMMENT '截售时间戳',
  `total_num`  int unsigned NOT NULL DEFAULT '0'  COMMENT '本批次总量',
  `has_num`    int unsigned NOT NULL DEFAULT '0'  COMMENT '本批次已售',
  PRIMARY KEY (`id`),
  KEY `idx_time` (`start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盲盒批次';

-- ------------------------------------------------------------
-- 盲盒 / 藏品
-- 样本：box_blind_box_list.json 实测顶层 22 字段 + 2 个嵌套对象
--
-- 关键类型陷阱（实测确认，前端做字符串比较）：
--   goods_type = "2"    （字符串）
--   batch_id   = "14"   （字符串）
--   issuer_id  = "0"    （字符串）
--   type       = "collection"（字符串枚举）
-- 这四个字段全部 varchar，禁用 int。
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `blind_box`;
CREATE TABLE `blind_box` (
  `id`                    int unsigned NOT NULL AUTO_INCREMENT,
  `name`                  varchar(128) NOT NULL DEFAULT '',
  `listimg`               varchar(255) NOT NULL DEFAULT ''  COMMENT '列表图 OSS 相对路径',
  `main_image`            varchar(255) NOT NULL DEFAULT ''  COMMENT '主图 OSS 相对路径',
  `image`                 varchar(255) NOT NULL DEFAULT ''  COMMENT '详情图 OSS 相对路径',

  `price`                 decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '售价，API 输出 string',

  `total_num`             int unsigned NOT NULL DEFAULT '0'   COMMENT '总限量（前端首页读这个，不是 num）',
  `total_num_batch`       int unsigned NOT NULL DEFAULT '0'   COMMENT '批次总量冗余',
  `sale_number`           int unsigned NOT NULL DEFAULT '0'   COMMENT '已售数量',
  `flux`                  int unsigned NOT NULL DEFAULT '0'   COMMENT '流通量',
  `total_num_show`        int unsigned NOT NULL DEFAULT '0'   COMMENT '前台展示总量',

  `release_time`          int unsigned NOT NULL DEFAULT '0'   COMMENT '发售时间戳',
  `end_time`              int unsigned NOT NULL DEFAULT '0'   COMMENT '截售时间戳',
  `createtime`            int unsigned NOT NULL DEFAULT '0',

  `type`                  varchar(16)  NOT NULL DEFAULT 'collection' COMMENT '类型枚举 collection/... ',
  `goods_type`            varchar(8)   NOT NULL DEFAULT ''     COMMENT '商品类型，字符串型数字',
  `batch_id`              varchar(16)  NOT NULL DEFAULT ''     COMMENT '批次ID，字符串型数字',

  `soldout`               tinyint(1)   NOT NULL DEFAULT '0'   COMMENT '是否售罄 0/1',
  `fictitious_stock`      tinyint(1)   NOT NULL DEFAULT '0'   COMMENT '是否虚拟库存 0/1',
  `open_draw`             tinyint(1)   NOT NULL DEFAULT '0'   COMMENT '是否开放抽盒 0/1',

  `issuer_id`             varchar(16)  NOT NULL DEFAULT ''     COMMENT '发行方ID，字符串型数字',

  -- 嵌套对象落库后的关联ID
  `batch_ref`             int unsigned NOT NULL DEFAULT '0'   COMMENT '关联 blind_box_batch.id',
  `publisher_ref`         int unsigned NOT NULL DEFAULT '0'   COMMENT '关联 publisher.id',

  -- 实测存在的展示字段（原站直接下发，本地由格式化层生成）
  `release_date_time_text`   varchar(32)   NOT NULL DEFAULT ''  COMMENT 'Y-m-d H:i',
  `release_time_date_text`   varchar(32)   NOT NULL DEFAULT ''  COMMENT 'Y-m-d H:i:s',
  `sale_status_text`         int           NOT NULL DEFAULT '0',
  `release_time_text`        int           NOT NULL DEFAULT '0',
  `diysort`                  int           NOT NULL DEFAULT '0',

  `is_show`               tinyint(1)   NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_type`      (`type`),
  KEY `idx_goods_type`(`goods_type`),
  KEY `idx_batch`     (`batch_ref`),
  KEY `idx_publisher` (`publisher_ref`),
  KEY `idx_release`   (`release_time`),
  KEY `idx_show_sort` (`is_show`, `diysort`, `release_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盲盒/藏品';

-- ------------------------------------------------------------
-- 用户
-- 🔴 user 表不在此文件定义 —— canonical 定义在 04-trade-core.sql
--    （105 列，严格对齐原站 user/getUserInfo 实测）。
--    本文件原有一份 12 列骨架表（抓到真实样本前按前端注释搭的），
--    与 04 重复定义构成「后应用者静默获胜」陷阱：
--    若有人按 01→04 以外的顺序灌库（或单独补灌 01），user 会被
--    降级回骨架表，seed.php 立刻报列不存在——与 collection 表
--    在 03/04 重复定义的存量坑同族（2026-10-18 修 collection 时
--    漏了这张，本次一并拆掉）。
--    04 的 DROP TABLE IF EXISTS user + CREATE 是自包含的，
--    删掉本定义不影响任何灌库顺序。
-- ------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 1;