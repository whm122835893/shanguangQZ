/** 全局共享类型（契约 §1.2 / §12） */

/** 管理端 v1 统一响应信封：{ code, data, message }，code===200 成功 */
export interface ApiEnvelope<T = unknown> {
  code: number
  data: T
  message: string
}

/** 分页信封（契约 §1.2：page/page_size 参数，list+total 响应） */
export interface PageData<T> {
  list: T[]
  total: number
  page: number
  page_size: number
}

/** 登录响应（AuthController::login 契约） */
export interface LoginResult {
  token: string
  refresh_token: string
  expires_in: number
  admin: AdminProfile
}

export interface AdminProfile {
  id: number
  username: string
  real_name: string
  /** 1 超级管理员 2 运营 3 客服 4 财务 5 审计 */
  role: number
  avatar: string
}

/** /auth/me 响应 */
export interface AdminMe extends AdminProfile {
  phone: string
  email: string
  twofa_enabled: boolean
  permissions: string[]
}

/** 五角色（与后端 Guard 对齐） */
export const ROLE_NAMES: Record<number, string> = {
  1: '超级管理员',
  2: '运营',
  3: '客服',
  4: '财务',
  5: '审计',
}

/** 菜单/路由元信息 */
export interface RouteMeta {
  title: string
  icon?: string
  /** 所需权限码；超级管理员直通 */
  permission?: string
  hidden?: boolean
  /** 高风险页面标记（顶部提示条） */
  danger?: boolean
  /** 标签页是否可关闭（首页不可关） */
  affix?: boolean
  activeMenu?: string
}
