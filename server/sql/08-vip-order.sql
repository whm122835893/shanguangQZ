-- ------------------------------------------------------------
-- 8. vip_order —— VIP 会员订单
--
-- 实测依据（2026-10-18 复探原站 + raw2/order_pay_createviporder.json）：
--   1. POST /order/pay/CreateVipOrder 空 body 即下单成功：
--      data = {order_sn:"2026101720203010157519849484", order_id:"1", time:"300"}
--      3 个字段全是 string；msg="下单成功"；无 token 返回 401
--   2. 同一账号连续两次下单，order_id 从 "1" 变 "2" —— 订单真实持久化，
--      且 id 按序递增（per-account 还是全局自增无法区分，见 D-007）
--   3. 🔴 VIP 订单独立于 artel_order：原站账号下有 2 笔 VIP 待付单时，
--      orderList 的 total 仍为 2（全是 mh_secondary_market_order 市场单），
--      batchPayOrder 也仍返回空态 —— 证明原站 VIP 单不走统一订单表
--
-- 🔴 本表是「实现层」设计而非「契约层」抄录：
--    原站 vip 订单表的列无任何样本（没有 vip 订单列表端点样本），
--    此处只收录响应可观测字段 + 最小必要管理字段。
--    契约层（3 字段响应）100% 来自实测；列设计是我们的自由度。
--
-- 🔴 不灌种子数据：没有 vip 订单列表样本，编造订单即编造数据。
--    表保持为空 → 首次调用 CreateVipOrder 得到 order_id="1"，
--    与原站首个样本的值一致，且是真实自增结果而非硬编码。
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `vip_order`;
CREATE TABLE `vip_order` (
  `id`         int unsigned NOT NULL AUTO_INCREMENT,
  `user_id`    int unsigned NOT NULL COMMENT '购买会员的用户（实测 order_id 按账号序递增）',
  `order_sn`   varchar(64)  NOT NULL COMMENT '订单号（实测形态：YmdHis 14位 + 14位随机，共 28 位）',
  `status`     tinyint(1)   NOT NULL DEFAULT 1 COMMENT '1待支付 2已支付 3已取消（状态域无样本，实现层设计）',
  `createtime` int unsigned NOT NULL DEFAULT 0 COMMENT '下单时间 unixtime',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_sn` (`order_sn`),
  KEY `idx_user` (`user_id`),
  KEY `idx_user_status` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='VIP 会员订单（实测独立于 artel_order，orderList 不含 VIP 单）';
