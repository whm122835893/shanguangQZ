-- ============================================================
-- 06-market-rule-payment.sql  —— 交易规则与支付渠道
--
-- 依据：本轮 174 个端点全量测绘（docs/db/ENDPOINT-SURVEY.md）
-- 实测样本：
--   recon/raw2/market_trade_collectiondetail.json     藏品详情（含 floor_price 嵌套）
--   recon/raw2/market_prepayment_collectiondetail.json 相关金额（同一套规则，无嵌套）
--   recon/raw2/wallet_huifu_wallet.json               汇付钱包开户信息
--   recon/raw/auth/user_getUserInfo.json               渠道开关与费率标志（12 字段）
--
-- 铁律：所有字段可追溯到上述样本，禁止按端点名猜字段
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. consignment_price_rule —— 寄售限价与费率规则
--
-- 实测依据（两个端点交叉验证，12 个字段完全一致）：
--   market/trade/collectionDetail  → data.floor_price.{12 字段}
--   market/Prepayment/collectionDetail → data.{12 字段}（同结构，去掉嵌套）
--
-- ⚠️ 关键陷阱：pay_platform_service_rate 与 platform_service_price
--    实测值都是 "0.07"，但语义完全不同：
--      pay_platform_service_rate = 平台服务费率（比率，0.07 = 7%）
--      platform_service_price   = 平台服务费（金额，随价格变化）
--    建表必须用不同列名 + 不同注释，绝不能合并成一列。
--
-- ⚠️ 类型陷阱（实测）：
--    minprice / price_pro / low_price 是 **int**（20 / 1 / 20）
--    price_price_ratio / *_rate / *_price / real_price 是 **string**（"100" / "0.07" / "0.93"）
--
-- ⚠️ 归属修正（实测核对后）：
--    reprice_limit / reprice_min_limit / reprice_beg_max_limit / max_buy_price
--    这 4 个字段在原站是**藏品顶层字段**（与 id/name/listimg 同级），
--    不在 floor_price 嵌套里，属于 collection 表，已在 03-content.sql 的
--    collection 表补列。本表不重复存，避免数据双写不一致。
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `consignment_price_rule`;
CREATE TABLE `consignment_price_rule` (
  `id`                       int unsigned NOT NULL AUTO_INCREMENT,
  `collection_id`            int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID，0 表示全局默认规则',
  -- 🔴 scene 区分两端点：tips 文案不同，不能共用一条记录
  --   实测 trade 版是「寄售出价下限为昨日平均成交价100%」，
  --         prepay 版是「出价下限为昨日平均成交价100%」——只差「寄售」二字。
  --    其余 10 个字段两端点完全相同。早期唯一索引是 uk_collection(collection_id)，
  --    一藏品只能一条记录，无法同时满足两端点。改为 (collection_id, scene) 联合唯一。
  `scene`                    varchar(16)  NOT NULL DEFAULT 'trade' COMMENT '端点场景：trade=寄售交易 / prepay=预付',
  `minprice`                 int          NOT NULL DEFAULT 0 COMMENT '最低限价（实测 int 20）',
  `price_pro`                int          NOT NULL DEFAULT 1 COMMENT '价格保护系数（实测 int 1）',
  `price_price_ratio`        varchar(16)  NOT NULL DEFAULT '100' COMMENT '出价下限占昨日均价比（实测 string "100" = 100%）',
  `tips`                     varchar(500) NOT NULL DEFAULT '' COMMENT '规则提示文案（实测长中文文案）',
  `pay_platform_service_rate` varchar(16) NOT NULL DEFAULT '0.00' COMMENT '平台服务费率·比率（实测 "0.07" = 7%）',
  `share_proportion_rate`    varchar(16)  NOT NULL DEFAULT '0.00' COMMENT '分成比例·比率（实测 "0.00"）',
  `platform_service_price`   varchar(16)  NOT NULL DEFAULT '0.00' COMMENT '平台服务费·金额（实测 "0.07"，与费率数值可能相同但语义不同）',
  `share_proportion_price`   varchar(16)  NOT NULL DEFAULT '0.00' COMMENT '分成金额（实测 "0.00"）',
  `real_price`               varchar(16)  NOT NULL DEFAULT '0.00' COMMENT '到手价 = 售价 - 平台费 - 分成（实测 "0.93"）',
  `low_price`                int          NOT NULL DEFAULT 0 COMMENT '最低限价（冗余字段，实测 int 20，与 minprice 同值）',
  `low_price_text`           varchar(32)  NOT NULL DEFAULT '' COMMENT '最低限价中文（实测 "二十元"）',
  `status`                   tinyint(1)   NOT NULL DEFAULT 1,
  `createtime`               int unsigned NOT NULL DEFAULT 0,
  `updatetime`               int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_collection_scene` (`collection_id`, `scene`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='寄售限价与费率规则（实测 11 字段，来源 market/*/collectionDetail.floor_price）';

-- ------------------------------------------------------------
-- 2. payment_channel —— 支付渠道配置
--
-- 实测依据：
--   wallet/huifu/wallet → data.openstatus(bool) + data.info.{11 字段}
--   注：openstatus 是「当前用户是否已开户」，属 user_payment_account 表的字段；
--       payment_channel 只存渠道级配置，openstatus 不落本表。
--   user/getUserInfo    → 12 个渠道开关/费率标志（已落在 user 表）
--
-- ⚠️ 实测样本中 info 内多数字段为空字符串（账号未开户），
--    因此除 mer_cust_id（实测 "6666000247745831"）与 redirect_url 外，
--    其余字段类型按「字符串 ID + 状态码」保守设定，注释标明实测状态。
--
-- ⚠️ 三个渠道的 status 语义（实测 user 表 0/1）：
--    huifu_status / sandpay_status / yifutong_status / yibao_status / tipay_status
--    0 = 未开通，1 = 已开通
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `payment_channel`;
CREATE TABLE `payment_channel` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT,
  `code`          varchar(32)  NOT NULL COMMENT '渠道标识：huifu/sandpay/yifutong/yibao/tipay',
  `name`          varchar(64)  NOT NULL DEFAULT '' COMMENT '渠道名称',
  `is_open`       tinyint(1)   NOT NULL DEFAULT 0 COMMENT '渠道是否启用（实测 user.huifu_account=1）',
  `service_charge` tinyint(1)  NOT NULL DEFAULT 0 COMMENT '是否收取服务费（实测 *_service_charge 0/1）',
  `mer_cust_id`   varchar(64)  NOT NULL DEFAULT '' COMMENT '商户号（实测 "6666000247745831"）',
  `sys_id`        varchar(64)  NOT NULL DEFAULT '' COMMENT '系统号（实测空串）',
  `acct_id`       varchar(64)  NOT NULL DEFAULT '' COMMENT '账户号（实测空串）',
  `user_cust_id`  varchar(64)  NOT NULL DEFAULT '' COMMENT '用户客户号（实测空串，开户后才有）',
  `biz_product_id` varchar(64) NOT NULL DEFAULT '' COMMENT '业务产品号（实测空串）',
  `redirect_url`  varchar(500) NOT NULL DEFAULT '' COMMENT '跳转地址（实测 hfpay.cloudpnr.com 完整 URL）',
  `mer_priv`      varchar(64)  NOT NULL DEFAULT '' COMMENT '商户私有串（实测空串）',
  `extension`     varchar(255) NOT NULL DEFAULT '' COMMENT '扩展串（实测空串）',
  `resp_code`     varchar(32)  NOT NULL DEFAULT '' COMMENT '响应码（实测 "C00000" = 成功）',
  `resp_desc`     varchar(128) NOT NULL DEFAULT '' COMMENT '响应描述（实测 "成功"）',
  `sort`          int          NOT NULL DEFAULT 0,
  `status`        tinyint(1)   NOT NULL DEFAULT 1,
  `createtime`    int unsigned NOT NULL DEFAULT 0,
  `updatetime`    int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付渠道配置（实测 wallet/huifu/wallet 15 字段）';

-- ------------------------------------------------------------
-- 3. user_payment_account —— 用户渠道开户记录
--
-- 实测依据：wallet/huifu/wallet 的 data.info 结构
--   同一用户在不同渠道各有一份开户信息，故独立建表
--   与 user 表的 *_status 字段关系：user.huifu_status=1 ⇔ 本表存在 huifu 记录
--
-- ⚠️ 安全：mer_cust_id / user_cust_id 属敏感标识，
--    出参必须走 Formatter 白名单，审计日志需脱敏
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `user_payment_account`;
CREATE TABLE `user_payment_account` (
  `id`             int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`        int unsigned NOT NULL,
  `channel_code`   varchar(32)  NOT NULL COMMENT '渠道标识，与 payment_channel.code 对应',
  `openstatus`     tinyint(1)   NOT NULL DEFAULT 0 COMMENT '是否已开户（实测 openstatus=true）',
  `mer_cust_id`    varchar(64)  NOT NULL DEFAULT '' COMMENT '商户号（实测有值）',
  `user_cust_id`   varchar(64)  NOT NULL DEFAULT '' COMMENT '用户在该渠道的客户号（实测空串）',
  `acct_id`        varchar(64)  NOT NULL DEFAULT '' COMMENT '渠道账户号（实测空串）',
  `sys_id`         varchar(64)  NOT NULL DEFAULT '' COMMENT '系统号（实测空串）',
  `biz_product_id` varchar(64)  NOT NULL DEFAULT '' COMMENT '业务产品号（实测空串）',
  `resp_code`      varchar(32)  NOT NULL DEFAULT '' COMMENT '开户响应码（实测 "C00000"）',
  `resp_desc`      varchar(128) NOT NULL DEFAULT '' COMMENT '开户响应描述（实测 "成功"）',
  `openuid`        int unsigned NOT NULL DEFAULT 0 COMMENT '开户时间 unixtime（实测未返回，用本地时间）',
  `createtime`     int unsigned NOT NULL DEFAULT 0,
  `updatetime`     int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_channel` (`user_id`,`channel_code`),
  KEY `idx_channel` (`channel_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户支付渠道开户记录';

-- ------------------------------------------------------------
-- 4. oss_upload_credential —— OSS 上传凭证
--
-- 实测依据：common/uploadAccess
--   data.{accessid, host, policy, signature, expire, callback, key, code}
--   注：code 是响应信封字段（恒为 1），不落库。
--
-- ⚠️ security 敏感：该表记录阿里云 STS 临时凭证
--    - accessid / signature 属临时凭证，虽有 expire 保护，仍禁止入审计日志
--    - 生产环境建议不落库（直接由 OSS SDK 生成），此处落库仅为复刻原站行为
--    - 读取端点不得返回 policy/signature 给非上传态用户
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `oss_upload_credential`;
CREATE TABLE `oss_upload_credential` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`    int unsigned NOT NULL DEFAULT 0,
  `accessid`   varchar(128) NOT NULL DEFAULT '' COMMENT '临时 AccessKey ID（实测 "LTAI5tFDKuxRnYQ2Lcevai1P"）',
  `host`       varchar(255) NOT NULL DEFAULT '' COMMENT '上传域名（实测 img.gandart.shop）',
  `policy`     text         COMMENT '上传策略 base64（实测含 expiration/start-with/conditions）',
  `signature`  varchar(255) NOT NULL DEFAULT '' COMMENT '策略签名（实测 base64 串）',
  `callback`   text         COMMENT '回调策略 base64（实测含 callbackUrl/callbackBody）',
  `dir`        varchar(255) NOT NULL DEFAULT '' COMMENT '上传目录前缀（实测 key="20261017/"，即日期目录；原站字段名为 key，DB 改名 dir 避免与 SQL 保留字冲突）',
  `expire`     int unsigned NOT NULL DEFAULT 0 COMMENT '凭证过期时间 unixtime（实测 1792240324）',
  `createtime` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expire` (`expire`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='OSS 上传临时凭证（实测 common/uploadAccess 8 字段）';

SET FOREIGN_KEY_CHECKS = 1;
