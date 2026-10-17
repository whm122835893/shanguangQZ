import axios from 'axios'
/* 由 tools/build-oss-map.py 生成：原站路径 -> 本项目的实际静态文件名（png 无透明通道已转 jpg） */
import OSS_MAP from './oss-map.json'

export const API_HOST = 'https://api.gandart.shop'
export const OSS_HOST = 'https://img.gandart.shop'

/* 预览环境会话保活：登录成功后保存凭据（仅本机 localStorage），
   token 过期（code 401）时静默重新登录并重放原请求，用户无感。 */
const CRED_KEY = '_gd_cred'

export const saveCred = (account, password) => {
  try { localStorage.setItem(CRED_KEY, JSON.stringify({ account, password })) } catch (e) { /* 忽略 */ }
}

const readCred = () => {
  try {
    const v = JSON.parse(localStorage.getItem(CRED_KEY) || 'null')
    return v && v.account && v.password ? v : null
  } catch (e) { return null }
}

/** 用保存的凭据重新登录，返回新 token（原站 token 存于 data.userinfo.token） */
const relogin = async () => {
  const cred = readCred()
  if (!cred) return ''
  const r = await axios.post(
    '/api/user/login',
    { account: cred.account, password: cred.password },
    { headers: { 'Content-Type': 'application/json' }, timeout: 15000 }
  )
  const tk = r.data && r.data.data && r.data.data.userinfo && r.data.data.userinfo.token
  if (r.data && r.data.code === 1 && tk) {
    localStorage.setItem('token', tk)
    return tk
  }
  return ''
}

export const http = axios.create({
  baseURL: '/api',
  timeout: 15000,
  headers: { 'Content-Type': 'application/json' }
})

http.interceptors.request.use((config) => {
  // 原站 token 以裸字符串存 localStorage；兼容历史 JSON 字符串格式
  let token = localStorage.getItem('token') || ''
  if (token) {
    try {
      const v = JSON.parse(token)
      if (typeof v === 'string') token = v
    } catch (e) { /* 裸字符串，忽略 */ }
  }
  if (token) {
    config.headers.token = token
    /* 线上预览域名经 CloudStudio 网关转发时会剥掉自定义请求头（token），
       原站 API 同时接受 query 传 token，因此双通道下发，避免线上 401。*/
    config.params = { ...(config.params || {}), token }
  }
  return config
})

/* 并发 401 时只发起一次重登录 */
let refreshing = null

/**
 * 登录失效统一处理。
 * 原站实测：token 失效时网关直接返回 **HTTP 状态码 401**（body 也是 {code:401}），
 * axios 会进入错误拦截器，因此成功/错误两条路径都要拦截。
 * 处理策略：用本机保存的凭据静默重新登录，拿到新 token 后重放原请求（用户无感）；
 * 无凭据或续登失败才清 token 回登录页。
 */
async function handleUnauthorized(cfg) {
  if (cfg && cfg._retried) return Promise.reject({ code: 401, msg: '登录已失效' })
  try {
    refreshing = refreshing || relogin()
    const tk = await refreshing
    refreshing = null
    if (tk) {
      cfg._retried = true
      cfg.headers = cfg.headers || {}
      cfg.headers.token = tk
      cfg.params = { ...(cfg.params || {}), token: tk }
      return http.request(cfg)
    }
  } catch (e) { refreshing = null }
  /* 无法续期：清 token 回登录页（原站未登录访问需登录页的行为） */
  localStorage.removeItem('token')
  if (!location.hash.includes('/pages/login/')) {
    location.hash = '#/pages/login/login'
  }
  return Promise.reject({ code: 401, msg: '登录已失效' })
}

http.interceptors.response.use(
  (res) => {
    const d = res.data
    if (d && d.code === 1) return d
    /* body 形式 401（少数接口 HTTP 仍为 200）也按登录失效处理 */
    if (d && d.code === 401) return handleUnauthorized(res.config)
    return Promise.reject(d)
  },
  (err) => {
    /* HTTP 401（原站网关对失效 token 直接返回 401 状态码）-> 静默续登并重放 */
    const status = err && err.response && err.response.status
    if (status === 401 && err.config && !err.config._retried) {
      return handleUnauthorized(err.config)
    }
    return Promise.reject(err)
  }
)

/**
 * 拼接 OSS 图片地址：原站返回相对路径，转成本项目静态资源 /static/oss/<OSS路径>。
 * 图片由 tools/fetch-oss-images.sh 从原站 img.gandart.shop 下载后填充到 public/static/oss/，
 * 随构建一起进 dist，手机端直接静态加载，无代理环节、无格式兼容问题。
 * 未预下载的图片由 server.cjs 兜底（从原站下载后落盘）。
 */
export const oss = (p) => {
  if (!p) return ''
  let path = String(p)
  if (/^https?:/i.test(path)) path = path.replace(/^https?:\/\/img\.gandart\.shop/i, '')
  /* 剥离 CDN 处理参数（?image_process=resize,l_350 等）。
     原站 img.gandart.shop 走阿里云 OSS 图片处理，裁剪结果由 CDN 生成；
     本地静态服务器不识别该query，带上会导致 404（图裂）。
     缩放由各处 CSS 的 object-fit + 固定尺寸实现。 */
  path = path.split('?')[0]
  if (!path.startsWith('/')) path = '/' + path
  /* 落盘时 PNG 无透明通道的图已转为 .jpg，按映射表取实际文件名 */
  const mapped = OSS_MAP[path]
  return '/static/oss' + (mapped || path)
}
