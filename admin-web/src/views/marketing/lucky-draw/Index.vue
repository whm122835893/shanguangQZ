<script setup lang="ts">
/** 抽奖活动列表（契约 §8.11.4，109~112/117）：列表 + 创建/编辑 + 奖项池入口 + 抽奖记录抽屉 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listLuckyDraw, createLuckyDraw, updateLuckyDraw, listDrawRecords } from '@/api/marketing'
import { fmtDateTime, fmtRange } from '@/utils/date'

/** 抽奖活动行（后端 luckyBrief 口径，free_chances ↔ free_number，status ↔ switch） */
type LuckyRowVM = {
  id: number
  name: string
  start_time: string
  end_time: string
  free_chances: number
  status: number
  prize_count: number
}

function asRow(row: unknown): LuckyRowVM {
  return row as LuckyRowVM
}

function statusMeta(status: number): { label: string; type: 'success' | 'info' } {
  return status === 1 ? { label: '开启', type: 'success' } : { label: '关闭', type: 'info' }
}

const searchFields: SearchField[] = [
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '开启', value: 1 },
      { label: '关闭', value: 0 },
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
    const res = await listLuckyDraw({ page: page.value, page_size: pageSize.value, ...query.value })
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

/* ---------- 创建 / 编辑（110/112） ---------- */

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const submitting = ref(false)

const form = reactive<{
  name: string
  description: string
  start_time: string
  end_time: string
  free_chances: number | undefined
}>({ name: '', description: '', start_time: '', end_time: '', free_chances: 0 })

const dialogTitle = computed(() => (editingId.value === null ? '创建抽奖活动' : '编辑抽奖活动'))

function openCreate(): void {
  editingId.value = null
  Object.assign(form, { name: '', description: '', start_time: '', end_time: '', free_chances: 0 })
  dialogVisible.value = true
}

function openEdit(row: LuckyRowVM): void {
  editingId.value = row.id
  Object.assign(form, {
    name: row.name,
    description: '',
    start_time: toPickerValue(row.start_time),
    end_time: toPickerValue(row.end_time),
    free_chances: row.free_chances,
  })
  dialogVisible.value = true
}

async function onSubmit(): Promise<void> {
  if (form.name.trim() === '') {
    ElMessage.error('请填写活动名称')
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
      await createLuckyDraw({
        name: form.name.trim(),
        description: form.description,
        start_time: form.start_time,
        end_time: form.end_time,
        free_chances: form.free_chances ?? 0,
      })
      ElMessage.success('抽奖活动已创建')
    } else {
      await updateLuckyDraw(editingId.value, {
        name: form.name.trim(),
        start_time: form.start_time,
        end_time: form.end_time,
        free_chances: form.free_chances ?? 0,
      })
      ElMessage.success('抽奖活动已更新')
    }
    dialogVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 奖项池入口 / 抽奖记录抽屉（113/117） ---------- */

function goPrizes(row: LuckyRowVM): void {
  void router.push(`/marketing/lucky-draw/${row.id}/prizes`)
}

/** 抽奖记录行（后端 luckyRecords 口径） */
type DrawRecordVM = {
  id: number
  user_id: number
  nickname: string
  mobile: string
  collectible_id: number
  prize_name: string
  score: number
  create_time: string
}

function asRecord(row: unknown): DrawRecordVM {
  return row as DrawRecordVM
}

const drawerVisible = ref(false)
const drawerRow = ref<LuckyRowVM | null>(null)
const recRows = ref<Record<string, unknown>[]>([])
const recPage = ref(1)
const recPageSize = ref(20)
const recTotal = ref(0)
const recLoading = ref(false)

const drawerTitle = computed(() =>
  drawerRow.value ? `抽奖记录 - ${drawerRow.value.name}（#${drawerRow.value.id}）` : '抽奖记录',
)

async function fetchRecords(): Promise<void> {
  const row = drawerRow.value
  if (!row) return
  recLoading.value = true
  try {
    const res = await listDrawRecords(row.id, { page: recPage.value, page_size: recPageSize.value })
    recRows.value = res.list
    recTotal.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    recLoading.value = false
  }
}

function openRecords(row: LuckyRowVM): void {
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
      <el-button v-permission="'marketing:lucky:create'" type="primary" @click="openCreate">创建活动</el-button>
    </div>

    <DataTable :data="rows" :loading="loading" empty-text="暂无抽奖活动">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column label="活动名称" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ asRow(row).name || '-' }}</template>
      </el-table-column>
      <el-table-column label="活动时间" min-width="300" show-overflow-tooltip>
        <template #default="{ row }">{{ fmtRange(asRow(row).start_time, asRow(row).end_time) }}</template>
      </el-table-column>
      <el-table-column label="每日免费次数" width="120">
        <template #default="{ row }">{{ asRow(row).free_chances }}</template>
      </el-table-column>
      <el-table-column label="奖项数" width="90">
        <template #default="{ row }">{{ asRow(row).prize_count }}</template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag size="small" :type="statusMeta(asRow(row).status).type">
            {{ statusMeta(asRow(row).status).label }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'marketing:lucky:detail'" link type="primary" @click="goPrizes(asRow(row))">
            奖项池
          </el-button>
          <el-button v-permission="'marketing:lucky:detail'" link type="primary" @click="openRecords(asRow(row))">
            记录
          </el-button>
          <el-button v-permission="'marketing:lucky:edit'" link type="primary" @click="openEdit(asRow(row))">
            编辑
          </el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px" :close-on-click-modal="false">
      <el-form label-width="130px" @submit.prevent="onSubmit">
        <el-form-item label="活动名称" required>
          <el-input v-model="form.name" maxlength="255" placeholder="请输入活动名称" />
        </el-form-item>
        <el-form-item v-if="editingId === null" label="活动说明">
          <el-input v-model="form.description" type="textarea" :rows="3" placeholder="选填" />
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
        <el-form-item label="每日免费次数">
          <el-input-number v-model="form.free_chances" :min="0" :step="1" step-strictly />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="onSubmit">保存</el-button>
      </template>
    </el-dialog>

    <el-drawer v-model="drawerVisible" :title="drawerTitle" size="760px">
      <DataTable :data="recRows" :loading="recLoading" empty-text="暂无抽奖记录">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="用户" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">
            #{{ asRecord(row).user_id }} {{ asRecord(row).nickname ? `（${asRecord(row).nickname}）` : '' }}
          </template>
        </el-table-column>
        <el-table-column label="手机号" width="130">
          <template #default="{ row }">{{ asRecord(row).mobile || '-' }}</template>
        </el-table-column>
        <el-table-column label="奖品" min-width="120" show-overflow-tooltip>
          <template #default="{ row }">{{ asRecord(row).prize_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="藏品ID" width="90">
          <template #default="{ row }">{{ asRecord(row).collectible_id || '-' }}</template>
        </el-table-column>
        <el-table-column prop="score" label="积分" width="80" />
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
