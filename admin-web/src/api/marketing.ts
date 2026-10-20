import { httpGet, httpPost, httpPut, httpDelete, httpPage } from '@/utils/request'

/** 营销活动（契约 §8.11，35 接口：优先购/签到/邀请/抽奖/合成/空投/注册福利/盲盒数据） */

// ---- 优先购（10）----
export const listPriority = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/marketing/priority', params)
export const createPriority = (data: Record<string, unknown>) => httpPost<{ id: number }>('/marketing/priority', data)
export const priorityDetail = (id: number) => httpGet<Record<string, unknown>>(`/marketing/priority/${id}`)
export const updatePriority = (id: number, data: Record<string, unknown>) => httpPut(`/marketing/priority/${id}`, data)
export const listWhitelist = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/marketing/priority/${id}/whitelist`, params)
export const addWhitelist = (id: number, data: Record<string, unknown>) =>
  httpPost(`/marketing/priority/${id}/whitelist`, data)
export const batchWhitelist = (id: number, items: unknown[]) =>
  httpPost<{ success_count: number; fail_count: number; fail_list: unknown[] }>(
    `/marketing/priority/${id}/whitelist/batch`, { items },
  )
export const updateWhitelist = (id: number, wid: number, data: Record<string, unknown>) =>
  httpPut(`/marketing/priority/${id}/whitelist/${wid}`, data)
export const deleteWhitelist = (id: number, wid: number) =>
  httpDelete(`/marketing/priority/${id}/whitelist/${wid}`)
export const cleanupPriority = (password: string) =>
  httpPost<{ cleaned_count: number }>('/marketing/priority/cleanup', { password })

// ---- 签到（3）----
export const getCheckinConfig = () => httpGet<Record<string, unknown>>('/marketing/checkin/config')
export const updateCheckinConfig = (data: Record<string, unknown>) =>
  httpPut<{ success: boolean }>('/marketing/checkin/config', data)
export const listCheckinRecords = (params: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/marketing/checkin/records', params)

// ---- 邀请（3）----
export const getInviteConfig = () => httpGet<Record<string, unknown>>('/marketing/invite/config')
export const updateInviteConfig = (data: Record<string, unknown>) =>
  httpPut<{ success: boolean }>('/marketing/invite/config', data)
export const listInviteRecords = (params: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/marketing/invite/records', params)

// ---- 抽奖（9）----
export const listLuckyDraw = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/marketing/lucky-draw', params)
export const createLuckyDraw = (data: Record<string, unknown>) => httpPost<{ id: number }>('/marketing/lucky-draw', data)
export const luckyDrawDetail = (id: number) => httpGet<Record<string, unknown>>(`/marketing/lucky-draw/${id}`)
export const updateLuckyDraw = (id: number, data: Record<string, unknown>) => httpPut(`/marketing/lucky-draw/${id}`, data)
export const listPrizes = (id: number) => httpGet<{ list: Record<string, unknown>[] }>(`/marketing/lucky-draw/${id}/prizes`)
export const createPrize = (id: number, data: Record<string, unknown>) =>
  httpPost<{ id: number }>(`/marketing/lucky-draw/${id}/prizes`, data)
export const updatePrize = (id: number, pid: number, data: Record<string, unknown>) =>
  httpPut(`/marketing/lucky-draw/${id}/prizes/${pid}`, data)
export const deletePrize = (id: number, pid: number) => httpDelete(`/marketing/lucky-draw/${id}/prizes/${pid}`)
export const listDrawRecords = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/marketing/lucky-draw/${id}/records`, params)

// ---- 合成（5）----
export const listSynthesis = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/marketing/synthesis', params)
export const createSynthesis = (data: Record<string, unknown>) => httpPost<{ id: number }>('/marketing/synthesis', data)
export const synthesisDetail = (id: number) => httpGet<Record<string, unknown>>(`/marketing/synthesis/${id}`)
export const updateSynthesis = (id: number, data: Record<string, unknown>) => httpPut(`/marketing/synthesis/${id}`, data)
export const listSynthesisRecords = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/marketing/synthesis/${id}/records`, params)

// ---- 活动空投（2）----
export const listAirdropTasks = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/marketing/airdrop', params)
export const listAirdropRecords = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/marketing/airdrop/records', params)

// ---- 注册福利（2）----
export const getRegisterReward = () => httpGet<Record<string, unknown>>('/marketing/register/reward')
export const updateRegisterReward = (data: Record<string, unknown>) =>
  httpPut<{ success: boolean }>('/marketing/register/reward', data)

// ---- 盲盒活动数据（1）----
export const blindboxActivityStats = (params?: Record<string, unknown>) =>
  httpGet<Record<string, unknown>>('/marketing/blindbox/stats', params)
