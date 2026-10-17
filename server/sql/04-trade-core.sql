-- ============================================================
-- 04-trade-core.sql  —— P1 交易核心表
-- 数据来源：原站实测 JSON（/workspace/recon/raw/auth/*.json）
-- 字段映射表：/workspace/recon/schema-map/measured-fields.txt
-- 铁律：所有字段必须能追溯到实测样本，禁止按端点名猜字段
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. user —— 用户主表
--    实测：user/getUserInfo 返回 106 个叶子字段（含 verification/statistics 两个嵌套对象）
--    注意：money/agent_money/collection_total_price 等金额在 API 层是 string，
--          DB 用 decimal，Formatter 负责转 string
--    注意：ore 类字段（user_ore 等）是 6 位小数的链上数量，单独用 decimal(24,6)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user` (
  `id`                        int unsigned NOT NULL AUTO_INCREMENT,
  `group_id`                  int unsigned NOT NULL DEFAULT 0 COMMENT '分销组',
  `username`                  varchar(64)  NOT NULL DEFAULT '' COMMENT '登录名',
  `nickname`                  varchar(64)  NOT NULL DEFAULT '' COMMENT '昵称',
  `email`                     varchar(128) NOT NULL DEFAULT '' COMMENT '邮箱',
  `mobile`                    varchar(32)  NOT NULL DEFAULT '' COMMENT '手机号',
  `avatar`                    varchar(255) NOT NULL DEFAULT '' COMMENT '头像路径',
  `level`                     int          NOT NULL DEFAULT 1,
  `gender`                    tinyint(1)   NOT NULL DEFAULT 0 COMMENT '0未知 1男 2女',
  `birthday`                  int unsigned DEFAULT NULL COMMENT '生日 unixtime｜实测原站下发 null，必须可空',
  `bio`                       varchar(500) NOT NULL DEFAULT '' COMMENT '个人简介',
  `money`                     decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '余额',
  `agent_money`               decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '佣金余额',
  `score`                     int          NOT NULL DEFAULT 0 COMMENT '积分',
  `successions`               int          NOT NULL DEFAULT 0 COMMENT '连续签到天数',
  `maxsuccessions`            int          NOT NULL DEFAULT 0 COMMENT '最大连续签到',
  `prevtime`                  int unsigned NOT NULL DEFAULT 0 COMMENT '上次签到时间',
  `logintime`                 int unsigned NOT NULL DEFAULT 0,
  `loginip`                   varchar(64)  NOT NULL DEFAULT '',
  `loginfailure`              int          NOT NULL DEFAULT 0 COMMENT '连续登录失败次数',
  `joinip`                    varchar(64)  NOT NULL DEFAULT '',
  `jointime`                  int unsigned NOT NULL DEFAULT 0,
  `createtime`                int unsigned NOT NULL DEFAULT 0,
  `updatetime`                int unsigned NOT NULL DEFAULT 0,
  `status`                    varchar(32)  NOT NULL DEFAULT 'normal' COMMENT 'normal/frozen',
  `email_verified`            tinyint(1)   NOT NULL DEFAULT 0 COMMENT '来自 verification.email',
  `mobile_verified`           tinyint(1)   NOT NULL DEFAULT 0 COMMENT '来自 verification.mobile',
  `team_id`                   int unsigned NOT NULL DEFAULT 0,
  `inviter_user_id`           int unsigned NOT NULL DEFAULT 0,
  `inviter_code`              varchar(32)  NOT NULL DEFAULT '' COMMENT '邀请码 Q+uid',
  `new_inviter_user_id`       int unsigned NOT NULL DEFAULT 0,
  `sq_user_id`                int unsigned NOT NULL DEFAULT 0,
  `yao_count`                 int          NOT NULL DEFAULT 0 COMMENT '摇一摇次数',
  `yao_count_uptime`          int unsigned NOT NULL DEFAULT 0,
  `real_name_authentication`  tinyint(1)   NOT NULL DEFAULT 0,
  `real_name`                 varchar(64)  NOT NULL DEFAULT '',
  `id_card`                   varchar(64)  NOT NULL DEFAULT '' COMMENT '实名证件号（加密存储）',
  `id_card_front`             varchar(255) NOT NULL DEFAULT '',
  `id_card_back`              varchar(255) NOT NULL DEFAULT '',
  `real_name_time`            int unsigned NOT NULL DEFAULT 0,
  `first_buy_time`            int unsigned NOT NULL DEFAULT 0,
  `collection_total_price`    decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '持有藏品总价',
  `collection_num`            int          NOT NULL DEFAULT 0 COMMENT '持有藏品数',
  `blind_box_num`             int          NOT NULL DEFAULT 0 COMMENT '持有盲盒数',
  `buy_collection_total_price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计买入',
  `freeze`                    int          NOT NULL DEFAULT 0 COMMENT '冻结数量',
  `ali_openid`                varchar(64)  DEFAULT NULL,
  `option_pass`               tinyint(1)   DEFAULT NULL,
  `is_white`                  tinyint(1)   NOT NULL DEFAULT 0 COMMENT '白名单',
  `service_price`             decimal(12,2) NOT NULL DEFAULT 0.00,
  `recharge_price`            decimal(12,2) NOT NULL DEFAULT 0.00,
  `xin_count`                 int          NOT NULL DEFAULT 0,
  `pay_password`              tinyint(1)   NOT NULL DEFAULT 0 COMMENT '是否设置支付密码',
  `inviter_status`            tinyint(1)   NOT NULL DEFAULT 0,
  `ll_id`                     varchar(64)  DEFAULT NULL,
  `virtual`                   tinyint(1)   NOT NULL DEFAULT 0,
  `market_open`               tinyint(1)   NOT NULL DEFAULT 1 COMMENT '是否允许市场交易',
  `person_intro`              varchar(500) DEFAULT NULL,
  `has_bank`                  tinyint(1)   NOT NULL DEFAULT 0,
  `yttime`                    int unsigned DEFAULT NULL,
  `crystal_yellow`            int          NOT NULL DEFAULT 0,
  `crystal_purple`            int          NOT NULL DEFAULT 0,
  `crystal_blue`              int          NOT NULL DEFAULT 0,
  `share_status`              tinyint(1)   NOT NULL DEFAULT 0,
  `share_success_time`        int unsigned DEFAULT NULL,
  `huifu_service_charge`      tinyint(1)   NOT NULL DEFAULT 1,
  `sandpay_service_charge`    tinyint(1)   NOT NULL DEFAULT 0,
  `yifutong_service_charge`   tinyint(1)   NOT NULL DEFAULT 0,
  `ntf_url`                   varchar(255) DEFAULT NULL,
  `invite_type`               tinyint(1)   NOT NULL DEFAULT 1,
  `yibao_status`              tinyint(1)   NOT NULL DEFAULT 0,
  `tipay_status`              tinyint(1)   NOT NULL DEFAULT 0,
  `sandpay_status`            tinyint(1)   NOT NULL DEFAULT 0,
  `yifutong_status`           tinyint(1)   NOT NULL DEFAULT 0,
  `show_wallet`               tinyint(1)   NOT NULL DEFAULT 1,
  `nft_url`                   varchar(255) DEFAULT NULL,
  `private_key`               varchar(255) DEFAULT NULL COMMENT '敏感，加密存储',
  `random_name`               tinyint(1)   NOT NULL DEFAULT 0,
  `is_show_rank`              tinyint(1)   NOT NULL DEFAULT 1,
  `user_wx`                   varchar(64)  DEFAULT NULL,
  `user_qq`                   varchar(64)  DEFAULT NULL,
  `user_phone`                varchar(32)  DEFAULT NULL,
  `account`                   varchar(64)  NOT NULL DEFAULT '' COMMENT '提现账户',
  `huifu_account`             tinyint(1)   NOT NULL DEFAULT 1,
  `user_ore`                  decimal(24,6) NOT NULL DEFAULT 0.000000,
  `team_ore`                  decimal(24,6) NOT NULL DEFAULT 0.000000,
  `person_ore`                decimal(24,6) NOT NULL DEFAULT 0.000000,
  `help_invited_user_product_ore_number` decimal(24,6) NOT NULL DEFAULT 0.000000,
  `rabbit_team_status`        tinyint(1)   NOT NULL DEFAULT 0,
  `rabbit_person_status`      tinyint(1)   NOT NULL DEFAULT 0,
  `invited_user_buy_rabbit_collection_person` int NOT NULL DEFAULT 0,
  `is_first_one_market_buy_rabbit` tinyint(1) NOT NULL DEFAULT 0,
  `begin_team_rabbit_time`    int unsigned DEFAULT NULL,
  `medals_number`             decimal(12,2) NOT NULL DEFAULT 0.00,
  `transfer_permission`       tinyint(1)   NOT NULL DEFAULT 0,
  `transfer_permission_level` tinyint(1)   NOT NULL DEFAULT 1,
  `vip_time`                  int unsigned NOT NULL DEFAULT 0,
  `sync_time`                 int unsigned NOT NULL DEFAULT 0,
  `r_status`                  tinyint(1)   NOT NULL DEFAULT 0,
  `is_vip`                    tinyint(1)   NOT NULL DEFAULT 0,
  -- 以下为 statistics 嵌套对象的统计字段，由定时任务回写，不接受用户直接写入
  `invited_count`             int          NOT NULL DEFAULT 0 COMMENT 'statistics.invited_count',
  `buy_count`                 int          NOT NULL DEFAULT 0 COMMENT 'statistics.buy_count',
  `sale_count`                int          NOT NULL DEFAULT 0 COMMENT 'statistics.sale_count',
  `join_day`                  int          NOT NULL DEFAULT 0 COMMENT 'statistics.join_day',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mobile` (`mobile`),
  UNIQUE KEY `uk_inviter_code` (`inviter_code`),
  KEY `idx_inviter` (`inviter_user_id`),
  KEY `idx_createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户主表（实测 106 叶子字段）';

-- ------------------------------------------------------------
-- 2. user_token —— 会话令牌
--    实测：登录返回 token(uuid) + expiretime + expires_in=2592000(30天)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `user_token`;
CREATE TABLE `user_token` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`     int unsigned NOT NULL,
  `token`       char(64)     NOT NULL COMMENT 'token 的 sha256 哈希(64 hex)，非明文——原站明文 token 是 36 位，存哈希后需 64 位',
  `device`      varchar(64)  NOT NULL DEFAULT '',
  `ip`          varchar(64)  NOT NULL DEFAULT '',
  `expiretime`  int unsigned NOT NULL COMMENT '过期时间 unixtime',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expire` (`expiretime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户登录令牌';

-- ------------------------------------------------------------
-- 3. collection —— 藏品主表（已开盒的藏品定义）
--    实测：user_collection/antMycollection 内嵌 collection 实测 10 字段
--    注意：与 blind_box（未开盒商品）是两个不同实体，不可合表
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `collection`;
CREATE TABLE `collection` (
  `id`                 int unsigned NOT NULL AUTO_INCREMENT,
  `name`               varchar(128) NOT NULL DEFAULT '',
  `main_image`         varchar(255) NOT NULL DEFAULT '',
  `listimg`            varchar(255) NOT NULL DEFAULT '',
  `collection_level`   varchar(32)  NOT NULL DEFAULT '' COMMENT '稀有度等级',
  `total_num_show`     int          NOT NULL DEFAULT 0 COMMENT '前台展示总��量',
  `total_num`          int unsigned NOT NULL DEFAULT 0 COMMENT '真实总发行量',
  `is_img_diff`        tinyint(1)   NOT NULL DEFAULT 0 COMMENT '是否隐藏主图',
  `unfreeze_collection_id` int unsigned NOT NULL DEFAULT 0,
  `freeze_time`        int unsigned NOT NULL DEFAULT 0,
  `series_id`          int unsigned NOT NULL DEFAULT 0,
  `publisher_id`       int unsigned NOT NULL DEFAULT 0,
  `createtime`         int unsigned NOT NULL DEFAULT 0,
  `updatetime`         int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_series` (`series_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='藏品定义（已开盒）';

-- ------------------------------------------------------------
-- 4. user_collection —— 用户藏品持有表
--    实测：持有记录 8 字段 + collection 嵌套 10 字段
--    关键类型陷阱：sell_num 是 string，num/lock_num/is_collection 是 int
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `user_collection`;
CREATE TABLE `user_collection` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`        int unsigned NOT NULL,
  `collection_id`  int unsigned NOT NULL,
  `num`            int          NOT NULL DEFAULT 0 COMMENT '持有数量',
  `sell_num`       int          NOT NULL DEFAULT 0 COMMENT '已售数量（API 输出 string）',
  `lock_num`       int          NOT NULL DEFAULT 0 COMMENT '锁定/质押数量',
  `is_collection`  tinyint(1)   NOT NULL DEFAULT 1 COMMENT '是否已归集',
  `can_compound`   tinyint(1)   NOT NULL DEFAULT 0 COMMENT '是否可合成',
  `utime`          int unsigned NOT NULL DEFAULT 0 COMMENT '变动时间',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_collection` (`user_id`,`collection_id`),
  KEY `idx_collection` (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户藏品持有';

-- ------------------------------------------------------------
-- 5. user_collection_record —— 藏品变动流水
--    对应端点 user_collection/getRecord（实测参数未破解，字段来自变动语义 + antMycollection 的 utime）
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `user_collection_record`;
CREATE TABLE `user_collection_record` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`        int unsigned NOT NULL,
  `collection_id`  int unsigned NOT NULL,
  `change_num`     int          NOT NULL DEFAULT 0 COMMENT '变动数量，卖出为负',
  `type`           tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1开盒 2买入 3卖出 4合成 5转赠 6冻结',
  `ref_id`         int unsigned NOT NULL DEFAULT 0 COMMENT '关联业务单据ID',
  `remark`         varchar(255) NOT NULL DEFAULT '',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`,`createtime`),
  KEY `idx_collection` (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='藏品变动流水';

-- ------------------------------------------------------------
-- 6. artel_order —— 统一订单表（多态）
--    实测：order/orderList 18 字段
--    多态依据：artel_order 区分业务场景，实测值 mh_secondary_market_order
--    实测类型陷阱：num/order_type 是 string，order_status 是 int，
--                   uc_ids 存在 null 与 "121926" 两种形态 → varchar 可空
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `artel_order`;
CREATE TABLE `artel_order` (
  `id`                 int unsigned NOT NULL AUTO_INCREMENT,
  `order_sn`           varchar(64)  NOT NULL COMMENT '订单号',
  `user_id`            int unsigned NOT NULL,
  `artel_order`        varchar(64)  NOT NULL DEFAULT '' COMMENT '业务场景标识',
  `order_type`         varchar(16)  NOT NULL DEFAULT '' COMMENT '子类型（API 输出 string）',
  `order_status`       int          NOT NULL DEFAULT 0 COMMENT '状态：实测 2/3',
  `total_price`        decimal(12,2) NOT NULL DEFAULT 0.00,
  `num`                varchar(16)  NOT NULL DEFAULT '1' COMMENT '数量（API 输出 string）',
  `goods_id`           int unsigned NOT NULL DEFAULT 0,
  `goods_type`         tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1盲盒 2藏品',
  `goods_name`         varchar(255) NOT NULL DEFAULT '',
  `goods_cover_image`  varchar(255) NOT NULL DEFAULT '',
  `series_name`        varchar(128) NOT NULL DEFAULT '',
  `crystal_type`       varchar(32)  NOT NULL DEFAULT 'crystal_no',
  `crystal_number`     varchar(32)  NOT NULL DEFAULT '0',
  `uc_ids`             varchar(255) DEFAULT NULL COMMENT '关联 user_collection.id，逗号分隔；可为空',
  `pay_channel`        varchar(32)  NOT NULL DEFAULT '' COMMENT 'huifu/sandpay/yifutong',
  `pay_time`           int unsigned NOT NULL DEFAULT 0,
  `createtime`         int unsigned NOT NULL DEFAULT 0,
  `updatetime`         int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_sn` (`order_sn`),
  KEY `idx_user_time` (`user_id`,`createtime`),
  KEY `idx_status` (`order_status`),
  KEY `idx_goods` (`goods_id`,`goods_type`),
  KEY `idx_artel` (`artel_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='统一订单表（多态，实测 18 字段）';

-- ------------------------------------------------------------
-- 7. artel_order_item —— 订单明细
--    实测中 collection_code 为数组（编号列表），单独拆表
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `artel_order_item`;
CREATE TABLE `artel_order_item` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `order_id`       int unsigned NOT NULL,
  `order_sn`       varchar(64)  NOT NULL DEFAULT '',
  `user_id`        int unsigned NOT NULL,
  `collection_id`  int unsigned NOT NULL DEFAULT 0,
  `collection_code` varchar(64) NOT NULL DEFAULT '' COMMENT '藏品编号',
  `num`            int          NOT NULL DEFAULT 1,
  `price`          decimal(12,2) NOT NULL DEFAULT 0.00,
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_code` (`collection_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订单明细';

-- ------------------------------------------------------------
-- 8. market_goods —— 市场挂单
--    实测：getMarketGoodsListByGoodsId 返回 list/count/ltnum
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `market_goods`;
CREATE TABLE `market_goods` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`        int unsigned NOT NULL COMMENT '挂单人',
  `collection_id`  int unsigned NOT NULL,
  `collection_code` varchar(64) NOT NULL DEFAULT '',
  `price`          decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '挂单价',
  `num`            int          NOT NULL DEFAULT 1 COMMENT '剩余数量',
  `sold_num`       int          NOT NULL DEFAULT 0,
  `status`         tinyint(1)   NOT NULL DEFAULT 1 COMMENT '1在售 2已售 3已下架 4已锁定',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `updatetime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_collection_status` (`collection_id`,`status`),
  KEY `idx_user` (`user_id`),
  KEY `idx_price` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='市场挂单';

-- ------------------------------------------------------------
-- 9. beg_buy_order —— 求购单
--    实测：begBuyList 必填 collection_id + sort(合法值 desc)，返回标准分页信封
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `beg_buy_order`;
CREATE TABLE `beg_buy_order` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`        int unsigned NOT NULL COMMENT '求购人',
  `collection_id`  int unsigned NOT NULL,
  `price`          decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '求购价',
  `num`            int          NOT NULL DEFAULT 1 COMMENT '求购数量',
  `filled_num`     int          NOT NULL DEFAULT 0,
  `status`         tinyint(1)   NOT NULL DEFAULT 1 COMMENT '1求购中 2已完成 3已取消',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `updatetime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_collection_status` (`collection_id`,`status`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='求购单';

-- ------------------------------------------------------------
-- 10. consignment —— 寄售
--    实测：order/myConsignment 返回标准分页；tradingInformation 统计字段
--    实测统计键：transaction_record_number / current_buying /
--                current_consignment / floor_price / today_amount /
--                today_volume / today_highest_price / today_lowest_price
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `consignment`;
CREATE TABLE `consignment` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`        int unsigned NOT NULL,
  `collection_id`  int unsigned NOT NULL,
  `collection_code` varchar(64) NOT NULL DEFAULT '',
  `num`            int          NOT NULL DEFAULT 1,
  `price`          decimal(12,2) NOT NULL DEFAULT 0.00,
  `sold_num`       int          NOT NULL DEFAULT 0,
  `status`         tinyint(1)   NOT NULL DEFAULT 1 COMMENT '1寄售中 2已售出 3已撤回',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `updatetime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_collection` (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='寄售记录';

-- ------------------------------------------------------------
-- 11. message —— 用户消息
--    实测：user/newList 返回 list[] + count
--    关键：createtime 是格式化字符串 "2026-10-15 15:38"，由 Formatter 转换
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `message`;
CREATE TABLE `message` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`     int unsigned NOT NULL,
  `title`       varchar(128) NOT NULL DEFAULT '',
  `content`     varchar(1000) NOT NULL DEFAULT '',
  `read_status` tinyint(1)   NOT NULL DEFAULT 0 COMMENT '0未读 1已读',
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`read_status`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户消息';

-- ------------------------------------------------------------
-- 12. synthesis_theme —— 合成主题
--    实测：synthesis/themeList theme 实测 6 字段
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `synthesis_theme`;
CREATE TABLE `synthesis_theme` (
  `id`        int unsigned NOT NULL AUTO_INCREMENT,
  `types`     tinyint(1)   NOT NULL DEFAULT 1,
  `name`      varchar(128) NOT NULL DEFAULT '',
  `image`     varchar(255) NOT NULL DEFAULT '',
  `rule_text` text         COMMENT '合成规则文案',
  `sort`      int          NOT NULL DEFAULT 0,
  `status`    tinyint(1)   NOT NULL DEFAULT 1,
  `createtime` int unsigned NOT NULL DEFAULT 0,
  `updatetime` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合成主题';

-- ------------------------------------------------------------
-- 13. synthesis_plan —— 合成方案（theme.play[]）
--    实测：play 实测 16 字段
--    关键：start_time/end_time 为 13 位毫秒时间戳
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `synthesis_plan`;
CREATE TABLE `synthesis_plan` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `theme_id`       int unsigned NOT NULL,
  `activity_id`    int unsigned NOT NULL DEFAULT 0,
  `title`          varchar(255) NOT NULL DEFAULT '',
  `image`          varchar(255) NOT NULL DEFAULT '',
  `description`    text,
  `start_time`     bigint       NOT NULL DEFAULT 0 COMMENT '毫秒时间戳（实测 13 位）',
  `end_time`       bigint       NOT NULL DEFAULT 0 COMMENT '毫秒时间戳',
  `real_start_time` bigint      NOT NULL DEFAULT 0,
  `real_end_time`  bigint       NOT NULL DEFAULT 0,
  `is_auto`        tinyint(1)   NOT NULL DEFAULT 0,
  `is_delay`       tinyint(1)   NOT NULL DEFAULT 0,
  `is_advance`     tinyint(1)   NOT NULL DEFAULT 0,
  `advance_date`   int          NOT NULL DEFAULT 0,
  `delay_date`     int          NOT NULL DEFAULT 0,
  `stock`          int          NOT NULL DEFAULT 0,
  `classify`       varchar(32)  NOT NULL DEFAULT 'regular',
  `type`           varchar(16)  NOT NULL DEFAULT '10',
  `need_items`     text         COMMENT '合成所需材料 JSON，由方案表引用',
  `status`         tinyint(1)   NOT NULL DEFAULT 1,
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `updatetime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_theme` (`theme_id`),
  KEY `idx_time` (`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合成方案';

-- ------------------------------------------------------------
-- 14. lottery —— 抽奖活动
--    实测：lottery/info 返回 46 字段（数值普遍以 string 下发）
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `lottery`;
CREATE TABLE `lottery` (
  `id`                 int unsigned NOT NULL AUTO_INCREMENT,
  `name`               varchar(255) NOT NULL DEFAULT '',
  `content`            longtext     COMMENT '活动说明 HTML',
  `image1`             varchar(255) NOT NULL DEFAULT '',
  `image2`             varchar(255) NOT NULL DEFAULT '',
  `crystal`            varchar(32)  NOT NULL DEFAULT '10' COMMENT '消耗水晶类型',
  `lottery_max`        varchar(32)  NOT NULL DEFAULT '0' COMMENT '奖池总量（string 下发）',
  `grand_total`        int unsigned NOT NULL DEFAULT 0 COMMENT '已抽出数量',
  `highest`            varchar(32)  NOT NULL DEFAULT '0',
  `highest_sp_id`      varchar(64)  NOT NULL DEFAULT '',
  `snapshot_ids`       varchar(255) NOT NULL DEFAULT '',
  `is_limit`           tinyint(1)   NOT NULL DEFAULT 0,
  `is_recycle`         tinyint(1)   NOT NULL DEFAULT 0,
  `rule`               varchar(32)  NOT NULL DEFAULT '10',
  `type`               varchar(16)  NOT NULL DEFAULT '20',
  `switch`             tinyint(1)   NOT NULL DEFAULT 1,
  `start`              tinyint(1)   NOT NULL DEFAULT 0,
  `start_sp_id`        varchar(64)  NOT NULL DEFAULT '',
  `display_number`     int          NOT NULL DEFAULT 1,
  `display_lottery_log` tinyint(1)  NOT NULL DEFAULT 1,
  `integral_1`         varchar(32)  NOT NULL DEFAULT '0',
  `integral_5`         varchar(32)  NOT NULL DEFAULT '0',
  `free_number`        int          NOT NULL DEFAULT 0 COMMENT '免费次数',
  `is_consecutive_draws_1` tinyint(1) NOT NULL DEFAULT 0,
  `is_consecutive_draws_5` tinyint(1) NOT NULL DEFAULT 0,
  `collection_json`    text         COMMENT '奖池藏品 JSON',
  `start_time`         int unsigned NOT NULL DEFAULT 0,
  `end_time`           int unsigned NOT NULL DEFAULT 0,
  `createtime`         int unsigned NOT NULL DEFAULT 0,
  `updatetime`         int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_time` (`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖活动（实测 46 字段）';

-- ------------------------------------------------------------
-- 15. lottery_record —— 抽奖记录
--    实测：lotteryList[] 实测 6 字段
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `lottery_record`;
CREATE TABLE `lottery_record` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `lottery_id`  int unsigned NOT NULL,
  `user_id`     int unsigned NOT NULL,
  `collection_id` int unsigned NOT NULL DEFAULT 0,
  `lottery_lv`  varchar(128) NOT NULL DEFAULT '' COMMENT '中奖名称',
  `score`       int          NOT NULL DEFAULT 0,
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_lottery` (`lottery_id`),
  KEY `idx_user_time` (`user_id`,`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='抽奖记录';

-- ------------------------------------------------------------
-- 16. union —— 公会
--    实测：union/user/myunion 的 union 对象字段（空样本）
--    config 6 字段单独建表
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `union_config`;
CREATE TABLE `union_config` (
  `id`                      int unsigned NOT NULL AUTO_INCREMENT,
  `user_num`                int          NOT NULL DEFAULT 0 COMMENT '最大成员数',
  `have_rate`               decimal(6,2) NOT NULL DEFAULT 0.00,
  `be_invite_back_rate`     decimal(6,2) NOT NULL DEFAULT 0.00,
  `have_be_invite_back_rate` decimal(6,2) NOT NULL DEFAULT 0.00,
  `manager_user_rate`       decimal(6,2) NOT NULL DEFAULT 0.00,
  `user_rate`               decimal(6,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公会配置（实测 6 字段）';

DROP TABLE IF EXISTS `union`;
CREATE TABLE `union` (
  `id`          int unsigned NOT NULL AUTO_INCREMENT,
  `name`        varchar(128) NOT NULL DEFAULT '',
  `avatar`      varchar(255) NOT NULL DEFAULT '',
  `user_id`     int unsigned NOT NULL COMMENT '会长',
  `sort`        int          NOT NULL DEFAULT 0 COMMENT '排名',
  `status`      tinyint(1)   NOT NULL DEFAULT 1,
  `createtime`  int unsigned NOT NULL DEFAULT 0,
  `updatetime`  int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公会';

DROP TABLE IF EXISTS `union_member`;
CREATE TABLE `union_member` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `union_id`   int unsigned NOT NULL,
  `user_id`    int unsigned NOT NULL,
  `power`      int          NOT NULL DEFAULT 0 COMMENT '权限：实测 my.power=0',
  `is_manage`  tinyint(1)   NOT NULL DEFAULT 0,
  `is_join`    tinyint(1)   NOT NULL DEFAULT 1,
  `sort`       varchar(32)  NOT NULL DEFAULT '' COMMENT 'my.sort 实测是字符串',
  `createtime` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_union_user` (`union_id`,`user_id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公会成员';

SET FOREIGN_KEY_CHECKS = 1;
