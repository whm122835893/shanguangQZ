/**
 * 构建后处理：剥离产物中残留的外部资源依赖（外链清零）
 *
 * 背景：Vant 的 @font-face 除了内联 base64 woff2，还挂了一个阿里 CDN 的 woff 兜底源：
 *   src:url(data:font/woff2;base64,...) format("woff2"),
 *       url(//at.alicdn.com/t/c/font_2553510_xxx.woff?t=...) format("woff")
 * 浏览器通常命中 woff2 后不再请求 woff，所以运行时抓包看不到，
 * 但它是一条真实的外链声明。本项目完全未使用 van-icon 组件，
 * 故在打包后直接把外链源剔除，避免任何对外域名的依赖。
 *
 * 同时兜底清理：产物中残留的绝对 http(s) 图片/接口引用（非注释、非 source map）。
 */
const fs = require('fs')
const path = require('path')

const DIST = path.join(__dirname, 'dist')
/** 允许保留的域名（仅出现在字符串常量里、不产生浏览器请求） */
const ALLOWED_HOSTS = [
  'w3.org',           // SVG xmlns 命名空间，不发请求
  'vuejs.org',        // Vue 报错文案链接，不发请求
  'reactjs.org',
  'schema.org'
]

function walk(dir, out = []) {
  for (const name of fs.readdirSync(dir)) {
    const p = path.join(dir, name)
    const st = fs.statSync(p)
    if (st.isDirectory()) walk(p, out)
    else out.push(p)
  }
  return out
}

let stripped = 0
let files = 0

for (const file of walk(DIST)) {
  if (!/\.(css|js|html)$/.test(file)) continue
  const before = fs.readFileSync(file, 'utf8')
  let after = before

  /* 1) 剥离 @font-face 中的外部字体源（保留内联 base64 源） */
  after = after.replace(
    /,url\(\s*(?:https?:)?\/\/[^)]+\.(?:woff2?|ttf|otf|eot)(?:\?[^)]*)?\s*\)\s*format\(["']?[a-z0-9]+["']?\)/gi,
    ''
  )

  /* 2) 剥离 CSS 中残留的外部 url() 资源 */
  after = after.replace(/url\(\s*(?:https?:)?\/\/([^)'"]+)\s*\)/gi, (m, host) => {
    if (ALLOWED_HOSTS.some((h) => host.includes(h))) return m
    return 'none'
  })

  if (after !== before) {
    fs.writeFileSync(file, after)
    stripped++
  }
  files++
}

/* 3) 构建产物自检：确认已无 alicdn 等外部字体引用 */
const check = walk(DIST)
  .filter((f) => /\.(css|js|html)$/.test(f))
  .map((f) => fs.readFileSync(f, 'utf8'))
  .join('')
if (/at\.alicdn\.com|font_2553510/.test(check)) {
  console.error('[archive] 警告：产物中仍存在外部字体引用，请检查')
  process.exitCode = 1
} else {
  console.log(`[archive] 外链剥离完成：扫描 ${files} 个产物文件，改写 ${stripped} 个`)
}