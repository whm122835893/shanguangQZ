<script setup lang="ts">
/** 角色管理（契约 §8.15）：列表 + 创建/编辑（含权限分配勾选）+ 删除（密码确认） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import DataTable from '@/components/DataTable.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PasswordVerify from '@/components/PasswordVerify.vue'
import { listRoles, createRole, updateRole, deleteRole, listPermissions } from '@/api/permission'
import { required } from '@/utils/validate'

type RoleRow = {
  id: number
  name: string
  description?: string
  permissions?: unknown
  permission_codes?: unknown
  codes?: unknown
}

type PermItem = { code: string; name: string }
type PermModule = { key: string; label: string; perms: PermItem[] }

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

const roles = ref<RoleRow[]>([])
const modules = ref<PermModule[]>([])
const loading = ref(false)

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

function moduleLabel(key: string): string {
  return MODULE_LABELS[key] ?? key
}

/** 权限记录 → { code, name }，模块取 module/group 字段，缺省用 code 前缀 */
function toPermItem(p: Record<string, unknown>): PermItem {
  const code = toStr(p['code'])
  return { code, name: toStr(p['name']) || code }
}

function buildModules(raw: unknown): PermModule[] {
  const list = Array.isArray(raw) ? (raw as Record<string, unknown>[]) : []
  const map = new Map<string, PermItem[]>()
  for (const p of list) {
    const item = toPermItem(p)
    if (item.code === '') continue
    const key = toStr(p['module']) || toStr(p['group']) || item.code.split(':')[0]
    const arr = map.get(key) ?? []
    arr.push(item)
    map.set(key, arr)
  }
  return [...map.entries()].map(([key, perms]) => ({ key, label: moduleLabel(key), perms }))
}

/** 角色记录的权限码（字段名兼容 permissions/permission_codes/codes，元素兼容 string 或 {code}） */
function extractCodes(row: RoleRow): string[] {
  const raw = row.permissions ?? row.permission_codes ?? row.codes
  if (!Array.isArray(raw)) return []
  return raw
    .map((x) => {
      if (typeof x === 'string') return x
      if (x !== null && typeof x === 'object') return toStr((x as Record<string, unknown>)['code'])
      return ''
    })
    .filter((s) => s !== '')
}

async function loadAll(): Promise<void> {
  loading.value = true
  try {
    const [roleRes, permRes] = await Promise.all([listRoles(), listPermissions()])
    roles.value = roleRes.list as RoleRow[]
    modules.value = buildModules(permRes.list)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function permCount(row: RoleRow): number {
  return extractCodes(row).length
}

/* ---------- 创建 / 编辑（含权限分配） ---------- */

const formVisible = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const formRef = ref<FormInstance>()
const editingId = ref(0)
const submitting = ref(false)
const form = reactive<{ name: string; description: string; codes: string[]; password: string }>({
  name: '',
  description: '',
  codes: [],
  password: '',
})

const formRules = ref<FormRules>({
  name: [required('请输入角色名称')],
})

function openCreate(): void {
  formMode.value = 'create'
  Object.assign(form, { name: '', description: '', codes: [], password: '' })
  formVisible.value = true
}

function openEdit(row: RoleRow): void {
  formMode.value = 'edit'
  editingId.value = row.id
  Object.assign(form, { name: row.name, description: toStr(row.description), codes: extractCodes(row), password: '' })
  formVisible.value = true
}

async function submitForm(): Promise<void> {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  if (formMode.value === 'edit' && form.password === '') {
    ElMessage.error('权限/角色变更属高风险操作，请输入管理员密码')
    return
  }
  submitting.value = true
  try {
    if (formMode.value === 'create') {
      await createRole({ name: form.name.trim(), description: form.description.trim(), permissions: form.codes })
      ElMessage.success('角色已创建')
    } else {
      await updateRole(editingId.value, {
        name: form.name.trim(),
        description: form.description.trim(),
        permissions: form.codes,
        password: form.password,
      })
      ElMessage.success('角色已更新')
    }
    formVisible.value = false
    void loadAll()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 删除 ---------- */

const deleteVisible = ref(false)
const deleteRow = ref<RoleRow | null>(null)
const deleteLoading = ref(false)

function openDelete(row: RoleRow): void {
  deleteRow.value = row
  deleteVisible.value = true
}

async function onDeleteConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = deleteRow.value
  if (!row) return
  deleteLoading.value = true
  try {
    await deleteRole(row.id, payload.password)
    ElMessage.success('角色已删除')
    deleteVisible.value = false
    void loadAll()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  void loadAll()
})
</script>

<template>
  <div class="card">
    <div style="margin-bottom: 12px; display: flex; justify-content: space-between">
      <h3 style="margin: 0">角色管理</h3>
      <el-button v-permission="'permission:role:create'" type="primary" @click="openCreate">创建角色</el-button>
    </div>

    <DataTable :data="roles" :loading="loading" empty-text="暂无角色">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="name" label="角色名称" min-width="140" show-overflow-tooltip />
      <el-table-column label="描述" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as RoleRow).description || '-' }}</template>
      </el-table-column>
      <el-table-column label="权限数" width="100">
        <template #default="{ row }">
          <el-tag size="small" type="info">{{ permCount(row as RoleRow) }} 项</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'permission:role:update'" link type="primary" @click="openEdit(row as RoleRow)">编辑/权限</el-button>
          <el-button v-permission="'permission:role:delete'" link type="danger" @click="openDelete(row as RoleRow)">删除</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <!-- 创建 / 编辑（含权限勾选） -->
    <el-dialog v-model="formVisible" :title="formMode === 'create' ? '创建角色' : `编辑角色：${form.name}`" width="680px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="90px">
        <el-form-item label="角色名称" prop="name">
          <el-input v-model="form.name" placeholder="如 运营专员" maxlength="50" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" maxlength="200" placeholder="角色职责说明（选填）" />
        </el-form-item>
        <el-form-item label="权限分配">
          <el-checkbox-group v-model="form.codes" class="perm-group">
            <div v-for="m in modules" :key="m.key" class="perm-module">
              <div class="perm-module-title">{{ m.label }}（{{ m.perms.length }}）</div>
              <el-checkbox v-for="p in m.perms" :key="p.code" :value="p.code" :label="p.code" class="perm-item">
                {{ p.name || p.code }}
              </el-checkbox>
            </div>
            <el-empty v-if="modules.length === 0" description="权限列表为空" :image-size="60" />
          </el-checkbox-group>
        </el-form-item>
        <el-form-item v-if="formMode === 'edit'" label="管理员密码">
          <PasswordVerify v-model="form.password" placeholder="角色/权限变更需输入管理员密码" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitForm">确定</el-button>
      </template>
    </el-dialog>

    <ConfirmDialog
      v-model="deleteVisible"
      title="删除角色"
      :message="`将永久删除角色「${deleteRow?.name ?? ''}」，关联该角色的管理员将失去对应权限；请输入您的登录密码确认。`"
      :need-reason="false"
      :loading="deleteLoading"
      @confirm="onDeleteConfirm"
    />
  </div>
</template>

<style scoped>
.perm-group { width: 100%; }
.perm-module { margin-bottom: 8px; }
.perm-module-title { font-weight: 600; font-size: 13px; color: var(--el-text-color-secondary); margin: 6px 0; }
.perm-item { margin-right: 16px; }
</style>
