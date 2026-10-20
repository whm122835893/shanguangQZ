import { httpPost, httpPage } from '@/utils/request'

/** 平台运维（契约 §8.19，4 接口；清库四重确认 + 自动备份） */
export const cleanupDatabase = (password: string, confirm_text: string, reason: string) =>
  httpPost<{ backup_id: number }>('/platform/cleanup', { password, confirm_text, reason })
export const listCleanupLogs = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/platform/cleanup-logs', params)
export const listBackups = (params?: Record<string, unknown>) => httpPage<Record<string, unknown>>('/platform/backups', params)
export const restoreBackup = (id: number, password: string) =>
  httpPost<{ success: boolean }>(`/platform/backups/${id}/restore`, { password })
