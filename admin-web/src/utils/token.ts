/** Token 存取（localStorage；XSS 场景配合后端短期 JWT + refresh 轮换） */

const KEY_TOKEN = 'admin_token'
const KEY_REFRESH = 'admin_refresh_token'

export function getToken(): string {
  return localStorage.getItem(KEY_TOKEN) ?? ''
}

export function setToken(token: string): void {
  localStorage.setItem(KEY_TOKEN, token)
}

export function getRefreshToken(): string {
  return localStorage.getItem(KEY_REFRESH) ?? ''
}

export function setRefreshToken(token: string): void {
  localStorage.setItem(KEY_REFRESH, token)
}

export function clearTokens(): void {
  localStorage.removeItem(KEY_TOKEN)
  localStorage.removeItem(KEY_REFRESH)
}
