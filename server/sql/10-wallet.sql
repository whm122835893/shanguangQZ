-- ============================================================
-- 10-wallet.sql —— 钱包账本：wallet_transaction 流水表 + 存量余额基线
--
-- 背景：user.money 此前为裸字段（全库无任何资金流水），出错无法审计。
--       本迁移建立「余额每笔变动必落一行流水」的账本，并确立资金恒等式：
--
--         user.money == SUM(CASE direction WHEN 1 THEN amount ELSE -amount END)
--
-- 幂等：本文件可重复执行（CREATE TABLE IF NOT EXISTS + INSERT IGNORE，
--       基线行 biz_id 固定为 user_<id>，靠 biz_uniq 生成列唯一约束去重）。
-- 执行：mysql gandart < 10-wallet.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `wallet_transaction` (
  `id`             bigint unsigned       NOT NULL AUTO_INCREMENT,
  `user_id`        int unsigned          NOT NULL COMMENT '用户 id',
  `direction`      tinyint unsigned      NOT NULL COMMENT '1收入 2支出',
  `amount`         decimal(12,2) unsigned NOT NULL COMMENT '本笔变动额，恒为正',
  `balance_before` decimal(12,2)         NOT NULL COMMENT '变动前余额快照',
  `balance_after`  decimal(12,2)         NOT NULL COMMENT '变动后余额快照',
  `biz_type`       varchar(32)           NOT NULL COMMENT '业务域：recharge/market_buy/migrate_baseline/...',
  `biz_id`         varchar(64)           NOT NULL DEFAULT '' COMMENT '关联业务单号；为空则不参与幂等约束',
  `remark`         varchar(255)          NOT NULL DEFAULT '' COMMENT '备注',
  `createtime`     int unsigned          NOT NULL COMMENT 'unixtime',
  `biz_uniq`       varchar(97) GENERATED ALWAYS AS (IF(`biz_id` = '', NULL, CONCAT(`biz_type`, ':', `biz_id`))) STORED COMMENT '幂等键（条件唯一生成列，biz_id 为空时为 NULL 不受约束）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_biz_uniq` (`biz_uniq`),
  KEY `idx_user_id` (`user_id`, `id`),
  KEY `idx_biz` (`biz_type`, `biz_id`),
  CONSTRAINT `fk_wallet_tx_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_wallet_tx_direction` CHECK (`direction` IN (1, 2)),
  CONSTRAINT `chk_wallet_tx_amount` CHECK (`amount` > 0),
  CONSTRAINT `chk_wallet_tx_after` CHECK (`balance_after` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='钱包资金流水（本表首引外键：资金审计数据禁止随用户级联蒸发）';

-- 存量余额基线：把导入时刻的非零余额记为第一笔收入流水，
-- 使恒等式对历史数据也成立（正余额才迁移，负余额属于脏数据需人工核查）。
INSERT IGNORE INTO `wallet_transaction`
  (`user_id`, `direction`, `amount`, `balance_before`, `balance_after`, `biz_type`, `biz_id`, `remark`, `createtime`)
SELECT `id`, 1, `money`, 0.00, `money`, 'migrate_baseline', CONCAT('user_', `id`),
       '存量余额基线（10-wallet.sql）', UNIX_TIMESTAMP()
FROM `user`
WHERE `money` > 0;

-- 迁移后自检：应返回 0 行（0 行 = 全站账实相符）
-- SELECT u.id, u.money, COALESCE(SUM(CASE t.direction WHEN 1 THEN t.amount ELSE -t.amount END), 0) AS ledger
-- FROM `user` u LEFT JOIN wallet_transaction t ON t.user_id = u.id
-- GROUP BY u.id, u.money HAVING u.money <> COALESCE(SUM(CASE t.direction WHEN 1 THEN t.amount ELSE -t.amount END), 0);
