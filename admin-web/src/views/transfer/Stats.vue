<script setup lang="ts">
/** 转赠统计（契约 §8.10.91/92）：统计卡 + 每日趋势折线（渐变填充）+ 异常转赠监控（transfer:monitor） */
import { computed, onMounted, ref } from 'vue'
import { transferStats, abnormalTransfers } from '@/api/transfer'
import EChartsWrapper from '@/components/EChartsWrapper.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import type { EChartsOption } from 'echarts'

type TagType = 'primary' | 'success' | 'warning' | 'danger' | 'info'

const STATS_DAYS = 14

const summary = ref<{ count: number; collectibles: number; blindboxes: number } | null>(null)
const daily = ref<Array<{ date: string; count: number }>>([])
const statsLoading = ref(true)

const cards = computed(() => [
  {
    label: `转赠总量（近${STATS_DAYS}天）`,
    value: summary.value ? String(summary.value.count) : '-',
    bg: 'linear-gradient(135deg,#409eff,#53a8ff)',
  },
  {
    label: '藏品转赠',
    value: summary.value ? String(summary.value.collectibles) : '-',
    bg: 'linear-gradient(135deg,#67c23a,#95d475)',
  },
  {
    label: '盲盒转赠',
    value: summary.value ? String(summary.value.blindboxes) : '-',
    bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)',
  },
])

const chartOption = computed<EChartsOption>(() => {
  if (!daily.value.length) return {}
  return {
    tooltip: { trigger: 'axis' },
    grid: { left: 40, right: 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: daily.value.map((d) => d.date) },
    yAxis: { type: 'value', minInterval: 1 },
    series: [
      {
        name: '转赠次数',
        type: 'line',
        smooth: true,
        data: daily.value.map((d) => d.count),
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

async function loadStats(): Promise<void> {
  statsLoading.value = true
  try {
    const data = await transferStats(STATS_DAYS)
    summary.value = {
      count: Number(data.total_count) || 0,
      collectibles: Number(data.total_collectibles) || 0,
      blindboxes: Number(data.total_blindboxes) || 0,
    }
    daily.value = data.daily_stats.map((d) => {
      const r = d as Record<string, unknown>
      return { date: String(r.date ?? ''), count: Number(r.count ?? 0) || 0 }
    })
  } finally {
    statsLoading.value = false
  }
}

/* ---- 异常转赠（滑动 7 日窗口：高频发送/接收、反复互转对） ---- */
const KIND_MAP: Record<string, { label: string; type: TagType }> = {
  high_frequency_sender: { label: '高频发送', type: 'warning' },
  high_frequency_receiver: { label: '高频接收', type: 'warning' },
  repeated_pair: { label: '反复互转', type: 'danger' },
}

const abnormalRows = ref<Record<string, unknown>[]>([])
const abnormalTotal = ref(0)
const abnormalPage = ref(1)
const abnormalPageSize = ref(20)
const abnormalLoading = ref(false)

function sv(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}
function kindOf(row: Record<string, unknown>): { label: string; type: TagType } {
  return KIND_MAP[sv(row.kind)] ?? { label: sv(row.kind) || '未知', type: 'info' }
}
function usersOf(row: Record<string, unknown>): string {
  return sv(row.kind) === 'repeated_pair'
    ? `#${sv(row.user_a)} ↔ #${sv(row.user_b)}`
    : `#${sv(row.user_id)}`
}

async function loadAbnormal(p = abnormalPage.value, size = abnormalPageSize.value): Promise<void> {
  abnormalLoading.value = true
  try {
    const data = await abnormalTransfers({ page: p, page_size: size })
    abnormalRows.value = data.list
    abnormalTotal.value = data.total
    abnormalPage.value = data.page
    abnormalPageSize.value = data.page_size
  } finally {
    abnormalLoading.value = false
  }
}

function onAbnormalPage(p: number, size: number): void {
  void loadAbnormal(p, size)
}

onMounted(() => {
  void loadStats()
  void loadAbnormal()
})
</script>

<template>
  <div>
    <el-row v-loading="statsLoading" :gutter="16">
      <el-col v-for="c in cards" :key="c.label" :xs="24" :sm="8">
        <div class="metric-card" :style="{ background: c.bg }">
          <div class="metric-label">{{ c.label }}</div>
          <div class="metric-value">{{ c.value }}</div>
        </div>
      </el-col>
    </el-row>

    <div v-loading="statsLoading" class="card" style="margin-top: 16px">
      <h3 style="margin-top: 0">每日转赠趋势</h3>
      <EChartsWrapper v-if="daily.length" :option="chartOption" height="320px" />
      <el-empty v-else description="暂无转赠数据" :image-size="64" />
    </div>

    <div v-permission="'transfer:monitor'" class="card">
      <h3 style="margin-top: 0">异常转赠（滑动 7 日窗口）</h3>
      <DataTable :data="abnormalRows" :loading="abnormalLoading" empty-text="暂无异常转赠">
        <el-table-column label="异常类型" width="140">
          <template #default="{ row }">
            <el-tag :type="kindOf(row).type" size="small">{{ kindOf(row).label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="涉及用户" min-width="160">
          <template #default="{ row }">{{ usersOf(row) }}</template>
        </el-table-column>
        <el-table-column prop="count" label="次数" width="100" />
        <el-table-column label="监测窗口" width="120">
          <template #default="{ row }">{{ sv(row.window) }}</template>
        </el-table-column>
      </DataTable>
      <Pagination
        :page="abnormalPage"
        :page-size="abnormalPageSize"
        :total="abnormalTotal"
        :loading="abnormalLoading"
        @change="onAbnormalPage"
      />
    </div>
  </div>
</template>
