# OPEN-DECISIONS —— 悬而未决登记册

>铁律：只追加 + 就地关闭（OPEN → RESOLVED，补Resolution 字段）。
> 每次 Phase 开始时把未决项复现到工作上下文最前面，逐条判断能否关闭。
> 已关闭的项可升格为 ADR。

**汇总：7 未决 + 1 已决**

---

## D-001 user.is_exist 字段语义未确认

| 项 | 内容 |
|----|------|
| Date | 2026-10-18 |
| Source | Phase 2 · user/login 契约对齐 |
| slug | `design-decision-to-evaluate` |
| Related Constraints | 仅 `user/login` 下发，`user/getUserInfo` 不下发；实测值恒为 1 |
| Current Leaning | 疑为「账号资料是否已完善」或「用户记录是否存在」，但**无证据** |
| Blocked By | 只有一个样本值（恒 1），单点无法反推语义 |
| Resolves When | 能抓到 `is_exist=0` 的账号样本，或找到前端代码里对该字段的分支判断 |
| Status | **OPEN** |

**背景**

`recon/raw/_login.json` 实测 `data.userinfo.is_exist = 1`，且该字段**只出现在登录响应**，
`user/getUserInfo` 的 102 字段里没有它。本地建模时漏了这列，导致登录出参 `is_exist=0`
（原站为 1），由 `tools/contract/run.php` 的 login 用例抓出。

**已做的处置（契约优先，不猜语义）**

1. DDL + 实际库 + `User::$schema` + seed 四处同步加列
2. `DEFAULT 1` 对齐实测，但 seed **显式从登录样本取值**而非吃默认值
   —— 理由：吃默认值属于「默认值恰好对」，一旦有人改 DEFAULT 就会静默漂移
3. 契约测试纳入 login 用例，值差异会立刻标红

**Resolution**（待填）

---

## D-002 market 域写端点无成功样本

| 项 | 内容 |
|----|------|
| Date | 2026-10-18 |
| Source | Phase 1 · 样本资产盘点（240 个样本） |
| slug | `waiting-on-external-condition` |
| Related Constraints | `market/trade/begBuy` 等写端点；`getMarketGoodsListByGoodsId` 样本为空数组 |
| Current Leaning | 挂起，只做读端点（`collectionDetail` / `tradingInformation`） |
| Blocked By | 测试账号在原站**无挂单数据**，无法触发写端点成功路径 |
| Resolves When | 账号在原站有在售挂单，或用第二个账号挂单后再抓 |
| Status | **OPEN** |

**背景**

样本盘点 7 分类法结果：A类（成功有数据）46个 / B 类（成功空）38 / C类（业务错误）89 /
D 类（服务异常）15 / E 类（404 已下线）32 / F 类（401 需重抓）20 / G 类（解析失败）0。

写端点全在 C/D/F 类，没有一条成功样本。**没有样本就没有契约**，
凭端点名猜字段是本项目明令禁止的做法。

**可推进的部分（不阻塞）**

- 20 个 401 样本可重抓（原站 token 在 `recon/.token`，仍有效）
- 读端点 `market/trade/collectionDetail`、`market/Prepayment/collectionDetail` 有A 类样本，可立即切

**Resolution**（待填）
---

## D-003 collection_id=8（思维粒子）无费率规则数据

| 项 | 内容 |
|----|------|
| Date | 2026-10-18 |
| Source | Phase 3 · market/trade/collectionDetail 切 DB |
| slug | `existing-design-boundary` |
| Related Constraints | seed 铁律：只灌实测样本里有的值，禁止编造 |
| Current Leaning | 保持现状（该藏品请求返回空 data），不造假规则 |
| Blocked By | 原站只抓到 collection id=1 的 collectionDetail 样本；id=8 的规则值无实测出处 |
| Resolves When | 抓到 id=8（或任意其他藏品）的 collectionDetail 样本，补灌即可 |
| Status | **OPEN** |

**背景**

`collection` 表现有两条记录：id=8（思维粒子，来自 antMycollection 嵌套样本）与
id=1（小小键盘-P仔，来自 collectionDetail 样本）。但 `consignment_price_rule`
只有 id=1 的 trade/prepay 两条规则。

前端若对 id=8 打开寄售/预付页面，会拿到 `data: []`。前端两个消费页面
（SubmitBegBuy.vue / SubmitEntrust.vue）均有 `r.data` 判空保护，不会崩，
但页面文案为空。

**已做的处置**

返回空数组而非伪造规则。伪造一条"看起来合理"的费率（比如照抄 id=1 的值）
会让前端按错误的 7% 费率计算手续费并展示给用户——**编造的费率比空数据危险得多**。

---

## D-004 私有方法跨类调用 fatal —— 为什么必须抽 trait 而非复制

| 项 | 内容 |
|----|------|
| Date | 2026-10-18 |
| Source | Phase 3 · TradeController 拆分门禁 |
| slug | `existing-design-boundary` |
| Related Constraints | 单文件 ≤300 行 P0 红线；PHP trait 方法冲突规则 |
| Current Leaning | 共用逻辑抽 `Concerns\MakesOrders` trait，两个 Controller 分别 `use` |
| Blocked By | 无（已实施并验证） |
| Resolves When | 已关闭于 v2.4：CreateVipOrder 等 5 端点实测从 500 恢复 200 |
| Status | **RESOLVED** |

**背景**

v2.3 大拆分时 `makeOrder()` 留在 TradeController 且是 `private`，而
PayController 的 5 个 `Create*Order` 端点仍在调 `$this->makeOrder()`。
两类互不继承 → 必然 fatal。实测：`POST /api/order/pay/CreateVipOrder`
返回 500 HTML 错误页。

**为什么静态检查发现不了**

PHP 方法解析在运行期。`php -l` 只查语法；lint 不查跨类方法存在性；
只有真实 HTTP 请求打到那个端点才暴露。同类坑本项目已犯过一次
（ContentPlayController 漏 `use HasAuth`），**两次都是 HTTP 层复验抓出的**。

**为什么抽 trait 而不是各复制一份**

复制两份 = 未来改下单逻辑要改两处，必然漏改。trait 是单一出处。
注意 trait 内不能再 `use HasAuth`——与宿主类的 `use HasAuth` 构成
同名私有方法冲突，PHP 直接 fatal。约定宿主自行提供 `currentUser()`。

---

## D-005 tradingInformation 统计语义与 list 行结构无样本

| 项 | 内容 |
|----|------|
| Date | 2026-10-18 |
| Source | Phase 3 · market/trade/tradingInformation 切 DB |
| slug | `waiting-on-external-condition` |
| Related Constraints | 样本账号无挂单/成交（全空态）；前端 SellInfo.vue 复刻注释的 list 行字段带省略号 |
| Current Leaning | 结构契约严格按样本实现（9 键 + 双 null）；统计取「结构上有依据的最小过滤」 |
| Blocked By | 账号需在原站有挂单/成交，才能抓到非空样本验证语义与行结构 |
| Resolves When | 抓到 transaction_record_number>0 或 list 非空的样本 |
| Status | **OPEN** |

**背景**

`recon/raw2/market_trade_tradinginformation.json` 是 B 类样本（成功但空）：
9 个键的**类型契约**完整（6 int + list[] + 双 null），但所有值都是 0/空。
`order/order/myConsignment` 样本同样空（total=0）。

**已锁定的契约（无需样本也可确认）**

- `today_highest_price` / `today_lowest_price` 无成交时是 **null 不是 0**：
  给 0 前端会显示「今日最高 ￥0」的假成交。已由契约用例 + HTTP 验证双重锁定
- 9 键集合与顺序，与样本逐字段一致

**无法确认的三件事（等非空样本）**

1. `transaction_record_number` 的成交态判定：哪些 `order_status` 算「成交」？
   当前实现只按 `goods_id + goods_type` 过滤，**不过滤 status**——
   漏算是可见 bug，错算是静默错数，两害取其轻
2. `floor_price` 是否等于 `min(consignment.price where status=1)`？当前按此实现，
   本地无寄售单恒 0，无法证伪
3. `list` 行结构：前端复刻注释只列出 `{price, collection_code, total_num, time, ...}`
   且带省略号。**在拿到完整行样本前，list 恒返回 []**，
   禁止用 orderList 的行结构（MockData::order）凑数——那是跨端点套用字段

**已做的处置**

统计查询抽 `app/service/MarketStats.php`（Controller 与契约用例共用同一入口，
用例测真实查询而非副本）；本地数据状态下所有统计恒等于样本值，且是真实查询结果。

---

## D-006 begBuyList / batchPayOrder 非空态契约无样本

| 项 | 内容 |
|----|------|
| Date | 2026-10-18 |
| Source | Phase 3 · begBuyList + batchPayOrder 切契约 |
| slug | `waiting-on-external-condition` |
| Related Constraints | begBuyList 样本 `recon/raw4/market_trade_begBuyList_161.json` 是 B 类空态；batchPayOrder 样本同为 B 类；求购写端点 `begBuy` 无成功样本（承 D-002） |
| Current Leaning | 空态契约已全锁（begBuyList 5 键键序 + per_page/last_page=0；batchPayOrder 2 字段 int 0）；`data` 行结构在拿到非空样本前恒 `[]` |
| Blocked By | 账号需在原站有求购单 / batchBuy 待付批量，才能触发非空路径 |
| Resolves When | 抓到 begBuyList total>0 或 batchPayOrder data 非空的样本 |
| Status | **OPEN** |

**背景**

两个端点的**空态契约**均已实测锁定并经契约用例 + HTTP 验证双重覆盖：

- `begBuyList`：5 键信封 `{current_page, data, last_page, per_page, total}`，键序 current_page 开头（与 orderList 的 total 开头相反），空态 `last_page=0 / per_page=0`（不是 `Response::page()` 的 `max(1,...)` 钳位值）
- `batchPayOrder`：`{batch_order_id: int 0, data: []}`。恒空态是**复探实证**——原站账号挂着 2 笔市场单 + 2 笔 VIP 待付单时仍空，说明「批量」专指 batchBuy 待付批量，不是占位符

**无法确认的部分（等非空样本）**

1. `begBuyList` 的 `data` 行结构：前端复刻注释只列 `{price, num, time, ...}` 带省略号。禁止用 orderList 行结构（`MockData::order`）凑数——跨端点套用字段正是本项目明令禁止的
2. `batchPayOrder` 非空时 `batch_order_id` 的语义（批量组 id？）与 `data` 行结构
3. begBuyList 非空时 `per_page` 是否变正值、`sort=asc/desc` 的排序字段语义

**已做的处置**

本地 `beg_buy_order` 表 0 行（无种子来源，D-002），`total` 恒 0 与样本一致，
且是 `MarketStats::begBuyOrderCount()` 真实查询结果。契约用例注释已标注：
将来灌入求购数据本用例会红——那是预期信号，非空契约需要新样本才能确立。

---

## D-007 CreateVipOrder 的 order_id 递增语义无法区分 per-account 还是全局自增

| 项 | 内容 |
|----|------|
| Date | 2026-10-18 |
| Source | Phase 3 · CreateVipOrder 复探原站 |
| slug | `waiting-on-external-condition` |
| Related Constraints | 无列表端点暴露 VIP 订单（orderList 不含 VIP 单，已实证），id 语义不影响任何已测契约 |
| Current Leaning | 本地按 `vip_order` 表主键全局自增实现；账号过滤查询时表现与 per-account 递增不可区分 |
| Blocked By | 需要**第二个账号**在原站下一单才能区分 |
| Resolves When | 用第二账号调 CreateVipOrder：若返回 "1" 则是 per-account，若接着第一账号的计数（如 "3"）则是全局自增 |
| Status | **OPEN** |

**背景**

复探原站时同账号连调两次 `CreateVipOrder`，`order_id` 从 `"1"` 变 `"2"`——
这证明订单**真实持久化且 id 递增**（内存实现会恒返回 "1"），但无法区分：

- per-account：每个账号从 "1" 开始计数
- 全局自增：全表一个计数器，第二账号首单会拿到 "3"

**为什么暂时不影响交付**

1. 没有任何已实测端点**列出** VIP 订单（`orderList` 实测不含 VIP 单），id 只在下单响应里出现一次
2. 两种语义在下单响应上不可区分（同账号第二单都是 "2"）
3. 前端消费点只有「拿 order_id 去支付」一种用法，不依赖 id 的绝对值

**已做的处置**

`vip_order` 表按主键自增（全局）+ `user_id` 列记录归属 + `idx_user_status` 索引——
将来若证明是 per-account，改查询口径即可，不需要改表结构。
