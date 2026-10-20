<script setup lang="ts">
/** 优先购白名单（契约 §8.11.1，97~101）：分页列表 + 单条添加 + JSON 批量导入 + 编辑 + 删除 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import {
  listWhitelist,
  addWhitelist,
  batchWhitelist,
  updateWhitelist,
  deleteWhitelist,
} from '@/api/marketing'
import { fmtDateTime } from '@/utils/date'

/** 白名单行（后端 whitelistBrief 口径，手机号已脱敏） */
type WhitelistRowVM = {
  id: number
  user_id: number
  nickname: string
  phone: string
  max_quantity: number
  used_quantity: number
  expires_at: string
  status: number
  create_time: string
}

function asRow(row: unknown): WhitelistRowVM {
  return row as WhitelistRowVM
}

function statusMeta(status: number): { label: string; type: 'success' | 'info' | 'danger' } {
  if (status === 1) return { label: '有效', type: 'success' }
  if (status === 2) return { label: '已清理', type: 'info' }
  return { label: `状态${status}`, type: 'danger' }
}

const route = useRoute()
const router = useRouter()
const activityId = Number(route.params.id)

/** 后端白名单列表仅支持 status 过滤（97） */
const searchFields: SearchField[] = [
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '有效', value: 1 },
      { label: '已清理', value: 2 },
    ],
  },
]

const query = ref<Record<string, unknown>>({})
const rows = ref<Record<string, unknown>[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listWhitelist(activityId, { page: page.value, page_size: pageSize.value, ...query.value })
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

/** ISO 时间 → 选择器值（0 / 空 → ''，表示永不过期） */
function toPickerValue(v: string | undefined): string {
  if (!v) return ''
  const t = fmtDateTime(v)
  return t === '-' ? '' : t
}

/* ---------- 添加 / 编辑（98/100） ---------- */

const dialogVisible = ref(false)
const editingWid = ref<number | null>(null)
const submitting = ref(false)

const form = reactive<{
  user_id: number | undefined
  phone: string
  max_quantity: number | undefined
  expires_at: string
  status: number
}>({ user_id: undefined, phone: '', max_quantity: 1, expires_at: '', status: 1 })

const dialogTitle = computed(() => (editingWid.value === null ? '添加白名单' : '编辑白名单'))

function openAdd(): void {
  editingWid.value = null
  Object.assign(form, { user_id: undefined, phone: '', max_quantity: 1, expires_at: '', status: 1 })
  dialogVisible.value = true
}

function openEdit(row: WhitelistRowVM): void {
  editingWid.value = row.id
  Object.assign(form, {
    user_id: row.user_id,
    phone: row.phone,
    max_quantity: row.max_quantity,
    expires_at: toPickerValue(row.expires_at),
    status: row.status,
  })
  dialogVisible.value = true
}

async function onSubmit(): Promise<void> {
  if (editingWid.value === null && !form.user_id) {
    ElMessage.error('请填写用户 ID')
    return
  }
  if (!form.max_quantity || form.max_quantity < 1) {
    ElMessage.error('限购数量必须 ≥ 1')
    return
  }
  submitting.value = true
  try {
    if (editingWid.value === null) {
      await addWhitelist(activityId, {
        user_id: form.user_id,
        phone: form.phone.trim(),
        max_quantity: form.max_quantity,
        expires_at: form.expires_at,
      })
      ElMessage.success('白名单已添加')
    } else {
      await updateWhitelist(activityId, editingWid.value, {
        max_quantity: form.max_quantity,
        expires_at: form.expires_at,
        status: form.status,
      })
      ElMessage.success('白名单已更新')
    }
    dialogVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 批量导入（99）：JSON 数组粘贴 ---------- */

const batchVisible = ref(false)
const batchText = ref('')
const batchResult = ref<{ success_count: number; fail_count: number; fail_list: unknown[] } | null>(null)
const batchSubmitting = ref(false)

function openBatch(): void {
  batchText.value = ''
  batchResult.value = null
  batchVisible.value = true
}

/** 解析并校验粘贴的 JSON 数组，元素需含 user_id 或 phone */
function parseBatchItems(): Record<string, unknown>[] | null {
  let parsed: unknown
  try {
    parsed = JSON.parse(batchText.value)
  } catch {
    ElMessage.error('JSON 解析失败，请检查格式')
    return null
  }
  if (!Array.isArray(parsed) || parsed.length === 0) {
    ElMessage.error('需为非空 JSON 数组，如：[{"user_id":1,"max_quantity":2}]')
    return null
  }
  const items: Record<string, unknown>[] = []
  for (const it of parsed) {
    if (typeof it !== 'object' || it === null || Array.isArray(it)) {
      ElMessage.error('数组元素必须为对象（{ user_id, phone?, max_quantity?, expires_at? }）')
      return null
    }
    const rec = it as Record<string, unknown>
    const uid = Number(rec.user_id ?? 0)
    const phone = String(rec.phone ?? '').trim()
    if (!(uid > 0) && phone === '') {
      ElMessage.error('每个元素必须提供 user_id 或 phone')
      return null
    }
    items.push(rec)
  }
  return items
}

async function onBatchSubmit(): Promise<void> {
  const items = parseBatchItems()
  if (items === null) return
  batchSubmitting.value = true
  try {
    const res = await batchWhitelist(activityId, items)
    batchResult.value = res
    ElMessage.success(`批量导入完成：成功 ${res.success_count} 条，失败 ${res.fail_count} 条`)
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    batchSubmitting.value = false
  }
}

/** 失败明细行 → 文案 */
function failText(f: unknown): string {
  if (typeof f !== 'object' || f === null) return String(f)
  const rec = f as Record<string, unknown>
  const idx = Number(rec.index ?? 0) + 1
  return `第 ${idx} 项（用户 ${rec.user_id ?? '-'} / ${rec.phone || '-'}）：${String(rec.reason ?? '未知原因')}`
}

/* ---------- 删除（101） ---------- */

async function onDelete(row: WhitelistRowVM): Promise<void> {
  try {
    await ElMessageBox.confirm(
      `将删除用户 #${row.user_id} 的白名单资格，删除后不可恢复。`,
      '删除确认',
      { type: 'warning', confirmButtonText: '删除', cancelButtonText: '取消' },
    )
  } catch {
    return
  }
  try {
    await deleteWhitelist(activityId, row.id)
    ElMessage.success('已删除')
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div>
    <el-page-header class="card" content="优先购白名单" @back="router.push('/marketing/priority')" />

    <div class="card">
      <div style="margin-bottom: 8px; color: #909399; font-size: 13px">活动 ID：{{ activityId }}</div>
      <SearchForm v-model="query" :fields="searchFields" @search="onSearch" @reset="onSearch" />

      <div style="margin-bottom: 12px">
        <el-button v-permission="'marketing:priority:whitelist'" type="primary" @click="openAdd">添加白名单</el-button>
        <el-button v-permission="'marketing:priority:whitelist'" type="success" @click="openBatch">批量导入</el-button>
      </div>

      <DataTable :data="rows" :loading="loading" empty-text="暂无白名单记录">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="用户" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            #{{ asRow(row).user_id }} {{ asRow(row).nickname ? `（${asRow(row).nickname}）` : '' }}
          </template>
        </el-table-column>
        <el-table-column label="手机号" width="140">
          <template #default="{ row }">{{ asRow(row).phone || '-' }}</template>
        </el-table-column>
        <el-table-column label="限购 / 已用" width="110">
          <template #default="{ row }">{{ asRow(row).max_quantity }} / {{ asRow(row).used_quantity }}</template>
        </el-table-column>
        <el-table-column label="过期时间" width="180">
          <template #default="{ row }">{{ asRow(row).expires_at ? fmtDateTime(asRow(row).expires_at) : '永不过期' }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag size="small" :type="statusMeta(asRow(row).status).type">
              {{ statusMeta(asRow(row).status).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="添加时间" width="180">
          <template #default="{ row }">{{ fmtDateTime(asRow(row).create_time) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button v-permission="'marketing:priority:whitelist'" link type="primary" @click="openEdit(asRow(row))">
              编辑
            </el-button>
            <el-button v-permission="'marketing:priority:whitelist'" link type="danger" @click="onDelete(asRow(row))">
              删除
            </el-button>
          </template>
        </el-table-column>
      </DataTable>

      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="520px" :close-on-click-modal="false">
      <el-form label-width="110px" @submit.prevent="onSubmit">
        <el-form-item v-if="editingWid === null" label="用户 ID" required>
          <el-input-number v-model="form.user_id" :min="1" :step="1" step-strictly style="width: 200px" />
        </el-form-item>
        <el-form-item v-if="editingWid === null" label="手机号">
          <el-input v-model="form.phone" placeholder="选填，仅作记录" maxlength="20" style="width: 220px" />
        </el-form-item>
        <el-form-item label="限购数量" required>
          <el-input-number v-model="form.max_quantity" :min="1" :step="1" step-strictly />
        </el-form-item>
        <el-form-item label="过期时间">
          <el-date-picker
            v-model="form.expires_at"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="留空表示永不过期"
            style="width: 220px"
          />
        </el-form-item>
        <el-form-item v-if="editingWid !== null" label="状态">
          <el-select v-model="form.status" style="width: 160px">
            <el-option label="有效" :value="1" />
            <el-option label="停用" :value="0" />
            <el-option label="已清理" :value="2" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="onSubmit">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="batchVisible" title="批量导入白名单" width="640px" :close-on-click-modal="false">
      <el-input
        v-model="batchText"
        type="textarea"
        :rows="10"
        placeholder='粘贴 JSON 数组，元素字段：user_id（或 phone）、max_quantity（默认 1）、expires_at。例如：&#10;[{"user_id":1,"max_quantity":2},{"phone":"13800138000"}]'
      />
      <div v-if="batchResult" style="margin-top: 12px">
        <el-alert
          :type="batchResult.fail_count > 0 ? 'warning' : 'success'"
          :closable="false"
          show-icon
          :title="`导入完成：成功 ${batchResult.success_count} 条，失败 ${batchResult.fail_count} 条`"
        />
        <div v-if="batchResult.fail_list.length > 0" style="margin-top: 8px">
          <div style="font-size: 13px; color: #909399; margin-bottom: 4px">失败明细：</div>
          <div v-for="(f, i) in batchResult.fail_list" :key="i" style="font-size: 12px; color: #f56c6c; line-height: 1.8">
            {{ failText(f) }}
          </div>
        </div>
      </div>
      <template #footer>
        <el-button @click="batchVisible = false">关闭</el-button>
        <el-button type="primary" :loading="batchSubmitting" @click="onBatchSubmit">导入</el-button>
      </template>
    </el-dialog>
  </div>
</template>
