/** 权限判断：超管（role=1）直通；其余按 permissions 数组校验（契约 §13.3） */
import { useAdminStore } from '@/store/admin'

export function hasPermission(permission: string | undefined): boolean {
  if (!permission) return true
  const store = useAdminStore()
  if (store.role === 1) return true
  return store.permissions.includes(permission)
}

export function hasAnyPermission(permissions: string[]): boolean {
  return permissions.some((p) => hasPermission(p))
}
