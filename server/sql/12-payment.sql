-- ============================================================
-- 12-payment.sql —— 支付闭环三件套
--   1. recharge_order 充值单表（此前 recharge 只回显假单号，无任何持久化）
--   2. artel_order.pay_no 支付流水号（回调幂等去重的依据）
--   3. payment_channel.notify_secret 回调 HMAC 密钥（渠道级，验签用）
--
-- 幂等：IF NOT EXISTS，可重复执行。
-- 执行：mysql gandart < 12-payment.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `recharge_order` (
  `id`         int(10) unsigned    NOT NULL AUTO_INCREMENT,
  `order_sn`   varchar(64)         NOT NULL COMMENT '充值单号，全局唯一',
  `user_id`    int(10) unsigned    NOT NULL COMMENT '充值用户',
  `amount`     decimal(12,2) unsigned NOT NULL COMMENT '充值金额（服务端定，不信任客户端）',
  `status`     tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1待支付 2已入账 3已关闭',
  `pay_way`    varchar(32)         NOT NULL DEFAULT '' COMMENT '发起时的支付方式',
  `pay_no`     varchar(64)         NOT NULL DEFAULT '' COMMENT '通道流水号（回调写入）',
  `pay_time`   int(10) unsigned    NOT NULL DEFAULT 0 COMMENT '入账时间',
  `createtime` int(10) unsigned    NOT NULL DEFAULT 0,
  `updatetime` int(10) unsigned    NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_sn` (`order_sn`),
  KEY `idx_user_id` (`user_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值单（结算只认 SettlementService，幂等 + 金额校验）';

ALTER TABLE `artel_order`
  ADD COLUMN IF NOT EXISTS `pay_no` varchar(64) NOT NULL DEFAULT '' COMMENT '通道支付流水号（结算写入，幂等依据）' AFTER `pay_channel`;

ALTER TABLE `payment_channel`
  ADD COLUMN IF NOT EXISTS `notify_secret` varchar(128) NOT NULL DEFAULT '' COMMENT '异步回调 HMAC-SHA256 密钥（PayNotify 验签用）' AFTER `extension`;

-- 充值单与用户的引用完整性（首外键同 10-wallet.sql 口径）
-- 注：MySQL/MariaDB 的 ALTER TABLE 不支持 IF NOT EXISTS 语法加外键，
--     重复执行会报 Duplicate key name，属预期（幂等粒度到「列/索引已存在」即可）。
ALTER TABLE `recharge_order`
  ADD CONSTRAINT `fk_recharge_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
