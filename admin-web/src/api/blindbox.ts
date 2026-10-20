import { httpGet, httpPost, httpPut, httpDelete, httpPage } from '@/utils/request'

/** 盲盒管理（契约 §8.6，17 接口） */
export const listBlindboxes = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/blindboxes', params)
export const createBlindbox = (data: Record<string, unknown>) => httpPost<{ id: number }>('/blindboxes', data)
export const blindboxDetail = (id: number) => httpGet<Record<string, unknown>>(`/blindboxes/${id}`)
export const editBlindbox = (id: number, data: Record<string, unknown>) => httpPut(`/blindboxes/${id}`, data)
export const deleteBlindbox = (id: number, password: string) =>
  httpDelete(`/blindboxes/${id}`, { password } as unknown as Record<string, unknown>)
export const configItems = (id: number, items: unknown[]) => httpPost(`/blindboxes/${id}/items`, { items })
export const updateItem = (id: number, itemId: number, data: Record<string, unknown>) =>
  httpPut(`/blindboxes/${id}/items/${itemId}`, data)
export const deleteItem = (id: number, itemId: number) => httpDelete(`/blindboxes/${id}/items/${itemId}`)
export const releaseBlindbox = (id: number, data: Record<string, unknown>) => httpPost(`/blindboxes/${id}/release`, data)
export const relistBlindbox = (id: number, data: Record<string, unknown>) => httpPost(`/blindboxes/${id}/relist`, data)
export const forceSoldoutBlindbox = (id: number, reason: string, password: string) =>
  httpPost(`/blindboxes/${id}/force-soldout`, { reason, password })
export const destroyBlindbox = (id: number, quantity: number, reason: string, password: string) =>
  httpPost(`/blindboxes/${id}/destroy`, { quantity, reason, password })
export const airdropBlindbox = (id: number, quantity: number, phones: string[], password: string) =>
  httpPost<{ task_id: number; success_count: number; fail_count: number }>(`/blindboxes/${id}/airdrop`, {
    quantity, phones, password,
  })
export const recoverBlindbox = (id: number, userBlindboxId: number, reason: string, password: string) =>
  httpPost(`/blindboxes/${id}/recover`, { user_blindbox_id: userBlindboxId, reason, password })
export const openRecords = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/blindboxes/${id}/open-records`, params)
export const blindboxAudit = (id: number) => httpGet<Record<string, unknown>>(`/blindboxes/${id}/audit`)
export const blindboxDestroyRecords = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/blindboxes/${id}/destroy-records`, params)
