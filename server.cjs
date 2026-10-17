/**
 * 单端口服务：托管Vue3 静态产物 + 反向代理原站 API
 * 移动端预览用 —— 手机浏览器打开即为完整可交互的复刻站
 */
const http = require('http')
const https = require('https')
const fs = require('fs')
const path = require('path')

const PORT = process.env.PORT || 3000
const DIST = path.join(__dirname, 'dist')
const API_HOST = 'api.gandart.shop'
const IMG_HOST = 'img.gandart.shop'
/* 原站 OSS 图片本地缓存目录：图片一律从原站下载填充到本项目，
   避免手机端直连 img.gandart.shop 因跨域/体积导致大图加载失败。*/
const IMG_CACHE = path.join(__dirname, '.img-cache')
fs.mkdirSync(IMG_CACHE, { recursive: true })

const IMG_MIME = {
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.svg': 'image/svg+xml'
}

/* 移动端加速：OSS 原图常为 1MB+ 大图，手机弱网下易加载失败。
   落盘缓存后调用本地压缩工具生成 webp 缩略版（长边 750 / 质量 82，体积可降 90%+），
   命中后直接返回小图；压缩不可用时回退原图，不影响正确性。 */
const COMPRESSOR = path.join(__dirname, 'tools/compress-image.py')
const { execFile } = require('child_process')

function buildWebp(srcFile, dstFile) {
  return new Promise((resolve) => {
    execFile('python3', [COMPRESSOR, srcFile, dstFile], { timeout: 20000 }, (err) => {
      resolve(!err && fs.existsSync(dstFile) && fs.statSync(dstFile).size > 0)
    })
  })
}

/** 代理 OSS 图片并落盘缓存（命中缓存直接返回，不重复下载） */
function serveOssImage(req, res, urlPath, search) {
  const ext = (path.extname(urlPath) || '.png').toLowerCase()
  const key = Buffer.from(urlPath).toString('base64url').slice(0, 180) + ext
  const file = path.join(IMG_CACHE, key)

  const send = () => {
    const st = fs.statSync(file)
    const h = {
      'Content-Type': IMG_MIME[ext] || 'application/octet-stream',
      'Content-Length': st.size,
      'Cache-Control': 'public, max-age=31536000, immutable'
    }
    res.writeHead(200, h)
    fs.createReadStream(file).pipe(res)
  }

  /* 已有压缩版：直接返回小图（移动端秒开）
     仅当压缩后确实更小才启用，避免小图转 webp 反而变大 */
  const webp = file + '.webp'
  if (fs.existsSync(webp) && fs.statSync(webp).size > 0) {
    const oSize = fs.existsSync(file) ? fs.statSync(file).size : Infinity
    if (fs.statSync(webp).size >= oSize) {
      try { fs.unlinkSync(webp) } catch (e) { /* 忽略 */ }
      return send()
    }
    const st = fs.statSync(webp)
    res.writeHead(200, {
      'Content-Type': 'image/webp',
      'Content-Length': st.size,
      'Cache-Control': 'public, max-age=31536000, immutable'
    })
    return fs.createReadStream(webp).pipe(res)
  }

  if (fs.existsSync(file) && fs.statSync(file).size > 0) {
    /* 首次命中原图后异步生成压缩版，供后续请求使用 */
    if (/jpeg|jpg|png/i.test(ext)) buildWebp(file, webp)
    return send()
  }

  const up = https.request(
    {
      host: IMG_HOST,
      path: urlPath + (search || ''),
      method: 'GET',
      headers: {
        host: IMG_HOST,
        origin: 'https://h5.gandart.shop',
        referer: 'https://h5.gandart.shop/',
        'user-agent': req.headers['user-agent'] || ''
      }
    },
    (r) => {
      if (r.statusCode !== 200) {
        r.resume()
        res.writeHead(r.statusCode || 404, { 'Content-Type': 'text/plain; charset=utf-8' })
        return res.end('oss ' + (r.statusCode || 'error'))
      }
      const tmp = file + '.tmp'
      const ws = fs.createWriteStream(tmp)
      r.pipe(ws)
      ws.on('finish', () => {
        fs.renameSync(tmp, file)
        send()
      })
      ws.on('error', () => res.writeHead(500).end())
    }
  )
  up.on('error', () => {
    res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' })
    res.end('oss proxy error')
  })
  up.end()
}

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'application/javascript',
  '.css': 'text/css',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.svg': 'image/svg+xml',
  '.json': 'application/json',
  '.woff2': 'font/woff2'
}

http
  .createServer((req, res) => {
    /* ---- OSS 图片：优先静态资源（已下载进本项目），缺失时兜底从原站下载并落盘 ----
       注意：不含格式转换，一律原图原格式，避免 WebP 在旧版 iOS Safari 不兼容导致图不显示。*/
    if (req.url.startsWith('/img/') || req.url.startsWith('/static/oss/')) {
      const qIdx = req.url.indexOf('?')
      const p = qIdx === -1 ? req.url : req.url.slice(0, qIdx)
      const s = qIdx === -1 ? '' : req.url.slice(qIdx)
      const urlPath = p.replace(/^\/img/, '').replace(/^\/static\/oss/, '')
      const ext = (path.extname(urlPath) || '.png').toLowerCase()
      const staticFile = path.join(DIST, 'static', 'oss', urlPath)

      /* 静态资源已存在：直接返回（构建产物内，最快最稳） */
      if (fs.existsSync(staticFile) && fs.statSync(staticFile).size > 0) {
        const st = fs.statSync(staticFile)
        res.writeHead(200, {
          'Content-Type': IMG_MIME[ext] || 'application/octet-stream',
          'Content-Length': st.size,
          'Cache-Control': 'public, max-age=31536000, immutable'
        })
        return fs.createReadStream(staticFile).pipe(res)
      }

      /* 未预下载：从原站拉取并落盘到 dist/static/oss（下次即静态命中） */
      const pub = path.join(__dirname, 'public', 'static', 'oss', urlPath)
      fs.mkdirSync(path.dirname(staticFile), { recursive: true })
      fs.mkdirSync(path.dirname(pub), { recursive: true })
      const up = https.request(
        {
          host: IMG_HOST,
          path: urlPath + (s || ''),
          method: 'GET',
          headers: {
            host: IMG_HOST,
            origin: 'https://h5.gandart.shop',
            referer: 'https://h5.gandart.shop/',
            'user-agent': req.headers['user-agent'] || ''
          }
        },
        (r) => {
          if (r.statusCode !== 200) {
            r.resume()
            res.writeHead(r.statusCode || 404, { 'Content-Type': 'text/plain; charset=utf-8' })
            return res.end('oss ' + (r.statusCode || 'error'))
          }
          const tmp = staticFile + '.tmp'
          const ws = fs.createWriteStream(tmp)
          r.pipe(ws)
          ws.on('finish', () => {
            fs.renameSync(tmp, staticFile)
            const st = fs.statSync(staticFile)
            try { fs.copyFileSync(staticFile, pub) } catch (e) { /* 忽略 */ }
            res.writeHead(200, {
              'Content-Type': IMG_MIME[ext] || 'application/octet-stream',
              'Content-Length': st.size,
              'Cache-Control': 'public, max-age=31536000, immutable'
            })
            fs.createReadStream(staticFile).pipe(res)
          })
          ws.on('error', () => res.writeHead(500).end())
        }
      )
      up.on('error', () => {
        res.writeHead(502, { 'Content-Type': 'text/plain; charset=utf-8' })
        res.end('oss proxy error')
      })
      return up.end()
    }

    // ---- API 代理：补上原站校验所需�� Origin / Referer ----
    // 注意：原站有一类「二进制资源」接口不带 /api 前缀（如 /addons/qrcode/... 返回 PNG），
    //必须一并放行，否则会被 SPA fallback 吞成 index.html，前端 <img> 拿到 HTML 导致图裂。
    if (req.url.startsWith('/api') || req.url.startsWith('/addons/')) {
      const proxyReq = https.request(
        {
          host: API_HOST,
          path: req.url,
          method: req.method,
          headers: {
            ...req.headers,
            host: API_HOST,
            origin: 'https://h5.gandart.shop',
            referer: 'https://h5.gandart.shop/',
            /* 二维码等接口返回 PNG，要求上游不压缩，避免解压后长度与content-length 不符 */
            'accept-encoding': 'identity'
          }
        },
        (proxyRes) => {
          /* 上游若仍回gzip/br，本服务不解压，交由浏览器处理（去掉长度头） */
          const enc = String(proxyRes.headers['content-encoding'] || '').toLowerCase()
          const h = { ...proxyRes.headers }
          if (enc && enc !== 'identity') delete h['content-length']
          res.writeHead(proxyRes.statusCode || 200, h)
          proxyRes.pipe(res)
        }
      )
      proxyReq.on('error', () => {
        res.writeHead(502, { 'Content-Type': 'application/json' })
        res.end(JSON.stringify({ code: 0, msg: 'proxy error' }))
      })
      req.pipe(proxyReq)
      return
    }

    // ---- 静态资源 ----
    const relPath = decodeURIComponent(req.url.split('?')[0])
    let filePath = path.join(DIST, relPath)

    // 资源型路径（/assets/*）不存在时返回 404，绝不回退 index.html。
    // 否则旧 HTML 引用的已删除 JS 会拿到 HTML 内容，导致移动端白屏。
    if (relPath.startsWith('/assets/') || path.extname(relPath)) {
      if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
        res.writeHead(404, { 'Content-Type': 'application/json; charset=utf-8' })
        res.end(JSON.stringify({ code: 404, msg: 'asset not found: ' + relPath }))
        return
      }
    } else if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
      // 页面路由回退到 index.html
      filePath = path.join(DIST, 'index.html')
    }

    const ext = path.extname(filePath).toLowerCase()
    const headers = { 'Content-Type': MIME[ext] || 'application/octet-stream' }
    // index.html 禁止缓存，避免旧 HTML 引用已删除的 hash 资源导致白屏
    if (path.basename(filePath) === 'index.html') {
      headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
      headers.Pragma = 'no-cache'
      headers.Expires = '0'
    } else if (relPath.startsWith('/assets/')) {
      headers['Cache-Control'] = 'public, max-age=31536000, immutable'
    } else if (relPath.startsWith('/img/')) {
      /* 由 serveOssImage 提前返回，此处仅兜底 */
      headers['Cache-Control'] = 'public, max-age=31536000, immutable'
    }
    res.writeHead(200, headers)
    fs.createReadStream(filePath).pipe(res)
  })
  .listen(PORT, '0.0.0.0', () => {
    console.log(`gandart-h5 preview ready on http://0.0.0.0:${PORT}`)
  })
