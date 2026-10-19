import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

// 联调目标可切换：
//   npm run dev              -> 本地 Mock Server（默认，避免误打原站产生真实写操作）
//   PROXY_TARGET=https://api.gandart.shop npm run dev   -> 回到原站做对照抓包
const PROXY_TARGET = process.env.PROXY_TARGET || 'http://127.0.0.1:4200'
const isOriginSite = PROXY_TARGET.includes('gandart.shop')

export default defineConfig({
  plugins: [vue()],
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
      /* 其余 /static/* 已在 public/static/ 落盘，由 Vite 直接提供。*/
      /* 二维码走本地 Mock（/addons/qrcode）。注意不带 /api 前缀 */
      '/addons': { target: PROXY_TARGET, changeOrigin: true }
    }
  }
})
