# OPEN-DECISIONS —— 悬而未决登记册

>铁律：只追加 + 就地关闭（OPEN → RESOLVED，补Resolution 字段）。
> 每次 Phase 开始时把未决项复现到工作上下文最前面，逐条判断能否关闭。
> 已关闭的项可升格为 ADR。

**汇总：4 未决+ 0 已决**

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
