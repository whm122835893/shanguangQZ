-- ============================================================
-- 限流计数表
--
-- 🔴 为什么用数据库而不是 Cache：
--    file驱动的 Cache::inc() 在本项目实测不可靠——
--    连续 70 次请求计数器不累积，表现为「配了限流但从不触发」的假安全。
--    用数据库做原子自增，行为可预期、可验证、可审计。
--
-- 清理策略：按 minute_bucket 过期，运维侧可定期
--    DELETE FROM rate_limit WHERE minute_bucket < UNIX_TIMESTAMP() - 300;
-- ============================================================
DROP TABLE IF EXISTS `rate_limit`;
CREATE TABLE `rate_limit` (
  `id`            int unsigned NOT NULL AUTO_INCREMENT,
  `ip`            varchar(45)  NOT NULL                COMMENT '客户端 IP，兼容 IPv6',
  `route`         varchar(128) NOT NULL                COMMENT '路由标识',
  `minute_bucket` int unsigned NOT NULL                COMMENT '时间桶：floor(时间戳/窗口秒数)',
  `hits`          int unsigned NOT NULL DEFAULT '1'    COMMENT '窗口内请求次数',
  `createtime`    int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bucket` (`ip`, `route`, `minute_bucket`),
  KEY `idx_bucket` (`minute_bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='接口限流计数';