<script setup lang="ts">
/** 充值记录（契约 §8.12 /wallet/recharges）：只读列表 + 筛选 + 导出 CSV（wallet:export） */
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listRecharges, exportWallet } from '@/api/wallet'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

type RechargeRow = {
  id: number
  order_sn: string
  user_id: number
  amount: string
  status: number
  channel: string
  pay_no: string
  time: string
}

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

/** 充值单状态（12-payment.sql：1待支付 2已入账 3已关闭） */
const STATUS_MAP: Record<number, { label: string; type: TagType }> = {
  1: { label: '待支付', type: 'warning' },
  2: { label: '已入账', type: 'success' },
  3: { label: '已关闭', type: 'info' },
}

const fields: SearchField[] = [
  { key: 'user_id', label: '用户ID' },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '待支付', value: 1 },
      { label: '已入账', value: 2 },
      { label: '已关闭', value: 3 },
    ],
  },
  { key: 'date', label: '充值时间', type: 'daterange' },
]

const query = ref<Record<string, unknown>>({})
const list = ref<RechargeRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)
const exporting = ref(false)

function buildParams(withPage: boolean): Record<string, unknown> {
  const params: Record<string, unknown> = withPage ? { page: page.value, page_size: pageSize.value } : {}
  const q = query.value
  if (q.user_id !== undefined && q.user_id !== null && q.user_id !== '') params.user_id = q.user_id
  if (q.status !== undefined && q.status !== null && q.status !== '') params.status = q.status
  const range = q.date
  if (Array.isArray(range) && range.length === 2 && range[0] && range[1]) {
    params.date_start = String(range[0])
    params.date_end = String(range[1])
  }
  return params
}

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listRecharges(buildParams(true))
    list.value = (res.list ?? []) as RechargeRow[]
    total.value = res.total
    page.value = res.page
    pageSize.value = res.page_size
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

/** 导出充值记录 CSV（后端流式下载，最多 10000 条） */
async function onExport(): Promise<void> {
  exporting.value = true
  try {
    await exportWallet({ ...buildParams(false), type: 'recharge' })
    ElMessage.success('导出文件已开始下载')
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

    <div class="toolbar">
      <el-button v-permission="'wallet:export'" type="primary" plain :loading="exporting" @click="onExport">
        导出 CSV
      </el-button>
    </div>

    <DataTable :data="list" :loading="loading">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column label="金额" width="130" align="right">
        <template #default="{ row }">¥{{ money((row as RechargeRow).amount) }}</template>
      </el-table-column>
      <el-table-column label="渠道" width="140">
        <template #default="{ row }">{{ (row as RechargeRow).channel || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[(row as RechargeRow).status]?.type ?? 'info'" size="small">
            {{ STATUS_MAP[(row as RechargeRow).status]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as RechargeRow).time) }}</template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
  </div>
</template>

<style scoped>
.toolbar { display: flex; justify-content: flex-end; margin-bottom: 12px; }
</style>
