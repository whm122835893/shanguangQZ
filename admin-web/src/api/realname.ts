import { httpGet, httpPost, httpPage } from '@/utils/request'

/** 实名认证（契约 §8.4，4 接口；列表全脱敏，full 需二次密码 + 审计） */
export interface RealnameRow {
  id: number
  user_id: number
  name: string
  idcard: string
  status: number
  time: string
}

export const listRealnames = (params: Record<string, unknown>) => httpPage<RealnameRow>('/realnames', params)
export const realnameDetail = (id: number) => httpGet<Record<string, unknown>>(`/realnames/${id}`)
export const realnameFull = (id: number, password: string) => httpPost(`/realnames/${id}/full`, { password })
export const realnameAuditLogs = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/realnames/${id}/audit-logs`, params)
