import { httpGet, httpPage, downloadFile } from '@/utils/request'

/** 钱包财务（契约 §8.12，6 接口；资金恒等式以 conservation 为准） */
export const listRecharges = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/wallet/recharges', params)
export const listTransactions = (params: Record<string, unknown>) => httpPage<Record<string, unknown>>('/wallet/transactions', params)
export const feeStats = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/wallet/fee-stats', params)
export const conservation = (params?: Record<string, unknown>) => httpGet<Record<string, unknown>>('/wallet/conservation', params)
export const walletAbnormal = (params?: Record<string, unknown>) => httpPage<Record<string, unknown>>('/wallet/abnormal', params)
export const exportWallet = (params: Record<string, unknown>) => downloadFile('/wallet/export', params, 'wallet-export.csv')
