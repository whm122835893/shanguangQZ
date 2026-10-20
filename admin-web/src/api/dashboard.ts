import { httpGet } from '@/utils/request'

/** 仪表盘（契约 §8.2，6 接口） */
export interface Metrics {
  user_total: number
  user_new_today: number
  collection_total: number
  blindbox_total: number
  order_total: number
  order_today: number
  sales_amount: string
  sales_today: string
  market_gmv: string
  pending_refunds: number
  pending_approvals: number
  risk_alerts: number
  open_tickets: number
}

export const getMetrics = () => httpGet<Metrics>('/dashboard/metrics')
export const getFinance = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/dashboard/finance', params)
export const getAlerts = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/dashboard/alerts', params)
export const getActivities = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/dashboard/activities', params)
export const getTrends = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/dashboard/trends', params)
export const getPriorityStats = (params?: Record<string, unknown>) =>
  httpGet<Record<string, unknown>>('/dashboard/priority-stats', params)
