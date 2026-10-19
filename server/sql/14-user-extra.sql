-- ============================================================
-- 14-user-extra.sql —— 收货地址表（C 端地址页数据载体）
--
-- 背景：/user/address_list 原为内存假数据。前端 AddressList.vue
--       只读列表（无增删改端点调用），故仅建表 + 列表查询。
--
-- 用法：mysql gandart < sql/14-user-extra.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_address` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '收件人',
  `mobile` varchar(32) NOT NULL DEFAULT '',
  `province` varchar(64) NOT NULL DEFAULT '',
  `city` varchar(64) NOT NULL DEFAULT '',
  `district` varchar(64) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '详细地址',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `createtime` int(10) unsigned NOT NULL DEFAULT 0,
  `updatetime` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='收货地址';
