-- ============================================================
-- 闪光集市 H5 · P0 批次补充表（banner / site_config / collection）
--
-- 设计依据：docs/db/DATABASE-DESIGN.md
-- 字段来源：原站实测响应（/workspace/recon/raw/）
--   banner       → banner_show_getBanner.json   实测 5 字段
--   site_config  → index_getVersion.json        实测 {version,download_url}
--   collection   → docs/api/API.md §4.3        实测 10 字段
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 首页轮播图
-- 实测样本：data.data[0] = {id:2, image, url:"", url_type:1, is_show:1}
-- ⚠️ url_type 是 int 枚举（实测 1），url 为空串表示站内跳转
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `banner`;
CREATE TABLE `banner` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `title`      varchar(128) NOT NULL DEFAULT ''   COMMENT '轮播标题（列表接口不下发）',
  `image`      varchar(255) NOT NULL DEFAULT ''   COMMENT '图片 OSS 相对路径',
  `url`        varchar(512) NOT NULL DEFAULT ''   COMMENT '跳转 URL，空串为站内',
  `url_type`   int          NOT NULL DEFAULT '1'  COMMENT '跳转类型 1站内 2外链',
  `sort`       int          NOT NULL DEFAULT '0'  COMMENT '排序，越大越靠前',
  `is_show`    tinyint(1)   NOT NULL DEFAULT '1'  COMMENT '是否展示 0/1',
  `createtime` int unsigned NOT NULL DEFAULT '0',
  `updatetime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_show_sort` (`is_show`, `sort`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='首页轮播图';

-- ------------------------------------------------------------
-- 站点配置（KV 型）
-- 来源：/index/getVersion 实测 {version:"1.0.0", download_url:""}
--       /index/getConfig  实测无 key 时返回 code=0 '缺少key值'
--
-- ⚠️ 不建 protocols 表：实测 /index/index/agreement 等 3 个端点
--    返回的是同一份 dict（usageterms + privacy_agreement，7 万字 HTML），
--    属静态内容，建议放静态文件而非数据库。
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `site_config`;
CREATE TABLE `site_config` (
  `key`        varchar(64)  NOT NULL COMMENT '配置键，对应 getConfig 的 key 入参',
  `value`      text         DEFAULT NULL COMMENT '配置值',
  `group_name` varchar(32)  NOT NULL DEFAULT 'base' COMMENT '分组，便于后台归类',
  `remark`     varchar(255) NOT NULL DEFAULT ''  COMMENT '说明',
  `updatetime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`key`),
  KEY `idx_group` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站点配置';

-- ------------------------------------------------------------
-- 藏品（已开盒的藏品实例）
-- 来源：docs/api/API.md §4.3 实测 10 字段
--
-- ⚠️ collection 与 blind_box 的区别（极易混淆）：
--    blind_box   = 未开盒的盒子，有 batch/open_draw/goods_type
--    collection  = 已开盒的藏品，有 collection_level/unfreeze_collection_id/freeze_time
--    两者不可合表。开盒动作 = blind_box → user_collection + collection
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `collection`;
CREATE TABLE `collection` (
  `id`                    int unsigned NOT NULL AUTO_INCREMENT,
  `name`                  varchar(128) NOT NULL DEFAULT ''  COMMENT '藏品名',
  `main_image`            varchar(255) NOT NULL DEFAULT ''  COMMENT '主图',
  `listimg`               varchar(255) NOT NULL DEFAULT ''  COMMENT '列表图（可与主图不同）',

  `collection_level`      varchar(16)  NOT NULL DEFAULT ''  COMMENT '藏品等级，实测为空串',
  `series_id`             int unsigned NOT NULL DEFAULT '0'  COMMENT '所属系列',
  `issuer_id`             int unsigned NOT NULL DEFAULT '0'  COMMENT '发行方，关联 publisher.id',

  `total_num_show`        int unsigned NOT NULL DEFAULT '0'  COMMENT '展示用发行量',
  `total_num`             int unsigned NOT NULL DEFAULT '0'  COMMENT '真实发行量',
  `is_img_diff`           tinyint(1)   NOT NULL DEFAULT '0'  COMMENT '图片是否差异化 0/1',

  `unfreeze_collection_id`int unsigned NOT NULL DEFAULT '0'  COMMENT '解冻关联藏品 id',
  `freeze_time`           int unsigned NOT NULL DEFAULT '0'  COMMENT '冻结时间戳',

  -- 以下 4 个字段实测来自 market/trade/collectionDetail 的「顶层」（与 name/listimg 同级），
  -- 不在 floor_price 嵌套里。原站值 reprice_limit=999999999 / max_buy_price=999999999 表示不限。
  `reprice_limit`         int unsigned NOT NULL DEFAULT '0'    COMMENT '重新定价上限，999999999=不限',
  `reprice_min_limit`     varchar(16)  NOT NULL DEFAULT '0.00' COMMENT '重新定价下限，实测 string "0.00"',
  `reprice_beg_max_limit` varchar(16)  NOT NULL DEFAULT '0.00' COMMENT '重新定价起始上限，实测 string "0.00"',
  `max_buy_price`         int unsigned NOT NULL DEFAULT '0'    COMMENT '最高买入价，999999999=不限',

  `diysort`               int          NOT NULL DEFAULT '0',
  `is_show`               tinyint(1)   NOT NULL DEFAULT '1',
  `createtime`            int unsigned NOT NULL DEFAULT '0',
  `updatetime`            int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_series`   (`series_id`),
  KEY `idx_issuer`   (`issuer_id`),
  KEY `idx_show_sort`(`is_show`, `diysort`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='藏品（已开盒实例）';

-- ------------------------------------------------------------
-- 藏品系列（原站 /index/getSeries 实测返回空数组，
--          但端点存在且前端 pages/index 在用，仍需建表）
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `series`;
CREATE TABLE `series` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `name`       varchar(64)  NOT NULL DEFAULT ''   COMMENT '系列名，如「历史区」「盲盒区」',
  `type`       int          NOT NULL DEFAULT '10' COMMENT '系列类型（实测枚举待确认）',
  `sort`       int          NOT NULL DEFAULT '0',
  `is_show`    tinyint(1)   NOT NULL DEFAULT '1',
  `createtime` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_show_sort` (`is_show`, `sort`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='藏品系列/分区';