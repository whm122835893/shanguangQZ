import { httpGet, httpPut } from '@/utils/request'

/** 系统配置（契约 §8.14，10 接口：5 域 × 读/写） */
export const getPaymentConfig = () => httpGet<Record<string, unknown>>('/system/payment')
export const updatePaymentConfig = (data: Record<string, unknown>) => httpPut<{ success: boolean }>('/system/payment', data)
export const getSecurityConfig = () => httpGet<Record<string, unknown>>('/system/security')
export const updateSecurityConfig = (data: Record<string, unknown>) => httpPut<{ success: boolean }>('/system/security', data)
export const getCorsConfig = () => httpGet<Record<string, unknown>>('/system/cors')
export const updateCorsConfig = (data: Record<string, unknown>) => httpPut<{ success: boolean }>('/system/cors', data)
export const getSmsOssConfig = () => httpGet<Record<string, unknown>>('/system/sms-oss')
export const updateSmsOssConfig = (data: Record<string, unknown>) => httpPut<{ success: boolean }>('/system/sms-oss', data)
export const getGlobalConfig = () => httpGet<Record<string, unknown>>('/system/global')
export const updateGlobalConfig = (data: Record<string, unknown>) => httpPut<{ success: boolean }>('/system/global', data)
