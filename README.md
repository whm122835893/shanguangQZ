# gandart-h5 —— 闪光集市 H5 像素级复刻

对 [h5.gandart.shop](https://h5.gandart.shop) 的一比一像素级复刻。原站是 uni-app/Vue2 编译产物，本项目用 Vue 3 + Vite 重建，移动端 viewport 基准 `390×844`（`device_scale_factor=2`，截图 `780×1688`）。

**当前进度：原站 116 条路由全部复刻完成（本地注册 119 条，含 2 条重定向/别名），113 个页面组件。**

---

## 快速开始

```bash
npm install
npm run dev        # 开发服务器 http://localhost:5173
```

生产预览（单端口，供手机浏览器访问）：

```bash
npm run build
node server.cjs    # 默认 3000 端口，可用 PORT=8080 覆盖
```

> dev 环境需要在手机同局域网下访问，服务器已绑定 `0.0.0.0`。

---

## ⚠️ 必须先读：这个项目依赖原站后端

**本项目复刻的是前端，不是后端。** 所有数据（登录、藏品、订单、市场、抽签、社区）都实时请求原站 API，图片也来自原站 OSS。脱离原站后端，页面会全部空转。

### 依赖点清单

| 位置 | 依赖 | 性质 |
|------|------|------|
| `src/api/http.js:5` | `API_HOST = 'https://api.gandart.shop'` | **强依赖**，所有接口 |
| `src/api/http.js:6` | `OSS_HOST = 'https://img.gandart.shop'` | **强依赖**，未落盘图片的兜底 |
| `vite.config.js:26` | dev 代理 target + 伪造 `Origin`/`Referer` 头 | 开发期必需 |
| `server.cjs:12` | `API_HOST` / `IMG_HOST` 常量 | 生产预览必需 |

`vite.config.js` 与 `server.cjs` 中**手动伪造的来源头**：

```js
headers: { Origin: 'https://h5.gandart.shop', Referer: 'https://h5.gandart.shop/' }
```

原站接口大概校验请求来源。**如果将来要切到自有后端，这两处写死的域名和伪造头是首要改造点** —— 应改为从环境变量读取，而不是继续硬编码。

### 图片本地化程度（重要）

- **静态素材 100% 本地**：`public/static/` 下 186 个文件、约 33MB，无任何远程 CDN 引用。
- **藏品业务图片未完全本地化**：`src/api/oss-map.json` 当前只有 **6 条映射**，`public/static/oss/` 实际落盘 **18 张图**。市场列表、藏品详情等页面的绝大多数图片**仍直连原站 CDN**，经 `server.cjs` 兜底代理下载并压缩缓存。

若要彻底离线，需执行：

```bash
# 1. 把 IMGS 数组补全为全站用到的图片路径后执行
bash tools/fetch-oss-images.sh
# 2. 该脚本会自动调用 build-oss-map.py 重建映射表
```

---

## 技术栈

| 层 | 选型 | 说明 |
|----|------|------|
| 框架 | Vue 3.5 | `<script setup>` |
| 构建 | Vite 6 | |
| 路由 | Vue Router 4 | Hash 模式（对齐原站 uni-app hash 路由） |
| 状态 | Pinia 2 | 仅 `src/stores/user.js` 一个 store |
| UI | Vant 4 | **只用了 `showToast` / `showConfirmDialog`**，其余 UI 全部手写 |
| 网络 | axios | |
| 样式 | 原生 CSS | **未使用 SCSS**（`sass` 在 devDependencies 中但无实际调用） |

---

## 目录结构

```
src/
├── api/
│   ├── http.js          # axios 实例、token 注入、会话保活、oss() 图片地址拼接
│   ├── index.js         # 接口定义
│   └── oss-map.json     # 原站图片路径 -> 本地文件名映射（由 tools 生成）
├── components/          # 共用组件
├── pages/               # 113 个页面组件，按原站目录分组
│   ├── activity/  address/  blindBox/  certification/  collection/
│   ├── common/    compound/  consignments/  draw/  index/
│   ├── invitation/  kline/  login/  lottery/  market/
│   ├── message/   my/       notice/    order/  product/
│   ├── rank/      rebate/   rules/     shop/   sign/
│   ├── task/      union/    wallet/    webview/
├── router/index.js      # 119 条路由
├── stores/user.js       # 登录态
└── styles/
    ├── tokens.css       # 设计 Token，全部取自原站实测计算样式
    └── overrides.css    # Vant 样式覆盖（在 tokens 之后引入）
```

---

## 设计 Token

`src/styles/tokens.css` 中所有变量均**取自原站实测**，不是凭感觉调的。维护时改这里，不要在组件里写死颜色。

```css
--gd-primary: #f23d4d;      /* 公告条 / 按钮 / 价格 */
--gd-text: #4a4a4a;         /* 正文 rgb(74,74,74) */
--gd-text-dark: #23262f;    /* 标题 rgb(35,38,47) */
--gd-bg: #f6f5f5;           /* 页面底色 rgb(246,245,245) */
```

---

## 登录与会话

- token 以**裸字符串**存 `localStorage.token`（对齐原站格式），`userInfo` 存 `localStorage.userInfo`。
- `stores/user.js` 的 `readToken()` 兼容历史的 JSON 字符串格式。
- **会话保活**：`http.js` 登录成功后把账号密码存 `localStorage._gd_cred`，token 过期（401）时静默重新登录并重放原请求。仅本机生效。

---

## 像素级复刻方法论

### 对比流程

```bash
cd /workspace/_recon
python3 cmp_id.py /pages/xxx/yyy     # 原站+复刻自动登录 → 全页截图 → 算像素差
```

产出：
- `O-<页面>.png` / `M-<页面>.png` —— 原站 / 复刻截图
- `h-<页面>.png` —— 差异热力图
- 终端输出 `thr40`（RGB 任一通道差 > 40 的像素占比）、`thr80`（> 80）

辅助脚本：`domall.py`（原站 DOM 几何）、`mdom.py`（复刻端几何）、`oshot.py`、`mkcmp.py`（左右并排合成对比图）。

### 指标现状

已完成 9 批 47 页，全部 thr40 < 1.3%，多数 < 0.5%。对比图在 `docs/`。

### 从原站扒 CSS 时的坑（血泪教训）

1. **类名会骗人**。`u-border-bottom` + `u-input--square` 组合在 `setPwd`、`login-code`、`verification` 三页**实际都渲染为全边框圆角矩形**。别按类名下划线实现。

2. **DOM 的 `left` 不是文字起点**。文字类元素必须用 `mdom.py` 或截图量实际 x 区间验证。

3. **flex `justify-content: center` 会吞掉子元素间真实 gap**，需要显式 `margin-left`。

4. **相邻块级元素的 `margin` 会外边距塌陷**，改用 `display: flex`（列向）规避。

5. **原站 `uni-image` 外层与内层 `img` 尺寸常不一致**（内层溢出且不裁剪）。

6. **计数倒计时这类动态文本无法逐像素对齐**，两版秒数不同步会产生固有差异。

### 遇到字体 glyph 图标怎么办

uni-icons 那类字体图标（`uicon-empty-data`）**无法用 SVG 精确手绘**。做法：从原站截图裁剪 → 按前景/背景色反解 alpha 通道 → 存为透明 PNG，再按 alpha bbox 定尺寸。

已有：`public/static/empty/empty-data.png`。

---

## 构建脚本说明

```json
"prebuild":  "node archive-assets.cjs",
"postbuild": "node archive-assets.cjs"
```

`archive-assets.cjs` 会把上一版 `dist/assets` 归档，构建完再补回。原因：旧 HTML 若被浏览器缓存，仍需能加载对应 hash 的 JS，否则移动端白屏。

---

## tools/

| 脚本 | 作用 |
|------|------|
| `fetch-oss-images.sh` | 从原站 CDN 下载图片到 `public/static/oss/`（需先补全 `IMGS` 数组） |
| `build-oss-map.py` | 扫描 oss 目录，PNG 无透明通道的转 JPEG(q88)，生成 `oss-map.json` |
| `compress-image.py` | 图片压缩 |

---

## 项目约定

- **图标**：全部内联 SVG 或本地 PNG，**禁止 emoji 作功能图标**。
- **颜色**：一律引用 `tokens.css` 变量，禁止硬编码。
- **页面注释**：每个新页面的 `<script setup>` 顶部都有一段实测几何注释（坐标、尺寸、字号、颜色），是复刻的对照依据，改动时同步更新。

---

## 已知问题

1. **藏品图片未完全本地化**，见上文「图片本地化程度」。
2. **`pinia` 只服务一个 store**，`sass` 装了但完全没用到 —— 可考虑清理。
3. **登录态下部分页面原站本身就是空数据态**（如 `newsDetails` 接口返回 `null`、`blindBoxDetails` 现有藏品均非盲盒、`rebate`/`exchange` 整页空白），这些页面按「可见结果 1:1」还原，不代表功能已实现。
4. **`market/sellBegBuy` 原站会重定向到首页**，复刻照此行为实现。
