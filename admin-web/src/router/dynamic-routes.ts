import type { RouteRecordRaw } from 'vue-router'
import { defineAsyncComponent, type Component } from 'vue'
import { hasPermission } from '@/utils/permission'
import type { RouteMeta } from '@/types/api'

/**
 * 动态权限路由（契约 §9.2 菜单映射）
 * 每条路由声明 permission；buildAccessibleRoutes 按 /auth/me 返回的 permissions 过滤。
 * 超管（role=1）直通全量（utils/permission 已处理）。
 */
interface DynamicRouteDef {
  path: string
  name: string
  component: string
  meta: RouteMeta
}

const defs: DynamicRouteDef[] = [
  // 数据大盘
  { path: '/dashboard', name: 'dashboard', component: 'dashboard/Index', meta: { title: '数据大盘', icon: 'Odometer', permission: 'dashboard:view', affix: true } },
  // 用户管理
  { path: '/user', name: 'user', component: 'user/Index', meta: { title: '用户管理', icon: 'User', permission: 'user:list' } },
  { path: '/user/:id', name: 'user-detail', component: 'user/Detail', meta: { title: '用户详情', hidden: true, permission: 'user:detail', activeMenu: '/user' } },
  // 实名认证
  { path: '/realname', name: 'realname', component: 'realname/Index', meta: { title: '实名认证', icon: 'Postcard', permission: 'realname:list' } },
  // 藏品管理
  { path: '/collectible', name: 'collectible', component: 'collectible/Index', meta: { title: '藏品管理', icon: 'Collection', permission: 'collectible:list' } },
  { path: '/collectible/create', name: 'collectible-create', component: 'collectible/Create', meta: { title: '创建藏品', hidden: true, permission: 'collectible:create', activeMenu: '/collectible' } },
  { path: '/collectible/:id', name: 'collectible-detail', component: 'collectible/Detail', meta: { title: '藏品详情', hidden: true, permission: 'collectible:detail', activeMenu: '/collectible' } },
  { path: '/collectible/:id/edit', name: 'collectible-edit', component: 'collectible/Edit', meta: { title: '编辑藏品', hidden: true, permission: 'collectible:edit', activeMenu: '/collectible' } },
  { path: '/collectible/:id/release', name: 'collectible-release', component: 'collectible/ReleaseConfig', meta: { title: '发售配置', hidden: true, permission: 'collectible:release', activeMenu: '/collectible' } },
  { path: '/collectible/:id/quota', name: 'collectible-quota', component: 'collectible/QuotaConfig', meta: { title: '配额配置', hidden: true, permission: 'collectible:quota', activeMenu: '/collectible' } },
  { path: '/collectible/:id/airdrop', name: 'collectible-airdrop', component: 'collectible/Airdrop', meta: { title: '独立空投', hidden: true, permission: 'collectible:airdrop', activeMenu: '/collectible' } },
  { path: '/collectible/:id/destroy', name: 'collectible-destroy', component: 'collectible/Destroy', meta: { title: '销毁库存', hidden: true, permission: 'collectible:destroy', activeMenu: '/collectible' } },
  { path: '/collectible/:id/audit', name: 'collectible-audit', component: 'collectible/Audit', meta: { title: '库存审计', hidden: true, permission: 'collectible:audit', activeMenu: '/collectible' } },
  // 盲盒管理
  { path: '/blindbox', name: 'blindbox', component: 'blindbox/Index', meta: { title: '盲盒管理', icon: 'Box', permission: 'blindbox:list' } },
  { path: '/blindbox/create', name: 'blindbox-create', component: 'blindbox/Create', meta: { title: '创建盲盒', hidden: true, permission: 'blindbox:create', activeMenu: '/blindbox' } },
  { path: '/blindbox/:id', name: 'blindbox-detail', component: 'blindbox/Detail', meta: { title: '盲盒详情', hidden: true, permission: 'blindbox:detail', activeMenu: '/blindbox' } },
  { path: '/blindbox/:id/edit', name: 'blindbox-edit', component: 'blindbox/Edit', meta: { title: '编辑盲盒', hidden: true, permission: 'blindbox:edit', activeMenu: '/blindbox' } },
  { path: '/blindbox/:id/items', name: 'blindbox-items', component: 'blindbox/ItemConfig', meta: { title: '子藏品配置', hidden: true, permission: 'blindbox:config', activeMenu: '/blindbox' } },
  { path: '/blindbox/:id/release', name: 'blindbox-release', component: 'blindbox/ReleaseConfig', meta: { title: '发售配置', hidden: true, permission: 'blindbox:release', activeMenu: '/blindbox' } },
  { path: '/blindbox/:id/airdrop', name: 'blindbox-airdrop', component: 'blindbox/Airdrop', meta: { title: '独立空投', hidden: true, permission: 'blindbox:airdrop', activeMenu: '/blindbox' } },
  { path: '/blindbox/:id/destroy', name: 'blindbox-destroy', component: 'blindbox/Destroy', meta: { title: '销毁盲盒', hidden: true, permission: 'blindbox:destroy', activeMenu: '/blindbox' } },
  { path: '/blindbox/:id/audit', name: 'blindbox-audit', component: 'blindbox/Audit', meta: { title: '库存审计', hidden: true, permission: 'blindbox:audit', activeMenu: '/blindbox' } },
  // 订单管理
  { path: '/order', name: 'order', component: 'order/Index', meta: { title: '订单管理', icon: 'Tickets', permission: 'order:list' } },
  { path: '/order/:id', name: 'order-detail', component: 'order/Detail', meta: { title: '订单详情', hidden: true, permission: 'order:detail', activeMenu: '/order' } },
  { path: '/order/abnormal', name: 'order-abnormal', component: 'order/Abnormal', meta: { title: '异常订单', permission: 'order:audit' } },
  // 退款管理
  { path: '/refund', name: 'refund', component: 'refund/Index', meta: { title: '退款管理', icon: 'Money', permission: 'refund:list' } },
  // 市场寄售
  { path: '/market', name: 'market', component: 'market/Index', meta: { title: '寄售监控', icon: 'ShoppingBag', permission: 'market:list' } },
  { path: '/market/trades', name: 'market-trades', component: 'market/TradeRecords', meta: { title: '成交记录', permission: 'market:list' } },
  { path: '/market/alerts', name: 'market-alerts', component: 'market/PriceAlerts', meta: { title: '价格预警', permission: 'market:monitor' } },
  { path: '/market/fee', name: 'market-fee', component: 'market/FeeConfig', meta: { title: '手续费配置', permission: 'market:config' } },
  // 转赠管理
  { path: '/transfer', name: 'transfer', component: 'transfer/Index', meta: { title: '转赠记录', icon: 'Share', permission: 'transfer:list' } },
  { path: '/transfer/stats', name: 'transfer-stats', component: 'transfer/Stats', meta: { title: '转赠统计', permission: 'transfer:stats' } },
  // 营销活动
  { path: '/marketing/priority', name: 'marketing-priority', component: 'marketing/priority/Index', meta: { title: '优先购', icon: 'AlarmClock', permission: 'marketing:priority:list' } },
  { path: '/marketing/priority/:id/whitelist', name: 'marketing-priority-whitelist', component: 'marketing/priority/Whitelist', meta: { title: '白名单', hidden: true, permission: 'marketing:priority:whitelist', activeMenu: '/marketing/priority' } },
  { path: '/marketing/checkin', name: 'marketing-checkin', component: 'marketing/checkin/Config', meta: { title: '签到管理', icon: 'Calendar', permission: 'marketing:checkin:config' } },
  { path: '/marketing/invite', name: 'marketing-invite', component: 'marketing/invite/Config', meta: { title: '邀请管理', icon: 'User', permission: 'marketing:invite:config' } },
  { path: '/marketing/lucky-draw', name: 'marketing-lucky-draw', component: 'marketing/lucky-draw/Index', meta: { title: '抽奖活动', icon: 'Trophy', permission: 'marketing:lucky:list' } },
  { path: '/marketing/lucky-draw/:id/prizes', name: 'marketing-lucky-draw-prizes', component: 'marketing/lucky-draw/Prizes', meta: { title: '奖项池', hidden: true, permission: 'marketing:lucky:prize', activeMenu: '/marketing/lucky-draw' } },
  { path: '/marketing/synthesis', name: 'marketing-synthesis', component: 'marketing/synthesis/Index', meta: { title: '合成活动', icon: 'Connection', permission: 'marketing:synthesis:list' } },
  { path: '/marketing/airdrop', name: 'marketing-airdrop', component: 'marketing/airdrop/Index', meta: { title: '活动空投', icon: 'Promotion', permission: 'marketing:airdrop' } },
  { path: '/marketing/register', name: 'marketing-register', component: 'marketing/register/Config', meta: { title: '注册福利', icon: 'Present', permission: 'marketing:register:config' } },
  { path: '/marketing/blindbox', name: 'marketing-blindbox', component: 'marketing/blindbox/Stats', meta: { title: '盲盒活动数据', icon: 'Box', permission: 'marketing:blindbox:stats' } },
  // 钱包财务
  { path: '/wallet/recharge', name: 'wallet-recharge', component: 'wallet/Recharge', meta: { title: '充值记录', icon: 'Wallet', permission: 'wallet:recharge' } },
  { path: '/wallet/transaction', name: 'wallet-transaction', component: 'wallet/Transactions', meta: { title: '资金流水', permission: 'wallet:transaction' } },
  { path: '/wallet/fee', name: 'wallet-fee', component: 'wallet/FeeStats', meta: { title: '手续费统计', permission: 'wallet:fee' } },
  { path: '/wallet/conservation', name: 'wallet-conservation', component: 'wallet/Conservation', meta: { title: '资金守恒', permission: 'wallet:audit' } },
  { path: '/wallet/abnormal', name: 'wallet-abnormal', component: 'wallet/Abnormal', meta: { title: '异常监控', permission: 'wallet:monitor' } },
  // CMS 内容
  { path: '/cms/banner', name: 'cms-banner', component: 'cms/Banner', meta: { title: 'Banner 管理', icon: 'Picture', permission: 'cms:banner:list' } },
  { path: '/cms/announcement', name: 'cms-announcement', component: 'cms/Announcement', meta: { title: '公告管理', icon: 'Bell', permission: 'cms:announcement:list' } },
  { path: '/cms/agreement', name: 'cms-agreement', component: 'cms/Agreement', meta: { title: '协议管理', icon: 'DocumentChecked', permission: 'cms:agreement:list' } },
  { path: '/cms/artifact', name: 'cms-artifact', component: 'cms/Artifact', meta: { title: '文物展览', icon: 'Museum', permission: 'cms:artifact:list' } },
  { path: '/cms/decoration', name: 'cms-decoration', component: 'cms/Decoration', meta: { title: '网页装修', icon: 'Brush', permission: 'cms:decoration:edit' } },
  // 系统配置
  { path: '/system/payment', name: 'system-payment', component: 'system/Payment', meta: { title: '支付配置', icon: 'CreditCard', permission: 'system:config' } },
  { path: '/system/security', name: 'system-security', component: 'system/Security', meta: { title: '安全策略', permission: 'system:security' } },
  { path: '/system/cors', name: 'system-cors', component: 'system/Cors', meta: { title: 'CORS/域名', permission: 'system:config' } },
  { path: '/system/sms-oss', name: 'system-sms-oss', component: 'system/SmsOss', meta: { title: '短信/OSS', permission: 'system:config' } },
  { path: '/system/global', name: 'system-global', component: 'system/Global', meta: { title: '全局参数', icon: 'Setting', permission: 'system:config' } },
  // 权限审计
  { path: '/permission/admin', name: 'permission-admin', component: 'permission/Admin', meta: { title: '管理员账号', icon: 'UserFilled', permission: 'permission:admin:list' } },
  { path: '/permission/role', name: 'permission-role', component: 'permission/Role', meta: { title: '角色管理', icon: 'Medal', permission: 'permission:role:list' } },
  { path: '/permission/permission', name: 'permission-permission', component: 'permission/Permission', meta: { title: '权限树', icon: 'Key', permission: 'permission:permission:list' } },
  { path: '/permission/operation-log', name: 'permission-operation-log', component: 'permission/OperationLog', meta: { title: '操作日志', icon: 'List', permission: 'permission:log:list' } },
  { path: '/permission/login-log', name: 'permission-login-log', component: 'permission/LoginLog', meta: { title: '登录日志', permission: 'permission:log:login' } },
  // 风控安全
  { path: '/security/blacklist', name: 'security-blacklist', component: 'security/Blacklist', meta: { title: '黑名单', icon: 'Warning', permission: 'security:blacklist:list' } },
  { path: '/security/risk-alert', name: 'security-risk-alert', component: 'security/RiskAlert', meta: { title: '风控告警', icon: 'BellFilled', permission: 'security:alert:list' } },
  { path: '/security/event', name: 'security-event', component: 'security/Event', meta: { title: '安全事件', icon: 'Lock', permission: 'security:event:list' } },
  { path: '/security/tx-lock', name: 'security-tx-lock', component: 'security/TxLock', meta: { title: '交易密码锁定', permission: 'security:txlock:list' } },
  { path: '/security/approval', name: 'security-approval', component: 'security/Approval', meta: { title: '敏感操作审批', icon: 'Stamp', permission: 'security:approval:list' } },
  // 客服工单
  { path: '/ticket', name: 'ticket', component: 'ticket/Index', meta: { title: '工单管理', icon: 'Headset', permission: 'ticket:list' } },
  { path: '/ticket/:id', name: 'ticket-detail', component: 'ticket/Detail', meta: { title: '工单详情', hidden: true, permission: 'ticket:detail', activeMenu: '/ticket' } },
  { path: '/ticket/feedback', name: 'ticket-feedback', component: 'ticket/Feedback', meta: { title: '用户反馈', permission: 'ticket:feedback' } },
  // 数据报表
  { path: '/report/sales', name: 'report-sales', component: 'report/Sales', meta: { title: '销售报表', icon: 'TrendCharts', permission: 'report:sales' } },
  { path: '/report/user', name: 'report-user', component: 'report/User', meta: { title: '用户报表', permission: 'report:user' } },
  { path: '/report/collectible', name: 'report-collectible', component: 'report/Collectible', meta: { title: '藏品报表', permission: 'report:collectible' } },
  { path: '/report/blindbox', name: 'report-blindbox', component: 'report/Blindbox', meta: { title: '盲盒报表', permission: 'report:blindbox' } },
  { path: '/report/finance', name: 'report-finance', component: 'report/Finance', meta: { title: '财务对账', permission: 'report:finance' } },
  { path: '/report/custom-export', name: 'report-custom-export', component: 'report/CustomExport', meta: { title: '自定义导出', icon: 'Download', permission: 'report:export' } },
  // 平台运维
  { path: '/platform', name: 'platform', component: 'platform/Cleanup', meta: { title: '平台清库', icon: 'Delete', permission: 'platform:cleanup', danger: true } },
  { path: '/platform/logs', name: 'platform-logs', component: 'platform/Index', meta: { title: '清库/备份记录', icon: 'FolderOpened', permission: 'platform:log' } },
]

/** 组件路径 → 懒加载映射（views 目录约定） */
const viewModules = import.meta.glob('../views/**/*.vue') as Record<string, () => Promise<Component>>

function resolve(def: DynamicRouteDef): Component {
  const loader = viewModules[`../views/${def.component}.vue`]
  if (!loader) {
    // 组件缺失时兜底占位页（构建期不报错，运行时可见提示）
    return defineAsyncComponent(() => import('@/views/error/NotImplemented.vue'))
  }
  return defineAsyncComponent(loader)
}

/** 按当前管理员权限过滤生成可访问路由（由守卫逐条 addRoute 到主布局下） */
export function buildAccessibleRoutes(): RouteRecordRaw[] {
  const accessible: RouteRecordRaw[] = []
  for (const def of defs) {
    if (!hasPermission(def.meta.permission)) continue
    accessible.push({
      path: def.path,
      name: def.name,
      component: resolve(def),
      meta: { ...def.meta } as unknown as RouteRecordRaw['meta'],
    })
  }
  return accessible
}

/** 侧边栏菜单树：从可访问路由中取 meta.visible 的一级项按分组渲染 */
interface MenuNode {
  path: string
  title: string
  icon?: string
  children?: MenuNode[]
}

/** 菜单分组（§9.2 顺序） */
const MENU_GROUPS: Array<{ group: string; names: string[] }> = [
  { group: '', names: ['dashboard'] },
  { group: '用户', names: ['user', 'realname'] },
  { group: '数字资产', names: ['collectible', 'blindbox'] },
  { group: '交易', names: ['order', 'order-abnormal', 'refund', 'market', 'market-trades', 'market-alerts', 'market-fee', 'transfer', 'transfer-stats'] },
  { group: '营销活动', names: ['marketing-priority', 'marketing-checkin', 'marketing-invite', 'marketing-lucky-draw', 'marketing-synthesis', 'marketing-airdrop', 'marketing-register', 'marketing-blindbox'] },
  { group: '财务', names: ['wallet-recharge', 'wallet-transaction', 'wallet-fee', 'wallet-conservation', 'wallet-abnormal'] },
  { group: '内容', names: ['cms-banner', 'cms-announcement', 'cms-agreement', 'cms-artifact', 'cms-decoration'] },
  { group: '系统', names: ['system-payment', 'system-security', 'system-cors', 'system-sms-oss', 'system-global'] },
  { group: '权限审计', names: ['permission-admin', 'permission-role', 'permission-permission', 'permission-operation-log', 'permission-login-log'] },
  { group: '风控安全', names: ['security-blacklist', 'security-risk-alert', 'security-event', 'security-tx-lock', 'security-approval'] },
  { group: '客服', names: ['ticket', 'ticket-feedback'] },
  { group: '报表', names: ['report-sales', 'report-user', 'report-collectible', 'report-blindbox', 'report-finance', 'report-custom-export'] },
  { group: '平台运维', names: ['platform', 'platform-logs'] },
]

export function menuTree(accessRoutes: RouteRecordRaw[]): MenuNode[] {
  const byName = new Map(accessRoutes.map((r) => [String(r.name), r]))
  const nodes: MenuNode[] = []
  for (const { group, names } of MENU_GROUPS) {
    const children: MenuNode[] = []
    for (const n of names) {
      const r = byName.get(n)
      if (r) {
        const meta = r.meta as unknown as RouteMeta
        children.push({ path: String(r.path), title: meta.title, icon: meta.icon })
      }
    }
    if (children.length === 0) continue
    if (group === '') {
      nodes.push(...children)
    } else {
      nodes.push({ path: `group-${group}`, title: group, children })
    }
  }
  return nodes
}
