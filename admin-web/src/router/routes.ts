import type { RouteRecordRaw } from 'vue-router'
import MainLayout from '@/layouts/MainLayout.vue'

/** 静态路由：登录、404、布局壳（动态子路由由 permission store 注入） */
export const staticRoutes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/login/Index.vue'),
    meta: { title: '登录', hidden: true },
  },
  {
    path: '/',
    component: MainLayout,
    redirect: '/dashboard',
    children: [], // 动态注入
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/error/NotFound.vue'),
    meta: { title: '404', hidden: true },
  },
]

/** 主布局路由引用（dynamic-routes 挂 children 用） */
export const layoutRoute = staticRoutes[1]
