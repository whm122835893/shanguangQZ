import { httpGet, httpPost, httpPut, httpDelete, httpPage } from '@/utils/request'

/** 权限审计（契约 §8.15，15 接口） */

// ---- 管理员（7）----
export const listAdmins = (params?: Record<string, unknown>) => httpPage<Record<string, unknown>>('/permission/admins', params)
export const createAdmin = (data: Record<string, unknown>) => httpPost<{ id: number }>('/permission/admins', data)
export const updateAdmin = (id: number, data: Record<string, unknown>) => httpPut(`/permission/admins/${id}`, data)
export const deleteAdmin = (id: number, password: string) =>
  httpDelete(`/permission/admins/${id}`, { password } as unknown as Record<string, unknown>)
export const resetAdminPassword = (id: number, new_password: string, password: string) =>
  httpPost(`/permission/admins/${id}/reset-password`, { new_password, password })
export const enableAdmin = (id: number, status: number, password: string) =>
  httpPut(`/permission/admins/${id}`, { status, password })
export const adminDetail = (id: number) => httpGet<Record<string, unknown>>(`/permission/admins/${id}`)

// ---- 角色（4）----
export const listRoles = () => httpGet<{ list: Record<string, unknown>[] }>('/permission/roles')
export const createRole = (data: Record<string, unknown>) => httpPost<{ id: number }>('/permission/roles', data)
export const updateRole = (id: number, data: Record<string, unknown>) => httpPut(`/permission/roles/${id}`, data)
export const deleteRole = (id: number, password: string) =>
  httpDelete(`/permission/roles/${id}`, { password } as unknown as Record<string, unknown>)

// ---- 权限树（1）----
export const listPermissions = () => httpGet<{ list: Record<string, unknown>[] }>('/permission/permissions')

// ---- 日志（3）----
export const listOperationLogs = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/permission/operation-logs', params)
export const exportOperationLogs = () => httpGet<Record<string, unknown>>('/permission/operation-logs/export')
export const operationLogDetail = (id: number) => httpGet<Record<string, unknown>>(`/permission/operation-logs/${id}`)
export const listLoginLogs = (params?: Record<string, unknown>) =>
  httpPage<Record<string, unknown>>('/permission/login-logs', params)
