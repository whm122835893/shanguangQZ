<script setup lang="ts">
/** 操作日志（契约 §8.15）：搜索 + 分页列表 + 详情抽屉（完整 payload JSON）+ 导出 */
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listOperationLogs, operationLogDetail, exportOperationLogs } from '@/api/permission'
import { fmtDateTime } from '@/utils/date'

type LogRow = {
  id: number
  admin_name?: string
  action?: string
  target_type?: string
  target_id?: number | string
  summary?: string
  ip?: string
  created_at?: string
}

const fields: SearchField[] = [
  { key: 'admin_name', label: '管理员', type: 'input' },
  { key: 'action', label: '动作', type: 'input', placeholder: '如 user.freeze' },
  { key: 'date_range', label: '日期范围', type: 'daterange' },
]

const query = ref<Record<string, unknown>>({})
const list = ref<LogRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value }
    const adminName = toStr(query.value['admin_name']).trim()
    if (adminName !== '') params.admin_name = adminName
    const action = toStr(query.value['action']).trim()
    if (action !== '') params.action = action
    const range = query.value['date_range']
    if (Array.isArray(range) && range.length === 2 && toStr(range[0]) !== '' && toStr(range[1]) !== '') {
      params.start_date = toStr(range[0])
      params.end_date = toStr(range[1])
    }
    const res = await listOperationLogs(params)
    list.value = res.list as LogRow[]
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

/* ---------- 详情抽屉 ---------- */

const drawerVisible = ref(false)
const detailLoading = ref(false)
const detailRow = ref<LogRow | null>(null)
const detailPayload = ref('')

function prettyJson(v: unknown): string {
  if (v === null || v === undefined) return '-'
  if (typeof v === 'string') {
    try {
      return JSON.stringify(JSON.parse(v), null, 2)
    } catch {
      return v
    }
  }
  try {
    return JSON.stringify(v, null, 2)
  } catch {
    return String(v)
  }
}

function openDetail(row: LogRow): void {
  detailRow.value = row
  detailPayload.value = '加载中…'
  drawerVisible.value = true
  void (async () => {
    detailLoading.value = true
    try {
      const d = await operationLogDetail(row.id)
      detailPayload.value = prettyJson(d['payload'] ?? d['detail'] ?? d)
    } catch {
      detailPayload.value = '加载失败（请求拦截器已提示）'
    } finally {
      detailLoading.value = false
    }
  })()
}

/* ---------- 导出 ---------- */

const exporting = ref(false)

async function onExport(): Promise<void> {
  exporting.value = true
  try {
    const res = await exportOperationLogs()
    const url = res['url'] ?? res['file_url'] ?? res['download_url']
    if (typeof url === 'string' && url !== '') {
      window.open(url, '_blank')
      ElMessage.success('已开始下载')
    } else {
      ElMessage.success('导出任务已提交，请稍后在下载中心查看')
    }
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <div style="margin-bottom: 12px; display: flex; justify-content: flex-end">
      <el-button v-permission="'permission:log:export'" type="success" plain :loading="exporting" @click="onExport">导出</el-button>
    </div>

    <DataTable :data="list" :loading="loading" empty-text="暂无操作日志">
      <el-table-column prop="id" label="ID" width="90" />
      <el-table-column label="管理员" width="120">
        <template #default="{ row }">{{ (row as LogRow).admin_name || '-' }}</template>
      </el-table-column>
      <el-table-column label="动作" width="170" show-overflow-tooltip>
        <template #default="{ row }">
          <el-tag size="small" effect="plain">{{ (row as LogRow).action || '-' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="目标类型" width="120">
        <template #default="{ row }">{{ (row as LogRow).target_type || '-' }}</template>
      </el-table-column>
      <el-table-column label="目标 ID" width="100">
        <template #default="{ row }">{{ (row as LogRow).target_id ?? '-' }}</template>
      </el-table-column>
      <el-table-column label="摘要" min-width="220" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as LogRow).summary || '-' }}</template>
      </el-table-column>
      <el-table-column label="IP" width="140">
        <template #default="{ row }">{{ (row as LogRow).ip || '-' }}</template>
      </el-table-column>
      <el-table-column label="时间" width="180">
        <template #default="{ row }">{{ fmtDateTime((row as LogRow).created_at) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="80" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openDetail(row as LogRow)">详情</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <!-- 详情抽屉 -->
    <el-drawer v-model="drawerVisible" title="操作日志详情" size="480px">
      <el-descriptions v-if="detailRow" :column="1" border size="small" style="margin-bottom: 16px">
        <el-descriptions-item label="ID">{{ detailRow.id }}</el-descriptions-item>
        <el-descriptions-item label="管理员">{{ detailRow.admin_name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="动作">{{ detailRow.action || '-' }}</el-descriptions-item>
        <el-descriptions-item label="目标">{{ detailRow.target_type || '-' }}{{ detailRow.target_id !== undefined ? ` #${detailRow.target_id}` : '' }}</el-descriptions-item>
        <el-descriptions-item label="IP">{{ detailRow.ip || '-' }}</el-descriptions-item>
        <el-descriptions-item label="时间">{{ fmtDateTime(detailRow.created_at) }}</el-descriptions-item>
        <el-descriptions-item label="摘要">{{ detailRow.summary || '-' }}</el-descriptions-item>
      </el-descriptions>
      <h4 style="margin: 0 0 8px">Payload（完整请求载荷）</h4>
      <pre v-loading="detailLoading" class="payload-pre">{{ detailPayload }}</pre>
    </el-drawer>
  </div>
</template>

<style scoped>
.payload-pre {
  margin: 0;
  padding: 12px;
  background: var(--el-fill-color-light);
  border-radius: 6px;
  font-size: 12px;
  line-height: 1.6;
  max-height: 420px;
  overflow: auto;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
