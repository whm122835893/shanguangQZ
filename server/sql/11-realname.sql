-- ============================================================
-- 11-realname.sql —— 实名三态分离：脱敏值 / AES 密文 / HMAC 指纹
--
-- 背景：user.real_name / user.id_card 此前为明文（哪怕存的是脱敏值，
--       提交时的原文从未安全落库），且无一证一号约束。
-- 本迁移为 user 表补三列并加指纹唯一键：
--   real_name_enc  明文姓名的 AES-256-GCM 密文（base64(nonce|tag|cipher)）
--   id_card_enc    证件号密文，同上
--   id_card_hash   HMAC-SHA256(标准化证件号) —— 一证一号唯一键
-- 展示契约不变：real_name / id_card 两列继续存脱敏值（UserFormatter 原样透传）。
--
-- 幂等：IF NOT EXISTS，可重复执行。
-- 执行：mysql gandart < 11-realname.sql
-- ============================================================

ALTER TABLE `user`
  ADD COLUMN IF NOT EXISTS `real_name_enc` varchar(255) NULL DEFAULT NULL COMMENT '实名姓名 AES-256-GCM 密文（base64 nonce|tag|cipher）' AFTER `real_name`,
  ADD COLUMN IF NOT EXISTS `id_card_enc`   varchar(255) NULL DEFAULT NULL COMMENT '证件号 AES-256-GCM 密文，同上' AFTER `id_card`,
  ADD COLUMN IF NOT EXISTS `id_card_hash`  char(64)     NULL DEFAULT NULL COMMENT 'HMAC-SHA256(标准化证件号)，一证一号指纹' AFTER `id_card_enc`;

-- 一证一号数据库级兜底（服务层已有前置检查，此处防并发竞态；NULL 不受唯一约束影响）
CREATE UNIQUE INDEX IF NOT EXISTS `uk_id_card_hash` ON `user` (`id_card_hash`);

-- 自检：以下应返回 0 行（同指纹多用户的存量冲突，出现则需人工裁决）
-- SELECT id_card_hash, COUNT(*) c FROM user WHERE id_card_hash IS NOT NULL GROUP BY id_card_hash HAVING c > 1;
