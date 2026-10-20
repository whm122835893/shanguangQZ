<script setup lang="ts">
/** 异常资金监控（契约 §8.12 /wallet/abnormal）：余额 ≠ 账本 / 负余额 用户清单，只读 + 分页 */
import { onMounted, ref } from 'vue'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { walletAbnormal } from '@/api/wallet'
import { money } from '@/utils/decimal'

type AbnormalRow = {
  user_id: number
  balance: string
  ledger_sum: string
  type: string
  diff: string
}

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

const TYPE_MAP: Record<string, { label: string; type: TagType }> = {
  balance_mismatch: { label: '余额与账本不一致', type: 'danger' },
  negative: { label: '余额为负', type: 'danger' },
}

const fields: SearchField[] = [
  {
    key: 'type',
    label: '异常类型',
    type: 'select',
    options: [
      { label: '余额与账本不一致', value: 'balance_mismatch' },
      { label: '余额为负', value: 'negative' },
    ],
  },
]

const query = ref<Record<string, unknown>>({})
const list = ref<AbnormalRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

function buildParams(): Record<string, unknown> {
  const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value }
  const t = query.value.type
  if (t !== undefined && t !== null && t !== '') params.type = t
  return params
}

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await walletAbnormal(buildParams())
    list.value = (res.list ?? []) as AbnormalRow[]
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

function typeOf(row: AbnormalRow): { label: string; type: TagType } {
  return TYPE_MAP[row.type] ?? { label: row.type || '未知', type: 'info' }
}

function descOf(row: AbnormalRow): string {
  return row.type === 'negative'
    ? `当前余额为负（¥${money(row.balance)}），存在透支风险`
    : `账面余额 ¥${money(row.balance)} 与账本合计 ¥${money(row.ledger_sum)} 不一致，差额 ¥${money(row.diff)}`
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <DataTable :data="list" :loading="loading" empty-text="暂无异常资金记录">
      <el-table-column label="用户ID" width="110" prop="user_id" />
      <el-table-column label="异常类型" width="170">
        <template #default="{ row }">
          <el-tag :type="typeOf(row as AbnormalRow).type" size="small">{{ typeOf(row as AbnormalRow).label }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="描述" min-width="320" show-overflow-tooltip>
        <template #default="{ row }">{{ descOf(row as AbnormalRow) }}</template>
      </el-table-column>
      <el-table-column label="账面余额" width="140" align="right">
        <template #default="{ row }">¥{{ money((row as AbnormalRow).balance) }}</template>
      </el-table-column>
      <el-table-column label="账本合计" width="140" align="right">
        <template #default="{ row }">¥{{ money((row as AbnormalRow).ledger_sum) }}</template>
      </el-table-column>
      <el-table-column label="差额" width="130" align="right">
        <template #default="{ row }">¥{{ money((row as AbnormalRow).diff) }}</template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
  </div>
</template>
