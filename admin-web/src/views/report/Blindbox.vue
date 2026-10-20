<script setup lang="ts">
/** 盲盒报表（契约 §8.18.4）：销量统计卡 + 开盒趋势折线（渐变面积）+ 盲盒明细表 */
import { computed, onMounted, ref } from 'vue'
import { reportBlindboxes } from '@/api/report'
import DataTable from '@/components/DataTable.vue'
import EChartsWrapper from '@/components/EChartsWrapper.vue'
import { money } from '@/utils/decimal'
import type { EChartsOption } from 'echarts'

type Dict = Record<string, unknown>

const data = ref<Dict | null>(null)
const loading = ref(false)

/* ---- 兼容访问工具 ---- */
function asDict(v: unknown): Dict {
  return v !== null && typeof v === 'object' && !Array.isArray(v) ? (v as Dict) : {}
}
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
const sales = computed<Dict>(() => asDict(d.value.sales))

const cards = computed(() => [
  { label: '盲盒款数', value: String(nv(sales.value.boxes)), bg: 'linear-gradient(135deg,#409eff,#53a8ff)' },
  { label: '已售出（件）', value: String(nv(sales.value.sold)), bg: 'linear-gradient(135deg,#67c23a,#95d475)' },
  { label: '销售额', value: '¥' + money(sv(sales.value.amount)), bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)' },
  { label: '开盒率', value: nv(d.value.open_rate).toFixed(2) + '%', bg: 'linear-gradient(135deg,#f56c6c,#fab6b6)' },
  { label: '空奖率', value: nv(d.value.empty_rate).toFixed(2) + '%', bg: 'linear-gradient(135deg,#909399,#b1b3b8)' },
])

/** 兼容 chart_data / daily / series 字段名 */
const chartRows = computed<Dict[]>(() => {
  const src = data.value
  if (!src) return []
  return asRows(src.chart_data ?? src.daily ?? src.series)
})

const chartOption = computed<EChartsOption>(() => {
  if (!chartRows.value.length) return {}
  return {
    tooltip: { trigger: 'axis' },
    legend: { data: ['开盒量'] },
    grid: { left: 40, right: 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: chartRows.value.map((r) => sv(r.date ?? r.day)) },
    yAxis: { type: 'value', minInterval: 1 },
    series: [
      {
        name: '开盒量',
        type: 'line',
        smooth: true,
        data: chartRows.value.map((r) => nv(r.opens ?? r.count ?? r.num)),
        itemStyle: { color: '#e6a23c' },
        areaStyle: {
          color: {
            type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(230,162,60,0.35)' },
              { offset: 1, color: 'rgba(230,162,60,0.02)' },
            ],
          },
        },
      },
    ],
  }
})

/** 兼容 list / item_distribution 字段名 */
const rows = computed<Dict[]>(() => {
  const src = data.value
  if (!src) return []
  return asRows(src.list ?? src.item_distribution)
})

function nameOf(r: Dict): string {
  const name = sv(r.name ?? r.title ?? r.blindbox_name)
  if (name !== '') return name
  const id = sv(r.blindbox_id ?? r.box_id ?? r.id)
  return id !== '' ? `#${id}` : '-'
}

function saleNumOf(r: Dict): string {
  return String(nv(r.sold ?? r.sales ?? r.num ?? r.opens ?? r.count))
}

function saleAmountOf(r: Dict): string {
  return '¥' + money(sv(r.amount ?? r.sales_amount ?? r.total_amount ?? r.sale_amount))
}

async function fetchReport(): Promise<void> {
  loading.value = true
  try {
    data.value = await reportBlindboxes()
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
      <el-col v-for="c in cards" :key="c.label" :xs="12" :sm="8" :lg="4">
        <div class="metric-card" :style="{ background: c.bg }">
          <div class="metric-label">{{ c.label }}</div>
          <div class="metric-value">{{ c.value }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="card" style="margin-top: 16px">
      <h3 style="margin-top: 0">开盒趋势</h3>
      <EChartsWrapper v-if="chartRows.length" :option="chartOption" height="320px" />
      <el-empty v-else description="暂无开盒数据" :image-size="64" />
    </div>

    <div class="card" v-if="rows.length">
      <h3 style="margin-top: 0">盲盒明细</h3>
      <DataTable :data="rows" :loading="loading" empty-text="暂无盲盒数据">
        <el-table-column label="盲盒" min-width="180" show-overflow-tooltip>
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
