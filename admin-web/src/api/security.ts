import { httpGet, httpPost, httpPut, httpPage } from '@/utils/request'

/** 风控安全（契约 §8.16，15 接口） */

// ---- 黑名单（4）----
export const listSecurityBlacklist = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/security/blacklist', params)
export const addToBlacklist = (data: Record<string, unknown>) => httpPost<{ id: number }>('/security/blacklist', data)
export const updateBlacklistEntry = (id: number, data: Record<string, unknown>) =>
  httpPut(`/security/blacklist/${id}`, data)
export const removeFromBlacklist = (id: number, reason: string, password: string) =>
  httpPost(`/security/blacklist/${id}/remove`, { reason, password })

// ---- 风控告警（3）----
export const listRiskAlerts = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/security/risk-alerts', params)
export const riskAlertDetail = (id: number) => httpGet<Record<string, unknown>>(`/security/risk-alerts/${id}`)
export const handleRiskAlert = (id: number, data: Record<string, unknown>) =>
  httpPut(`/security/risk-alerts/${id}`, data)

// ---- 安全事件（3）----
export const listSecurityEvents = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/security/events', params)
export const securityEventDetail = (id: number) => httpGet<Record<string, unknown>>(`/security/events/${id}`)
export const handleSecurityEvent = (id: number, data: Record<string, unknown>) =>
  httpPut(`/security/events/${id}`, data)

// ---- 交易密码锁定（2）----
export const listTxLocks = (params?: Record<string, unknown>) => httpPage<Record<string, unknown>>('/security/tx-locks', params)
export const unlockTxLock = (id: number, reason: string, password: string) =>
  httpPost(`/security/tx-locks/${id}/unlock`, { reason, password })

// ---- 敏感操作审批（3）----
export const listApprovals = (params?: Record<string, unknown>) => httpPage<Record<string, unknown>>('/security/approvals', params)
export const approvalDetail = (id: number) => httpGet<Record<string, unknown>>(`/security/approvals/${id}`)
export const approveOrReject = (id: number, action: 'approve' | 'reject', comment: string, password: string) =>
  httpPost(`/security/approvals/${id}/${action}`, { comment, password })
