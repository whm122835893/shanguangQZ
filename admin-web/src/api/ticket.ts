import { httpGet, httpPost, httpPage } from '@/utils/request'

/** 客服工单（契约 §8.17，8 接口） */
export const listTickets = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/tickets', params)
export const listFeedbacks = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/tickets/feedbacks', params)
export const handleFeedback = (id: number, data: Record<string, unknown>) => httpPost(`/tickets/feedbacks/${id}`, data)
export const ticketDetail = (id: number) => httpGet<Record<string, unknown>>(`/tickets/${id}`)
export const assignTicket = (id: number, assignee_id: number) => httpPost(`/tickets/${id}/assign`, { assignee_id })
export const replyTicket = (id: number, content: string) => httpPost(`/tickets/${id}/reply`, { content })
export const updateTicketStatus = (id: number, status: number, note?: string) =>
  httpPost(`/tickets/${id}/status`, { status, note })
export const compensateTicket = (id: number, amount: string, reason: string, password: string) =>
  httpPost(`/tickets/${id}/compensate`, { amount, reason, password })
