<script setup lang="ts">
/** 黑名单管理（契约 §8.16.1）：分页 + 添加/编辑（type 1=用户 2=IP 3=设备）+ 移出（原因+密码，审计留痕） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listSecurityBlacklist, addToBlacklist, updateBlacklistEntry, removeFromBlacklist } from '@/api/security'
import { fmtDateTime } from '@/utils/date'

type BlacklistRow = {
  id: number
  user_id: number
  nickname: string
  mobile: string
  type: number
  target_value: string
  reason: string
  status: number
  expires_at: string
  lifted_at: string
  admin_name: string
  create_time: string
}

/** blacklist.blacklist_type：1=用户 2=IP 3=设备 */
const TYPE_MAP: Record<number, { label: string; tag: 'warning' | 'info' | 'danger' }> = {
  1: { label: '用户', tag: 'warning' },
  2: { label: 'IP', tag: 'info' },
  3: { label: '设备', tag: 'danger' },
}

/** blacklist.status：1=在名单 0=已移出 */
const STATUS_MAP: Record<number, { label: string; tag: 'danger' | 'info' }> = {
  1: { label: '在名单', tag: 'danger' },
  0: { label: '已移出', tag: 'info' },
}

const fields: SearchField[] = [
  { key: 'user_id', label: '用户ID' },
  {
    key: 'type',
    label: '类型',
    type: 'select',
    options: [
      { label: '用户', value: 1 },
      { label: 'IP', value: 2 },
      { label: '设备', value: 3 },
    ],
  },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '在名单', value: 1 },
      { label: '已移出', value: 0 },
    ],
  },
]

const query = ref<Record<string, unknown>>({})
const list = ref<BlacklistRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listSecurityBlacklist({ page: page.value, page_size: pageSize.value, ...query.value })
    list.value = (res.list ?? []) as BlacklistRow[]
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

/* ---------- 添加 / 编辑（共用弹窗；editId 为 null 表示添加） ---------- */

const editVisible = ref(false)
const editId = ref<number | null>(null)
const editLoading = ref(false)
const editForm = reactive<{ user_id: number | null; type: number; reason: string }>({
  user_id: null,
  type: 1,
  reason: '',
})

const editTitle = (): string => (editId.value === null ? '添加黑名单' : '编辑黑名单')

function openAdd(): void {
  editId.value = null
  editForm.user_id = null
  editForm.type = 1
  editForm.reason = ''
  editVisible.value = true
}

function openEdit(row: BlacklistRow): void {
  editId.value = row.id
  editForm.user_id = row.user_id
  editForm.type = row.type
  editForm.reason = row.reason
  editVisible.value = true
}

async function submitEdit(): Promise<void> {
  if (!editForm.user_id || editForm.user_id <= 0) {
    ElMessage.error('请输入正确的用户ID')
    return
  }
  if (editForm.reason.trim() === '') {
    ElMessage.error('请填写原因')
    return
  }
  editLoading.value = true
  try {
    if (editId.value === null) {
      await addToBlacklist({ user_id: editForm.user_id, type: editForm.type, reason: editForm.reason.trim() })
      ElMessage.success('已加入黑名单')
    } else {
      await updateBlacklistEntry(editId.value, { type: editForm.type, reason: editForm.reason.trim() })
      ElMessage.success('黑名单记录已更新')
    }
    editVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    editLoading.value = false
  }
}

/* ---------- 移出（原因 + 管理员密码） ---------- */

const removeVisible = ref(false)
const removeRow = ref<BlacklistRow | null>(null)
const removeLoading = ref(false)

const removeMessage = (): string => {
  const row = removeRow.value
  if (!row) return ''
  return `将把记录 #${row.id}（用户 ID ${row.user_id} / ${TYPE_MAP[row.type]?.label ?? '未知'}）移出黑名单。` +
    '移出后该用户/IP/设备立即恢复访问，操作写入审计日志，不可撤销。'
}

function openRemove(row: BlacklistRow): void {
  removeRow.value = row
  removeVisible.value = true
}

async function onRemoveConfirm(payload: { reason: string; password: string }): Promise<void> {
  if (!removeRow.value) return
  removeLoading.value = true
  try {
    await removeFromBlacklist(removeRow.value.id, payload.reason, payload.password)
    ElMessage.success('已移出黑名单')
    removeVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    removeLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <div class="table-toolbar">
      <el-button v-permission="'security:blacklist:create'" type="primary" @click="openAdd">添加黑名单</el-button>
    </div>

    <DataTable :data="list" :loading="loading">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column prop="nickname" label="昵称" min-width="120" show-overflow-tooltip />
      <el-table-column prop="mobile" label="手机号" width="130" />
      <el-table-column label="类型" width="90">
        <template #default="{ row }">
          <el-tag :type="TYPE_MAP[Number(row.type)]?.tag ?? 'info'" size="small">
            {{ TYPE_MAP[Number(row.type)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="target_value" label="目标值" min-width="130" show-overflow-tooltip />
      <el-table-column prop="reason" label="原因" min-width="160" show-overflow-tooltip />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[Number(row.status)]?.tag ?? 'info'" size="small">
            {{ STATUS_MAP[Number(row.status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="到期时间" width="170">
        <template #default="{ row }">{{ row.expires_at ? fmtDateTime(row.expires_at as string) : '永久' }}</template>
      </el-table-column>
      <el-table-column prop="admin_name" label="操作人" width="110" />
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(row.create_time as string) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <template v-if="Number(row.status) === 1">
            <el-button v-permission="'security:blacklist:create'" link type="primary" @click="openEdit(row as BlacklistRow)">编辑</el-button>
            <el-button v-permission="'security:blacklist:delete'" link type="danger" @click="openRemove(row as BlacklistRow)">移出</el-button>
          </template>
          <span v-else class="handled-text">已移出</span>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-dialog v-model="editVisible" :title="editTitle()" width="460px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="用户ID" required>
          <el-input-number v-model="editForm.user_id" :min="1" :controls="false" placeholder="请输入用户ID" style="width: 100%" :disabled="editId !== null" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="editForm.type" style="width: 100%">
            <el-option label="用户" :value="1" />
            <el-option label="IP" :value="2" />
            <el-option label="设备" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="原因" required>
          <el-input v-model="editForm.reason" type="textarea" :rows="3" placeholder="请输入拉黑原因（将写入审计日志）" maxlength="255" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" :loading="editLoading" @click="submitEdit">确定</el-button>
      </template>
    </el-dialog>

    <ConfirmDialog
      v-model="removeVisible"
      title="移出黑名单"
      :message="removeMessage()"
      :loading="removeLoading"
      @confirm="onRemoveConfirm"
    />
  </div>
</template>

<style scoped>
.table-toolbar { margin-bottom: 12px; display: flex; justify-content: flex-end; }
.handled-text { color: #909399; font-size: 12px; }
</style>
