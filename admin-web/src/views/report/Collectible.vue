<script setup lang="ts">
/** 藏品报表（契约 §8.18.3）：转赠/转售统计卡 + 藏品明细表（藏品/销量/销售额） */
import { computed, onMounted, ref } from 'vue'
import { reportCollectibles } from '@/api/report'
import DataTable from '@/components/DataTable.vue'
import { money } from '@/utils/decimal'

type Dict = Record<string, unknown>

const data = ref<Dict | null>(null)
const loading = ref(false)

/* ---- 兼容访问工具 ---- */
function asRows(v: unknown): Dict[] {
  return Array.isArray(v)
    ? v.filter((x): x is Dict => x !== null && typeof x === 'object' && !Array.isArray(x))
    : []
}
function sv(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}
function nv(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

const d = computed<Dict>(() => data.value ?? {})

/** 兼容 list / holding_distribution / top_list 字段名 */
const rows = computed<Dict[]>(() => {
  const src = data.value
  if (!src) return []
  return asRows(src.list ?? src.holding_distribution ?? src.top_list)
})

const cards = computed(() => [
  { label: '转赠总量', value: String(nv(d.value.transfer_count)), bg: 'linear-gradient(135deg,#409eff,#53a8ff)' },
  { label: '转售率', value: nv(d.value.resale_rate).toFixed(2) + '%', bg: 'linear-gradient(135deg,#67c23a,#95d475)' },
  { label: '纳入统计藏品', value: String(rows.value.length), bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)' },
])

function nameOf(r: Dict): string {
  const name = sv(r.name ?? r.title ?? r.collection_name)
  if (name !== '') return name
  const id = sv(r.collection_id ?? r.goods_id ?? r.id)
  return id !== '' ? `#${id}` : '-'
}

function saleNumOf(r: Dict): string {
  return String(nv(r.sold ?? r.sales ?? r.num ?? r.holding ?? r.count))
}

function saleAmountOf(r: Dict): string {
  return '¥' + money(sv(r.amount ?? r.sales_amount ?? r.total_amount ?? r.sale_amount))
}

async function fetchReport(): Promise<void> {
  loading.value = true
  try {
    data.value = await reportCollectibles()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  void fetchReport()
})
</script>

<template>
  <div v-loading="loading">
    <el-row :gutter="16">
      <el-col v-for="c in cards" :key="c.label" :xs="24" :sm="8">
        <div class="metric-card" :style="{ background: c.bg }">
          <div class="metric-label">{{ c.label }}</div>
          <div class="metric-value">{{ c.value }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="card" style="margin-top: 16px">
      <h3 style="margin-top: 0">藏品明细</h3>
      <DataTable :data="rows" :loading="loading" empty-text="暂无藏品数据">
        <el-table-column label="藏品" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">{{ nameOf(row as Dict) }}</template>
        </el-table-column>
        <el-table-column label="销量" width="140">
          <template #default="{ row }">{{ saleNumOf(row as Dict) }}</template>
        </el-table-column>
        <el-table-column label="销售额" width="160">
          <template #default="{ row }">{{ saleAmountOf(row as Dict) }}</template>
        </el-table-column>
      </DataTable>
    </div>
  </div>
</template>
