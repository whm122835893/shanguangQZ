<script setup lang="ts">
/** 合成活动（契约 §8.11.5，118~122）：列表 + 创建/编辑 + 合成记录抽屉（管理端只读） */
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listSynthesis, createSynthesis, updateSynthesis, listSynthesisRecords } from '@/api/marketing'
import { fmtDateTime, fmtRange } from '@/utils/date'

/** 合成活动行（后端 synthesisBrief 口径，materials 为 JSON 数据） */
type SynthesisRowVM = {
  id: number
  name: string
  target_collectible_id: number
  materials: unknown
  per_user_limit: number
  total_limit: number
  start_time: string
  end_time: string
  status: number
  create_time: string
}

function asRow(row: unknown): SynthesisRowVM {
  return row as SynthesisRowVM
}

function statusMeta(status: number): { label: string; type: 'success' | 'info' } {
  return status === 1 ? { label: '启用', type: 'success' } : { label: '停用', type: 'info' }
}

const searchFields: SearchField[] = [
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

const query = ref<Record<string, unknown>>({})
const rows = ref<Record<string, unknown>[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listSynthesis({ page: page.value, page_size: pageSize.value, ...query.value })
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

/** ISO 时间 → 选择器值（空值 '-' → ''） */
function toPickerValue(v: string | undefined): string {
  if (!v) return ''
  const t = fmtDateTime(v)
  return t === '-' ? '' : t
}

/* ---------- 创建 / 编辑（119/121） ---------- */

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const submitting = ref(false)

const form = reactive<{
  name: string
  target_collectible_id: number | undefined
  per_user_limit: number | undefined
  total_limit: number | undefined
  start_time: string
  end_time: string
}>({ name: '', target_collectible_id: undefined, per_user_limit: 0, total_limit: 0, start_time: '', end_time: '' })

const materialsText = ref('[]')

const dialogTitle = computed(() => (editingId.value === null ? '创建合成活动' : '编辑合成活动'))

function openCreate(): void {
  editingId.value = null
  Object.assign(form, {
    name: '',
    target_collectible_id: undefined,
    per_user_limit: 0,
    total_limit: 0,
    start_time: '',
    end_time: '',
  })
  materialsText.value = '[]'
  dialogVisible.value = true
}

function openEdit(row: SynthesisRowVM): void {
  editingId.value = row.id
  Object.assign(form, {
    name: row.name,
    target_collectible_id: row.target_collectible_id,
    per_user_limit: row.per_user_limit,
    total_limit: row.total_limit,
    start_time: toPickerValue(row.start_time),
    end_time: toPickerValue(row.end_time),
  })
  materialsText.value =
    row.materials === null || row.materials === undefined ? '[]' : JSON.stringify(row.materials, null, 2)
  dialogVisible.value = true
}

async function onSubmit(): Promise<void> {
  if (form.name.trim() === '') {
    ElMessage.error('请填写活动名称')
    return
  }
  if (!form.target_collectible_id) {
    ElMessage.error('请填写目标藏品 ID')
    return
  }
  let materials: unknown = []
  if (materialsText.value.trim() !== '') {
    try {
      materials = JSON.parse(materialsText.value)
    } catch {
      ElMessage.error('materials JSON 解析失败，请检查格式')
      return
    }
  }
  if (form.start_time === '' || form.end_time === '') {
    ElMessage.error('请选择活动起止时间')
    return
  }
  if (form.end_time <= form.start_time) {
    ElMessage.error('结束时间必须晚于开始时间')
    return
  }
  const data: Record<string, unknown> = {
    name: form.name.trim(),
    target_collectible_id: form.target_collectible_id,
    materials,
    per_user_limit: form.per_user_limit ?? 0,
    total_limit: form.total_limit ?? 0,
    start_time: form.start_time,
    end_time: form.end_time,
  }
  submitting.value = true
  try {
    if (editingId.value === null) {
      await createSynthesis(data)
      ElMessage.success('合成活动已创建')
    } else {
      await updateSynthesis(editingId.value, data)
      ElMessage.success('合成活动已更新')
    }
    dialogVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 合成记录抽屉（122） ---------- */

/** 记录行（后端 synthesisRecords 口径） */
type SynthesisRecordVM = {
  id: number
  user_id: number
  nickname: string
  mobile: string
  materials: unknown
  target_collectible_id: number
  status: number
  fail_reason: string
  create_time: string
}

function asRecord(row: unknown): SynthesisRecordVM {
  return row as SynthesisRecordVM
}

function statusText(s: number): { label: string; type: 'success' | 'danger' } {
  return s === 1 ? { label: '成功', type: 'success' } : { label: '失败', type: 'danger' }
}

const drawerVisible = ref(false)
const drawerRow = ref<SynthesisRowVM | null>(null)
const recRows = ref<Record<string, unknown>[]>([])
const recPage = ref(1)
const recPageSize = ref(20)
const recTotal = ref(0)
const recLoading = ref(false)

const drawerTitle = computed(() =>
  drawerRow.value ? `合成记录 - ${drawerRow.value.name}（#${drawerRow.value.id}）` : '合成记录',
)

async function fetchRecords(): Promise<void> {
  const row = drawerRow.value
  if (!row) return
  recLoading.value = true
  try {
    const res = await listSynthesisRecords(row.id, { page: recPage.value, page_size: recPageSize.value })
    recRows.value = res.list
    recTotal.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    recLoading.value = false
  }
}

function openRecords(row: SynthesisRowVM): void {
  drawerRow.value = row
  recPage.value = 1
  drawerVisible.value = true
  void fetchRecords()
}

function onRecPageChange(p: number, s: number): void {
  recPage.value = p
  recPageSize.value = s
  void fetchRecords()
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="searchFields" @search="onSearch" @reset="onSearch" />

    <div style="margin-bottom: 12px">
      <el-button v-permission="'marketing:synthesis:create'" type="primary" @click="openCreate">创建活动</el-button>
    </div>

    <DataTable :data="rows" :loading="loading" empty-text="暂无合成活动">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column label="活动名称" min-width="150" show-overflow-tooltip>
        <template #default="{ row }">{{ asRow(row).name || '-' }}</template>
      </el-table-column>
      <el-table-column label="目标藏品" width="110">
        <template #default="{ row }">#{{ asRow(row).target_collectible_id }}</template>
      </el-table-column>
      <el-table-column label="材料配置" min-width="180" show-overflow-tooltip>
        <template #default="{ row }">{{ JSON.stringify(asRow(row).materials ?? []) }}</template>
      </el-table-column>
      <el-table-column label="每人限合成" width="110">
        <template #default="{ row }">{{ asRow(row).per_user_limit > 0 ? asRow(row).per_user_limit : '不限' }}</template>
      </el-table-column>
      <el-table-column label="合成总量" width="100">
        <template #default="{ row }">{{ asRow(row).total_limit > 0 ? asRow(row).total_limit : '不限' }}</template>
      </el-table-column>
      <el-table-column label="活动时间" min-width="300" show-overflow-tooltip>
        <template #default="{ row }">{{ fmtRange(asRow(row).start_time, asRow(row).end_time) }}</template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag size="small" :type="statusMeta(asRow(row).status).type">
            {{ statusMeta(asRow(row).status).label }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="140" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'marketing:synthesis:detail'" link type="primary" @click="openRecords(asRow(row))">
            合成记录
          </el-button>
          <el-button v-permission="'marketing:synthesis:edit'" link type="primary" @click="openEdit(asRow(row))">
            编辑
          </el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" :close-on-click-modal="false">
      <el-form label-width="140px" @submit.prevent="onSubmit">
        <el-form-item label="活动名称" required>
          <el-input v-model="form.name" maxlength="255" placeholder="请输入活动名称" />
        </el-form-item>
        <el-form-item label="目标藏品 ID" required>
          <el-input-number v-model="form.target_collectible_id" :min="1" :step="1" step-strictly style="width: 200px" />
        </el-form-item>
        <el-form-item label="材料配置（JSON）">
          <el-input
            v-model="materialsText"
            type="textarea"
            :rows="5"
            placeholder='所需材料及数量，如：[{"collectible_id":2,"num":3}]'
          />
        </el-form-item>
        <el-form-item label="每人限合成">
          <el-input-number v-model="form.per_user_limit" :min="0" :step="1" step-strictly />
          <span style="margin-left: 8px; font-size: 12px; color: #909399">0 表示不限</span>
        </el-form-item>
        <el-form-item label="合成总量">
          <el-input-number v-model="form.total_limit" :min="0" :step="1" step-strictly />
          <span style="margin-left: 8px; font-size: 12px; color: #909399">0 表示不限</span>
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
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="onSubmit">保存</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="drawerVisible" :title="drawerTitle" size="760px">
      <DataTable :data="recRows" :loading="recLoading" empty-text="暂无合成记录">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="用户" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            #{{ asRecord(row).user_id }} {{ asRecord(row).nickname ? `（${asRecord(row).nickname}）` : '' }}
          </template>
        </el-table-column>
        <el-table-column label="手机号" width="130">
          <template #default="{ row }">{{ asRecord(row).mobile || '-' }}</template>
        </el-table-column>
        <el-table-column label="消耗材料" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">{{ JSON.stringify(asRecord(row).materials ?? []) }}</template>
        </el-table-column>
        <el-table-column label="结果" width="110">
          <template #default="{ row }">
            <el-tooltip
              v-if="asRecord(row).status !== 1 && asRecord(row).fail_reason"
              :content="asRecord(row).fail_reason"
              placement="top"
            >
              <el-tag size="small" :type="statusText(asRecord(row).status).type">
                {{ statusText(asRecord(row).status).label }}
              </el-tag>
            </el-tooltip>
            <el-tag v-else size="small" :type="statusText(asRecord(row).status).type">
              {{ statusText(asRecord(row).status).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="170">
          <template #default="{ row }">{{ fmtDateTime(asRecord(row).create_time) }}</template>
        </el-table-column>
      </DataTable>
      <Pagination
        :page="recPage"
        :page-size="recPageSize"
        :total="recTotal"
        :loading="recLoading"
        @change="onRecPageChange"
      />
    </el-drawer>
  </div>
</template>
