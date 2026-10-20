import { httpGet, httpPost, httpPut, httpDelete, httpPage } from '@/utils/request'

/** 藏品管理（契约 §8.5，18 接口） */
export interface CollectibleRow {
  id: number
  name: string
  cover: string
  edition: number
  price: string
  sale_status: number
  stock_pool: number
  sold_count: number
  is_resaleable: number
  is_qualification_enabled: number
}

export const listCollectibles = (params: Record<string, unknown>) => httpPage<CollectibleRow>('/collectibles', params)
export const createCollectible = (data: Record<string, unknown>) => httpPost<{ id: number }>('/collectibles', data)
export const collectibleDetail = (id: number) => httpGet<Record<string, unknown>>(`/collectibles/${id}`)
export const editCollectible = (id: number, data: Record<string, unknown>) => httpPut(`/collectibles/${id}`, data)
export const deleteCollectible = (id: number, password: string) =>
  httpDelete(`/collectibles/${id}`, { password } as unknown as Record<string, unknown>)
export const releaseCollectible = (id: number, data: Record<string, unknown>) =>
  httpPost(`/collectibles/${id}/release`, data)
export const configQuotas = (id: number, quotas: unknown[]) => httpPost(`/collectibles/${id}/quotas`, { quotas })
export const updateQuota = (id: number, quotaId: number, data: Record<string, unknown>) =>
  httpPut(`/collectibles/${id}/quotas/${quotaId}`, data)
export const relistCollectible = (id: number, data: Record<string, unknown>) =>
  httpPost(`/collectibles/${id}/relist`, data)
export const forceSoldout = (id: number, reason: string, password: string) =>
  httpPost(`/collectibles/${id}/force-soldout`, { reason, password })
export const destroyStock = (id: number, quantity: number, reason: string, password: string) =>
  httpPost(`/collectibles/${id}/destroy`, { quantity, reason, password })
export const airdropCollectible = (id: number, quantity: number, phones: string[], password: string) =>
  httpPost<{ task_id: number; success_count: number; fail_count: number }>(`/collectibles/${id}/airdrop`, {
    quantity, phones, password,
  })
export const resaleToggle = (id: number, is_resaleable: number, reason: string, password: string) =>
  httpPost(`/collectibles/${id}/resale-toggle`, { is_resaleable, reason, password })
export const priceControl = (id: number, data: Record<string, unknown>) =>
  httpPost(`/collectibles/${id}/price-control`, data)
export const configQualification = (id: number, data: Record<string, unknown>) =>
  httpPost(`/collectibles/${id}/qualification`, data)
export const collectibleAudit = (id: number) => httpGet<Record<string, unknown>>(`/collectibles/${id}/audit`)
export const airdropRecords = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/collectibles/${id}/airdrop-records`, params)
export const destroyRecords = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/collectibles/${id}/destroy-records`, params)
