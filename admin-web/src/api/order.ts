import { httpGet, httpPost, httpPage, downloadFile } from '@/utils/request'

/** 订单管理（契约 §8.7，8 接口） */
/** 订单行（对齐后端 OrderController::brief 输出） */
export interface OrderRow {
  id: number
  order_sn: string
  user_id: number
  user_name?: string
  order_type: number
  order_status: number
  is_paid: boolean
  goods_id: number
  goods_name?: string
  num: number
  total_price: string
  fee: string
  pay_channel?: string
  source?: string
  time: string
}

export const listOrders = (params: Record<string, unknown>) => httpPage<OrderRow>('/orders', params)
export const listAbnormalOrders = (params: Record<string, unknown>) => httpPage<OrderRow>('/orders/abnormal', params)
export const exportOrders = (params: Record<string, unknown>) => downloadFile('/orders/export', params, 'orders.csv')
export const orderDetail = (id: number) => httpGet<Record<string, unknown>>(`/orders/${id}`)
export const cancelOrder = (id: number, reason: string, password: string) =>
  httpPost(`/orders/${id}/cancel`, { reason, password })
export const markOrderPaid = (id: number, reason: string, password: string) =>
  httpPost(`/orders/${id}/mark-paid`, { reason, password })
export const refundOrder = (id: number, amount: string, reason: string) =>
  httpPost<{ refund_id: number }>(`/orders/${id}/refund`, { amount, reason })
export const repairOrder = (id: number, repair_type: string, reason: string, password: string) =>
  httpPost(`/orders/${id}/repair`, { repair_type, reason, password })
