<script setup lang="ts">
/** 成交记录（契约 §8.9.82）：二级市场已支付订单，只读列表 + 分页 */
import { onMounted, ref } from 'vue'
import { listTrades } from '@/api/market'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import SearchForm from '@/components/SearchForm.vue'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

const fields = [
  { key: 'collectible_id', label: '藏品ID' },
  { key: 'date_range', label: '成交时间', type: 'daterange' as const },
]

const query = ref<Record<string, unknown>>({})
const rows = ref<Record<string, unknown>[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const loading = ref(false)

function sv(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

function rangePair(v: unknown): [string, string] {
  return Array.isArray(v) && v.length >= 2 ? [String(v[0] ?? ''), String(v[1] ?? '')] : ['', '']
}

function buildParams(p: number, size: number): Record<string, unknown> {
  const q = query.value
  const out: Record<string, unknown> = { page: p, page_size: size }
  const cid = q.collectible_id
  if (cid !== undefined && cid !== null && cid !== '') out.collectible_id = cid
  const [start, end] = rangePair(q.date_range)
  if (start !== '') out.start_date = start
  if (end !== '') out.end_date = end
  return out
}

async function load(p = page.value, size = pageSize.value): Promise<void> {
  loading.value = true
  try {
    const data = await listTrades(buildParams(p, size))
    rows.value = data.list
    total.value = data.total
    page.value = data.page
    pageSize.value = data.page_size
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  void load(1)
}
function onPageChange(p: number, size: number): void {
  void load(p, size)
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onSearch" />
    <DataTable :data="rows" :loading="loading">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="order_sn" label="订单号" min-width="180" show-overflow-tooltip />
      <el-table-column label="买家" min-width="140">
        <template #default="{ row }">
          <div>#{{ sv(row.user_id) }}</div>
          <div class="sub-text">{{ sv(row.buyer_nickname) }}</div>
        </template>
      </el-table-column>
      <el-table-column label="藏品" min-width="160">
        <template #default="{ row }">
          <div>{{ sv(row.goods_name) }}</div>
          <div class="sub-text">#{{ sv(row.collectible_id) }}</div>
        </template>
      </el-table-column>
      <el-table-column label="成交价" width="120" align="right">
        <template #default="{ row }">¥{{ money(sv(row.total_price)) }}</template>
      </el-table-column>
      <el-table-column prop="num" label="数量" width="80" />
      <el-table-column prop="pay_channel" label="支付渠道" width="110" />
      <el-table-column label="支付时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(sv(row.pay_time)) }}</template>
      </el-table-column>
    </DataTable>
    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
  </div>
</template>

<style scoped>
.sub-text { font-size: 12px; color: #909399; }
</style>
