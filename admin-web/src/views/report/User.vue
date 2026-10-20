<script setup lang="ts">
/** 用户报表（契约 §8.18.2）：新增/活跃/实名统计卡 + 每日新增折线（渐变面积） */
import { computed, onMounted, ref } from 'vue'
import { reportUsers } from '@/api/report'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import EChartsWrapper from '@/components/EChartsWrapper.vue'
import type { EChartsOption } from 'echarts'

type Dict = Record<string, unknown>

const fields: SearchField[] = [{ key: 'date_range', label: '日期范围', type: 'daterange' }]

const query = ref<Dict>({})
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

function dateParams(): Record<string, unknown> {
  const range = query.value.date_range
  if (!Array.isArray(range) || range.length < 2) return {}
  return { date_start: String(range[0] ?? ''), date_end: String(range[1] ?? '') }
}

const d = computed<Dict>(() => data.value ?? {})

const cards = computed(() => [
  { label: '范围内新增用户', value: String(nv(d.value.new_users)), bg: 'linear-gradient(135deg,#409eff,#53a8ff)' },
  { label: '累计用户', value: String(nv(d.value.total_users)), bg: 'linear-gradient(135deg,#67c23a,#95d475)' },
  { label: '日活 DAU', value: String(nv(d.value.dau)), bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)' },
  { label: '月活 MAU', value: String(nv(d.value.mau)), bg: 'linear-gradient(135deg,#f56c6c,#fab6b6)' },
  { label: '实名率', value: nv(d.value.realname_rate).toFixed(2) + '%', bg: 'linear-gradient(135deg,#909399,#b1b3b8)' },
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
    legend: { data: ['新增用户'] },
    grid: { left: 40, right: 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: chartRows.value.map((r) => sv(r.date ?? r.day)) },
    yAxis: { type: 'value', minInterval: 1 },
    series: [
      {
        name: '新增用户',
        type: 'line',
        smooth: true,
        data: chartRows.value.map((r) => nv(r.new_users ?? r.count ?? r.users)),
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
    ],
  }
})

async function fetchReport(): Promise<void> {
  loading.value = true
  try {
    data.value = await reportUsers(dateParams())
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

onMounted(() => {
  void fetchReport()
})
</script>

<template>
  <div v-loading="loading">
    <div class="card">
      <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />
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
      <h3 style="margin-top: 0">每日新增用户趋势</h3>
      <EChartsWrapper v-if="chartRows.length" :option="chartOption" height="340px" />
      <el-empty v-else description="暂无趋势数据" :image-size="64" />
    </div>
  </div>
</template>
