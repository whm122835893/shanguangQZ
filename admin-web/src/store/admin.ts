import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { AdminMe } from '@/types/api'
import { getMe, logoutApi } from '@/api/auth'
import { clearTokens } from '@/utils/token'

/** 管理员信息 + Token + 权限码 */
export const useAdminStore = defineStore('admin', () => {
  const id = ref(0)
  const username = ref('')
  const realName = ref('')
  const role = ref(0)
  const avatar = ref('')
  const permissions = ref<string[]>([])
  const loaded = ref(false)

  const isSuper = computed(() => role.value === 1)
  const roleName = computed(
    () => ({ 1: '超级管理员', 2: '运营', 3: '客服', 4: '财务', 5: '审计' })[role.value] ?? '未知角色',
  )

  /** 登录成功或刷新页面后拉取 /auth/me */
  async function fetchProfile(): Promise<void> {
    const me: AdminMe = await getMe()
    id.value = me.id
    username.value = me.username
    realName.value = me.real_name
    role.value = me.role
    avatar.value = me.avatar
    permissions.value = me.permissions ?? []
    loaded.value = true
  }

  async function logout(): Promise<void> {
    try {
      await logoutApi()
    } finally {
      clearTokens()
      loaded.value = false
      permissions.value = []
      role.value = 0
    }
  }

  function hasPerm(permission: string | undefined): boolean {
    if (!permission) return true
    if (role.value === 1) return true
    return permissions.value.includes(permission)
  }

  return { id, username, realName, role, avatar, permissions, loaded, isSuper, roleName, fetchProfile, logout, hasPerm }
})
