import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { RouteRecordRaw } from 'vue-router'
import { buildAccessibleRoutes, menuTree } from '@/router/dynamic-routes'

/** 权限路由 + 侧边栏菜单（登录后按 permissions 过滤生成） */
export const usePermissionStore = defineStore('permission', () => {
  const accessRoutes = ref<RouteRecordRaw[]>([])
  const generated = ref(false)

  const menus = computed(() => menuTree(accessRoutes.value))

  function generate(): RouteRecordRaw[] {
    accessRoutes.value = buildAccessibleRoutes()
    generated.value = true
    return accessRoutes.value
  }

  function reset(): void {
    accessRoutes.value = []
    generated.value = false
  }

  return { accessRoutes, generated, menus, generate, reset }
})
