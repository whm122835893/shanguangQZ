<script setup lang="ts">
/** 销售报表（契约 §8.18.1）：统计卡 + 明细 + 销售趋势（渐变面积 + 订单折线）+ CSV 导出 */
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { reportSales, customExport } from '@/api/report'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import EChartsWrapper from '@/components/EChartsWrapper.vue'
import { money } from '@/utils/decimal'
import type { EChartsOption } from 'echarts'

type Dict = Record<string, unknown>

const fields: SearchField[] = [{ key: 'date_range', label: '日期范围', type: 'daterange' }]

const query = ref<Dict>({})
const data = ref<Dict | null>(null)
const loading = ref(false)
const exporting = ref(false)

/* ---- 兼容访问工具（后端为宽松对象结构） ---- */
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

/** 日期范围 → date_start/date_end 传参 */
function dateParams(): Record<string, unknown> {
  const range = query.value.date_range
  if (!Array.isArray(range) || range.length < 2) return {}
  return { date_start: String(range[0] ?? ''), date_end: String(range[1] ?? '') }
}

const stats = computed<Dict>(() => asDict(data.value?.stats))

const cards = computed(() => [
  { label: '销售额', value: '¥' + money(sv(stats.value.total_amount)), bg: 'linear-gradient(135deg,#67c23a,#95d475)' },
  { label: '订单数', value: String(nv(stats.value.total_orders)), bg: 'linear-gradient(135deg,#409eff,#53a8ff)' },
  { label: '销量（件）', value: String(nv(stats.value.total_num)), bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)' },
  { label: '客单价', value: '¥' + money(sv(stats.value.avg_price)), bg: 'linear-gradient(135deg,#f56c6c,#fab6b6)' },
  { label: '手续费合计', value: '¥' + money(sv(stats.value.fee_total)), bg: 'linear-gradient(135deg,#909399,#b1b3b8)' },
])

/** 兼容 chart_data / daily / series 三种数组字段名 */
const chartRows = computed<Dict[]>(() => {
  const d = data.value
  if (!d) return []
  return asRows(d.chart_data ?? d.daily ?? d.series)
})

const chartOption = computed<EChartsOption>(() => {
  if (!chartRows.value.length) return {}
  return {
    tooltip: { trigger: 'axis' },
    legend: { data: ['销售额', '订单量'] },
    grid: { left: 60, right: 30, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: chartRows.value.map((r) => sv(r.date ?? r.day)) },
    yAxis: { type: 'value' },
    series: [
      {
        name: '销售额',
        type: 'line',
        smooth: true,
        data: chartRows.value.map((r) => nv(r.amount ?? r.sales ?? r.sale_amount)),
        itemStyle: { color: '#409eff' },
        areaStyle: {
          color: {
            type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(64,158,255,0.35)' },
              { offset: 1, color: 'rgba(64,158,255,0.02)' },
            ],
          },
        },
      },
      {
        name: '订单量',
        type: 'line',
        smooth: true,
        data: chartRows.value.map((r) => nv(r.orders ?? r.count ?? r.order_count)),
        itemStyle: { color: '#67c23a' },
      },
    ],
  }
})

async function fetchReport(): Promise<void> {
  loading.value = true
  try {
    data.value = await reportSales(dateParams())
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  void fetchReport()
}

function onReset(): void {
  void fetchReport()
}

async function onExport(): Promise<void> {
  exporting.value = true
  try {
    await customExport('orders', ['*'], dateParams())
    ElMessage.success('导出成功，CSV 文件已下载')
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    exporting.value = false
  }
}

onMounted(() => {
  void fetchReport()
})
</script>

<template>
  <div v-loading="loading">
    <div class="card">
      <div class="toolbar">
        <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />
        <el-button v-permission="'report:export'" type="primary" :loading="exporting" @click="onExport">
          导出 CSV
        </el-button>
      </div>
    </div>

    <el-row :gutter="16">
      <el-col v-for="c in cards" :key="c.label" :xs="12" :sm="8" :lg="4">
        <div class="metric-card" :style="{ background: c.bg }">
          <div class="metric-label">{{ c.label }}</div>
          <div class="metric-value">{{ c.value }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="card" style="margin-top: 16px">
      <h3 style="margin-top: 0">统计明细</h3>
      <el-descriptions :column="3" border>
        <el-descriptions-item label="销售额">¥{{ money(sv(stats.total_amount)) }}</el-descriptions-item>
        <el-descriptions-item label="订单数">{{ nv(stats.total_orders) }}</el-descriptions-item>
        <el-descriptions-item label="销量（件）">{{ nv(stats.total_num) }}</el-descriptions-item>
        <el-descriptions-item label="客单价">¥{{ money(sv(stats.avg_price)) }}</el-descriptions-item>
        <el-descriptions-item label="手续费合计">¥{{ money(sv(stats.fee_total)) }}</el-descriptions-item>
        <el-descriptions-item label="统计维度">{{ sv(data?.group_by) || 'day' }}</el-descriptions-item>
      </el-descriptions>
    </div>

    <div class="card">
      <h3 style="margin-top: 0">销售趋势</h3>
      <EChartsWrapper v-if="chartRows.length" :option="chartOption" height="340px" />
      <el-empty v-else description="暂无趋势数据" :image-size="64" />
    </div>
  </div>
</template>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
</style>
