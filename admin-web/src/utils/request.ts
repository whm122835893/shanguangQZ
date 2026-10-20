import axios, { AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'
import { ElMessage } from 'element-plus'
import { getToken, getRefreshToken, setToken, setRefreshToken, clearTokens } from './token'
import router from '@/router'
import type { ApiEnvelope, PageData } from '@/types/api'

/** 契约 §12：BaseURL 环境变量配置；JWT 注入；401 跳登录 / 403 弹无权限 / 409 提示刷新 */
const service: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_BASE_API,
  timeout: 30_000,
})

/* ---------- 请求拦截：注入 JWT ---------- */
service.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

/* ---------- 刷新锁：并发 401 只触发一次 refresh ---------- */
let refreshing: Promise<boolean> | null = null

async function tryRefresh(): Promise<boolean> {
  const refreshToken = getRefreshToken()
  if (!refreshToken) return false
  try {
    const resp = await axios.post<ApiEnvelope<{ token: string; refresh_token: string; expires_in: number }>>(
      `${import.meta.env.VITE_BASE_API}/auth/refresh`,
      { refresh_token: refreshToken },
      { timeout: 10_000 },
    )
    if (resp.data.code !== 200) return false
    setToken(resp.data.data.token)
    setRefreshToken(resp.data.data.refresh_token)
    return true
  } catch {
    return false
  }
}

function forceLogout(): void {
  clearTokens()
  if (router.currentRoute.value.path !== '/login') {
    void router.push({ path: '/login', query: { redirect: router.currentRoute.value.fullPath } })
  }
}

/* ---------- 响应拦截：双通道（HTTP status + 业务 code） ---------- */
service.interceptors.response.use(
  (response) => {
    const body = response.data as ApiEnvelope
    // 信封 code === 200 成功；失败时 HTTP status 同步落地，一般走 error 分支
    if (body.code !== 200) {
      ElMessage.error(body.message || '请求失败')
      return Promise.reject(new Error(body.message || 'Request failed'))
    }
    return response
  },
  async (error: AxiosError<ApiEnvelope>) => {
    const status = error.response?.status
    const message = error.response?.data?.message || error.message || '网络异常'

    if (status === 401) {
      if (!refreshing) {
        refreshing = tryRefresh().finally(() => {
          refreshing = null
        })
      }
      const ok = await refreshing
      if (ok && error.config) {
        // 重放原请求（更新 Authorization）
        error.config.headers.Authorization = `Bearer ${getToken()}`
        return service.request(error.config)
      }
      forceLogout()
      ElMessage.error('登录已过期，请重新登录')
      return Promise.reject(new Error('unauthorized'))
    }

    if (status === 403) {
      ElMessage.error(message || '没有操作权限')
      return Promise.reject(new Error(message))
    }

    if (status === 409) {
      // 冲突：提示用户刷新后重试
      ElMessage({ type: 'warning', message: `${message}（数据可能已变更，请刷新后重试）`, duration: 4000 })
      return Promise.reject(new Error(message))
    }

    ElMessage.error(message)
    return Promise.reject(new Error(message))
  },
)

/** GET 包装：参数直传，返回 data */
export async function httpGet<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  const resp = await service.get<ApiEnvelope<T>>(url, { params })
  return resp.data.data
}

/** POST / PUT / DELETE 包装 */
export async function httpPost<T>(url: string, data?: Record<string, unknown>): Promise<T> {
  const resp = await service.post<ApiEnvelope<T>>(url, data ?? {})
  return resp.data.data
}

export async function httpPut<T>(url: string, data?: Record<string, unknown>): Promise<T> {
  const resp = await service.put<ApiEnvelope<T>>(url, data ?? {})
  return resp.data.data
}

export async function httpDelete<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  const resp = await service.delete<ApiEnvelope<T>>(url, { params })
  return resp.data.data
}

/** 分页 GET：统一 page/page_size（契约 §12） */
export async function httpPage<T>(
  url: string,
  query?: Record<string, unknown> & { page?: number; page_size?: number },
): Promise<PageData<T>> {
  return (httpGet(url, { page: 1, page_size: 20, ...(query ?? {}) })) as Promise<PageData<T>>
}

/** 导出下载：后端流式 CSV（契约 §12：导出优先后端流式下载） */
export async function downloadFile(url: string, params?: Record<string, unknown>, filename = 'export.csv'): Promise<void> {
  const resp = await service.get(url, { params, responseType: 'blob' })
  const blob = new Blob([resp.data as BlobPart], { type: 'text/csv;charset=utf-8' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  link.click()
  URL.revokeObjectURL(link.href)
}

/** 高风险操作：上传文件用 multipart */
export async function uploadFile<T>(url: string, file: File, extra?: Record<string, unknown>): Promise<T> {
  const form = new FormData()
  form.append('file', file)
  if (extra) {
    for (const [k, v] of Object.entries(extra)) form.append(k, String(v))
  }
  const resp = await service.post<ApiEnvelope<T>>(url, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return resp.data.data
}

export default service
