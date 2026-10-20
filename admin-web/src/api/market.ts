import { httpGet, httpPost, httpPut, httpPage } from '@/utils/request'

/** 市场管理（契约 §8.9，7 接口） */
export const listListings = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/market/listings', params)
export const delistListing = (id: number, reason: string, password: string) =>
  httpPost('/market/listings/delist', { id, reason, password })
export const listTrades = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/market/trades', params)
export const listPriceAlerts = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/market/price-alerts', params)
export const getFeeConfig = () =>
  httpGet<{ fee_rate: string; fee_min: string; fee_max: string }>('/market/fee-config')
export const updateFeeConfig = (data: { fee_rate: string; fee_min: string; fee_max: string; password: string }) =>
  httpPut<{ success: boolean }>('/market/fee-config', data)
export const collectibleListings = (id: number, params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>(`/market/collectibles/${id}/listings`, params)
