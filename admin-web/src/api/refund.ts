import { httpGet, httpPost, httpPage } from '@/utils/request'

/** 退款管理（契约 §8.8，4 接口） */
export const listRefunds = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/refunds', params)
export const refundDetail = (id: number) => httpGet<Record<string, unknown>>(`/refunds/${id}`)
export const approveRefund = (id: number, comment: string, password: string) =>
  httpPost(`/refunds/${id}/approve`, { comment, password })
export const rejectRefund = (id: number, comment: string, password: string) =>
  httpPost(`/refunds/${id}/reject`, { comment, password })
