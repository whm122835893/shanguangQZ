# 闪光集市 后端 API 实现规范 v1.0

> **定位**：本文是**实现规范**（写后端时照着做的约定），不是侦察报告。
> - 侦察证据（221 端点全量清单、抓包原文）→ `docs/api/API.md`
> - 前端兼容红线 → `PROJECT_MEMORY.md` §5.1
> - 缺陷清单 → `docs/AUDIT-API-外链.md`、`docs/依赖原站清单.md`
>
> **数据口径**：只写**实测确认**的字段。凡未实测的，一律标 `[待确认]`，不臆造列名。
> **版本**：v1.0 · 2026-10-17 · 基于远端 `9a31d9a`

---

## 0. 阅读指引：端点分级（最重要的一节）

221 个端点**不是平权的**。按「前端是否已接线 + 契约是否已实测」分为两档，**开发优先级完全不同**：

| 档位 | 数量 | 判定依据 | 开发策略 |
|---|---|---|---|
| **Tier 1** | **70** | 前端 `src/` 实际调用（实测 grep 全量端点） | ✅ **必须实现**，契约已确认，照本文 §3 落地 |
| **Tier 2** | **151** | 仅存在于逆向出的 chunk 中，前端未调用、未抓到成功响应 | ⚠️ **先不实现**，逐个与产品确认后再补 |

**Tier 1 的 70 个** = `src/api/index.js` 登记 62 + 页面层散落 8（`lottery` 4 + `Compound` 2 + `AddressList` 1 + 重复 synthetic）。这是 MVP 的全部工作量。

> 是的 ——  前端 118 条路由、117 个页面，**真正依赖的后端只有 70 个端点**。其余 151 个是原站遗留（多语言、已下线活动、后台管理用等）。

---

## 1. 全局契约（强制 · 违反即前端崩溃）

### 1.1 传输约定

| 项 | 约定 | 说明 |
|---|---|---|
| BaseURL | `https://<domain>/api` | 前端 axios `baseURL: '/api'` |
| **HTTP 方法** | **全部 POST** | 含列表、详情类。这是 ThinkPHP 遗留，前端无任何 GET 调用路径之外的兼容 |
| Content-Type | `application/json` | |
| 路径参数 | `.../ids/{ids}` | **变量名必须是 `ids`**，前端有硬编码拼接 |

### 1.2 认证（双通道，缺一不可）

```
Header:  token: <token>
Query:   ?token=<token>
```

- **必须同时接受两种传法**。原因：线上网关（CloudStudio / CDN）会剥掉自定义请求头，只保留 query；前端已在拦截器里双通道下发（`http.js:56,59`）。
- token 返回位置：`POST /user/login` → `data.userinfo.token`，同时返回 `expiretime` / `expires_in`。
- **未携带或失效时：HTTP 状态码必须是 401**，同时 body 为 `{"code":401,"msg":"请登录后操作",...}`。前端两条路径都拦（`http.js:96-112`），只返其中一种会导致另一方漏判。

### 1.3 响应信封（四字段，一个不能少）

```json
{ "code": 1, "msg": "", "time": 1792175153, "data": {} }
```

| code | 语义 | 前端行为 |
|---|---|---|
| **1** | **成功** | 读 `data` —— 注意这是反直觉约定，**不是 0** |
| **0** | 业务失败 | 读 `msg` 直接展示给用户（原站 msg 是中文，如「账户不正确」「存在其他尚未支付的订单」） |
| **401** | 未登录/失效 | 触发静默续登并重放原请求 |
| 404 / 500 | 服务端异常 | 前端无特殊处理，仅 reject |

> **红线**：不要「优化」成 `code: 0 = 成功`。前端 `http.js:99` 硬判 `d.code === 1`。

### 1.4 分页结构

```json
{ "total": 100, "per_page": 10, "current_page": 1, "last_page": 10, "data": [] }
```

**入参不统一（原站历史包袱）**，必须三种写法**全部容忍**：

| 写法 | 示例端点 |
|---|---|
| `page` + `list_rows` | `/box/blind_box/list` |
| `page` + `limit` | `/activity/index`、`/lottery/*` |
| `page` + `pageSize` | `/union/index/logs` |
| 仅 `page` | 多数详情类列表 |

**建议实现**：统一读参工具按顺序取 `list_rows → limit → pageSize → 默认 10`。不要用单一字段名，否则部分页面翻页失效。

### 1.5 类型约定（传输层）

| 约定 | 例子 | 存储建议 |
|---|---|---|
| **金额 = string** | `total_price: "100.00"` `num: "1"` | 库里用 `DECIMAL(14,2)`，**禁用 float** |
| **布尔 = int** | `is_show: 1` `is_collection: 0` | `TINYINT(1)`，禁止输出 `true/false` |
| **时间 = int** | `createtime: 1792163273` | Unix 秒。`time` 字段实测过 int 与 string 两种，**统一输出 int** |
| 图片 = 相对路径 | `/uploads/20261016/xxx.jpg` | **不带域名**，前端拼 OSS |
| 空值 | 大量 `null`（如 `uc_ids`、`collection_code: []`） | 前端已做兜底，允许 null |

### 1.6 图片路径（易错点）

- 返回 `/uploads/...` 相对路径。
- 可带 CDN裁剪参数 `?image_process=resize,l_420`（阿里云 OSS 处理）。
- **前端 `oss()` 会主动 `split('?')[0]` 剥掉 query**（`http.js:128`），所以新后端带不带都能兼容 —— 但带了等于给前端增加无用负担，建议在**网关层**做裁剪分发，业务层只返裸路径。

> **自建后端时**：前端已统一走 `GET /static/oss/<OSS路径>` 取图（不再直连任何 CDN）。
> 该路径必须由后端提供：首次访问时抓取并缓存到本地磁盘，之后直接读本地。
> Mock 参考实现：`mock-app/app/controller/Image.php`（含魔数校验，防把CDN 错误页当图片缓存）。
> ⚠️ **前端 `public/static/` 下不得创建 `oss/` 子目录**，否则会遮蔽该服务导致藏品图裂。

---

### 1.7 不带 `/api` 前缀的接口（原站特例，漏实现就是404）

| 端点 | 方法 | 响应 | 说明 |
|---|---|---|---|
| `/addons/qrcode/index/build` | GET | **SVG/PNG 二维码**（非 JSON！） | 参数 `text_type=h5`、`text=<邀请码>`、`url=<站内路径>`、`t=<时间戳防缓存>` |

**易踩的三个坑**：
1. 前缀：**不带** `/api`。写成 `/api/addons/...` 必 404。
2. 响应：**图片字节**，不是 `{code,msg,data}` 信封。若走 SPA fallback 返回 HTML，`<img>` 拿到 HTML 就是图裂。
3. 路径三段都在（`addons/qrcode/index/build`），不要"顺手简化"。

Mock 参考实现：`mock-app/app/controller/Addons.php`（用 `endroid/qr-code` 的 `SvgWriter` 纯 PHP 生成，**无需 GD 扩展**）。
代理放行：`vite.config.js` 与 `server.cjs` 都要单独放行 `/addons/`，否则被静态资源处理吞掉。

---

## 2. 数据模型（实测字段）

> 全部来自带 token 的真实采样。**未列出的字段属于「可能存在但未观测」，不要凭猜写入 DDL。**

### 2.1 `user` —— 用户主表（90+ 字段）

按业务分组，仅列实测命名与真实样本值：

| 分组 | 字段（实测样本） |
|---|---|
| 身份 | `id:16797` `username:"175****1293"` `nickname:"GqdusoI30"` `mobile` `avatar:"/defautAvatar.png"` `level:1` `gender:1` |
| 资金 | `money:"0.00"` `agent_money:"0.00"` `recharge_price:"0.00"` `service_price:"0.00"` `user_ore:"0.000000"` `team_ore` `person_ore` |
| 积分/连签 | `score:0` `successions:4` `maxsuccessions:4` `prevtime:1792171727` |
| **邀请** | **`inviter_code:"Q28116797"`** `inviter_user_id:0` `inviter_status:0` `invite_type:1` `new_inviter_user_id` `yao_count` |
| 实名 | `real_name_authentication:1` `real_name:"王*绵"` `id_card:"520******838"` `id_card_front` `id_card_back` `real_name_time` |
| 支付通道 | `huifu_account:1` `yibao_status:0` `sandpay_status:0` `tipay_status:0` `yifutong_status:0` `pay_password:0` |
| 费率 | `huifu_service_charge:1` `sandpay_service_charge:0` `yifutong_service_charge:0` |
| 藏品统计 | `collection_num:1` `collection_total_price:"1.00"` `buy_collection_total_price:"1.00"` `blind_box_num:0` `crystal_yellow/purple/blue` |
| 权限 | `freeze:0` `is_white:0` `market_open:1` `transfer_permission:0` `transfer_permission_level:1` `is_vip:0` `is_show_rank:1` `show_wallet:1` |
| 账号状态 | `createtime` `updatetime` `logintime` `joinip` `loginip` `status:"normal"` `url:"/u/16797"` |
| 聚合 | `statistics:{invited_count, buy_count, sale_count, join_day}` |

**🔴 三个必记的坑**：

1. **邀请码字段名是 `inviter_code`**，不是 `invite_code` —— 前端曾因此导致二维码为空白。
2. **支付方式不是用 `auth_key` 动态取值**，而是硬编码的 `huifu_account` / `yibao_status` / `sandpay_status` 三个字段，判定方式是 `Number(u[statusField]) === 1`。前端已按此固化逻辑，后端字段改名前端就全崩。
3. **实名信息已是脱敏值**（`王*绵`、`520******838`）—— 说明脱敏在后端完成。**新后端必须在服务层脱敏，绝不能在 SQL 层返回原文再指望前端处理。**

### 2.2 `order` —— 订单（18 字段，实测）

| 字段 | 实测值 | 说明 |
|---|---|---|
| `id` | `18554` | |
| `order_sn` | `"2026101623075357101499336911"` | 时间戳+随机，建议唯一索引 |
| `artel_order` | `"mh_secondary_market_order"` | **多态来源标识**（场景前缀） |
| `order_type` | `2` | 订单类型（12 个，见 §2.6） |
| `order_status` | `3` | 订单状态（8 个，见 §2.6） |
| `total_price` | `"100.00"` | **字符串** |
| `num` | `"1"` | **字符串**（原站连数量都是字符串） |
| `goods_id` / `goods_type` | `155` / `2` | 2=藏品 |
| `goods_name` / `goods_cover_image` | `"赤壁之战"` / 相对路径 | 商品快照，下单时冗余写入 |
| `series_name` | `"历史区"` | 冗余 |
| `crystal_type` / `crystal_number` | `"crystal_no"` / `"0"` | |
| `uc_ids` | `null` | 关联 user_collection id |
| `collection_code` | `[]` | 藏品编号 |
| `createtime` / `updatetime` | `1792163273` | |

> **✅ 枚举已破解**（2026-10-17，见 §2.6）。`order_type=2 + order_status=3` 读出「订单已取消/取消成功」，与实测样本 `artel_order=mh_secondary_market_order` 语义吻合。

### 2.6 订单枚举（✅ 已从原站前端硬编码字典破解）

**证据等级：高**。原站前端把完整状态字典**硬编码在 webpack 模块 `95ba`** 中，被6 个订单页面 chunk 共享引用。

| 项 | 值 |
|---|---|
| 源文件 | `h5.gandart.shop/static/js/pages-order-list.{hash}.js` |
| 访问式 | `orderStatusInfo[order_type][order_status]` |
| 结构 | 外层 = `order_type`（**12** 个）、内层 = `order_status`（**8** 个） |
| 规模 | 48 个有效组合，每组含 `title` / `process` / `icon` / `textColor` |

**`order_type` 枚举**：

|值 | 含义 | 证据 |
|---|---|---|
| 1 | 普通订单 | 推断 |
| 2 | **市场订单** | 实证（`changeMarketOrderStatus` 分派） |
| 3 | 空投订单 | 实证（`status=2` 文案「空投转入成功」） |
| 4 | 预付订单 | 实证（Prepayment 独立链路） |
| 5 | 求购订单 | 推断（与 begBuy 模块对应） |
| 6 / 7 / 10 | `[待确认]` | 推断（文案与 1/2 同构） |
| 8 | **寄售订单** | 实证（`status=1` 文案「寄售成功」） |
| 11 | **批次订单** | 实证（`batchPayOrder`） |
| 19 | **转赠订单** | 实证（`regiftDetail` + `changeOrderStatus?type=19`） |
| 31 | `[待确认]` | 推断（文案与 11 完全同构，疑批次变体） |

**`order_status` 枚举**（语义随 type 变化，此处为通用语义）：

| 值 | 通用语义 | 实证依据 |
|---|---|---|
| 1 | 待付款 | 倒计时组件 + 「立即支付」按钮 |
| 2 | 已完成 | 「转入成功」文案 |
| 3 | **已取消** | `changeOrderStatus({status:3})` |
| 4 | 锁单中 | 「锁单中」文案（type=4/8） |
| 5 | 待退款 | 文案直出 |
| 6 | 已退款 | 文案直出 |
| 7 | 转入中 | 「支付成功,转入中」 |
| 8 | 寄售中 | 「已出售，结算成功」（type=8） |

**状态流转（由原站按钮行为逆向）**：

```
order_status==1 → 显示「立即支付」按钮
order_status==2 → 显示「取消订单」按钮
order_status==4 → 显示「重新求购」按钮
取消动作 = changeOrderStatus({order_type, status:3, order_sn})
求购单取消 = status 置4
```

**已产出可用的 PHP 枚举** → `docs/api/OrderEnum.php`

含 `TYPE` / `STATUS` / `STATUS_TEXT`（48 组合完整文案）/ `TRANSITIONS`（防越级改状态）/ `title()` / `process()` 助手方法，可直接引入项目。

> ⚠️ 仍未确认：`order_type` 的 6/7/10/31 四个值。建议查原站 DB `order.artel_order` 字段或与产品确认。

### 2.3 `user_collection` —— 用户持仓（含嵌套 `collection`）

| 字段 | 实测值 | 说明 |
|---|---|---|
| `collection_id` | `8` | 藏品本体 id |
| `num` / `sell_num` / `lock_num` | `1` / `"0"` / `0` | 持有 / 在售 / 锁定。**注意 `sell_num` 是字符串，另两个是 int —— 原站类型不一致，建议统一为 int** |
| `is_collection` | `1` | 是否持有 |
| `can_compound` | `0` | 可否合成 |
| `utime` | `1792049907` | |
| `collection` | 嵌套对象，见下 | 藏品本体快照 |

分页额外字段：`current_count`（当前持有统计）、`current_consignment`（当前寄售统计）。

**嵌套 `collection` 本体（10 字段）**：

| 字段 | 实测值 |
|---|---|
| `id` / `name` | `8` / `"思维粒子"` |
| `main_image` / `listimg` | 相对路径（两个可能不同） |
| `collection_level` | `""` |
| `total_num_show` / `total_num` | `0` / `100000` 展示发行量 / 真实发行量 |
| `is_img_diff` | `0` |
| `unfreeze_collection_id` / `freeze_time` | `0` / `0` |

### 2.4 其他小实体（实测）

| 实体 | 来源 | 字段 |
|---|---|---|
| Banner | `/banner/show/getBanner` | `id` `image` `url` `url_type:1` `is_show:1` |
| Affiche 公告 | `/index/afficheList` | `id:134` `image` `title` `createtime` `type:"1"` `url` `affiche_type:"运营公告"` |
| LoginResult | `/user/login` → `data.userinfo` | `id` `nickname` `mobile` **`token`** `user_id` `expiretime` `expires_in` `is_exist` |

### 2.5 索引建议（针对上述已知字段）

```
user            : UNIQUE(username)  UNIQUE(inviter_code)  idx(status,freeze)
order           : UNIQUE(order_sn)  idx(user_id,order_status,createtime)  idx(goods_id,goods_type)
user_collection : UNIQUE(user_id,collection_id)  idx(user_id,lock_num)
market_goods    : idx(goods_id,order_status,price)   ← 行情列表与撮合的核心查询
```

> 完整 DDL 需在补完 `order_status` 字典后产出（见 §7）。

---

## 3. Tier 1 端点实现规范（70 个）

按模块给出实现要点。**凡实测过的一次写入参规则。**

### 3.1 index / banner —— 首页（6）

| 端点 | 入参 | 响应 `data` | 实现要点 |
|---|---|---|---|
| `POST /index/afficheList` | `{page, category, list_rows, search}` | 分页·Affiche | `search` 用于「相关公告」，按标题模糊匹配 |
| `POST /index/affichetypelist` | `{}` | **数组** `[{id:1,name:"寄售公告"}...]` | ⚠️ **不是分页对象**，是裸数组。实测 5 项 |
| `GET /index/getSeries` | `{type:10}` | 数组 | type=10 为探索页系列 |
| `POST/GET /index/getConfig` | `[待确认] key` | — | 🔴 原站传 `site_name/site/site_url/app_name` 均返「缺少key值」。**本项目的 `getConfig` 无调用方**，可延后，勿盲猜 key |
| `GET /index/afficheDetail/ids/{id}` | — | Affiche 含 `content`(HTML) | ⚠️ 返回含富文本 HTML，**必须做 XSS 过滤**（见 §5.6） |
| `GET /banner/show/getBanner` | — | 分页·Banner | 首页首屏，建议缓存 60s |

### 3.2 user / auth / sms —— 账户（11）

| 端点 | 入参 | 要点 |
|---|---|---|
| `POST /user/login` | `{account, password}` | 返回 `data.userinfo.{token, expiretime, expires_in, is_exist}` |
| `POST /user/register` | `[待确认]` 含邀请码 | 🔴 邀请码字段必须叫 **`inviter_code`** |
| `POST /user/resetpwd` | `[待确认]` | |
| `POST /sms/send` | `{mobile, event}` | `event`: `register` / `resetpwd` |
| `POST /user/getUserInfo` | — | 返回 §2.1 全量 90+ 字段 |
| `POST /user/address_list` | `{}` | 收货地址 |
| `POST /user/ranking` / `newrank1` | 分页 | 排行榜 |

**安全强制项**（见 §5）：`login` 必须限流 + 失败计数；`sms/send` 必须图形验证码前置 + 60s 冷却 + 单日上限。

### 3.3 user_collection —— 持仓与寄售（5）

| 端点 | 入参 | 要点 |
|---|---|---|
| `POST /user_collection/user_collection/antMycollection` | 分页 | 「我的藏品」，含 `current_count`/`current_consignment` |
| `POST /user_collection/user_collection/getRecord` | 分页 | 购买/流转记录 |
| `POST /user_collection/user_collection/detail/ids/{ids}` | — | 路径参数必须命名 `ids` |
| `POST /user_collection/user_collection/show_resale_money` | `{price:100, user_collection_id}` | 转售手续费试算 |
| `POST /market/Prepayment/collectionDetail` | `{id, type}` | |

### 3.4 market —— 市场（9）

| 端点 | 入参 | 要点 |
|---|---|---|
| `POST /market/market/getMarketList` | 分页 | 行情主列表 |
| `POST /market/market/marketList` | 分页 | 幸运盒行情分支 |
| `POST /market/market/marketGoodsDetail` | `{id, type:2}` | 返回 `detail.key` —— **下单必需** |
| `POST /market/market/getMarketGoodsListByGoodsId` | `{page, sort, order, type, keyword, id}` | 🔴 数据在 **`data.list`**，不是 `data.data` |
| `POST /market/market/MarketDetail/ids/{id}` | — | |
| `POST /market/trade/tradingInformation` | 分页 | 委托挂单列表 |
| `POST /market/trade/begBuyList` | `{page, sort, order, type, keyword, id, collection_id}` | 求购列表 |
| `POST /market/trade/collectionDetail` | `{id, type}` | 交易藏品详情 |

> **`data.list` vs `data.data`**：这是踩过的坑（`PROJECT_MEMORY` §4）。新后端请**统一用 `data.list`**，因为前端已兼容 `d.list || d.data` —— 用 `list` 语义更清晰。

### 3.5 order / pay —— 订单与支付（13）

| 端点 | 入参 | 要点 |
|---|---|---|
| `POST /order/pay/fastOrderNew` | `{goods_id, key, pay_way}` | 🔴 **核心交易接口**。`pay_way` 传**通道名** `"huifu"`/`"yibao"`/`"sandpay"`。**返回 `order_sn`/`order_id`** |
| `POST /order/pay/doPay` | `[待确认]` | 发起支付 |
| `POST /order/pay/CreateMarketOrder` | `[待确认]` | 市场下单 |
| `POST /order/pay/CreateMarketOrderRush` | `[待确认]` | 抢购（需并发控制，见 §4.3） |
| `POST /order/pay/CreateOrder` / `CreateVipOrder` / `CreateHuifuAccountOrder` / `Recharge` / `batchBuy` / `batchdopay` / `paySmsCheck` | `[待确认]` | 前端已定义但**未接线或未实测** |
| `POST /order/order/orderList` | 分页 | 我的订单，返回 §2.2 结构 |
| `POST /order/order/orderDetail/ids/{id}` | — | type=1 |
| `POST /order/order/MarketOrderDetail/ids/{id}` | — | type=2 |
| `POST /order/order/myConsignment` | 分页 | 我的寄售 |
| `POST /order/order/batchPayOrder` | 分页 | |
| `POST /order/order/getMarketOrderDetails` | `{order_id}` | |

**🔴 三个「 Ordering 必死」的点（实测踩过，务必在后端侧加固）**：

1. 端点名是 **`fastOrderNew`**。原站曾被误猜为 `fastCreateOrder` 返回 404。新后端不要「修正」这个名字。
2. `key` 必须来自 **`marketGoodsDetail` 的 `detail.key`**。从列表 `priceList[0].key` 取会导致后端返 `code:0 未找到合适商品` —— 建议后端在收到无效 key 时返回**更明确的 msg**。
3. **同一用户存在未支付订单时**，原站返回「存在其他尚未支付的订单」（这是真实业务状态，非 bug）。新后端应保留此校验，并给出**结构化的错误码**而非纯文案。

### 3.6 box / synthesis —— 盲盒与合成（8）

| 端点 | 入参 | 要点 |
|---|---|---|
| `GET /box/blind_box/list` | `{page, list_rows}` | 发售日历。实测无参返回 `data:[]` |
| `POST /synthesis/themeinfo` | `{thm}` | 🔴 **必须 POST**，GET 无效 |
| `POST /synthesis/material` | `{thm, ...}` | 返回 4003=无资格 / 3003=直接进方案详情 |
| `GET /synthesis/recordList` | 分页 | |
| `POST /lottery/info` | `{id}` | ⚠️ **当前绕过 API 层**直接调 `http` |
| `POST /lottery/statistics` | `{lottery_id}` / `{page, limit}` | 同一端点两种入参形态 |
| `POST /lottery/start` | `[待确认]` | 抽奖（写操作） |
| `POST /lottery/record` | `{page, limit:20}` | |

> `lottery` 这 4 个端点**未进 `src/api/index.js` 登记**（散落在 `Lottery.vue:97,109,156` 与 `Record.vue:40,45`）。收拢时可一并规范（见「依赖原站清单」待办 #5）。

### 3.7 其余 Tier 1（8）

`activity/index`、`/Activity/category`、`crystal_goods/list`、`union/user/myunion`、`union/index/logs`、`market/Prepayment/myOrderlist`、`market/trade/entrust`、`market/trade/begBuy`、`market/trade/entrustOrderList`、`market/trade/begBuyOrderList`、`market/trade/begBuyOrderInfo`、`market/Prepayment/myOrderInfo`、`order/order/saleMarketOrderDetail`。

其中 `/Activity/category` 注意**首字母大写**（ThinkPHP 遗留），不要「修正」为小写。

### 3.8 特殊：`/addons/qrcode/index/build`

| 项 | 值 |
|---|---|
| 路径 | **不带 `/api` 前缀** |
| 方法 | GET |
| 返回 | **PNG 二进制**，不是 JSON |
| 入参 | `?text_type=h5&text=<邀请链接>` |

> **网关必须单独放行 `/addons/`**，否则被 SPA fallback 吞成 `index.html`，前端 `<img>` 拿到 HTML → 二维码图裂。这是第二次踩同一个坑（`PROJECT_MEMORY` §3.2）。

---

## 4. 核心交易链路（必须优先保证）

### 4.1 快捷下单时序（实测）

```
用户点「快捷下单」
  ↓
getMarketGoodsListByGoodsId {page, sort:"price", order:"asc", type:2, keyword:""}
  → data.list[] 在售挂单
  ↓
marketGoodsDetail {id, type:2}        → 取 detail.key ← 关键，列表里没有
  ↓
user/getUserInfo                       → huifu_account / yibao_status / sandpay_status (=1 才显示)
  ↓
弹层显示支付方式（仅 able=true 的通道）
  ↓
fastOrderNew {goods_id, key, pay_way:"huifu"}
  → data.order_sn / data.order_id
  ↓
「支付风险确认页」（合规要求，不可省略）
  ↓
doPay  发起支付
```

### 4.2 实现时的检查清单

| # | 检查项 | 后果 |
|---|---|---|
| 1 | `marketGoodsDetail` 必须返回 `detail.key` | 缺失 → 下单报「未找到合适商品」 |
| 2 | `data.list` 而非 `data.data` | 前端兼容两者，但 `list` 是约定 |
| 3 | 挂单行必须带 `key` 字段 | 同上 |
| 4 | 支付方式三个字段命名不得改 | 前端硬编码 `huifu_account` 等 |
| 5 | 同用户未完成订单校验 | 缺 → 超卖风险 |
| 6 | 金额全程 DECIMAL/string | float → 分账不平 |

### 4.3 并发与幂等（建议，原站未做）

原站 `fastOrderNew` 未见幂等键，这是**高风险**。建议新后端补上：

| 机制 | 做法 |
|---|---|
| **幂等键** | 接受 `Idempotency-Key` 头或 body 内 `request_id`，60s 内重复请求返回首次结果 |
| **库存扣减** | `UPDATE user_collection SET lock_num=lock_num+1 WHERE id=? AND num-lock_num>0`，用行数判断成败 |
| **抢购防超卖** | `CreateMarketOrderRush` 必须走 DB 行锁或 Redis Lua，不能「先查后写」 |
| **订单状态机** | 状态流转用 `UPDATE ... WHERE order_status=<期望值>` + 影响行数校验 |
| **事务边界** | 下单+冻结+流水必须在同一事务 |

---

## 5. 安全加固清单（写后端时逐条对照）

> 来源：`backend-security-coder` 规范 + 本项目的具体风险点。

### 5.1 必须删除/关闭的原站遗留（安全债）

| 端点 | 风险 | 处置 |
|---|---|---|
| **`/functions/invokeFunction`** | 🔴 **动态函数调用 = RCE** | **绝对不要实现**。新后端连路由都不要注册 |
| `/index/gettest` | 测试接口暴露 | 不迁移 |
| `/user/delAccount` 等 | 无二次校验 | 加短信/密码二次确认 |

### 5.2 注入防护

- **全部 SQL 参数化 / 预编译**，禁止字符串拼接。
- 特别注意：`ids` 路径参数、`sort`/`order` 字段（不能白名单外的排序字段直接进 SQL）。
- `search` / `keyword` 模糊查询用参数化 `LIKE ?`，别拼 `%`。

### 5.3 认证与会话

| 项 | 要求 |
|---|---|
| 密码哈希 | **Argon2id**（或 bcrypt cost≥12）。原站强度未知，迁移时必须全量重置或惰性升级 |
| token | 随机 ≥128 bit，服务端可失效。禁止自增 id 当 token |
| 过期 | 保留 `expiretime` / `expires_in` 字段语义，**必须有过期** |
| 登录限流 | IP + 账号双维度，失败 5 次锁 15 分钟 |
| 短信 (`sms/send`) | 图形验证码前置 + 60s 冷却 + 单手机号单日上限 + IP 限流（**短信炸弹是直接资损**） |

### 5.4 越权（IDOR）

- 所有「我的 XX」接口（`orderList`、`antMycollection`、`address_list`、`myOrderlist`）**必须用 token 里的 user_id 作为过滤条件**，禁止信任请求体里的 id。
- `detail/ids/{ids}` 类详情，需校验该 resource 属于当前用户。

### 5.5 敏感数据

- **`real_name` / `id_card` 必须在服务端脱敏**（实测已是 `王*绵`、`520******838`），不得返回原文。
- 日志**禁止**记录 password、token、身份证、完整银行卡。
- `msg` 不外泄堆栈 —— 原站 debug 关闭后统一 "An error occurred"，可保留此举但**增加 `trace_id`** 便于排障。

### 5.6 输出编码

- `/index/afficheDetail` 返回 `content` 是 **HTML 富文本** → 必须在**入库前**做 XSS 白名单过滤（保留有限的 img/p/br），不能依赖前端转义。
- OSS 图片下载回源（如保留 `server.cjs` 兜底逻辑）必须限制目标域名白名单，防 SSRF。

### 5.7 CORS 与头

- CORS 改为**可配置白名单**（原站硬编码单域名 `https://h5.gandart.shop`），容纳预览/多环境域名。
- `Access-Control-Allow-Credentials: true` 时，**`Allow-Origin` 不得为 `*`**。

### 5.8 审计日志

下单、支付、提现、转赠、实名、改密、绑定银行卡 —— **全部记审计日志**（who/when/what/before/after/IP），这是资金类系统的合规底线。

---

## 6. 前端兼容红线（改了就崩）

汇总 `PROJECT_MEMORY.md` §5.1 + 本项目实测确认的**九条**：

| # | 契约 | 前端依据 |
|---|---|---|
| 1 | `code`：**1=成功**，0=失败 | `http.js:99` 硬判 |
| 2 | 信封固定 `{code,msg,time,data}` | 四处解构 |
| 3 | 列表 `{total,per_page,current_page,last_page,data}` | 分页渲染 |
| 4 | **金额字符串** `"100.00"` | 显示直接拼接 |
| 5 | 布尔 int `0/1` | 多处 `Number()` 判定 |
| 6 | 图片相对路径 `/uploads/...` | `oss()` 拼域名 |
| 7 | 路径参数名 `ids` | 前端硬编码 |
| 8 | **全部 POST** | 前端 axios 封装 |
| 9 | token **header + query 双通道** | `http.js:56,59` |

**额外三条（本项目特化）**：

| # | 契约 | 依据 |
|---|---|---|
| 10 | 邀请码字段 **`inviter_code`** | 曾误用 `invite_code` 致二维码空白 |
| 11 | 支付方式字段 `huifu_account`/`yibao_status`/`sandpay_status`，值 1=开通 | 曾致「所有支付方式不显示」 |
| 12 | `/addons/` 不带 `/api` 且返 PNG | 曾致二维码图裂两次 |

---

## 7. 待确认项（OPEN-DECISIONS）

> 写后端前必须逐个关闭，**不要用猜的**。

| ID | 待确认项 | 现状 | 阻塞什么 | 建议解法 |
|---|---|---|---|---|
| **OPEN-01** | ~~后端技术栈~~ | ✅ **已定：PHP** | — | 建议 ThinkPHP 6.x（见下方） |
| **OPEN-02** | 数据源切换方式 | 双写？灰度？影子库？ | 上线方案 | 建议先用 mock 对齐契约 |
| **OPEN-03** | ~~`order_status`/`order_type` 枚举~~ | ✅ **已破解**（§2.6） | — | 已产出 `OrderEnum.php`；剩余 4 个 type 值待产品确认 |
| **OPEN-04** | 写操作端点的真实入参 | 下单/支付/开盒/签到/提现**从未实际提交过** | §3.5 全部 `[待确认]` | 逐个与产品/前端确认 |
| **OPEN-05** | `getConfig` 的 `key` 取值 | 试了 4 个值都失败 | 该端点无调用方，暂不阻塞 | 从原站 JS 里再挖 |
| **OPEN-06** | `pay_way` 完整枚举 | 已知 huifu/yibao/sandpay | 支付弹层 | 查 user 表六个 *_status 字段 |
| **OPEN-07** | `/lottery/*` 4 端点是否收拢进 API 层 | 当前散落在页面 | 拦截器统一治理 | 见依赖清单待办 #5 |

### OPEN-01 技术栈决策 ✅ 已定：PHP

**2026-10-17 决策：后端用 PHP。**

在此前提下，两个子选项：

| 方案 | 评价 |
|---|---|
| **ThinkPHP 6.x**（推荐） | ✅ 与原站同构。221 端点的路由约定（`模块/控制器/方法` + `ids` 绑定 + `addons` 插件）**天然复用**，`success()` helper 直接产出 `{code,msg,time,data}`，分页器直出标准结构。**前端零改动，成本最低** |
| Laravel | 更现代但需自建兼容层（响应信封、`code=1` 语义、全 POST 映射、Paginator 结构），多花 2-3 周 |

**结论：用 ThinkPHP 6.x。** 理由 —— 原站已被逆向确认是 ThinkPHP（错误页模板 + 三段式路由 + 响应信封 + Paginator 四重证据），沿用同框架可让 221 个端点的路由层几乎零成本迁移，且资金类业务在同框架下有大量已验证的实践。**§1 全部契约与 §6 兼容红线不受选型影响**，直接照做即可。

---

## 8. 验收标准（EARS 格式，QA 据此测试）

| ID | 场景 | 验收标准 | 优先级 |
|---|---|---|---|
| AC-01 | 登录成功 | When 提交正确的 `{account,password}`，系统**必须**返回 `code=1` 且 `data.userinfo.token` 非空 | P0 |
| AC-02 | 登录失败 | If 账号密码错误，系统**必须**返回 `code=0` 且 `msg` 为中文可读提示 | P0 |
| AC-03 | token 双通道 | While 请求仅携带 query `?token=`，系统**必须**视为已登录 | P0 |
| AC-04 | 仅带 header token | While 请求仅携带 header `token`，系统**必须**视为已登录 | P0 |
| AC-05 | token 失效 | If token 失效，系统**必须**返回 **HTTP 401** 且 body `code=401` | P0 |
| AC-06 | 金额类型 | When 返回任一金额字段，系统**必须**输出字符串且保留 2 位小数 | P0 |
| AC-07 | 布尔类型 | When 返回任一布尔字段，系统**必须**输出 int `0/1`，不得为 `true/false` | P0 |
| AC-08 | 分页结构 | When 返回列表，系统**必须**包含 `{total,per_page,current_page,last_page,data}` | P0 |
| AC-09 | 分页入参兼容 | While 分别以 `list_rows`/`limit`/`pageSize` 传分页大小，系统**必须**均正确响应 | P1 |
| AC-10 | 快捷下单 | When 传合法 `{goods_id,key,pay_way}`，系统**必须**返回 `order_sn` | P0 |
| AC-11 | 重复下单 | While 存在未支付订单，系统**必须**拒绝并返回明确错误码 | P0 |
| AC-12 | 越权访问 | If 请求他人资源 id，系统**必须**返回 403 而非数据 | P0 |
| AC-13 | 脱敏 | When 返回实名信息，系统**必须**输出脱敏结果 | P0 |
| AC-14 | RCE 端点 | When 请求 `/functions/invokeFunction`，系统**必须**返回 404 | P0 |
| AC-15 | 二维码 | When 请求 `/addons/qrcode/index/build`，系统**必须**返回 PNG 二进制（Content-Type: image/png） | P1 |
| AC-16 | 短信限流 | While 同一号码 60s 内重复请求，系统**必须**拒绝 | P0 |

### 端到端验证脚本（冒烟）

```bash
# 1. 登录 → 取 token
curl -s -X POST https://<host>/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"account":"<a>","password":"<p>"}'
# 断言：code=1 且 data.userinfo.token 非空 → 记为 TK

# 2. 仅 query 通道（模拟网关剥 header）
curl -s -X POST "https://<host>/api/user/getUserInfo?token=$TK" \
  -H "Content-Type: application/json" -d '{}'
# 断言：code=1（验证 AC-03）

# 3. 越权（应 403）
curl -s -X POST "https://<host>/api/order/order/orderDetail/ids/1?token=$TK" \
  -H "Content-Type: application/json" -d '{}'
# 断言：非他人订单数据

# 4. RCE 端点必须不存在
curl -s -o /dev/null -w "%{http_code}" -X POST "https://<host>/api/functions/invokeFunction"
# 断言：404

# 5. 二维码返回 PNG
curl -s -o /dev/null -w "%{content_type}" \
  "https://<host>/addons/qrcode/index/build?text_type=h5&text=https%3A%2F%2Fx"
# 断言：image/png
```

---

## 附：本文与前序文档的关系

| 文档 | 作用 | 何时读 |
|---|---|---|
| **`本项目`（实现规范）** | 写后端时照着做 | 开发期 |
| `docs/api/API.md` | 原站侦察原始证据（221 端点全表） | 需要某个 Tier 2 端点时 |
| `PROJECT_MEMORY.md` | 9 条契约 + 13 条踩坑 | 每次开工前 |
| `docs/依赖原站清单.md` | 11 处硬编码 URL + 21 个 oss() 调用点 | 切自有后端时改 IP/域名 |
| `docs/AUDIT-API-外链.md` | 缺陷清单（含 `CollectionList` 死路由） | 联调排障时 |
