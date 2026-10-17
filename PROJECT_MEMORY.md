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

### 2.3 `python3` 环境
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
| `order_status` / `order_type` 枚举 | 只观测到 `order_status=3`，完整枚举未知 |

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

# 发布（必须带 language node）
node /root/.codebuddy/skills/sites/scripts/publish.js \
  --dir /workspace/gandart-h5 --language node --start-cmd "node server.cjs"
```

---

## 7. 当前工作区状态

- git：工作区干净，HEAD = `6744fac`，已推送远端
- 本地服务：未运行（需要时按 §6 启动）
- 待办：无明确遗留（见 §5.3）