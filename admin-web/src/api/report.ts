import { httpGet, downloadFile } from '@/utils/request'

/** 数据报表（契约 §8.18，6 接口；导出走后端流式下载） */
export const reportSales = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/reports/sales', params)
export const reportUsers = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/reports/users', params)
export const reportCollectibles = (params?: Record<string, unknown>) =>
  httpGet<Record<string, unknown>>('/reports/collectibles', params)
export const reportBlindboxes = (params?: Record<string, unknown>) =>
  httpGet<Record<string, unknown>>('/reports/blindboxes', params)
export const reportFinance = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/reports/finance', params)
export const customExport = (table: string, fields: string[], params?: Record<string, unknown>) =>
  downloadFile('/reports/custom-export', { table, fields, ...params }, `${table}-export.csv`)
