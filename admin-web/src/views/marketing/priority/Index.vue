<script setup lang="ts">
/** 优先购活动列表（契约 §8.11.1，93~96/102）：列表 + 创建/编辑 + 白名单入口 + 清理过期资格（密码 + 审计） */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listPriority, createPriority, updatePriority, cleanupPriority } from '@/api/marketing'
import { fmtDateTime, fmtRange } from '@/utils/date'

/** 优先购活动行（后端 priorityBrief 口径） */
type PriorityRowVM = {
  id: number
  collectible_id: number
  collectible_name: string
  start_time: string
  end_time: string
  max_quantity_per_user: number
  status: number
  create_time: string
}

/** el-table 作用域 row 为宽类型，局部收窄 */
function asRow(row: unknown): PriorityRowVM {
  return row as PriorityRowVM
}

function statusMeta(status: number): { label: string; type: 'success' | 'info' } {
  return status === 1 ? { label: '启用', type: 'success' } : { label: '停用', type: 'info' }
}

const searchFields: SearchField[] = [
  { key: 'collectible_id', label: '藏品ID' },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '启用', value: 1 },
      { label: '停用', value: 0 },
    ],
  },
]

const router = useRouter()
const query = ref<Record<string, unknown>>({})
const rows = ref<Record<string, unknown>[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listPriority({ page: page.value, page_size: pageSize.value, ...query.value })
    rows.value = res.list
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

function onPageChange(p: number, s: number): void {
  page.value = p
  pageSize.value = s
  void fetchList()
}

/* ---------- 创建 / 编辑（94/96） ---------- */

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const submitting = ref(false)

/** el-input-number 的 update:modelValue 载荷为 number | undefined，模型需对齐 */
const form = reactive<{
  collectible_id: number | undefined
  start_time: string
  end_time: string
  max_quantity_per_user: number | undefined
}>({ collectible_id: undefined, start_time: '', end_time: '', max_quantity_per_user: 1 })

const dialogTitle = computed(() => (editingId.value === null ? '创建优先购活动' : '编辑优先购活动'))

/** ISO 时间 → 选择器值（空值 '-' → ''） */
function toPickerValue(v: string | undefined): string {
  if (!v) return ''
  const t = fmtDateTime(v)
  return t === '-' ? '' : t
}

function openCreate(): void {
  editingId.value = null
  Object.assign(form, { collectible_id: undefined, start_time: '', end_time: '', max_quantity_per_user: 1 })
  dialogVisible.value = true
}

function openEdit(row: PriorityRowVM): void {
  editingId.value = row.id
  Object.assign(form, {
    collectible_id: row.collectible_id,
    start_time: toPickerValue(row.start_time),
    end_time: toPickerValue(row.end_time),
    max_quantity_per_user: row.max_quantity_per_user,
  })
  dialogVisible.value = true
}

async function onSubmit(): Promise<void> {
  if (editingId.value === null && !form.collectible_id) {
    ElMessage.error('请填写藏品 ID')
    return
  }
  if (form.start_time === '' || form.end_time === '') {
    ElMessage.error('请选择活动起止时间')
    return
  }
  if (form.end_time <= form.start_time) {
    ElMessage.error('结束时间必须晚于开始时间')
    return
  }
  submitting.value = true
  try {
    if (editingId.value === null) {
      await createPriority({
        collectible_id: form.collectible_id,
        start_time: form.start_time,
        end_time: form.end_time,
        max_quantity_per_user: form.max_quantity_per_user ?? 0,
      })
      ElMessage.success('优先购活动已创建')
    } else {
      await updatePriority(editingId.value, {
        start_time: form.start_time,
        end_time: form.end_time,
        max_quantity_per_user: form.max_quantity_per_user ?? 0,
      })
      ElMessage.success('优先购活动已更新')
    }
    dialogVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 清理过期资格（102，高风险：密码 + 审计） ---------- */

const cleanupVisible = ref(false)
const cleanupLoading = ref(false)

async function onCleanupConfirm(payload: { reason: string; password: string }): Promise<void> {
  cleanupLoading.value = true
  try {
    const res = await cleanupPriority(payload.password)
    cleanupVisible.value = false
    void ElMessageBox.alert(`本次共清理 ${res.cleaned_count} 条过期白名单资格`, '清理完成', { type: 'success' })
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    cleanupLoading.value = false
  }
}

/* ---------- 白名单入口 ---------- */

function goWhitelist(row: PriorityRowVM): void {
  void router.push(`/marketing/priority/${row.id}/whitelist`)
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="searchFields" @search="onSearch" @reset="onSearch" />

    <div style="margin-bottom: 12px">
      <el-button v-permission="'marketing:priority:create'" type="primary" @click="openCreate">创建活动</el-button>
      <el-button v-permission="'marketing:priority:manage'" type="warning" @click="cleanupVisible = true">
        清理过期资格
      </el-button>
    </div>

    <DataTable :data="rows" :loading="loading" empty-text="暂无优先购活动">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column label="藏品" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ asRow(row).collectible_name || `#${asRow(row).collectible_id}` }}</template>
      </el-table-column>
      <el-table-column label="活动时间" min-width="300" show-overflow-tooltip>
        <template #default="{ row }">{{ fmtRange(asRow(row).start_time, asRow(row).end_time) }}</template>
      </el-table-column>
      <el-table-column label="每人限购" width="100">
        <template #default="{ row }">
          {{ asRow(row).max_quantity_per_user > 0 ? asRow(row).max_quantity_per_user : '不限' }}
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag size="small" :type="statusMeta(asRow(row).status).type">
            {{ statusMeta(asRow(row).status).label }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" width="180">
        <template #default="{ row }">{{ fmtDateTime(asRow(row).create_time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'marketing:priority:whitelist'" link type="primary" @click="goWhitelist(asRow(row))">
            白名单
          </el-button>
          <el-button v-permission="'marketing:priority:edit'" link type="primary" @click="openEdit(asRow(row))">
            编辑
          </el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px" :close-on-click-modal="false">
      <el-form label-width="130px" @submit.prevent="onSubmit">
        <el-form-item label="藏品 ID" :required="editingId === null">
          <el-input-number
            v-model="form.collectible_id"
            :min="1"
            :step="1"
            step-strictly
            :disabled="editingId !== null"
            style="width: 200px"
          />
          <span v-if="editingId !== null" style="margin-left: 8px; font-size: 12px; color: #909399">
            创建后不可更换藏品
          </span>
        </el-form-item>
        <el-form-item label="开始时间" required>
          <el-date-picker
            v-model="form.start_time"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="选择开始时间"
            style="width: 220px"
          />
        </el-form-item>
        <el-form-item label="结束时间" required>
          <el-date-picker
            v-model="form.end_time"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="选择结束时间"
            style="width: 220px"
          />
        </el-form-item>
        <el-form-item label="每人限购数量">
          <el-input-number v-model="form.max_quantity_per_user" :min="0" :step="1" step-strictly />
          <span style="margin-left: 8px; font-size: 12px; color: #909399">0 表示不限</span>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="onSubmit">保存</el-button>
      </template>
    </el-dialog>

    <ConfirmDialog
      v-model="cleanupVisible"
      title="清理过期资格"
      message="将把全部已过期（过期时间早于当前时间）的白名单资格置为「已清理」状态，操作不可恢复；需管理员密码验证并写入审计日志。"
      :need-reason="false"
      :loading="cleanupLoading"
      @confirm="onCleanupConfirm"
    />
  </div>
</template>
