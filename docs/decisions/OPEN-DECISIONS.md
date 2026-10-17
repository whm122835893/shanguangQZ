# OPEN-DECISIONS —— 悬而未决登记册

>铁律：只追加 + 就地关闭（OPEN → RESOLVED，补Resolution 字段）。
> 每次 Phase 开始时把未决项复现到工作上下文最前面，逐条判断能否关闭。
> 已关闭的项可升格为 ADR。

**汇总：2 未决+ 0 已决**

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