import { httpGet, httpPost, httpPut, httpDelete, httpPage } from '@/utils/request'

/** CMS 内容管理（契约 §8.13，18 接口） */

// ---- Banner（5）----
export const listBanners = (params?: Record<string, unknown>) => httpPage<Record<string, unknown>>('/cms/banners', params)
export const createBanner = (data: Record<string, unknown>) => httpPost<{ id: number }>('/cms/banners', data)
export const updateBanner = (id: number, data: Record<string, unknown>) => httpPut(`/cms/banners/${id}`, data)
export const deleteBanner = (id: number) => httpDelete(`/cms/banners/${id}`)
export const bannerDetail = (id: number) => httpGet<Record<string, unknown>>(`/cms/banners/${id}`)

// ---- 公告（5）----
export const listAnnouncements = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/cms/announcements', params)
export const createAnnouncement = (data: Record<string, unknown>) => httpPost<{ id: number }>('/cms/announcements', data)
export const updateAnnouncement = (id: number, data: Record<string, unknown>) => httpPut(`/cms/announcements/${id}`, data)
export const deleteAnnouncement = (id: number) => httpDelete(`/cms/announcements/${id}`)
export const announcementDetail = (id: number) => httpGet<Record<string, unknown>>(`/cms/announcements/${id}`)

// ---- 协议（2）----
export const listAgreements = () => httpGet<{ list: Record<string, unknown>[] }>('/cms/agreements')
export const updateAgreement = (id: number, data: Record<string, unknown>) => httpPut(`/cms/agreements/${id}`, data)

// ---- 文物展览（4）----
export const listArtifacts = (params?: Record<string, unknown>) => httpPage<Record<string, unknown>>('/cms/artifacts', params)
export const createArtifact = (data: Record<string, unknown>) => httpPost<{ id: number }>('/cms/artifacts', data)
export const updateArtifact = (id: number, data: Record<string, unknown>) => httpPut(`/cms/artifacts/${id}`, data)
export const deleteArtifact = (id: number) => httpDelete(`/cms/artifacts/${id}`)

// ---- 网页装修（2）----
export const getDecoration = () => httpGet<Record<string, unknown>>('/cms/decoration')
export const updateDecoration = (data: Record<string, unknown>) => httpPut<{ success: boolean }>('/cms/decoration', data)
