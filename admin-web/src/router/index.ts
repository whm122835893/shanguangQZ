import { createRouter, createWebHistory } from 'vue-router'
import { staticRoutes } from './routes'
import { useAdminStore } from '@/store/admin'
import { usePermissionStore } from '@/store/permission'
import { getToken } from '@/utils/token'

const router = createRouter({
  history: createWebHistory(),
  routes: staticRoutes,
  scrollBehavior: () => ({ top: 0 }),
})

/** 全局守卫：未登录 → /login；已登录未生成动态路由 → 拉取 /auth/me 后注入 */
router.beforeEach(async (to) => {
  const token = getToken()
  if (to.path === '/login') {
    if (token) return { path: '/' }
    return true
  }
  if (!token) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  const adminStore = useAdminStore()
  const permissionStore = usePermissionStore()

  if (!permissionStore.generated) {
    try {
      if (!adminStore.loaded) await adminStore.fetchProfile()
      const routes = permissionStore.generate()
      // 已注册的布局路由不能改 children，必须逐条 addRoute 动态注入
      for (const r of routes) router.addRoute('/', r)
      // replace 保证动态路由立即生效
      return { path: to.path, query: to.query, hash: to.hash, replace: true }
    } catch {
      return { path: '/login', query: { redirect: to.fullPath } }
    }
  }
  return true
})

export default router
