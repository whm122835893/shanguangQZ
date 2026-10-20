import { httpGet, httpPost, httpPut, httpDelete, httpPage } from '@/utils/request'

/** 用户管理（契约 §8.3，15 接口） */
export interface UserRow {
  id: number
  nickname: string
  mobile: string
  money: string
  status: string
  jointime: string
}

export const listUsers = (params: Record<string, unknown>) => httpPage<UserRow>('/users', params)
export const userDetail = (id: number) => httpGet<Record<string, unknown>>(`/users/${id}`)
export const freezeUser = (id: number, reason: string) => httpPost(`/users/${id}/freeze`, { reason })
export const unfreezeUser = (id: number, reason: string) => httpPost(`/users/${id}/unfreeze`, { reason })
export const resetTxPassword = (id: number, password: string, reason: string) =>
  httpPost(`/users/${id}/reset-tx-password`, { password, reason })
export const forceLogoutUser = (id: number, password: string, reason: string) =>
  httpPost(`/users/${id}/force-logout`, { password, reason })
export const addBlacklist = (id: number, reason: string, password: string) =>
  httpPost(`/users/${id}/blacklist`, { reason, password })
export const removeBlacklist = (id: number, reason: string, password: string) =>
  httpDelete(`/users/${id}/blacklist`, { reason, password } as unknown as Record<string, unknown>)
export const userWallet = (id: number) => httpGet<Record<string, unknown>>(`/users/${id}/wallet`)
export const userCollectibles = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/users/${id}/collectibles`, params)
export const userBlindboxes = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/users/${id}/blindboxes`, params)
export const userPriorityQualifications = (id: number) =>
  httpGet<Record<string, unknown>>(`/users/${id}/priority-qualifications`)
export const userInvites = (id: number) => httpGet<Record<string, unknown>>(`/users/${id}/invites`)
export const recoverCollectible = (id: number, data: { user_collectible_id: number; reason: string; password: string }) =>
  httpPost(`/users/${id}/recover-collectible`, data)
export const recoverBlindbox = (id: number, data: { user_blindbox_id: number; reason: string; password: string }) =>
  httpPost(`/users/${id}/recover-blindbox`, data)
export const userFull = (id: number, password: string) => httpPost(`/users/${id}/full`, { password })
export const userAuditLogs = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/users/${id}/audit-logs`, params)
export const updateUserStatus = (id: number, status: string, reason: string) =>
  httpPut(`/users/${id}`, { status, reason })
