<script setup lang="ts">
/** 管理员账号（契约 §8.15）：列表 + 创建/编辑 + 重置密码 + 删除（密码确认）+ 启用禁用切换 */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import PasswordVerify from '@/components/PasswordVerify.vue'
import { listAdmins, createAdmin, updateAdmin, deleteAdmin, resetAdminPassword, enableAdmin } from '@/api/permission'
import { ROLE_NAMES } from '@/types/api'
import { fmtDateTime } from '@/utils/date'
import { required, mobile, passwordStrong } from '@/utils/validate'

type AdminRow = {
  id: number
  username: string
  real_name: string
  role: number
  phone?: string
  email?: string
  status?: number | string
  created_at?: string
}

const ROLE_OPTIONS = Object.entries(ROLE_NAMES).map(([value, label]) => ({ label, value: Number(value) }))

const fields: SearchField[] = [
  { key: 'username', label: '用户名', type: 'input' },
  { key: 'real_name', label: '姓名', type: 'input' },
]

const query = ref<Record<string, unknown>>({})
const list = ref<AdminRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

function isEnabled(row: AdminRow): boolean {
  const s = row.status
  return s === 1 || s === '1' || s === 'enabled' || s === 'active' || s === undefined
}

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value }
    const username = toStr(query.value['username']).trim()
    if (username !== '') params.username = username
    const realName = toStr(query.value['real_name']).trim()
    if (realName !== '') params.real_name = realName
    const res = await listAdmins(params)
    list.value = res.list as AdminRow[]
    total.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  page.value = 1
  void fetchList()
}

function onReset(): void {
  page.value = 1
  void fetchList()
}

function onPageChange(p: number, s: number): void {
  page.value = p
  pageSize.value = s
  void fetchList()
}

function roleLabel(role: number): string {
  return ROLE_NAMES[role] ?? `角色 ${role}`
}

function roleTagType(role: number): 'danger' | 'warning' | 'success' | 'info' | 'primary' {
  const map: Record<number, 'danger' | 'warning' | 'success' | 'info' | 'primary'> = { 1: 'danger', 2: 'warning', 3: 'primary', 4: 'success', 5: 'info' }
  return map[role] ?? 'info'
}

/* ---------- 创建 / 编辑 ---------- */

const formVisible = ref(false)
const formMode = ref<'create' | 'edit'>('create')
const formRef = ref<FormInstance>()
const editingId = ref(0)
const submitting = ref(false)
const form = reactive<{ username: string; real_name: string; role: number; phone: string; email: string; password: string }>({
  username: '',
  real_name: '',
  role: 2,
  phone: '',
  email: '',
  password: '',
})

const formTitle = ref('')
const formRules = ref<FormRules>({})

function openCreate(): void {
  formMode.value = 'create'
  formTitle.value = '创建管理员'
  formRules.value = {
    username: [required('请输入用户名')],
    real_name: [required('请输入姓名')],
    role: [{ required: true, message: '请选择角色', trigger: 'change' }],
    phone: [mobile],
    password: [passwordStrong],
  }
  Object.assign(form, { username: '', real_name: '', role: 2, phone: '', email: '', password: '' })
  formVisible.value = true
}

function openEdit(row: AdminRow): void {
  formMode.value = 'edit'
  editingId.value = row.id
  formTitle.value = `编辑管理员：${row.username}`
  formRules.value = {
    real_name: [required('请输入姓名')],
    role: [{ required: true, message: '请选择角色', trigger: 'change' }],
    phone: [mobile],
  }
  Object.assign(form, { username: row.username, real_name: row.real_name, role: row.role, phone: toStr(row.phone), email: toStr(row.email), password: '' })
  formVisible.value = true
}

async function submitForm(): Promise<void> {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  submitting.value = true
  try {
    if (formMode.value === 'create') {
      await createAdmin({ username: form.username.trim(), real_name: form.real_name.trim(), role: form.role, phone: form.phone.trim(), email: form.email.trim(), password: form.password })
      ElMessage.success('管理员已创建')
    } else {
      await updateAdmin(editingId.value, { real_name: form.real_name.trim(), role: form.role, phone: form.phone.trim(), email: form.email.trim() })
      ElMessage.success('管理员已更新')
    }
    formVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 重置密码 ---------- */

const resetVisible = ref(false)
const resetRef = ref<FormInstance>()
const resetRow = ref<AdminRow | null>(null)
const resetForm = reactive<{ new_password: string; password: string }>({ new_password: '', password: '' })

const resetRules: FormRules = {
  new_password: [passwordStrong],
  password: [{ required: true, message: '请输入当前管理员密码', trigger: 'blur' }],
}

function openReset(row: AdminRow): void {
  resetRow.value = row
  resetForm.new_password = ''
  resetForm.password = ''
  resetVisible.value = true
}

async function submitReset(): Promise<void> {
  if (!resetRef.value || !resetRow.value) return
  try {
    await resetRef.value.validate()
  } catch {
    return
  }
  submitting.value = true
  try {
    await resetAdminPassword(resetRow.value.id, resetForm.new_password, resetForm.password)
    ElMessage.success('密码已重置')
    resetVisible.value = false
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 删除 / 启用禁用（密码确认） ---------- */

const confirmVisible = ref(false)
const confirmMode = ref<'delete' | 'enable' | 'disable'>('delete')
const confirmRow = ref<AdminRow | null>(null)
const confirmLoading = ref(false)
const confirmTitle = ref('')
const confirmMessage = ref('')

function openDelete(row: AdminRow): void {
  confirmMode.value = 'delete'
  confirmRow.value = row
  confirmTitle.value = '删除管理员'
  confirmMessage.value = `将永久删除管理员 #${row.id}（${row.username}），该操作不可恢复；请输入您的登录密码确认。`
  confirmVisible.value = true
}

function openToggle(row: AdminRow): void {
  const target = isEnabled(row) ? 'disable' : 'enable'
  confirmMode.value = target
  confirmRow.value = row
  confirmTitle.value = target === 'disable' ? '禁用管理员' : '启用管理员'
  confirmMessage.value =
    target === 'disable'
      ? `将禁用管理员 #${row.id}（${row.username}），其全部会话立即失效且无法登录；请输入您的登录密码确认。`
      : `将启用管理员 #${row.id}（${row.username}），启用后即可重新登录；请输入您的登录密码确认。`
  confirmVisible.value = true
}

async function onConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = confirmRow.value
  if (!row) return
  confirmLoading.value = true
  try {
    if (confirmMode.value === 'delete') {
      await deleteAdmin(row.id, payload.password)
      ElMessage.success('管理员已删除')
    } else {
      await enableAdmin(row.id, confirmMode.value === 'enable' ? 1 : 0, payload.password)
      ElMessage.success(confirmMode.value === 'enable' ? '已启用' : '已禁用')
    }
    confirmVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    confirmLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <div style="margin-bottom: 12px">
      <el-button v-permission="'permission:admin:create'" type="primary" @click="openCreate">创建管理员</el-button>
    </div>

    <DataTable :data="list" :loading="loading" empty-text="暂无管理员">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="username" label="用户名" min-width="120" show-overflow-tooltip />
      <el-table-column label="姓名" width="120">
        <template #default="{ row }">{{ (row as AdminRow).real_name || '-' }}</template>
      </el-table-column>
      <el-table-column label="角色" width="120">
        <template #default="{ row }">
          <el-tag :type="roleTagType((row as AdminRow).role)" size="small">{{ roleLabel((row as AdminRow).role) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="手机号" width="140">
        <template #default="{ row }">{{ (row as AdminRow).phone || '-' }}</template>
      </el-table-column>
      <el-table-column label="邮箱" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as AdminRow).email || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="isEnabled(row as AdminRow) ? 'success' : 'danger'" size="small">
            {{ isEnabled(row as AdminRow) ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" width="180">
        <template #default="{ row }">{{ fmtDateTime((row as AdminRow).created_at) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="250" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'permission:admin:update'" link type="primary" @click="openEdit(row as AdminRow)">编辑</el-button>
          <el-button v-permission="'permission:admin:update'" link type="warning" @click="openReset(row as AdminRow)">重置密码</el-button>
          <el-button v-permission="'permission:admin:update'" link :type="isEnabled(row as AdminRow) ? 'info' : 'success'" @click="openToggle(row as AdminRow)">
            {{ isEnabled(row as AdminRow) ? '禁用' : '启用' }}
          </el-button>
          <el-button v-permission="'permission:admin:delete'" link type="danger" @click="openDelete(row as AdminRow)">删除</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <!-- 创建 / 编辑 -->
    <el-dialog v-model="formVisible" :title="formTitle" width="480px" :close-on-click-modal="false" destroy-on-close>
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="90px">
        <el-form-item label="用户名" prop="username">
          <el-input v-model="form.username" :disabled="formMode === 'edit'" placeholder="登录用户名" />
        </el-form-item>
        <el-form-item label="姓名" prop="real_name">
          <el-input v-model="form.real_name" placeholder="真实姓名" />
        </el-form-item>
        <el-form-item label="角色" prop="role">
          <el-select v-model="form.role" style="width: 100%">
            <el-option v-for="o in ROLE_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="手机号" prop="phone">
          <el-input v-model="form.phone" placeholder="选填" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="form.email" placeholder="选填" />
        </el-form-item>
        <el-form-item v-if="formMode === 'create'" label="初始密码" prop="password">
          <el-input v-model="form.password" type="password" show-password placeholder="至少 8 位" autocomplete="new-password" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="formVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitForm">确定</el-button>
      </template>
    </el-dialog>

    <!-- 重置密码 -->
    <el-dialog :model-value="resetVisible" :title="`重置密码：${resetRow?.username ?? ''}`" width="440px" :close-on-click-modal="false" destroy-on-close @update:model-value="resetVisible = $event" @closed="resetRow = null">
      <el-form ref="resetRef" :model="resetForm" :rules="resetRules" label-width="110px">
        <el-form-item label="新密码" prop="new_password">
          <el-input v-model="resetForm.new_password" type="password" show-password placeholder="至少 8 位" autocomplete="new-password" />
        </el-form-item>
        <el-form-item label="当前管理员密码" prop="password">
          <PasswordVerify v-model="resetForm.password" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="resetVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitReset">确认重置</el-button>
      </template>
    </el-dialog>

    <!-- 删除 / 启用禁用 -->
    <ConfirmDialog
      v-model="confirmVisible"
      :title="confirmTitle"
      :message="confirmMessage"
      :need-reason="false"
      :loading="confirmLoading"
      @confirm="onConfirm"
    />
  </div>
</template>
