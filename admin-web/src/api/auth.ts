import { httpGet, httpPost, httpPut } from '@/utils/request'
import type { AdminMe, LoginResult } from '@/types/api'

/** 认证模块（契约 §8.1，7 接口） */
export const loginApi = (data: { username: string; password: string; totp_code?: string }) =>
  httpPost<LoginResult>('/auth/login', data)

export const logoutApi = () => httpPost<{ success: boolean }>('/auth/logout')

export const refreshApi = (refresh_token: string) =>
  httpPost<{ token: string; refresh_token: string; expires_in: number }>('/auth/refresh', { refresh_token })

export const getMe = () => httpGet<AdminMe>('/auth/me')

export const changePassword = (data: { old_password: string; new_password: string }) =>
  httpPut<{ success: boolean }>('/auth/password', data)

export const twofaSetup = () => httpPost<{ secret: string; qr_code_url: string }>('/auth/2fa/setup')

export const twofaVerify = (code: string) => httpPost<{ success: boolean }>('/auth/2fa/verify', { code })
