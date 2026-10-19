# 项目记忆：闪光集市 H5 复刻

> 本文件是**跨会话记忆入口**。新会话开始时，先读本文件，再读 `docs/api/API.md`（后端契约）。
> 记录的是「对话里问不出来、只有踩过坑才知道」的知识——文件里没有的，说明还没踩到。

---

## 0. 一句话说明项目

对 `https://h5.gandart.shop`（闪光集市）做**一比一像素级复刻**，Vue 3 + Vite 重建，覆盖原站全部 118 条路由。

| 指标 | 值 |
|---|---|
| 路由 | 118（原站 116 + 2 条重定向别名） |
| 页面组件 | 114 个 `.vue` |
| 已登记 API | 62 个 |
| 线上预览 | `https://a9a528004a50bce1f.app.workbuddy.host` |
| 代码仓库 | `https://github.com/whm122835893/shanguangQZ` |
| 原站 API | `https://api.gandart.shop` |
| 图片 OSS | `https://img.gandart.shop` |

---

## 1. 侦察基础设施（复用这些，别重写）

`_recon/` 目录（**不在 git 仓库内**，仅本机）：

| 文件 | 用途 |
|---|---|
| `routes.json` | 从 `src/router/index.js` 导出的 118 条路由，供遍历脚本使用 |
| `getjs2.py` | 遍历全站路由下载原站 chunk（拿到 126 个） |
| `apicap.py` / `apiresp.py` | 抓原站 API 调用与请求体 |
| `apisample.py` / `apisample2.py` | curl 采样 API 响应（后者带 token） |
| `extfull.py` | 全站外链 + 图裂扫描 |
| `chk4.py` / `invchk_on.py` | 核心页面回归验证 |

**像素对比方法**：viewport `390×844`，`device_scale_factor=2` → 截图 `780×1688`。
`thr40` = 任一 RGB 通道差 > 40 的像素比例，主用指标。

---

## 2. 环境陷阱（会导致任务莫名失败）

### 2.1 git 连 GitHub 必须用 HTTP/1.1 ⚠️
```
git config --global http.version HTTP/1.1   # 已配置
git config --global http.postBuffer 524288000
```
HTTP/2 握手在此网络下不通，`git fetch` 会卡 134 秒后失败。已写入全局配置。

### 2.2 本地起服务不能用 `pkill -f "node server.cjs"`
会**把自己也杀掉**（SIGTERM/143）。正确做法：
```bash
(PORT=4190 nohup node server.cjs > /tmp/srv.log 2>&1 & echo $! > /tmp/srv.pid)
kill $(cat /tmp/srv.pid)
```

### 2.3 🔴🔴 推 GitHub：后端代码在 `server/backend/` 前缀下，不是仓库根目录

**这是本项目最容易反复踩的坑，已犯两次。**

远端 `whm122835893/shanguangQZ` 的目录约定：

| 路径 | 内容 |
|---|---|
| `server/backend/**` | **ThinkPHP 后端全部代码**（87 个文件） |
| `server/sql/**` | 数据库 DDL（7 个 .sql） |
| `src/**` | Vue 3 前端 |
| `docs/**` | 文档（api/ db/ decisions/） |
| `public/**` | 静态资源 |
| `tools/**` | recon 侦察脚本（根级，不属后端） |

本地工作区 `/workspace/mock-server/mock-app` **没有** `server/backend/` 这层目录，
它不是从远端克隆的。所以**本地相对路径 ≠ 远端路径**。

早期推送脚本直接用本地相对路径写入 tree，结果连续两次把新文件推到**仓库根目录**：

```
app/common/Msg.php              ← 错在根目录
app/model/UnionConfig.php       ← 错在根目录
tools/contract/Cases.php        ← 错在根目录
```

而 `server/backend/` 下的主副本根本没有这些文件 —— **等于代码没进仓库**。

**正确做法**（脚本已带`--prefix`，别去掉）：

```bash
python3 /workspace/recon/push-github.py \
    /workspace/mock-server/mock-app main whm122835893/shanguangQZ <token> \
    --prefix server/backend/

python3 /workspace/recon/sync-backend-prefix.py <token>          # 全量归位 + 清理影子
python3 /workspace/recon/verify-remote-sync.py <token>           # 只读比对，动手前必跑
```

**推完必须验证，不能只看「提交成功」**：

```bash
python3 /workspace/recon/verify-remote-sync.py <token>
# 期望：SHA 一致 87 / 分叉 0 / 缺失 0 / 远端有本地无 0
```

「远端有本地无」若不为 0，说明远端有本地缺失的历史资产，
此时**直接覆盖会丢代码**，必须先停下来查清。

**连带坑**：沙箱到 `github.com:443` 的 git 协议不通，只能用
Git Data API（blob -> tree -> commit -> ref）。`api.github.com` 通。

### 2.4 `python3` 环境
playwright 已装。超长脚本会**自动转后台任务**，需用 `TaskOutput` 取结果，别用 `sleep` 轮询。

---

## 3. 架构关键决策（改之前先读）

### 3.1 线上必须是 Node 服务，不能纯静态托管 ⚠️ 最容易踩
`dist` 内 axios `baseURL='/api'`。纯静态托管 → 所有接口 404 → 首页轮播空白、藏品全裂。

```json
"scripts": { "start": "node server.cjs" }
```
发布必须带：`--language node --start-cmd "node server.cjs"`
**缺 `start` 脚本 → 发布器探测不到 Node 服务 → 静默降级为静态托管**，这个坑非常隐蔽。

`server.cjs` 承担三件事：
1. 静态托管 `dist`
2. `/api` 反代到 `api.gandart.shop`
3. `/static/oss` 未预下载图片回源落盘

### 3.2 `addons/` 必须单独放行
`/addons/qrcode/index/build` **不带 `/api` 前缀**且**返回 PNG 而非 JSON**。
未放行 → 被 SPA fallback 吞成 `index.html` → `<img>` 拿到 HTML → 图裂。
```js
if (req.url.startsWith('/api') || req.url.startsWith('/addons/')) { /* 代理 */ }
```

### 3.3 `oss()` 必须剥离 CDN query
后端返回的图片 URL 自带 `?image_process=resize,l_350`（阿里云 OSS 图片处理）。
原站由 CDN 生成裁剪结果，本地静态服务器不识别 → 404 图裂。
```js
path = path.split('?')[0]   // 关键
```

---

## 4. 已踩过的坑（按错误信息索引）

| 现象 | 根因 | 修法 |
|---|---|---|
| 二维码图裂（src 为空） | 字段名是 **`inviter_code`**，误用 `invite_code` | `info.inviter_code \|\| info.invite_code` |
| 二维码图裂（404） | 写成 `/api/addons/...`，该接口不带 `/api` | `server.cjs` 放行 `/addons/` |
| 邀请页二维码空（直进页面） | 未经过「我的」页，`userInfo` 未拉取 | `onMounted` 调 `fetchUserInfo` |
| 所有支付方式不显示 | 用 `u[t.auth_key]`，实际字段是 `huifu_account`/`yibao_status`/`sandpay_status` | `Number(u[statusField]) === 1` |
| 快捷下单报 404 | 端点名猜成 `fastCreateOrder` | 实为 **`fastOrderNew`** |
| 下单报「未找到合适商品」 | `key` 取自 `priceList[0].key`（列表无此字段） | 取 `marketGoodsDetail` 的 `detail.key` |
| 在售列表解析为空 | 数据在 `data.list`，代码按 `data.data` 优先 | `d.list \|\| d.data` |
| 首页轮播空白 | 见 3.1（纯静态托管） | 补 `start` 脚本 + Node 发布 |
| 藏品图 404 | 见 3.3（CDN query） | `oss()` 剥离 query |
| 合成页缩略图 404 | 10 张只下了 2 张 | 全量补下载 |
| 幸运抽签点不动 | 指向不存在的 `/pages/lottery/draw` | 实为 `/pages/activity/draw?thm=3` |
| 顶栏签到图标进消息页 | 图标资源叫 `sign.png`（签到），模板绑错 | `/pages/sign/index` |
| 外链检查漏掉 CDN 字体 | 只扫运行时请求，浏览器命中 woff2 后**不请求** woff 兜底源 | 构建后 `strip-external.cjs` 剥离 |
| **dev server 首页白屏**：`Failed to fetch dynamically imported module: /src/pages/index/Index.vue` | 模板里 208 处 `src="/static/icon/xxx.png"`（88 个 .vue）被 Vite `import-analysis` 当成**模块依赖**去解析，找不到就整页崩。**建 `public/static/` 目录无效** —— Vite 要求文件真实存在 | `vite.config.js` 加 `staticAssetsPlugin()`：在 `resolveId`/`load` 层拦截 `/static/*.png`，编译期改写成 `https://h5.gandart.shop/...`。零改 .vue 源码 |
| 联调时页面「点了没反应」且不报错 | Mock 未注册该端点；空响应被前端 `try/catch` 静默吞掉 | 用 `mock-server/check-coverage.py` 比对前端 60 端点 vs Mock 路由，**不靠人肉点页面** |
| Mock 全部 POST，但前端混用 get/post | 原站 221 端点实测全 POST；`src/api/index.js` 有 9 处 `http.get`，而 `http.js` 请求拦截器**不改写 method** | Mock 所有业务端点统一 `Route::rule(..., 'GET|POST')` |
| 首页「限量份」数字为空 | Mock 给了 `num`，前端消费的是 **`total_num`** | Mock 补 `total_num`/`main_image`/`listimg`/`soldout`，并把这组字段固化为断言 |
| 联调时 banner / 藏品图裂 | `oss()` 指向本地 `public/static/oss/`，但那 287 张图**从未拉取** | `.env.development` 设 `VITE_OSS_REMOTE=1`，`oss()` 直接回源 `img.gandart.shop`；图片补齐后删掉该变量即切回本地静态方案 |
| `Request::param(): Argument #2 ($default) must be of type string, int given` | 给 `string $default` 形参传了 int 字面量 | 用 `Request::int('page', 1)`；契约层已**不提供** `param()`，避免误用 |
| ThinkPHP 6.1 被 Composer 安全公告拦截 | PKSA 反序列化漏洞 + XSS，影响 `>=6.1.3,<=8.0.4` | 改用 **ThinkPHP 8.1.4**（不在受影响范围），安装通过 |
| **生产构建后页面全白**，`Failed to fetch dynamically imported module: /assets/Index-xxx.js` | Vite 自定义插件未限定命令，把 dev 专有的虚拟前缀 `/@local-asset/` 写进了生产产物 | 插件加 `apply(_config, env) { return env.command === 'serve' }`。**dev-only 逻辑必须显式限定** |
| **生产 API/二维码 502** | Node `http.request({host})` 传了含端口的 `new URL().host`（如 `127.0.0.1:4200`），再单传 port 导致解析失败 | 用 `.hostname` + 单独的 `.port` |
| 生产环境藏品图/Banner 裂图 | `server.cjs` 只放行 `/api` 与 `/addons/`，漏了 `/static/oss/`（后端图片服务），被 SPA fallback 吞成 index.html | 三者一并放行：`/api`、`/addons/`、`/static/oss/` |
| tabBar 与「我的」宫格图标裂 | 模板用**前缀拼接** `:src="'/static/' + g.icon"`，静态正则扫不到；且真实路径在 `/static/icon/` 下，前缀本就写错 | 全量正则 + 运行时抓 404 双重定位；`My.vue` 前缀改为 `/static/icon/` |
| `rm -rf dist` 后 `npm run build` 报ENOENT | `prebuild` 的 `archive-assets.cjs` 归档 `dist/assets` 与 `.assets-archive`，两者互为源 | 重建前 `rm -rf .assets-archive dist && mkdir -p dist`，别单独删 dist |

### 4.2 原站依赖已全部切断（2026-10-17）

**结论：前端与后端均不再依赖原站，可完全离线运行。**

| 原依赖 | 替代方案 | 落地位置 |
|---|---|---|
| `api.gandart.shop` 全部业务接口 | Mock Server 67 路由 | `mock-app/route/mock.php` |
| `api.gandart.shop/addons/qrcode` | `endroid/qr-code` 本地生成（SvgWriter，无需 GD） | `mock-app/app/controller/Addons.php` |
| `img.gandart.shop` 图片CDN | 后端图片服务：首次抓取后缓存到 `runtime/img-cache`，二次零出网 | `mock-app/app/controller/Image.php` |
| `h5.gandart.shop/static/*` UI 图标 | **162 项全部落盘**到 `public/static/` | `public/static/` |

**原站已失效的 9 个资源**（原站自身 404，非本项目问题）：`tabBar/index-unactive.png`、`tabBar/my-active.png`、`icon/task-status.png`、`logo.png`、`my/avatar-bg.png`、`image/kline/card.jpg`、`image/kline/card2.jpg`、及 `compose/result-divider.png`、`copy-white.png` 等 —— 已放同路径SVG 占位图兜底，详见 `public/static/README.md`。

### 4.3 联调/断网校验四件套（可复跑）

| 脚本 | 作用 | 当前结果（2026-10-18 23:5x 实测） |
|---|---|---|
| `mock-server/check-coverage.py` | 前端 60 端点 vs Mock 72 路由 | **60/60 全覆盖** |
| `mock-server/check-endpoints.py` | 逐端点发真实请求（63×2=126 次），验 `code=1`／信封四键／401 | **102 成功 / 24 参数守卫 / 0 失败** |
| `mock-server/smoke.py` | 契约红线：信封、字段数、金额 string、布尔 int、token 双通道、分页三写法 | **14/14 通过** |
| `mock-server/check-offline.py` | 断网核验：配置层/源码层/Mock 响应/图标落盘四处无原站引用 | **通过（213 个本地图标）** |

浏览器级核验（dev 15 页 + 生产首页）：**外部请求 0 个，404 资源 0 个**。

**🔴 四件套在 2026-10-18 被查出全部失效过（本轮已修）**——教训见 §4.9。
跑之前先确认：Mock 在 `4200`、MariaDB 已起、seed 已灌（否则全是假红）。

### 4.3.1 原站依赖的最终结论（断网实证）

| 维度 | 状态 | 实证 |
|---|---|---|
| 业务 API | 零依赖 | Mock 72 路由全本地 DB 驱动；vite/server.cjs 默认代理本地 |
| 二维码 | 零依赖 | `endroid/qr-code` 本地 SVG 生成 |
| UI 图标 | 零依赖 | `public/static/` 213 个文件全部落盘 |
| 商品/藏品图 | **一次性回源** | `app/controller/Image.php`：三级查找 `runtime/img-cache` → `public/static/oss/` → 回源抓取并落盘 |

图片那一条是**唯一残留出网点**。`Image.php` 的查找顺序（2026-10-19 加第二级）：

1. `runtime/img-cache/`（按需抓取后落盘，同名 md5）
2. **`public/static/oss/`（静态预下载资产，命中即零出网）**
3. 回源 `img.gandart.shop` 抓一次并落盘到第 1 级

**为什么加第 2 级**：前端仓库的 `public/static/oss/` 早有 37 张预下载图片，
路径结构与 OSS 完全一致，此前 Mock 却完全没看它、直接回源——白出网。
2026-10-19 实测：库内 25 条图片路径中 **14 条静态目录已覆盖**。

**断网实证**（hosts 屏蔽 `img.gandart.shop`，按前端 `oss()` 映射后的真实路径请求）：
**25/25 全部返回真实图片字节，零出网**（2026-10-19 完成全量预热后）。

**预热现状**：`public/static/oss/` **46 个文件** / 11M，`src/api/oss-map.json` **48 条**映射，
库内 25 条图片路径 **100% 有本地副本 + 100% 有映射**（映射值无一指向缺失文件）。

> 映射条目（48）多于文件数（46）不是错：转码时 `.png → .jpg` 会在表里留下
> **两条 key**（原 `.png` 路径与新 `.jpg` 路径），value 都指向同一个 jpg。
> 好处是前端无论按原站返回的 `.png` 还是按 `.jpg` 请求，`oss()` 都能命中。
补预热跑 `gandart-h5/tools/fetch-oss-images.sh`（它会自动转码 + 重建映射表）。

⚠️ 该脚本 2026-10-19 修过两处（详见 §4.9 同族的「写死会变的事实」）：
① `IMGS` 清单原本写死 3 条路径，与库内 25 条数据脱节 → 改为写死清单之外
**追加从数据库读取的实际引用路径**；② 原站对不存在的 key 返回
**HTTP 404 + XML 错误体**（`NoSuchKey`，约 422 字节），`curl -s` 不看状态码
照样写成 `.jpg`——曾有一个这样的坏文件让 `build-oss-map.py` 在
`Image.open()` 处崩溃、**整轮转码中断且 `oss-map.json` 一条都没生成**。
现在 fetch 侧校验魔数丢弃、build 侧单文件失败只跳过告警。

⚠️ 一处待办：`site_config` 表 0 行（原站 `getVersion` 样本为空，无数据来源），
导致 `index/getConfig` 没有合法 key，只能验证「缺少key值」这条错误路径。

### 4.4 后端真实化（ThinkPHP + MariaDB，2026-10-17）

Mock 的内存假数据已开始替换为数据库驱动。**原则：每个接口实现前必须先抓原站真实响应**，
字段一律以实测为准，禁止凭猜测设计。

**已建库表**（DDL：`mock-server/sql/01-schema.sql`）

| 表 | 用途 | 数据来源 |
|---|---|---|
| `affiche` | 公告 | 原站实测 53 条 |
| `affiche_type` | 公告类型字典 | 原站实测 5 条 |
| `blind_box` | 盲盒/藏品 | 原站实测 1 条（id=161） |
| `blind_box_batch` | 批次（嵌套 batch 拆表） | 原站实测 |
| `publisher` | 发行方（嵌套 publisher 拆表） | 原站实测 |
| `user` | 用户（骨架，待真实字段） | ⚠️ 未抓到样本 |
| `rate_limit` | 限流计数 | 本项目新增 |
| `banner` | 轮播图 | 原站实测 2 条 |
| `site_config` | 站点配置 KV | 原站实测 getVersion |
| `collection` | 藏品（已开盒实例） | 实测 10 字段 |
| `series` | 藏品系列/分区 | 原站实测空数组 |

**完整表设计见 `docs/db/DATABASE-DESIGN.md` v2.0**（实测字段清单 / 30 张表清单 / 类型陷阱 / Model 照抄样例）。

**当前库中共 30 张表** = P0 内容藏品 11 + P1 交易核心 16 + 系统安全 3。
表数结论：原站全量 221 端点 ≈ 52 张表；本项目按前端 `src/api/index.js` 实际调用的
62 端点 / 11 模块收敛到 **38 张**，分 P0 / P1 / P2 / 按需 四批。

### 4.4.1 真实账号实测成果（2026-10-17，账号 175****1293）

登录成功（uid=16797，token 有效期 2592000 秒 = 30 天），
用真实 token 抓完50+ 鉴权端点，把 P1 交易表从「推断」升级为「实测驱动」。

| 实体 | 实测叶子字段 | 样本文件 |
|---|---|---|
| User | **106** | `recon/raw/auth/user_getUserInfo.json` |
| Order | **18**（2 条真实订单） | `recon/raw/auth/order_order_orderList.json` |
| UserCollection | **17** | `recon/raw/auth/user_collection_user_collection_antMycollection.json` |
| Synthesis | **22**（theme 6 + play 16） | `recon/raw/auth/synthesis_themeList.json` |
| Union | **16** | `recon/raw/auth/union_user_myunion.json` |
| Message | **7** | `recon/raw/auth/user_newList.json` |
| Lottery | **46** | `recon/raw/auth/lottery_info_v2.json` |
| 交易统计 | **10** | `recon/raw/auth/market_trade_tradingInformation.json` |

机器可读字段清单：`recon/schema-map/measured-fields.txt`（可 diff，可直接生成 Model）。
**字段覆盖率双向核验结果：原站字段零遗漏，DB 无冗余列。**

**实测踩出的 6 个类型陷阱**（建表必须照抄，转 int 前端就崩）

1. `order.order_status` 是 **int**，但同一条记录的 `order_type` / `num` 是 **string**
2. `order.uc_ids` 存在 `null` 与 `"121926"` **两种形态** → 必须可空 varchar
3. `synthesis_plan.start_time` 是 **13 位毫秒**（1792232280000），与其他表 10 位秒级不同
4. `user_collection.sell_num` 是 string，`num`/`lock_num`/`is_collection` 是 int
5. 分页信封 `per_page` 存在 string(`"10"`) 与 int(`10`) 两种形态
6. `lottery` 几乎所有数值字段都 string 下发（`crystal="10"` / `lottery_max="316000"`）

**参数破解成果**

| 端点 | 必填参数 | 结论 |
|---|---|---|
| `market/trade/begBuyList` | `collection_id` + `sort` | `sort=desc` 唯一合法值；`new`/`price`/`0`/`default` 均报「排序方式错误」 |
| `market/trade/tradingInformation` | `status` | 合法值 1/2/3（0 和空串报「状态错误」）；返回 10 字段统计骨架 |
| `user_collection/getRecord` | 未破解 | 试 `collection_id`/`type`/`goods_type`/`record_type` 全部「参数错误」 |
| `synthesis/material` | — | 原站返回 500，疑似已下线或依赖特殊登录态 |

**测试账号数据几近为空**：市场挂单、求购单、寄售、地址、消息以外的列表全部返回空数组，
但**信封字段齐全**，结构可信、缺的是行数据。已接受，不再纠缠。

**`collection` 与 `blind_box` 不可合表**（极易混淆）：
- `blind_box` = 未开盒的盒子，有 `batch`/`open_draw`/`goods_type`
- `collection` = 已开盒的藏品，有 `collection_level`/`freeze_time`/`unfreeze_collection_id`
- 开盒动作 = `blind_box` → `user_collection` + `collection`

**已完成真实化的端点**：`index/afficheList`、`index/affichetypelist`、
`index/afficheDetail/ids/{id}`、`box/blind_box/list`、`index/getConfig`（补了 `key` 校验）。

**原站实测发现的偏差**（此前 Mock 全是错的）

1. **盲盒 26 字段**，不是 8 个；含嵌套 `batch`（4 字段）+ `publisher`（2 字段）
2. **`box/blind_box/list` 的 data 是裸数组**，不是分页信封 —— 与其它端点不同
3. **「字符串包数字」**：`goods_type="2"` / `batch_id="14"` / `issuer_id="0"` 是字符串，改成 int 前端 `=== '2'` 会失效
4. **`image` 字段原站不下发** —— 之前 Mock 凭猜测加的，被契约核验脚本抓出
5. **`index/getConfig` 无 `key` 参数时返回 `code=0 msg='缺少key值'`**
6. **`synthesis/*`、`crystal_goods/list` 原站返回 401**（需登录），之前误标为 public
7. **`index/getSeries` 原站返回空数组**，之前 Mock 编了两条系列
8. **`activity/index` 原站 `total=0`**（站点无数据）

### 4.5 后端踩坑（2026-10-17，全部真实踩过）

| 现象 | 根因 | 修法 |
|---|---|---|
| **每个 HTTP 响应前面多出 922 字节 PHP 源码** | `config/middleware.php` **漏写 `<?php` 开头**，PHP 把整个文件当纯文本输出 | 补 `<?php`。定位方法：`ob_start()` 包住 `App::initialize()` 测泄漏字节数 |
| **限流"配了但从不触发"（假安全）** | file 驱动 `Cache::inc()` 计数器不累积，连打 70 次仍是 1 | 改用数据库原子自增（`rate_limit` 表 + 唯一键）。**安全措施必须实测验证，不能只看配置** |
| **限流判定误报"未生效"** | 限流响应 HTTP 状态码仍是 **200**，只有 `body.code=429`；用 `-o /dev/null -w %{http_code}` 检测会误判 | 检测必须 grep 响应体的 `"code":429`，见 `recon/check-ratelimit.sh` |
| **全局中间件写了没生效** | 挂在了 `config/middleware.php`（该文件只读 `alias`/`priority`），正确位置是 **`app/middleware.php`** | 改挂 `app/middleware.php` |
| **`Route::group(...)->middleware()` 静默失效** | ThinkPHP 8 的 `RuleGroup` **没有 `middleware()` 方法**，链式调用中断，`require` 的返回值(1) 被当响应体 | 全局中间件走 `app/middleware.php`；分组限流改用 `Route::rule()->middleware()` 逐条挂 |
| **路由被重复注册** | `Http::loadRoutes()` 用 `glob('route/*.php')` 加载目录下**所有**文件，路由表放`route/` 会被自动加载一次再被 `require` 一次 | 路由表放子目录 `route/sub/route_api.php` |
| **`Cache::inc` 抛 must be a valid callback** | `config/cache.php` 的 `'path' => ''` 空字符串导致 `Driver::$handler` 构造为 NULL | 给绝对路径 `runtime_path() . 'cache'` |
| **`PDOException Access denied for 'root'`** | `config/database.php` 用了 `env('DB_HOST')`，但 `.env` 是 `[DATABASE]` 分段格式，ThinkPHP 要 `env('database.hostname')`（**小写段名 + 点号**） | 全部改为分段小写取值 |
| **`column(null, 'id')` TypeError** | `column()` 第一参数必须是字段数组/string，不能传 null | `column('*', 'id')` |
| **批量导入 SQL 语法错** | 拼 SQL 时字符串没加引号 → `Unknown column '寄售公告'` | `esc()` 区分类型：数值裸输出，字符串加引号 |
| **登录脚本提取 token 得到空串** | 登录响应的 token 嵌在 `data.userinfo.token`，不是顶层 `data.token`；直接 `grep '"token"'` 会漏 | 用 `json.load(...)['data']['userinfo']['token']` 取，别用正则打平 |
| **`ALTER USER 'gandart'@'localhost'` 后仍 Access denied** | MariaDB 存在 `gandart@localhost` 与 `gandart@127.0.0.1` **两条授权记录**，只改一条不生效 | 两条 host 都执行 `ALTER USER`；或用 `sudo mysql` 走 socket 改 |
| **实测字段靠肉眼数不准** | User 实际 106 叶子字段（含 2 个嵌套对象），文档旧记「90+」 | 写脚本递归 flatten JSON 落成 `schema-map/measured-fields.txt`，再与 `information_schema.COLUMNS` 双向 diff |

**⚠️ 一条通用教训**：本次连续 4 次把「响应里吐出 PHP 源码」误判为 opcache / 目录同名冲突 /
`declare(strict_types)` 问题，实际原因只是**文件首行漏了 `<?php`**。
下次遇到「响应体里出现某个文件的源码」，第一步就查**那个文件的开头是不是 `<?php`**，
不要往缓存和路径上猜。

**⚠️ 第二条通用教训**：账号能登录 ≠ 能抓到数据。
本次测试账号登录成功、token 有效，但市场/求购/寄售列表**全部返回空数组**。
判断端点结构是否可信，看**信封字段是否齐全**，不要看是否有行数据；
更不要因为「没数据」就去推断字段。**空数组 + 齐全信封 = 结构可信，缺的是行。**

---

### 4.6 后端踩坑（2026-10-18 v2.4，collectionDetail 切 DB 期间）

| 现象 | 根因 | 修法 |
|---|---|---|
| **`POST /api/order/pay/CreateVipOrder` 返回 500 HTML 错误页** | v2.3 拆分时 `makeOrder()` 留在 TradeController 且是 `private`，PayController 的 5 个 `Create*Order` 端点仍在调 `$this->makeOrder()`——两类互不继承，必然 fatal | 抽 `Concerns\MakesOrders` trait 共用。**trait 内不能再 `use HasAuth`**（与宿主同名私有方法冲突，PHP 直接 fatal），约定宿主自行提供 `currentUser()` |
| **TradeController 加两个方法后 354 行超限** | 切 DB 的新方法带完整实测注释（这是必须的），+55 行就破 300 | 按域拆：register/resetPwd → 新 `AccountController`（user/ 路径本就属账号域）；unionLogs → `UserController`（与 myUnion 同域）；死代码 makeOrder 删。**先删死代码再拆，能少拆一个文件** |
| **seed.php 的 emoji 门禁误判 16 处** | 控制台输出用了 ✅/❌/⚠ 作状态标记。门禁扫描范围此前只含 app/route，本轮扩到 seed.php 才暴露 | 换成 `[OK]`/`[FAIL]`/`[WARN]` 文本标记（与 tools/contract/ 报告同一约定）。CLI 脚本虽非 UI，但统一消除扫描歧义 |
| **连续快速登录后续请求全 401** | 登录限流生效（安全设计正确），但验证脚本连着打多个请求会踩到 | 验证脚本的登录与后续请求之间 `sleep 2`；这是**特性不是 bug**，别为跑通把它关掉 |

**⚠️ 本轮最重要的一条教训**：私有方法跨类调用，`php -l` 不报、lint 不报、
类型检查也不报，只有真实 HTTP 请求打到那个端点才暴露。
本项目已两次栽在同一形态（ContentPlayController 漏 `use HasAuth`、
PayController 调不存在的 `makeOrder`），**两次都是 HTTP 层复验抓出的**。
结论：任何「跨文件的方法/类引用」改动，门禁里必须有一条真实 curl，
不能只跑静态检查。

**⚠️ 第二条**：拆文件降行数时，**先找死代码再拆**。
TradeController 的 `makeOrder` 零调用方（调用方全在 PayController），
删掉它 + 移走 register/resetPwd 就从 354 降到 ~280，不需要第三个文件。

---

### 4.7 后端踩坑（2026-10-18 v2.5，CreateVipOrder/batchPayOrder/begBuyList 切真实契约期间）

| 现象 | 根因 | 修法 |
|---|---|---|
| **CreateVipOrder 响应 `order_id=""`（DB 行却正确创建）** | ThinkPHP 8 的 `Model::save()` 内部靠 `getLastInsID()` 回写自增 id，但本环境模型 `initDb()` 走 `Db::connect(null)` 路径，`getLastInsID()` 返回 int(0)，`if ($key && $val)` 判假 → `setData('id')` 被跳过 → `$order['id']` 为 NULL。（已逐层排除：同一 facade 连接上直连 `PDO::lastInsertId`/`insertGetId`/`->model()` 绑定全部正常，分歧在连接实例） | `VipOrder::createForUser()` 改用 `Db::name('vip_order')->insertGetId($data)` 直插直返，`new self($data)` 构造返回。**本环境新建模型一律走 insertGetId，不要用 save() 后读 id** |
| **契约用例回滚后，手工验证 order_id 从 "2" 开始对不上** | InnoDB 的 auto_increment 计数器**不随事务回滚**——`Db::rollback()` 撤行不撤计数 | 验证/演示前 `ALTER TABLE vip_order AUTO_INCREMENT = 1`（表空时）恢复「首单 id=1」，与原站样本一致 |
| **begBuyList 错误响应漏了「排序方式错误」「藏品不存在」两层** | 只翻了样本，样本里恰好只有一层错误；而原站 `recon/.token` 仍有效，**直接 curl 原站**才探出完整四层链 | 复探原站：`collection_id=-1` 且无 sort → 报「排序方式错误」（证明存在性检查排最后，顺序不可调换）；`"abc"/"1.5"/"-1"/"99999"` → 「藏品不存在」。**原站可复探时，curl 比翻样本准**；写端点还能先制造数据再探读端点 |
| **三个「订单信息」端点信封长得不一样，差点复用错** | orderList：`{total 开头, per_page:15}`；begBuyList：`{current_page 开头, per_page:0}`（空态）；begBuyOrderList：`{total 开头, per_page:15}`。msg 还都叫「订单信息」 | `Response::page()` **不可复用**：固定 total 开头键序 + `max(1,...)` 会把 begBuyList 空态的 per_page/last_page 的 0 静默钳成 1。每个端点独立信封方法，键序在契约用例里手工断言（`compareEnvelope()` 存在但 `check()` 从不调用） |
| **Cases.php 加 3 个用例后 361 行破线** | 与 §4.6 同形态：加内容（这次是用例不是方法）+74 行即破 300 | 按域拆 4 组 + 注册中心：`cases/{User,Content,Market,Order}Cases.php` 各 ≤116 行，Cases.php 只留共享常量与调度（58 行）。**同一文件第二次触线，下次加用例前先估行数** |

**⚠️ 本轮最重要的一条教训**：**端点名会骗人，信封不会**。
`CreateVipOrder`/`batchPayOrder`/`begBuyList` 三个端点名字都像「订单列表」，
实测却是三种信封、三种空态语义、三种错误链。判定依据只有原站响应本身——
包括错误响应的层数与顺序（本轮全部靠复探原站取得，样本里没有）。

**⚠️ 第二条**：本环境 ThinkPHP 8 的 `Model::save()` 不回写自增 id（见上表第一行），
这是与官方文档行为不同的环境差异。凡「insert 后要拿 id」的模型方法，
一律 `Db::name()->insertGetId()`，已写入 pitfalls.jsonl 防再踩。

---

### 4.8 求购域切真实契约（2026-10-18 v2.6，用户授权原站制造数据）

> 授权原文：「可以，准你在原站制造数据」。账号 16797 在原站真实创建两笔求购单
> （藏品 162，单价 20000.00，order_id 76/77），76 自动过期、77 手动取消。
> **只做不涉钱的写操作**（求购单 money=0，无支付通道可触发）。

**样本资产（raw5/raw6/raw7 共 22 个）**

| 批次 | 内容 |
|---|---|
| `recon/raw5/`（8 个） | 建单成功 ×2、列表待付款/终态、详情 ×2、取消成功 |
| `recon/raw6/`（10 个） | begBuyList 矩阵：5 藏品 × 旧/新分支 + lr10/page2/asc/sortprice 变体 |
| `recon/raw7/`（4 个） | 前端死端点名空参复探：begBuy/entrust/entrustOrderList 均 404，createBegBuyOrder 存在 |

**已锁定的求购契约**

| 契约点 | 实测结论 |
|---|---|
| 字段数 | 列表行 **28** 字段（含 sale_number）、详情 **27** 字段 |
| 键序 | 列表行尾部 `..., order_type, sale_number, surplus_time_text`——**sale_number 不在行尾** |
| `surplus_time_text` | **纯时间数学** `max(0, 300-(now-createtime))`，与 status 无关。实证：77 手动取消（status=4）后仍 186→196 递减；76 显示 0 是时间过窗非状态置零 |
| 建单参数 | `collection_id` + `price` + `number` + `pay_way`（实测合法值 `huifu`） |
| 校验链 9 步 | 藏品ID不能为空→数量不能为空→支付方式不能为空→藏品不存在→请输入挂单价格→该藏品不可求购→地板价区间（tips 原文）→限制最大求购金额（取 reprice_beg_max_limit 取整）→下单成功。**多错并发的上报顺序有实证**（5 例），调换实现顺序会静默改文案 |
| 费率 | `platform_service_rate="6.00"`，`order_type=11`，`sale_number=0` |
| 建单响应 | `{order_sn, order_id, time}`，`time="300"`（付款窗口秒），order_id 是 **string**，order_sn 28 位 |
| 取消语义 | 成功置 status=4；**手动取消 canceltime 留 null**，自动过期才落值（76/77 双样本实证）；重复取消报「订单不存在」 |
| 归属校验 | IDOR 防护实证：第二个用户查/取消 16797 的单均「订单不存在」（最小披露） |
| begBuyList 信封 | **双分支**：可求购藏品（reprice_beg_max_limit>0）→ `{total 开头, per_page:50}`；不可求购 → `{current_page 开头, per_page:0}`。判别机制系归纳，登记 D-009 |

**本轮踩坑（都在 §4.8 首次出现）**

| 现象 | 根因 | 修法 |
|---|---|---|
| **格式化器 sale_number 放错位置** | 凭「计数类字段在行尾」的直觉排键序 | 实测在 order_type 之后、surplus_time_text 之前。**键序只能逐位抄样本** |
| **surplusSeconds 加了 status 检查** | 模型里想当然「非待付款不该倒计时」 | 77 号单取消后仍递减，删状态检查改纯时间数学 |
| **raw4 两个 begBuyList 样本一个缺 collection_id** | 误以为两个都是成功样本 | 复探 + 矩阵探测破译：缺参的是错误样本，`_161` 是旧分支成功样本 |
| **01-schema.sql 与 04-trade-core.sql 重复定义 user 表** | 01 是抓样本前的 12 列猜测骨架，04 是 105 列 canonical 实测表；「后应用者静默获胜」 | 01 删 DDL 改指针注释（同 collection 03/04 重复定义的存量坑族） |
| **seed 漏灌 collection_code，14642 比对红** | `$ORDER_MAP` 只看了首条样本（18554 的 collection_code=[]），而 14642 实测 `["1926/100000"]` | **样本必须逐条看**；数组形态转字符串入库 |
| **前端三个求购/委托端点名 404** | `submitBegBuy`/`submitEntrust`/`getEntrustOrderList` 打的名字原站不存在（实为 createBegBuyOrder 等） | Mock 保留 `[MOCK]` 路由兜底 + 路由注释标注；登记 D-008 |

**⚠️ 本轮最重要的一条教训**：**端点名第三次骗人**。
`fastCreateOrder`→`fastOrderNew`（§4 坑表）、`begBuy`/`entrust`→404、
`begBuyList`→同端点双信封。判定端点契约的唯一依据是**原站响应本身**——
复探（curl 原站）比翻样本准，样本是过去某时刻的切片，错误链层数/信封形状
不一定在样本里。原站 token 有效时，每个端点切契约前先 curl 一遍。

**⚠️ 第二条**：**同一端点可以按数据分流到两种信封**（begBuyList 双分支）。
用「一个端点一种信封」的直觉写代码，就会漏掉一半分支。判别列即使是从
数据点归纳的，也要显式传参（`$begBuyable` 形参）——将来换判别列只改一处。

**⚠️ 第三条**：事务回滚**不撤 auto_increment 计数**（78→79 实证）。
契约用例按 id 定位种子行不受影响；手工验证时下一个单号从 79 起不是 bug。

---

### 4.9 四个核验脚本全部失效过（2026-10-18 23:5x 查出，已修）

> **这是本轮最重要的发现**：四个脚本没有一个是「真的在核验」，全部在报假结果。
> 而文档（旧 §4.3）里记的却是一水的绿——**绿的不是项目，是没人看过的旧数字**。

| 脚本 | 假结果 | 根因 | 修法 |
|---|---|---|---|
| `check-coverage.py` | 「60 个未覆盖」 | 读 `route/mock.php`（0 条路由）；路由表早已迁到 `route/sub/route_api.php`（69 条）以避开 `Http::loadRoutes()` 的 glob 重复加载（见 §4.5） | 改读多个路由文件，并注释迁移原因 |
| `check-offline.py` | `getUserInfo` 报 401 | 用假 token `?token=t` 打**鉴权**端点，必然 401；而 401 响应体没数据，「响应含不含原站域名」这条判据形同虚设 | `login_token()` 真登录取 token 再测（含 sleep 2 避限流） |
| `check-endpoints.py` | 126 次全红「带 token 仍 401」 | 写死 `TOKEN = 'mock-4413...'`——`mock-` 前缀是 Mock **内存实现时期**的假 token，切真实鉴权（user_token 表 + Argon2id）后一律 401 | 真登录取 token；另给必填参数端点补真实参数（`begBuyList` 带 collection_id+sort 等），否则只验证到错误路径 |
| `smoke.py` | 连不上 | `BASE` 写死 8000（某次临时实例），服务实为 4200 | 改 `MOCK_BASE` 环境变量，默认 4200 |

**⚠️ 本条教训**：**核验脚本失效比没有核验更危险**。
因为脚本一直在跑、一直在红，**红得久了就没人看了**——
真正的回归（比如本轮 v2.6 改了 16 个文件）一旦出问题，会被淹没在同一片红里。
这与 §4.5「限流配了但从不触发 = 假安全」是同一家族：
**安全措施/核验措施必须实测验证它能触发，不能只看它存在。**

**通用修法（可复用）**：脚本里凡涉及**会变的外部事实**——
凭据、端口、文件路径、路由位置——一律不写死：
- 凭据 → 真登录现取（不要 `token=t`、`mock-xxx`、写死密码）
- 端口 → 环境变量 + 与其余脚本统一的默认值
- 路径 → 指向真实生效的文件，并在注释里写明「为什么是这个路径」

**顺带查出的数据缺口（已补）**：`affiche` 0→53、`blind_box` 0→1、
`affiche_type` 0→5、`publisher` 1、`blind_box_batch` 1（补跑 `recon/seed.py`）。
**两个 seed 脚本的分工**：`mock-app/seed.php` 只灌交易域（user/collection/
artel_order/beg_buy_order…），`recon/seed.py` 灌内容域（affiche/blind_box/publisher…）——
只跑前者会得到一个「能跑但首页没内容」的库。

---

### 4.1 踩坑索引（联调相关）

> 联调/断网校验的四件套见 §4.3。

---

## 5. 硬约束

### 5.1 必须保持兼容的 API 契约
原站是 **ThinkPHP**，重写后端时这些**改了就会坏前端**：
1. `code`：**1 = 成功**，0 = 失败（反直觉，别改成常规的 0=成功）
2. 响应信封固定 `{code, msg, time, data}`
3. 列表返回 `{total, per_page, current_page, last_page, data}`
4. **金额用字符串** `"100.00"`，不要 float
5. 布尔用 int `0/1`，不要 true/false
6. 图片返回**相对路径** `/uploads/...`，前端拼 OSS 域名
7. 路径参数保持 `detail/ids/{ids}` 命名
8. **全部 POST**（实测 117 次调用 100% POST，无 GET 语义）
9. token **header + query 双通道**（线上网关会剥自定义 header）

详见 `docs/api/API.md`。

### 5.2 工作方式约束
- **动手前先侦察原站**，不凭猜测改行为
- 修问题必须查明**原站真实逻辑**（抓包 / 逆向 chunk / 实测点击）
- 每页完成后**重新与原站截图对照**，报 thr40 数字
- 图片用**原站真实素材**且本地化，不留外链
- 批次节奏：**五个页面一批**
- 交易链路是**最重要**的功能

### 5.3 实测遗留项
| 项 | 状态 |
|---|---|
| 签到「立即签到」按钮 | 接口连通性**未验证**（写操作，未实际提交过一次） |
| 测试账号有一笔未支付订单 | 导致快捷下单返回「存在其他尚未支付的订单」——**真实业务状态**，非缺陷 |
| `order_status` / `order_type` 枚举 | ✅ **已破解**（见 §5.4） |
| `order_type` 的 6/7/10/31 含义 | ⚠️ 仍未知，需查 DB `artel_order` 或问产品 |

### 5.4 订单枚举已破解（2026-10-17）

原站前端把完整状态字典**硬编码在 webpack 模块 `95ba`**，被 6 个订单 chunk 共享引用。

| 项 | 值 |
|---|---|
| 源文件 | `h5.gandart.shop/static/js/pages-order-list.{hash}.js` |
| 访问式 | `orderStatusInfo[order_type][order_status]` |
| 规模 | `order_type` **12** 个 × `order_status` **8** 个 = 48 组合 |
| 产出 | `docs/api/OrderEnum.php`（可直接引入） |

**踩坑记录**：逆向时用 `grep -oE 'name:"hash"'` 只提到 1 个 chunk。webpack 压缩后映射表形如 `("home":"de23136d",...)`，**正则要放宽到 8 位十六进制且不强制引号**才能提全 118 个。

**枚举逆向的通用手法**（可复用到其他项目）：
1. 抓 `index.{hash}.js` → 提取 chunk 名与哈希映射
2. 并发下载全部 chunk
3. `grep -l "目标字段名"` 定位承载字典的 chunk
4. 找webpack 模块 ID（如 `95ba:function`）→ 提取该模块全文
5. 写**括号匹配状态机**解析嵌套对象字面量（正则会被嵌套结构骗）

⚠️ 维度方向要靠**访问式代码**确认（本项目是 `[order_type][order_status]`，不是反过来）—— 搞反了整张表就没意义。

---

## 6. 常用命令

```bash
cd /workspace/gandart-h5

# 开发
npm run dev                # 5173

# 构建（postbuild 会自动归档旧 assets + 剥离外链）
npm run build

# 本地预览（必须 Node，不能 vite preview）
node server.cjs            # 或 PORT=4190 node server.cjs
# 默认代理到本地 Mock；如需对照抓包：API_UPSTREAM=https://api.gandart.shop node server.cjs

# Mock Server（后端）
cd /workspace/mock-server/mock-app && php think run -p 4200
# ⚠️ 改config/*.php 后必须重启进程，php think run 会缓存配置
# ⚠️ 不要用 pkill -f "php think"，会连当前 shell 一起杀掉（见 §2）

# 数据库
mysqld_safe &                                     # 启动 MariaDB
mysql -ugandart -p'Gandart#2026Dev' -h127.0.0.1 gandart
cd /workspace/mock-server && mysql -ugandart -p'Gandart#2026Dev' -h127.0.0.1 gandart < sql/01-schema.sql

# 四件套校验（改完必跑）
cd /workspace/mock-server
python3 check-offline.py     # 断网核验：确认没把依赖指回原站
python3 check-coverage.py    # 端点覆盖
python3 check-endpoints.py   # 逐端点实测
python3 smoke.py             # 契约红线

# 后端真实化专用校验（2026-10-17 新增）
python3 /workspace/recon/verify-contract.py   # 本地输出 vs 原站实测，逐字段比对
/workspace/recon/check-endpoints.sh           # 66 端点连通性（POST + 真实ID）
/workspace/recon/check-ratelimit.sh           # 限流真实验证（grep body.code=429）
python3 /workspace/recon/seed.py              # 原站样本 → 数据库种子

# 发布（必须带language node）
node /root/.codebuddy/skills/sites/scripts/publish.js \
  --dir /workspace/gandart-h5 --language node --start-cmd "node server.cjs"
```

⚠️ 重建前必须 `rm -rf .assets-archive dist && mkdir -p dist`（见§4坑表最后一条）。

---

## 7. 当前工作区状态

- git：全部已提交并推送远端 main（文档 `25b61ec` 本地 / SQL `4ff662b` 本地 /
  后端 v2.6 求购代码 `b020090`；远端 main = `1b37a97`，553 blob，
  verify-remote-sync 103/103 SHA 一致、0 分叉、0 影子文件）
- 本地服务：未运行（需要时按 §6 启动）
- 待办：无明确遗留（未决项见 §5.3 与 `docs/decisions/OPEN-DECISIONS.md`：D-001/002/003/005/006/007/008/009 共 8 项 OPEN）

### 7.1 前后端联调环境（2026-10-17 打通）

| 服务 | 地址 | 说明 |
|---|---|---|
| 前端 dev | `http://127.0.0.1:5173` | `/api` `/addons` `/static/oss` → Mock；`/static/*` 图标由 public 提供 |
| 生产预览 | `http://127.0.0.1:3000` | `node server.cjs`，同样默认代理本地 Mock |
| Mock Server | `http://127.0.0.1:4200` | ThinkPHP 8.1.4，67 业务路由 + 二维码 + 图片服务，代码在 `/workspace/mock-server/` |

**两者均默认打本地 Mock**，需对照抓包时才显式指定原站：

```bash
npm run dev                                        # 默认打本地 Mock（安全，不会误触发真实写操作）
PROXY_TARGET=https://api.gandart.shop npm run dev  # 打原站做对照抓包
```

浏览器联调需先注入 token（路由守卫会拦未登录用户）：

```bash
curl -s -X POST http://127.0.0.1:5173/api/user/login \
  -H "Content-Type: application/json" \
  -d '{"account":"13900000001","password":"any"}'
# 取 data.userinfo.token
agent-browser eval "localStorage.setItem('token', JSON.stringify('<token>'))"
```

**已验证页面**：首页、市场、我的、我的藏品、订单列表、任务、商城、公告、签到、钱包、抽奖、合成记录、榜单（15 页均正常渲染，见 `docs/screenshots/index-mock.png`）。

---

## 8. 文档索引

| 文档 | 用途 |
|---|---|
| `docs/api/BACKEND-API-SPEC.md` | **后端实现规范**（写后端照着做）：全局契约、Tier 1 端点、实体、安全清单、验收标准 |
| `docs/api/OrderEnum.php` | 订单类型/状态枚举（逆向自原站，可直接引入） |
| `docs/api/API.md` | 原站侦察证据（221 端点全表、实测参数、实体字段） |
| `docs/依赖原站清单.md` | 11 处硬编码 URL + 21 个 oss() 调用点 + 端点分级 |
| `docs/AUDIT-API-外链.md` | 缺陷清单（`CollectionList` 死路由等） |
| `docs/decisions/OPEN-DECISIONS.md` | 悬而未决项登记册 |
| `docs/screenshots/index-mock.png` | Mock 联调下的首页实拍（banner/四宫格/公告/藏品列表均正常） |
| `docs/screenshots/index-offline.png` | **完全脱网后的首页实拍**（零外部请求、零404） |
| `public/static/README.md` | 静态资源两条铁律+ 原站已失效资源清单 |

### 已定的架构决策

| 日期 | 决策 | 理由 |
|---|---|---|
| 2026-10-17 | **后端用 PHP（ThinkPHP）** | 与原站同构，221 端点路由约定天然复用，前端零改动 |
| 2026-10-17 | **ThinkPHP 选 8.1.4 而非 6.x** | 6.1.3~8.0.4 命中 Composer 安全公告（反序列化 + XSS），8.1.4 不在受影响范围 |
| 2026-10-17 | **联调期代理默认打 Mock** | 避免误触发原站真实写操作（下单/提现）；需打原站对照时显式传 `PROXY_TARGET` |
| 2026-10-17 | **静态资源用 Vite 插件重写而非改源码** | 208 处 `src="/static/..."` 属既有复刻成果，不宜改动；解析层拦截可零改 .vue 解决。**注意插件必须限定 `env.command === 'serve'`** |
| 2026-10-17 | **全站脱网：图标落盘 public/static，二维码本地生成，图片由后端缓存服务** | 消除全部原站依赖，可离线运行；图片采用「首次抓取+本地缓存」而非全量预下载，节省仓库体积 |
| 2026-10-17 | **二维码输出 SVG 而非 PNG** | 沙箱 PHP 无 GD 扩展，PNG/ImageMagick Writer 均不可用；`SvgWriter` 纯 PHP，浏览器 `<img>` 可直接渲染 |
| 2026-10-17 | **`server.cjs` 与 `vite.config.js` 默认代理本地 Mock** | 避免生产部署误连原站；对照抓包时用 `API_UPSTREAM` / `PROXY_TARGET` 显式指定 |
---

## 交易链路真实后端（2026-10-19）

### 为什么做

排查原站依赖时发现 `order/pay/*` 整组写端点在原站**无响应**，
无法取得样本。同时查出本地实现存在资损级缺陷：

```php
// 旧实现（已删除）
return Response::success('支付成功', ['pay_no' => 'MOCKPAY' . random_int(...)]);
```

伪造支付成功 + 订单不落库。叠加 `server.cjs` 的 `API_UPSTREAM`
指向本地 Mock，发布即事故。

### 做了什么

| 文件 | 职责 |
|---|---|
| `app/service/PaymentGateway.php` | 网关接口 `charge(array $order, string $payWay): PayResult` |
| `app/service/PayResult.php` | 只允许 `paid()` / `rejected()`；`paid=true` 只能来自真实通道 |
| `app/service/NullGateway.php` | 恒定拒绝，**永不假成功** |
| `app/service/Gateways.php` | 按 `payment_channel` 分发，当前统一 NullGateway |
| `app/service/OrderService.php` | 服务端算价 + 幂等 + 事务落库 |
| `app/controller/api/PayOrderController.php` | 承接 CreateOrder / CreateMarketOrder / CreateMarketOrderRush / CreateHuifuAccountOrder（从 PayController 拆出） |

同时删除死代码 `app/controller/api/Concerns/MakesOrders.php`
（`makeOrder()` 已无调用方）。

### 三条不可让步的底线

1. **服务端算价** — 忽略前端传入的 `price`，只认 `market_goods` 挂单价。
   契约用 `price=0.01` 篡改验证：88.00 × 2 仍算 176.00。
2. **支付失败无副作用** — 通道未接入时 `pay_time` 不变、支付号为空。
3. **IDOR 最小披露** — 他人订单按「订单不存在」处理。

### 踩坑

| 坑 | 修法 |
|---|---|
| `UserToken::issue()` 签名是 `(device, ip)`，**不接收 user_id** | 归属靠单独 INSERT 的 `user_id` 列建立，不要指望 issue() |
| 一个文件放多个类违反 PSR-4，PHP 找不到类 | 一个文件一个类，按命名空间拆 |
| 契约用例的校验顺序错了 | 数量校验排在幂等校验之前（先判断买不买得起，再判断是否重复提交），断言文案要按真实顺序写 |
| `pay think run` 未起库时 `startTrans()` 直接 PDOException | 先 `mysqld_safe` 再跑契约 |

### 接入真实通道时怎么做

只需在 `Gateways::for()` 里按 `payment_channel` 返回真实网关实现，
其余代码不动。真实网关必须做到：
① `paid()` 只在收到通道回调或查单确认后调用；
② 回调入口校验签名；
③ 回调处理幂等（同一 order_sn 重复回调不重复发货）。

---

## 账号域真实后端（2026-10-19）

### 为什么做

MOCK 端点全量盘点后，挑出账号域三个端点先做 —— 它们依赖短信通道，
比支付通道容易接，且能一次解锁整个账号体系。

**原 MOCK 实现的漏洞（任何人可利用）**

```php
// sms/send —— 验证码直接回传给前端
return Response::ok(['mobile'=>$mobile,'event'=>$event,'expire'=>60,'code'=>'123456']);
```

调一次接口就拿到验证码，注册/改密的验证码环节形同虚设；而且全局写死一个码。
`register` 不落库（返回 MockData 假用户），`resetPwd` 不改密（只回显 mobile）。

### 做了什么

| 文件 | 职责 |
|---|---|
| `app/model/SmsCode.php` | 验证码模型：HMAC-SHA256 存储 + 各类阈值常量 |
| `app/service/SmsSender.php` | 通道接口 |
| `app/service/SmsResult.php` | 发送结果（`sent` / `sentWithCode` / `failed`） |
| `app/service/LocalSmsChannel.php` | 本地通道，码写日志，不进响应体 |
| `app/service/NullSmsChannel.php` | 恒定失败 |
| `app/service/SmsChannels.php` | 按 `env('SMS_CHANNEL')` 分发 |
| `app/service/SmsCodeService.php` | 生成/发送/校验，频率与爆破防护 |
| `app/service/AccountService.php` | 注册/改密的事务编排 |
| `app/service/PasswordPolicy.php` | 密码强度、唯一键判定、登录载荷 |
| `app/controller/api/SmsController.php` | sms/send（从 PayController 迁出） |
| `sql/09-account-auth.sql` | sms_code 改 HMAC + 新增 sms_send_log + user 补 2 列 |

### 四条底线

1. **明文码绝不出现在 HTTP 响应体** — 响应只含 `{mobile, event, expire}`
2. **一次性消费** — 校验成功即 `is_used=1`，重放被拒
3. **试 5 次作废** — 6 位码只有 10^6 空间，必须限爆破
4. **改密吊销该用户全部 token** — 改密语义是「怀疑密码泄露」，旧 token 有效等于没改

### 短信通道 vs 支付通道：为什么结论不同

| | 支付 | 短信 |
|---|---|---|
| 无通道时 | 拒绝（fail-closed） | 可发，走本地通道写日志 |
| 理由 | 支付成功=钱扣了 | 失败代价是收不到码，非资损 |
| 生产兜底 | 恒定拒绝 | 未配置通道时明确失败 |

短信若也 fail-closed，本地开发与自动化测试完全无法注册；
但生产环境未配置通道时必须失败 ——「说发送成功但用户永远收不到」也是假成功。

### 踩坑

| 坑 | 修法 |
|---|---|
| `.env` 分段键读取需带前缀（`env('SMS.HMAC_KEY')`） | 根级键**必须放在所有 `[分段]` 之前**，否则解析器把它归进上一个段 |
| `parse_ini_file` 只认 `;` 注释，`#` 会导致整个 .env 解析失败 | .env 注释一律用 `;` |
| ThinkPHP 的 Env 用 `parse_ini_file` 加载，语法错误时**静默返回空数组** | 改完 .env 必须 `var_dump(env('XXX'))` 实测确认，不能假定生效 |
| `AccountService` 写完 302 行触发 300 行红线 | 拆出 `PasswordPolicy`（密码策略/唯一键判定/登录载荷） |
| 拆完有未使用的 import（`UserToken` / `MockData`） | 拆分后必查 import 使用情况，否则是死引用 |

### 接入真实短信服务商

1. 新建 `implements SmsSender` 的类
2. 在 `SmsChannels::for()` 登记分发
3. 其余代码不动

生产环境还需在 `.env` 配 `SMS_CHANNEL=<服务商>` 与 `SMS_HMAC_KEY`（32 字节随机 hex）。

## P0 缺口补齐：钱包/实名/支付闭环/管理端（2026-10-19）

按《司南2.0项目文档》补齐 P0 四域，全部 TDD（先契约测试后实现）：

### 四域落地

| 域 | 迁移 | 服务 | 测试（全绿） |
|---|---|---|---|
| 钱包账本 | `sql/10-wallet.sql` | `WalletService`（账本唯一入口+恒等式审计） | `wallet_test.php` 30 项 |
| 实名认证 | `sql/11-realname.sql` | `RealnameService`（三态分离：脱敏/密文AES-GCM/指纹HMAC 一证一号） | `realname_test.php` 21 项 |
| 支付闭环 | `sql/12-payment.sql` | `SettlementService`（幂等结算）+ `PayNotify`（HMAC验签）+ `NotifyController` | `payment_test.php` 28 项 |
| 管理端 | `sql/13-admin.sql` | `AdminAuthService`（Argon2id+单会话12h+防枚举）+ `AdminController` | `admin_test.php` 15 项 + `admin_e2e.sh` 13 项 HTTP 全链路 |

### 管理端接入要点

- 路由在 `route/sub/route_admin.php`（无 `/api` 前缀），由 `route/app.php` 显式 require；
  `route/sub/` 下文件不会被 glob 自动加载，必须 require。
- token 走 header `X-Admin-Token`；创建管理员唯一入口 `php tools/create-admin.php <u> <p>`，
  密码 ≥10 位含字母数字，库内不允许默认弱口令。
- `affiche` 表无 `updatetime` 列（原站遗留），toggle 保存需按表分流。
- `affiche_type` 字典原为空表且原站样本遗失，`01-schema.sql` 已补 5 条默认字典
  （前端仅依赖 `{id,name}` 通用结构，可在库内增删）。

### 🔴 数据事故记录（自省）

重导 `01-schema.sql` 做 E2E 排障时，其 `DROP TABLE` 清掉了 content 域
（affiche/series/blind_box/publisher/banner）的原站样本数据，而
`recon/raw2/` 样本与 `recon/seed.py` 已随旧工作区丢失，**无法恢复**。
P0 四域表（10-13 号迁移新增）不受影响，user/artel_order 等业务数据完好。
教训：**01-schema.sql 含 DROP TABLE，任何场景下都不要对已有数据的库重导**；
补种子应单独 INSERT 或用 `--if-not-exists` 风格迁移。

### 遗留（P1）

- ~~content 域样本数据需重新抓原站灌回~~ → 已用演示数据填充（`bash tools/seed-demo.sh`，
  幂等可重跑；banner/公告走管理端接口灌入，藏品/系列/挂单走 SQL；图片全部命中
  `public/static/oss/` 预下载资产零出网。演示管理员：`demo_admin / DemoAdmin#2026`）
- `tools/contract/run.php`（本地输出 vs 原站实测比对）依赖样本目录，样本丢失后不可用，
  恢复样本后需 `GANDART_SAMPLE_DIR=... php tools/contract/run.php` 重跑
- `POST /api/system/ping` 返回 HTML 404 页为既有行为（mock.php 用 `Route::get` 只注册 GET），
  健康检查统一用 GET
- 管理端无「新建藏品」接口（P0 刻意最小集），演示藏品类数据目前靠 SQL 直插
  （见 `tools/seed-demo.sh` §5）；P1 若开放运营发藏，需补 `admin/collection/save`
  并带发行量守恒校验
