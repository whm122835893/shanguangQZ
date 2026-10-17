import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// 联调目标可切换：
//   npm run dev              -> 本地 Mock Server（默认，避免误打原站产生真实写操作）
//   PROXY_TARGET=https://api.gandart.shop npm run dev   -> 回到原站做对照抓包
const PROXY_TARGET = process.env.PROXY_TARGET || 'http://127.0.0.1:4200'
const isOriginSite = PROXY_TARGET.includes('gandart.shop')

/**
 * 静态资源直引插件（零改源码方案）
 *
 * 问题：208 处模板里写死 src="/static/icon/xxx.png"（88 个 .vue 文件），
 *      Vite 的 import-analysis 会把它当成模块依赖去解析，找不到就报
 *      "Failed to resolve import"，页面直接白屏（首页就挂）。
 *      靠建 public/static 目录无法解决 —— Vite 要求文件真实存在。
 *
 * 方案：在解析层拦截，判定「以 /static/ 开头的绝对路径 + 静态扩展名」为
 *      外部资源，编译期替换为原站绝对 URL，运行时由浏览器直连 h5.gandart.shop。
 *      好处：不改一行 .vue 源码，不往仓库塞 287 张二进制图。
 *
 * 注意：模板里还有一处 "/pages/" 开头，那是路由路径（非资源），必须排除。
 */
const STATIC_EXT= /\.(png|jpe?g|gif|webp|svg|ico|mp3|mp4)$/i

function staticAssetsPlugin() {
  const isStaticUrl = (url) =>
    typeof url === 'string' &&
    url.startsWith('/static/') &&
    STATIC_EXT.test(url)

  return {
    name: 'gandart-static-assets',
    /* 本插件仅用于 dev：生产构建走 Rollup 原生资源处理，产物里应是
       /static/xxx.png。若生产也启用，会把路径改写成 dev 专有的虚拟前缀
       /@local-asset/...，线上必然 404（页面空白 + chunk 加载失败）。
       用 apply(config, env) 的标准签名判断命令。 */
    apply(_config, env) {
      return env.command === 'serve'
    },
    enforce: 'pre',
    /* 模板里208 处 src="/static/xxx.png" 会被 Vite 的 import-analysis
       当成**模块依赖**解析，找不到就整页崩（首页白屏）。
       现在 90 个图标已全部下载到 public/static/，只需把它们标记为
       「外部资源」，让 Vite 原样保留 URL 交给浏览器，不再尝试模块解析。
       注意不能改写成外站地址 —— 那会重新引入原站依赖。 */
    resolveId(id) {
      if (isStaticUrl(id)) return { id: '/@local-asset' + id, external: true }
      return null
    }
  }
}

export default defineConfig({
  plugins: [vue(), staticAssetsPlugin()],
  // 保留上一版构建产物：旧 HTML 若被客户端缓存，仍能加载到对应的 JS，避免白屏
  build: {
    rollupOptions: {
      output: {
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]'
      }
    }
  },
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) }
  },
  server: {
    allowedHosts: true,
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: PROXY_TARGET,
        changeOrigin: true,
        // 只有打原站才需要伪造 Origin/Referer；本地 Mock 不需要
        headers: isOriginSite
          ? { Origin: 'https://h5.gandart.shop', Referer: 'https://h5.gandart.shop/' }
          : {}
      },
      /* 图片服务走本地 Mock（后端 Image 控制器），不再依赖原站 CDN。
         必须排在 '/static' 之前 —— Vite 按对象键顺序做前缀匹配。*/
      '/static/oss': { target: PROXY_TARGET, changeOrigin: true },
      /* 其余 /static/* 已在 public/static/ 落盘（90 个图标），由 Vite 直接提供，
         不再代理到任何外部服务器。*/
      /* 二维码走本地 Mock（/addons/qrcode），不再依赖原站。
         注意这条路径不带 /api 前缀 —— 原站就是如此，写成 /api/addons 会404。 */
      '/addons': { target: PROXY_TARGET, changeOrigin: true }
    }
  }
})