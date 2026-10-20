<script setup lang="ts">
/** 权限树（契约 §8.15）：listPermissions 全量权限按模块分组展示（折叠面板 + 权限码标签） */
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/DataTable.vue'
import { listPermissions } from '@/api/permission'

type PermRow = { code: string; name: string; module: string }
type PermModule = { key: string; label: string; perms: PermRow[] }

/** 权限模块前缀 → 中文名 */
const MODULE_LABELS: Record<string, string> = {
  dashboard: '数据大盘',
  user: '用户',
  realname: '实名认证',
  collectible: '藏品',
  blindbox: '盲盒',
  order: '订单',
  refund: '退款',
  market: '市场寄售',
  transfer: '转赠',
  marketing: '营销活动',
  wallet: '钱包财务',
  cms: '内容',
  system: '系统配置',
  permission: '权限审计',
  security: '风控安全',
  ticket: '客服工单',
  report: '数据报表',
  platform: '平台运维',
}

const loading = ref(false)
const perms = ref<PermRow[]>([])

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

function moduleLabel(key: string): string {
  return MODULE_LABELS[key] ?? key
}

/** 按模块分组（module/group 字段，缺省用 code 前缀） */
const grouped = computed<PermModule[]>(() => {
  const map = new Map<string, PermRow[]>()
  for (const p of perms.value) {
    const key = p.module || p.code.split(':')[0]
    const arr = map.get(key) ?? []
    arr.push(p)
    map.set(key, arr)
  }
  return [...map.entries()].map(([key, list]) => ({ key, label: moduleLabel(key), perms: list }))
})

const activeNames = ref<string[]>([])

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await listPermissions()
    const list = Array.isArray(res.list) ? (res.list as Record<string, unknown>[]) : []
    perms.value = list
      .map((p) => {
        const code = toStr(p['code'])
        return {
          code,
          name: toStr(p['name']) || code,
          module: toStr(p['module']) || toStr(p['group']),
        }
      })
      .filter((p) => p.code !== '')
    activeNames.value = grouped.value.map((g) => g.key)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div v-loading="loading" class="card">
    <h3 style="margin-top: 0">权限树</h3>
    <el-alert
      :title="`共 ${grouped.length} 个模块、${perms.length} 项权限；权限码用于菜单路由与按钮级控制（v-permission）。`"
      type="info"
      :closable="false"
      show-icon
      style="margin-bottom: 16px"
    />

    <el-collapse v-if="grouped.length > 0" v-model="activeNames">
      <el-collapse-item v-for="g in grouped" :key="g.key" :name="g.key">
        <template #title>
          <span style="font-weight: 600">{{ g.label }}</span>
          <el-tag size="small" type="info" style="margin-left: 8px">{{ g.perms.length }} 项</el-tag>
        </template>
        <div class="perm-tags">
          <el-tag v-for="p in g.perms" :key="p.code" style="margin: 0 8px 8px 0" effect="plain">
            <span class="perm-code">{{ p.code }}</span>
            <span v-if="p.name && p.name !== p.code" class="perm-name">{{ p.name }}</span>
          </el-tag>
        </div>
      </el-collapse-item>
    </el-collapse>

    <DataTable v-else :data="[]" :loading="false" empty-text="暂无权限数据" />
  </div>
</template>

<style scoped>
.perm-tags { padding: 4px 8px; }
.perm-code { font-family: monospace; }
.perm-name { margin-left: 6px; color: var(--el-text-color-secondary); }
</style>
