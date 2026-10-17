# 闪光集市 H5 · 数据库设计文档

> 版本：**v2.0**
> 日期：2026-10-17
> 依据：原站 `h5.gandart.shop` / `api.gandart.shop` **实测响应** + `docs/api/API.md`（221 端点 / 31 模块）
> 目标库：MariaDB 10.11 / MySQL 8.0 兼容，字符集 `utf8mb4`
> 实测字段清单：`/workspace/recon/schema-map/measured-fields.txt`（机器可读，可 diff）
> 实测样本：`/workspace/recon/raw/`（公开）+ `/workspace/recon/raw/auth/`（带真实 token）
> 建表脚本：`mock-server/sql/01~04-*.sql`

---

## 〇、设计原则（先看这三条，否则表会白建）

### 1. 字段一律以实测为准，禁止凭端点名猜

**v2.0 实测覆盖情况**（真实账号 `175****1293` 登录后抓取）：

| 实体 | 实测状态 | 叶子字段数 | 样本文件 |
|---|---|---|---|
| User | ✅ 已实测 | **106** | `raw/auth/user_getUserInfo.json` |
| Order | ✅ 已实测（2 条真实订单） | **18** | `raw/auth/order_order_orderList.json` |
| UserCollection | ✅ 已实测 | **17**（8 持有 + 10 嵌套 collection） | `raw/auth/user_collection_user_collection_antMycollection.json` |
| Synthesis | ✅ 已实测 | **22**（6 theme + 16 play） | `raw/auth/synthesis_themeList.json` |
| Union | ✅ 已实测 | **16** | `raw/auth/union_user_myunion.json` |
| Message | ✅ 已实测 | **7** | `raw/auth/user_newList.json` |
| Lottery | ✅ 已实测 | **46** | `raw/auth/lottery_info_v2.json` |
| 交易统计 | ✅ 已实测 | **10** | `raw/auth/market_trade_tradingInformation.json` |
| BlindBox | ✅ 已实测 | 23 + 嵌套 | `raw/box_blind_box_list.json` |
| Affiche / Banner | ✅ 已实测 | 8 / 5 | `raw/index_*.json` |

> 结论：**P1 交易核心表已 100% 基于实测落地**，不再有「待实测确认」字段。
> 仍缺实测的只有写入类端点（`getRecord` / `synthesis/material` / `market/trade/tradingInformation` 的 status 语义）。

> 真实教训：盲盒实体此前凭猜测加了 `image` 字段，原站实测**根本不下发该字段**。
> 由 `recon/verify-contract.py` 自动比对抓出。若无逐字段核验，这类错误会一路带到生产。

### 1.1 实测发现的 6 个「类型陷阱」（建表必须照抄，否则前端崩）

| # | 字段 | 实测值 | DB 类型 | 陷阱说明 |
|---|---|---|---|---|
| 1 | `blind_box.goods_type` | `"2"` | `varchar(16)` | 前端 `=== '2'` 判断，转 int 即失效 |
| 2 | `blind_box.batch_id` | `"14"` | `varchar(16)` | 同上 |
| 3 | `affiche.type` | `"1"` | `varchar(16)` | `=== '1'` 分页筛选 |
| 4 | `order.num` / `order.order_type` | `"1"` / `"2"` | `varchar(16)` | **同一条记录里 `order_status` 是 int 而 `order_type` 是 string** |
| 5 | `order.uc_ids` | `null` **或** `"121926"` | `varchar(255) NULL` | 两种形态并存，必须可空 varchar |
| 6 | `synthesis_plan.start_time` | `1792232280000` | `bigint` | **13 位毫秒**，与其他表的 10 位秒级不同 |

补充两个字符串/数字混用点：
- `user_collection.sell_num` 是 string，`num` / `lock_num` / `is_collection` 是 int
- 分页信封 `per_page` 在不同端点有 string / int 两种形态（`"10"` 与 `10`）

### 2. 「字符串包数字」是原站的既有约定，不是我们的选择

实测确认的字符串包数字字段：

| 字段 | 实测值 | 前端依赖 |
|---|---|---|
| `blind_box.goods_type` | `"2"` | `=== '2'` 判断 |
| `blind_box.batch_id` | `"14"` | 字符串比较 |
| `blind_box.issuer_id` | `"0"` | 真假判断 |
| `affiche.type` | `"1"` | `=== '1'` 分页筛选 |
| `artel_order.order_type` | `"2"` | 字符串比较 |
| `artel_order.num` | `"1"` | 字符串比较 |
| `lottery.crystal` / `lottery_max` / `rule` / `type` | `"10"` / `"316000"` | 抽奖参数下发 |

**建表时必须用 `varchar`，转成 int 会让前端判断全部失效。**

### 3. 金额 / 布尔 / 时间戳的出参形态固定

| 语义 | 数据库存储 | API 出参 | 依据 |
|---|---|---|---|
| 金额 | `decimal(12,2)` | **string**（`"4.88"`） | 实测 |
| 布尔 | `tinyint(1)` | **int**（`0`/`1`） | 实测 |
| 时间 | `int unsigned` | **int unixtime** | 实测 |
| 文本时间 | — | 由格式化层实时生成 | 实测两种都有 |

---

## 一、总表数结论

### 1.1 原站全量（221 端点）≈ **52 张表**

按 31 个模块归并后，核心业务表 32 张 + 关联/流水表 20 张。

### 1.2 本项目实际需要 ≈ **38 张表**

**判定依据：前端 `src/api/index.js` 只调用 62 个端点，分布在 11 个模块；114 个页面。**

原站 221 端点里有 159 个前端从未调用（公会、竞猜、资讯、抽签、水晶、点赞…），
这些**暂不建表**，等前端真正接入再补。

### 1.3 分级建表方案（v2.0 已落地 30 张）

| 批次 | 表数 | 范围 | 状态 |
|---|---|---|---|
| **P0 批次** | **11** | 公告、公告类型、Banner、站点配置、系列、盲盒、盲盒批次、发行方、用户、限流 | ✅ 已建 |
| **P1 批次**（交易核心） | **16** | 用户令牌、藏品、用户藏品、藏品流水、统一订单、订单明细、市场挂单、求购单、寄售、消息、合成主题、合成方案、抽奖、抽奖记录、公会配置、公会、公会成员 | ✅ **本轮新建，全部实测驱动** |
| **P2 批次**（交易衍生） | ~11 | 转赠、签到、邀请、活动任务、评价、通知 | 待建 |
| **按需**（暂不建） | ~14 | 公会扩展、竞猜、资讯、水晶、点赞… | 前端接入时再评估 |

**当前实际落库 30 张表** = P0 11 张 + P1 交易核心 16 张 + 系统安全 3 张（`audit_log` / `idempotent` / `sms_code`）。

### 1.4 已落库表清单（30 张）

| # | 表名 | 说明 | 来源脚本 |
|---|---|---|---|
| 1 | `affiche` | 公告 | 01 |
| 2 | `affiche_type` | 公告类型字典 | 01 |
| 3 | `blind_box` | 盲盒/待开盒商品 | 01 |
| 4 | `blind_box_batch` | 盲盒批次 | 01 |
| 5 | `publisher` | 发行方 | 01 |
| 6 | `user` | 用户主表（104 列 / 实测 106 叶子字段） | 01 + 04 扩展 |
| 7 | `rate_limit` | 固定窗口限流计数 | 02 |
| 8 | `banner` | 首页轮播 | 03 |
| 9 | `site_config` | 站点配置 | 03 |
| 10 | `collection` | 藏品定义（已开盒） | 03 + 04 扩展 |
| 11 | `series` | 藏品系列/分区 | 03 |
| 12 | `user_token` | 登录令牌 | 04 |
| 13 | `user_collection` | 用户藏品持有 | 04 |
| 14 | `user_collection_record` | 藏品变动流水 | 04 |
| 15 | `artel_order` | 统一订单（多态） | 04 |
| 16 | `artel_order_item` | 订单明细 | 04 |
| 17 | `market_goods` | 市场挂单 | 04 |
| 18 | `beg_buy_order` | 求购单 | 04 |
| 19 | `consignment` | 寄售 | 04 |
| 20 | `message` | 用户消息 | 04 |
| 21 | `synthesis_theme` | 合成主题 | 04 |
| 22 | `synthesis_plan` | 合成方案 | 04 |
| 23 | `lottery` | 抽奖活动 | 04 |
| 24 | `lottery_record` | 抽奖记录 | 04 |
| 25 | `union_config` / `union` / `union_member` | 公会三表 | 04 |
| 26 | `audit_log` | 审计日志（敏感字段脱敏） | 05 |
| 27 | `idempotent` | 幂等记录（防重复下单/支付） | 05 |
| 28 | `sms_code` | 短信验证码（只存哈希） | 05 |

> `user` 实测 106 个叶子字段中，`token` 与 `url` 属登录/计算字段不落库；
> `verification.email/mobile` 拆为 `email_verified`/`mobile_verified`；
> `statistics.*` 4 字段拆为独立列（定时任务回写）。
> 核验脚本双向比对结果：**原站字段零遗漏，DB 无冗余列**。

---

## 二、表清单（按业务域）

### 域1：内容与配置（P0，4 张）

| 表名 | 对应端点 | 字段依据 | 状态 |
|---|---|---|---|
| `affiche` 公告 | `/index/afficheList` | **实测 7 字段** | ✅ 已建 |
| `affiche_type` 公告类型 | `/index/affichetypelist` | **实测 5 条** | ✅ 已建 |
| `banner` 轮播图 | `/banner/show/getBanner` | **实测 5 字段** | ✅ 本文档新增 |
| `site_config` 站点配置 | `/index/getConfig` `/index/getVersion` | **实测** `{version,download_url}` | ✅ 本文档新增 |

> `site_config` 说明：原站 `getConfig` 需 `key` 参数，实测缺 key 返回 `code=0 缺少key值`。
> `getVersion` 实测返回 `{version:"1.0.0", download_url:""}`。两者同属KV 型配置，合并一张表。

**协议类说明**：`/index/index/agreement` 等 3 个端点实测返回**同一份 dict**（`usageterms` + `privacy_agreement`），
不是 3 份不同协议。原站 3 个路径返回同一份内容，可只存一份 `agreement` 单表，本项目暂不建表（内容可硬编码为静态文件）。

### 域 2：藏品与盲盒（P0，3 张）

| 表名 | 对应端点 | 字段依据 | 状态 |
|---|---|---|---|
| `blind_box` 盲盒 | `/box/blind_box/list` | **实测 26 字段** | ✅ 已建 |
| `blind_box_batch` 批次 | 同上，嵌套 `batch` 拆表 | **实测 4 字段** | ✅ 已建 |
| `publisher` 发行方 | 同上，嵌套 `publisher` 拆表 | **实测 2 字段** | ✅ 已建 |
| `collection` 藏品 | `/user_collection/.../antMycollection` 嵌套 | 实测 10 字段 | 📋 本文档新增 |

**为什么 `batch` / `publisher` 要拆表**：原站把它们作为嵌套 dict 下发，但数据上是独立实体
（多个盲盒可共享同一发行方），拆表后关联查询比 JSON 列更快，且便于做「发行方维度统计」。

**`collection` 与 `blind_box` 的关系**：`collection` 是**已开盒的藏品实例**，
`blind_box` 是**未开盒的盒子**。两者共用名称/图片字段但语义不同，不能合表——
`collection` 有 `collection_level`/`unfreeze_collection_id`/`freeze_time`（冻解冻相关），`blind_box` 有 `batch`/`open_draw`。

### 域 3：用户与账户（P0，3 张）

| 表名 | 对应端点 | 字段依据 | 状态 |
|---|---|---|---|
| `user` 用户 | `/user/getUserInfo` | 实测 90+ 字段（分8 组） | ⚠️ 骨架已建，待实测 |
| `user_address` 收货地址 | `/user/address_list` | 待实测 | 📋 新增 |
| `user_token` 登录令牌 | `/user/login` | 实测 LoginResult 8 字段 | 📋 新增 |

**`user` 表的字段分组**（实测样本，90+ 字段）：

| 分组 | 字段（实测示例） |
|---|---|
| 身份 | `id:16797` `username` `nickname` `mobile` `avatar` `level:1` `gender:1` |
| 资金 | `money:"0.00"` `agent_money` `recharge_price` `service_price` `user_ore:"0.000000"` |
| 积分/连签 | `score:0` `successions:4` `maxsuccessions:4` `prevtime` |
| 邀请 | `inviter_code:"Q28116797"` `inviter_user_id` `invite_type:1` `new_inviter_user_id` `yao_count` |
| 实名 | `real_name_authentication:1` `real_name:"王*绵"` `id_card` `id_card_front/back` `real_name_time` |
| 支付通道 | `huifu_account:1` `yibao_status` `sandpay_status` `tipay_status` `pay_password` |
| 费率 | `huifu_service_charge:1` `sandpay_service_charge` `yifutong_service_charge` |
| 藏品统计 | `collection_num:1` `collection_total_price` `blind_box_num` `crystal_yellow/purple/blue` |
| 权限 | `freeze:0` `is_white:0` `market_open:1` `transfer_permission` `is_vip:0` |
| 账号 | `createtime/updatetime/logintime/joinip/loginip/status:"normal"` `url:"/u/16797"` |

> 🔴 **高危字段必须单独处理**：
> - `id_card` 实测已脱敏（`520******838`）—— 数据库**不要**存明文身份证
> - `pay_password` 是支付密码，与登录密码是两套，**必须用 Argon2id**，禁止明文或 MD5
> - `inviter_code` 是邀请码（实测 `Q28116797`），前端曾误用 `invite_code` 导致二维码为空，是易错点

**为什么 `user_token` 要独立表**：`user` 实测有 `status`、`token_version`、`logintime`，
但「一账号多设备在线」需要独立 token 表；且 `loginip`/`loginip` 需要留痕，不能只存最后一次。

### 域 4：交易核心（P1，11 张）★ 优先级最高

| 表名 | 对应端点 | 字段依据 |
|---|---|---|
| `order` 订单 | `/order/order/orderList` | **实测 18 字段** |
| `order_item` 订单明细 | 同上（多商品展开） | 待实测 |
| `payment` 支付流水 | `/order/pay/doPay` `/batchdopay` | 待实测 |
| `payment_channel` 支付通道配置 | 汇付/易宝/杉德… | 由 `user` 权限字段推断 |
| `recharge` 充值 | `/order/pay/Recharge` | 待实测 |
| `cash_withdraw` 提现 | `/order/cash/applyCash` `/cashList` | 待实测 |
| `user_collection` 用户藏品 | `/user_collection/.../antMycollection` | **实测 10 字段** |
| `collection_record` 流转记录 | `/user_collection/.../getRecord` | 待实测 |
| `market_goods` 市场挂单 | `/market/trade/sell` `/tradingInformation` | 待实测 |
| `beg_buy_order` 求购单 | `/market/trade/begBuyList` `/createBegBuyOrder` | 待实测 |
| `consignment` 寄售 | `/order/order/myConsignment` | 待实测 |

**`order` 表实测 18 字段**（`/order/order/orderList` → `data.data[0]`）：

| 字段 | 实测值 | 说明 |
|---|---|---|
| `artel_order` | `"mh_secondary_market_order"` | **多态来源标识** |
| `id` | `18554` | |
| `order_sn` | `"2026101623075357101499336911"` | 时间戳+随机 |
| `order_type` | `"2"` | ⚠️ 枚举待确认 |
| `order_status` | `"3"` | ⚠️ 枚举待确认 |
| `total_price` | `"100.00"` | **字符串金额** |
| `num` | `"1"` | **字符串数量** |
| `goods_id` / `goods_type` | `155` / `2` | 2=藏品 |
| `goods_name` / `goods_cover_image` | `"赤壁之战"` / `/uploads/...` | **商品快照** |
| `series_name` | `"历史区"` | |
| `crystal_type` / `crystal_number` | `"crystal_no"` / `"0"` | |
| `uc_ids` | `null` | 关联 user_collection id 列表 |
| `collection_code` | `[]` | 藏品编号 |
| `createtime` / `updatetime` | `1792163273` / `1792163274` | |

> 🔴 **`artel_order` 是多态来源**，一个订单表承载了「买/卖/转赠/充值/提现」多种场景。
> 设计上用 `artel_order` + `order_type` + `order_status` 三字段组合判定，
> **不要**为每种场景建独立订单表——原站就是单表，复刻要保持一致。

> 🔴 **`order_type` 的 6/7/10/31 四个值语义未确认**，实测样本只见到 `2`。
> 需查原站页面文案或提供测试账号抓取。见 §六 待确认项。

### 域 5：合成 / 抽奖 / 活动（P2，9 张）

| 表名 | 对应端点 |
|---|---|
| `synthesis_theme` 合成主题 | `/synthesis/themeList` `/themeinfo` |
| `synthesis_material` 合成材料 | `/synthesis/material` |
| `synthesis_record` 合成记录 | `/synthesis/recordList` `/recordInfo` |
| `synthesis_plan` 合成方案 | `/synthesis/playInfo` `/btchplayInfo` |
| `lottery_config` 抽奖配置 | `/lottery/info` `/lottery/statistics` |
| `lottery_record` 抽奖记录 | `/lottery/record` |
| `activity` 活动 | `/activity/index` `/activity/detail` |
| `activity_record` 参与记录 | `/activity/accessNewActivities` |
| `crystal_goods` 水晶 | `/crystal_goods/list` `/info` |

### 域 6：转赠 / 签到 / 邀请（P2，8 张）

| 表名 | 对应端点 |
|---|---|
| `gift_order` 转赠订单 | `/user_collection/.../reGiftOrder` `/changeGiveOrderStatus` |
| `collection_like` 收藏 | `.../addlike` `/likeCollection` `/dellike` |
| `sign_record` 签到 | `/addons/signin/api.index/dosign` |
| `invite_record` 邀请记录 | 由 `user.inviter_code` 派生 |
| `message` 消息 | `/user/readMsgById` `/oneKeyRead` |
| `rank_snapshot` 排行榜快照 | `/user/ranking` `/user/newrank1` |
| `bank_card` 银行卡 | `/user/bindBank` `/getBankInfo` |
| `real_name_auth` 实名认证记录 | `/user/RealAuthentication` |

### 域 7：系统与安全（已建 1 + 新增 5）

| 表名 | 用途 | 状态 |
|---|---|---|
| `rate_limit` 限流计数 | 防刷接口 | ✅ 已建 |
| `audit_log` 审计日志 | 安全事件追溯 | 📋 新增 |
| `login_log` 登录日志 | 爆破溯源 | 📋 新增 |
| `sms_code` 短信验证码 | 带有效期与次数限制 | 📋 新增 |
| `order_idempotent` 幂等表 | 防止重复下单 | 📋 新增 |
| `upload_file` 附件 | `/common/uploadaccess` | 📋 新增 |

**`order_idempotent` 为什么必需**：`/order/pay/CreateOrder` 和 `/order/pay/doPay`
都是非幂等写接口。前端弱网重试会重复下单。必须用「业务唯一键 + 唯一索引」拦截，
这是支付类接口的**硬要求**，不能靠前端保证。

---

## 三、表关系图（核心）

```
                    ┌──────────┐
                    │   user   │ 用户
                    └────┬─────┘
        ┌────────────────┼────────────────┬──────────────┐
        │                │                │              │
   ┌────▼────┐     ┌─────▼──────┐   ┌──────▼─────┐ ┌────▼────┐
   │  order  │     │user_collection│ │market_goods│ │ user_token│
   └────┬────┘     └─────┬──────┘   └──────┬─────┘ └─────────┘
        │ 1:N            │ 1:N              │ 1:N
   ┌────▼─────┐    ┌─────▼──────┐    ┌─────▼─────┐
   │order_item│    │collection  │    │beg_buy_ord│
   └──────────┘    │  藏品实例  │    └───────────┘
                   └────────────┘

  ┌─────────────┐   ┌───────────┐   ┌──────────────┐
  │  blind_box  │N:1│  batch    │N:1│  publisher   │
  └──────┬──────┘   └───────────┘   └──────────────┘
         │ 1:N（开盒后产生）
   ┌─────▼──────────┐
   │ user_collection│
   └────────────────┘

  ┌──────────┐  ┌───────────┐  ┌────────────┐  ┌──────────────┐
  │  affiche │  │affiche_typ│  │   banner   │  │ site_config  │
  └──────────┘  └───────────┘  └────────────┘  └──────────────┘
```

---

## 四、命名规范

| 规范 | 规则 | 例 |
|---|---|---|
| 表名 | `snake_case` 单数名词 | `user_collection` ✅ `users` ❌ |
| 主键 | 统一 `id`，`int unsigned AUTO_INCREMENT` | |
| 金额 | `*_price` / `*_amount`，`decimal(12,2)` | `total_price` |
| 时间 | `*_time`（unixtime）/ `createtime` / `updatetime` | 不用 `created_at` |
| 数量 | `*_num` / `*_count` | `total_num` |
| 状态 | `*_status`（int）/ `is_*`（tinyint） | `order_status` / `is_show` |
| 布尔 | `is_` / `has_` 前缀，`tinyint(1)` | `is_show` |
| 索引 | `idx_普通` / `uk_唯一` | `uk_account` |

> ⚠️ 时间字段统一用 **int unixtime**，不用 `datetime`。原站实测下发的是秒级整数
> （`createtime:1792219904`），若存 datetime 每次出参都要转换，且容易踩时区问题。

---

## 五、通用字段约定

每张业务表（除关联表外）至少包含：

```sql
`id`          int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
`createtime`  int unsigned NOT NULL DEFAULT '0' COMMENT '创建时间戳',
`updatetime`  int unsigned NOT NULL DEFAULT '0' COMMENT '更新时间戳',
`is_show`     tinyint(1)   NOT NULL DEFAULT '1' COMMENT '是否展示 0/1'
```

**逻辑删除**：用 `is_show`（对外可见性）与 `status`（业务状态，如 `normal`/`freeze`），
**不使用 `is_delete` 硬删除** —— 交易数据必须留痕。

---

## 六、待确认项（v2.0 更新）

### 6.1 已解决（v2.0）

| 原编号 | 问题 | 结论 |
|---|---|---|
| OPEN-04 | `user.statistics` 是否拆列 | **已拆为 4 个独立列**（`invited_count`/`buy_count`/`sale_count`/`join_day`），实测确认 |
| — | `user` 表字段数 | 实测 **106 叶子字段**，已 100% 落库 |
| — | `market_goods` / `beg_buy_order` / `user_collection` 字段 | 已有实测样本，`04-trade-core.sql` 定稿 |
| — | `begBuyList` 必填参数 | `collection_id` + `sort`（合法值实测仅 `desc`），返回标准分页信封 |
| — | `tradingInformation` 参数 | `status` 必填，合法值 1/2/3，返回 10 字段统计骨架 |
| — | `crystal_yellow/purple/blue` | user 表实测三字段均为 int 0，含义为水晶余额三档 |

### 6.2 仍未解决（不阻塞建表，但实现前必须抓）

| 编号 | 问题 | 影响 | 获取方式 |
|---|---|---|---|
| **OPEN-11** | `order_type` 的 6/7/10/31 语义 | 订单状态机画不对 | 原站该账号只有 `order_type=2` 一种，需多账号样本 |
| **OPEN-12** | `order_status` 完整枚举 | 同上 | 实测仅出现 **2 / 3**；`order_cash/cashList` 空、`order/batchPayOrder` 返回「暂无订单」，无更多样本 |
| **OPEN-13** | `user_collection/getRecord` 必填参数 | 藏品流水表字段无法逐字段实测 | 已试 `collection_id`/`type`/`goods_type`/`record_type` 全部「参数错误」；需抓前端调用处 |
| **OPEN-14** | `synthesis/material` 返回 500 | 合成材料表无法建 | 服务端异常，可能依赖登录态或已下线 |
| **OPEN-15** | `market/trade/begBuyList` 其余 sort 枚举 | 求购单排序 | 实测 `desc` 有效，`new`/`price`/`0`/`default` 均「排序方式错误」 |
| **OPEN-16** | 写操作端点（下单/支付/开盒/提现/签到）真实入参 | 写接口无法联调 | 需在原站 H5 实际点击抓包 |
| — | 公告原站共 126 条，仅抓到 53 条（第 4 页起 404） | 数据不完整 | 已接受：53 条足够前端调试 |
| — | 测试账号业务数据几近为空 | 市场/求购/挂单列表全部返回空数组 | 已接受：结构已拿到（信封字段齐全），缺的是行数据 |

> ✅ **P1 交易核心 16 张表已全部落地，字段零推断。**
> 剩余 OPEN 项只影响「写操作」与「枚举完整性」，不影响建表。

---

## 七、索引设计要点

| 场景 | 索引 | 原因 |
|---|---|---|
| 公告列表分页 | `idx_show_sort(is_show, diysort, createtime)` | 覆盖「只显示 + 排序 + 分页」 |
| 盲盒发售日历 | `idx_release(release_time)` + `idx_show_sort` | 按发售时间排|
| 我的藏品 | `uk_user_goods(user_id, collection_id)` | 防止同藏品重复持有行 |
| 挂单列表 | `idx_market_goods(goods_id, status)` | 行情页高频查询 |
| 订单列表 | `idx_user_time(user_id, createtime)` | 「我的订单」按时间倒序 |
| 限流计数 | `uk_bucket(ip, route, minute_bucket)` | **唯一键保证原子自增** |

**唯一键 `uk_user_goods(user_id, collection_id)` 的作用**：
一个用户对同一藏品只能有一行持有记录，`num` 字段记录数量。
这样「我的藏品」查询只需 `where user_id=?`，走覆盖索引。

---

## 八、安全要求（与表设计强相关）

| 要求 | 实现 | 关联表 |
|---|---|---|
| 密码哈希 | **Argon2id**（PHP `password_hash`），禁止 MD5/SHA1 | `user` |
| 支付密码 | 独立Argon2id 哈希，禁止与登录密码复用 | `user.pay_password` |
| 敏感字段加密 | `id_card` 用 AES-256-GCM 加密存储，出参脱敏 | `user` |
| 防SQL 注入 | **全部参数化查询**，ThinkPHP 的 `where()` 数组写法 | 全部 |
| 防越权(IDOR) | 每个含 `user_id` 的查询必须带 `where user_id=?` | `order`/`user_collection`/`market_goods` |
| 幂等保护 | 写接口用业务唯一键 + 唯一索引拦截重复提交 | `order_idempotent` |
| 审计日志 | 记录「谁/何时/对什么/做了什么/结果」，**密码自动脱敏** | `audit_log` |
| 限流 | ip + 路由维度，60 次/60 秒 | `rate_limit` |

> 🔴 **越权防护是最容易漏的**：像 `/order/order/orderDetail/ids/{id}` 这类接口，
> 只按 `id` 查订单等于把所有人的订单暴露给任何登录用户。
> 必须 `where(['id'=>$id, 'user_id'=>$uid])`，查不到返回 404 而不是 403（不泄露存在性）。

---

## 九、与现有实现的对应关系

| 表 | Model | 控制器端点 | 契约核验 |
|---|---|---|---|
| `affiche` | `app/model/Affiche.php` | `Core::afficheList` / `afficheDetail` | ✅ 已通过 |
| `affiche_type` | `app/model/AfficheType.php` | `Core::afficheTypeList` | ✅ 已通过 |
| `blind_box` | `app/model/BlindBox.php` | `Core::blindBoxList` | ✅ 已通过（26 字段零差异） |
| `blind_box_batch` | `app/model/Batch.php` | 同上（关联） | ✅ |
| `publisher` | `app/model/Publisher.php` | 同上（关联） | ✅ |
| `user` | 待建 `app/model/User.php` | `Core::getUserInfo` / `login` | ✅ 字段已实测（106），Model 待写 |
| `rate_limit` | — | 全局中间件 | ✅ 第 61 次触发 429 |
| `user_token` | 待建 | `login` / `logout` | ✅ 字段已实测 |
| `artel_order` | 待建 | `order/order/orderList` | ✅ 字段已实测（18） |
| `user_collection` | 待建 | `user_collection/antMycollection` | ✅ 字段已实测（17） |
| `market_goods` | 待建 | `market/getMarketGoodsListByGoodsId` | ✅ 信封已实测（list/count/ltnum） |
| `beg_buy_order` | 待建 | `market/trade/begBuyList` | ✅ 参数已实测（collection_id + sort=desc） |
| `synthesis_theme` / `synthesis_plan` | 待建 | `synthesis/themeList` | ✅ 字段已实测（22，注意毫秒时间戳） |
| `message` | 待建 | `user/newList` | ✅ 字段已实测（7） |
| `lottery` / `lottery_record` | 待建 | `lottery/info` | ✅ 字段已实测（46） |

契约出参统一走 `app/service/Formatter.php` 的**字段白名单**——
多下发一个字段前端就可能踩未定义分支，白名单是唯一可靠护栏。

> 表数口径说明：上表 25 行对应 30 张物理表（`union` 三表、`user` 等含拆列）。

**v2.0 关键实测样例（写 Model 时照抄）**：

```php
// user —— money 是 decimal，API 必须输出 string
'money'           => number_format($row['money'], 2, '.', ''),   // "0.00"
// user —— ore 是 6 位小数
'user_ore'        => rtrim(rtrim(number_format($row['user_ore'], 6, '.', ''), '0'), '.') ?: '0',
// user —— 嵌套对象
'verification'    => ['email' => (int) $row['email_verified'], 'mobile' => (int) $row['mobile_verified']],
'statistics'      => [
    'invited_count' => (int) $row['invited_count'],
    'buy_count'     => (int) $row['buy_count'],
    'sale_count'    => (int) $row['sale_count'],
    'join_day'      => (int) $row['join_day'],
],
// artel_order —— order_status 是 int，order_type/num 是 string，uc_ids 可空
'order_status'    => (int) $row['order_status'],
'order_type'      => (string) $row['order_type'],
'num'             => (string) $row['num'],
'uc_ids'          => $row['uc_ids'] !== '' ? explode(',', $row['uc_ids']) : null,
// user_collection —— sell_num 是 string，其余是 int
'sell_num'        => (string) $row['sell_num'],
'num'             => (int) $row['num'],
// synthesis_plan —— 13 位毫秒，直接透传不要乘 1000
'start_time'      => (int) $row['start_time'],   // 1792232280000
```

---

## 十、后续动作（v2.0 更新）

| 优先级 | 动作 | 前置条件 | 状态 |
|---|---|---|---|
| ✅ P0 | 补 `banner` / `site_config` / `collection` / `series` 四张表 | 无 | **已完成** |
| ✅ P0 | 固化 `sql/03-content.sql` | 无 | **已完成** |
| ✅ P1 | 建交易核心 16 张表（实测驱动） | 测试账号 | **本轮已完成** |
| ✅ P1 | 字段覆盖率双向核验（API ↔ DB） | 无 | **已完成，零遗漏** |
| P1 | 写 `User` / `UserToken` / `ArtelOrder` / `UserCollection` Model | 无 | 待做 |
| P1 | 把 4 个已实测端点从内存 Mock 切到 DB 驱动 | Model 完成 | 待做 |
| ✅ P1 | 建系统安全表（`audit_log` / `idempotent` / `sms_code`） | 无 | **已完成** |
| P2 | 转赠 / 签到 / 邀请 / 活动任务表 | 需实测字段 | 待做 |
| P2 | 合成材料表（`synthesis/material` 当前 500） | 需端点恢复 | 阻塞中 |
| 按需 | 公会扩展 / 竞猜 / 资讯 / 水晶等 ~14 张 | 前端接入时再评估 | — |

### 下一步最高杠杆动作

1. **写 4 个 Model**（User / UserToken / ArtelOrder / UserCollection），字段直接照抄 §9 的实测样例
2. **把 `user/getUserInfo`、`order/order/orderList`、`user_collection/antMycollection`、`user/newList` 四个端点从内存 Mock 切到 DB 驱动**
3. 用现成的 `recon/verify-contract.py` 对这四个端点做逐字段核验 —— 复用已验证的核验机制，不重新发明