# API 接口与外链审查报告

- 审查对象：`/workspace/gandart-h5` @ `9a31d9a`（远端 HEAD 一致）
- 审查范围：117 个 `.vue` + 5 个 `.js` + 构建配置 + 服务端代理
- 审查方式：静态扫描 + 对原站 `api.gandart.shop` 逐端点实测验证
- 结论：**外链零风险**；**API 层发现 1 个确认缺陷、3 个契约偏差、1 个结构问题**

---

## 一、外链审查（结论：干净）

全项目外链域名统计：

| 域名 | 出现次数 | 性质 | 判定 |
|---|---|---|---|
| `h5.gandart.shop` | 9 | 原站域名，注释/来源标注 | OK |
| `api.gandart.shop` | 3 | 接口域名 | OK |
| `img.gandart.shop` | 1 | 图片 OSS 域名 | OK |
| `www.w3.org` | 1 | SVG `xmlns` 命名空间 | OK（不发请求） |

**排除 gandart.shop 与 w3.org 后，全项目无任何第三方外链。** 无 CDN 字体、无统计脚本、无外部图片热链。

关键确认点：

1. **CSS 层干净** —— `src/styles/`、`App.vue`、`main.js`、`index.html` 中**零** `url()`、`@import`、`@font-face` 外部引用。
2. **§4 记录的「CDN 字体漏扫」坑已闭环** —— `strip-external.cjs` 在 postbuild 剥离 Vant 的 `at.alicdn.com` woff 兜底源，并带产物自检（残留则 `exitCode=1`）。§4 那条踩坑记录说明当时是构建后剥离解决的，机制仍在。
3. **代理放行规则正确** —— `server.cjs:203` 保持 `req.url.startsWith('/api') || req.url.startsWith('/addons/')`，符合 §3.2。
4. **OSS query 剥离在位** —— `http.js:128` `path = path.split('?')[0]`，符合 §3.3。
5. `oss()` 已把绝对 URL 的 `img.gandart.shop` 前缀剥掉（`http.js:123`），图片统一走本地 `/static/oss`。

> 注：`API_HOST`/`OSS_HOST` 两个导出常量在 `http.js` 中定义但未被引用，属可清理的死代码，不影响运行。

---

## 二、API 缺陷：确认 1 个（已实测定论）

### 🔴 P0-1　`/box/collection/CollectionList` 是死路由，404

`src/api/index.js:14`：

```js
export const getCollectionList = (params) => http.get('/box/collection/CollectionList', { params })
```

**实测证据**（原站，`404` = 路由不存在；`401` = 路由存在仅需鉴权）：

| 端点 | 响应 | 判定 |
|---|---|---|
| `/box/collection/CollectionList` | `{"code":0,"msg":"404"}` | ❌ 路由不存在 |
| `/box/collection/collectionList` | `{"code":0,"msg":"404"}` | ❌ 路由不存在 |
| **`/box/collection/collection_list`** | `{"code":401,"msg":"请登录后操作"}` | ✅ **真实路由** |

三段式路由中控制器名是**小写下划线** `collection_list`，代码写成了驼峰 `CollectionList`。

**影响：目前为零。** `getCollectionList` 全项目无任何调用方（已 grep 确认），属死代码。但这是个埋雷 —— 任何人后续接线藏品列表页就会踩到，且错误信息是误导性的 `"404"`（看起来像"接口正常但数据为空"）。

**同源问题：`docs/api/API.md:311` 也记错了**，写的是 `/box/collection/CollectionList`，需同步修正为 `collection_list`。这正是 PROJECT_MEMORY §5.1「重写后端时会照抄契约」的传导路径 —— 错误文档会导致错误后端。

---

## 三、API 契约偏差（3 个）

### 🟡 P1-1　6 处 `http.get` 违反 §5.1 第 8 条「全部 POST」

`src/api/index.js` 中：

| 行 | 端点 |
|---|---|
| 4 | `/banner/show/getBanner` |
| 9 | `/index/getSeries` |
| 10 | `/index/getConfig` |
| 13 | `/box/blind_box/list` |
| 14 | `/box/collection/CollectionList` |
| 20 | `/synthesis/recordList` |
| 85 | `/Activity/category` |
| 86 | `/crystal_goods/list` |
| 183 | `/index/afficheDetail/ids/${id}` |

**实测结论：这批端点 GET/POST 行为等价**，响应体逐字节一致（唯一差异是 `time` 时间戳字段）。原站 ThinkPHP 对此不区分方法。

所以这不是线上 Bug，但**是重写后端的隐患**：一旦按 §5.1「全部 POST」实现后端，这些 GET 调用会全挂。文件里已有两处注释自证这个坑曾发生过：

- 第 5 行：`/* 原站实测：公告列表与分类均为 POST（早期误写为 GET，已按实测修正为 POST） */`
- 第 17 行：`/* 原站实测 pages/compound/compound 走 POST /synthesis/themeinfo { thm }（GET 无效） */`

**建议**：在 `http.js` 统一封装一个 `post()` 助手，或对这 9 处加注释标注「原站 GET/POST 等价，但按契约统一走 POST」，避免重写后端时漏改。

### 🟡 P1-2　`getConfig` 缺必填 `key` 参数，永远返回失败

`src/api/index.js:10`：`http.get('/index/getConfig')` —— 无参数。

实测：传 `key=site_name` / `site` / `site_url` / `app_name` 全部仍返回 `{"code":0,"msg":"缺少key值"}`。

说明 `key` 不是通过 query/POST body 传的，或需要特定值。`docs/api/API.md:128` 该行的参数列也是空的。

**影响：零** —— `getConfig` 无调用方，`Info.vue:29` 仅在注释里提及。但同样属未完成接线。

### 🟡 P1-3　`getCollectionList` 方法亦错（与 P0-1 同源）

即便修好端点名，§5.1 第 8 条要求 POST，此处仍应改。

---

## 四、结构问题：端点散落在页面层

`src/api/index.js` 只登记 62 个端点，但**页面里直接 `http.post` 绕过了 API 层**：

| 文件 | 端点 |
|---|---|
| `src/pages/address/AddressList.vue:28` | `/user/address_list` |
| `src/pages/compound/Compound.vue:69` | `/synthesis/themeinfo` |
| `src/pages/compound/Compound.vue:129` | `/synthesis/material` |
| `src/pages/lottery/Lottery.vue:97` | `/lottery/info` |
| `src/pages/lottery/Lottery.vue:109` | `/lottery/statistics` |
| `src/pages/lottery/Lottery.vue:156` | `/lottery/start` |
| `src/pages/lottery/Record.vue:40` | `/lottery/record` |
| `src/pages/lottery/Record.vue:45` | `/lottery/statistics` |

这 8 处有 3 个端点（`address_list`、`themeinfo`、`material`）在 `src/api/index.js` 里**已有对应封装**，属于重复实现。

**实测确认这 4 个 `/lottery/*` 端点真实存在**（均返回 401 鉴权提示，非 404），且 `API.md` 已收录 6 处 lottery 相关条目 —— 但它们没有进入 `src/api/index.js` 的统一登记。

**影响**：绕过 API 层意味着这些端点不受 `http.js` 拦截器统一管理（token 双通道、401 静默续登）。页面里手写的调用若自行传 token，行为会与全局不一致 —— 这正是 §4 中「所有支付方式不显示」那类字段/通道问题的同类隐患。

---

## 五、核对通过的部分 ✅

以下与 PROJECT_MEMORY 记录一致，无需改动：

- **§5.1 契约遵守情况**：响应拦截器严格判 `code === 1` 为成功（`http.js:99`）；401 双路径拦截（body 401 + HTTP 401）；token **header + query 双通道**（`http.js:56,59`）三处全在。
- **`fastOrderNew` 命名正确**（`index.js:117`），未复现 §4「猜成 `fastCreateOrder`」的错。
- **`detail/ids/{ids}` 路径参数命名保持**（`getUserCollectionDetail`、`getOrderDetails`、`getMarketDetail`）。
- **`inviter_code` 字段兼容写法**在位（`info.inviter_code || info.invite_code`）。
- **`server.cjs` 放行 `/addons/`**（`:203`），未复现二维码图裂。
- **`Archive-assets.cjs` / `strip-external.cjs`** 均在，`package.json` 的 postbuild 链路需依赖它们，建议改动构建配置时勿删。

---

## 六、待办清单（按优先级）

| # | 优先级 | 问题 | 位置 | 建议动作 |
|---|---|---|---|---|
| 1 | 🔴 P0 | `CollectionList` 死路由 404 | `src/api/index.js:14` | 改为 `/box/collection/collection_list` |
| 2 | 🔴 P0 | 同一错误写进契约文档 | `docs/api/API.md:311` | 同步改为 `collection_list`，否则重写后端会照抄错路由 |
| 3 | 🟡 P1 | 6 处 GET 与「全部 POST」契约冲突 | `src/api/index.js` 9 处 | 加注释标注等价，或统一封装 POST |
| 4 | 🟡 P1 | `getConfig` 缺 `key` 参数 | `src/api/index.js:10` | 补明 key 取值来源；否则标注为未完成 |
| 5 | 🟡 P1 | 8 处页面层散落调用绕过 API 层 | `pages/{address,compound,lottery}/*` | 收拢进 `src/api/index.js`，统一走拦截器 |
| 6 | 🟢 P2 | 死代码常量 | `src/api/http.js:5,6` | `API_HOST`/`OSS_HOST` 未被引用，可清理 |

**注意**：`public/` 下 287 个图片资源本次未拉取（jsDelivr 分通道时按扩展名排除，因其不参与接口审查）。若后续要构建或做像素对比，需用同样方式补齐，否则 `npm run build` 会缺图。

---

## 七、本次审查的踩坑（环境层，供参考）

- **git clone 不可用**：`github.com` 的 git 传输在此网络不通（curl 返回 `000`），`api.github.com` 走 HTTPS 可达。
- **codeload tarball 不可用**：`--max-time` 超时截断在 13.6MB（287 张图占体积大头），且**不支持 Range**（请求 Range 返回 `200` 而非 `206`），无法续传。
- **可用通道**：`api.github.com` 拉单文件（未认证限速 60/小时）+ `cdn.jsdelivr.net/gh/<owner>/<repo>@<sha>/<path>` 按文件批量拉取（本次 139/139 全部字节数与 git tree 一致校验通过）。
