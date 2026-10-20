<script setup lang="ts">
/** 资金流水（契约 §8.12 /wallet/transactions）：全平台账本流水，只读 + 筛选 + 分页 */
import { onMounted, ref } from 'vue'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listTransactions } from '@/api/wallet'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

type TxRow = {
  id: number
  user_id: number
  direction: number
  amount: string
  balance_before: string
  balance_after: string
  biz_type: string
  biz_id: string
  remark: string
  time: string
}

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

/** 业务类型 → 中文（后端账本实际使用的 biz_type 全集） */
const BIZ_TYPE_MAP: Record<string, string> = {
  recharge: '充值',
  buy: '购买',
  market_sell: '市场卖出',
  market_buy: '市场买入',
  transfer_in: '转赠收入',
  transfer_out: '转赠支出',
  refund: '退款',
  compensation: '赔付',
  order_pay: '订单支付',
  sell: '寄售卖出',
  refund_in: '退款入账',
}

const fields: SearchField[] = [
  { key: 'user_id', label: '用户ID' },
  {
    key: 'type',
    label: '业务类型',
    type: 'select',
    options: [
      { label: '充值', value: 'recharge' },
      { label: '购买', value: 'buy' },
      { label: '市场卖出', value: 'market_sell' },
      { label: '市场买入', value: 'market_buy' },
      { label: '转赠收入', value: 'transfer_in' },
      { label: '转赠支出', value: 'transfer_out' },
      { label: '退款', value: 'refund' },
      { label: '赔付', value: 'compensation' },
    ],
  },
  {
    key: 'direction',
    label: '方向',
    type: 'select',
    options: [
      { label: '收入', value: 1 },
      { label: '支出', value: 2 },
    ],
  },
  { key: 'date', label: '时间', type: 'daterange' },
]

const query = ref<Record<string, unknown>>({})
const list = ref<TxRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

function buildParams(): Record<string, unknown> {
  const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value }
  const q = query.value
  if (q.user_id !== undefined && q.user_id !== null && q.user_id !== '') params.user_id = q.user_id
  if (q.type !== undefined && q.type !== null && q.type !== '') params.type = q.type
  if (q.direction !== undefined && q.direction !== null && q.direction !== '') params.direction = q.direction
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
    const res = await listTransactions(buildParams())
    list.value = (res.list ?? []) as TxRow[]
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

function dirTag(d: number): { label: string; type: TagType } {
  return d === 1 ? { label: '收入', type: 'success' } : { label: '支出', type: 'danger' }
}

function amtText(row: TxRow): string {
  return `${row.direction === 1 ? '+' : '-'}${money(row.amount)}`
}

function bizLabel(row: TxRow): string {
  return BIZ_TYPE_MAP[row.biz_type] ?? row.biz_type ?? '-'
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <DataTable :data="list" :loading="loading">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column label="方向" width="80">
        <template #default="{ row }">
          <el-tag :type="dirTag((row as TxRow).direction).type" size="small">
            {{ dirTag((row as TxRow).direction).label }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="金额" width="120" align="right">
        <template #default="{ row }">
          <span :class="(row as TxRow).direction === 1 ? 'amount-in' : 'amount-out'">
            {{ amtText(row as TxRow) }}
          </span>
        </template>
      </el-table-column>
      <el-table-column label="余额变动" min-width="190" align="center">
        <template #default="{ row }">
          {{ money((row as TxRow).balance_before) }} → {{ money((row as TxRow).balance_after) }}
        </template>
      </el-table-column>
      <el-table-column label="业务类型" width="110">
        <template #default="{ row }">{{ bizLabel(row as TxRow) }}</template>
      </el-table-column>
      <el-table-column label="备注" min-width="150" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as TxRow).remark || '-' }}</template>
      </el-table-column>
      <el-table-column label="时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as TxRow).time) }}</template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
  </div>
</template>

<style scoped>
.amount-in { color: #67c23a; font-weight: 600; }
.amount-out { color: #f56c6c; font-weight: 600; }
</style>
