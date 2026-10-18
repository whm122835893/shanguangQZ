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

| 脚本 | 作用 | 当前结果 |
|---|---|---|
| `mock-server/check-coverage.py` | 前端 60 端点 vs Mock 67 路由 | **60/60 全覆盖** |
| `mock-server/check-endpoints.py` | 逐端点发真实请求（63×2=126 次），验 `code=1`／信封四键／401 | **126 次零失败** |
| `mock-server/smoke.py` | 契约红线：信封、字段数、金额 string、布尔 int、token 双通道、分页三写法 | **14/14 通过** |
| `mock-server/check-offline.py` | 断网核验：配置层/源码层/Mock 响应/图标落盘四处无原站引用 | **通过（162 个本地图标）** |

浏览器级核验（dev 15 页 + 生产首页）：**外部请求 0 个，404 资源 0 个**。

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

- git：工作区干净，已推送远端（v2.5 实质沉淀 = `ec10e60`，其后仅 §7 状态刷新）
- 本地服务：未运行（需要时按 §6 启动）
- 待办：无明确遗留（未决项见 §5.3 与 `docs/decisions/OPEN-DECISIONS.md`：D-001/002/003/005/006/007 共 6 项 OPEN）

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