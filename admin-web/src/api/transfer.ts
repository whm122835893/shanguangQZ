import { httpGet, httpPost, httpPage } from '@/utils/request'

/** 转赠管理（契约 §8.10，6 接口） */
export const listTransfers = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/transfers', params)
export const transferDetail = (id: number) => httpGet<Record<string, unknown>>(`/transfers/${id}`)
export const revokeTransfer = (id: number, reason: string, password: string) =>
  httpPost(`/transfers/${id}/revoke`, { reason, password })
export const cancelTransfer = (id: number, reason: string, password: string) =>
  httpPost(`/transfers/${id}/cancel`, { reason, password })
export const transferStats = (days?: number) =>
  httpGet<{ total_count: number; total_collectibles: number; total_blindboxes: number; daily_stats: unknown[] }>(
    '/transfers/stats', days ? { days } : undefined,
  )
export const abnormalTransfers = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/transfers/abnormal', params)
