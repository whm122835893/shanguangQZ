<script setup lang="ts">
/** 数据大盘（契约 §8.2）：指标卡片 + 趋势图 + 告警 + 动态 */
import { onMounted, ref, computed } from 'vue'
import { getMetrics, getTrends, getAlerts, getActivities, type Metrics } from '@/api/dashboard'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'
import EChartsWrapper from '@/components/EChartsWrapper.vue'
import type { EChartsOption } from 'echarts'

const metrics = ref<Metrics | null>(null)
const trends = ref<Record<string, unknown> | null>(null)
const alerts = ref<{ list?: Array<Record<string, unknown>> } | null>(null)
const activities = ref<{ list?: Array<Record<string, unknown>> } | null>(null)
const loading = ref(true)

/** 指标卡渐变色板（§11.3） */
const cards = computed(() => {
  const m = metrics.value
  if (!m) return []
  return [
    { label: '注册用户', value: String(m.user_total), sub: `今日 +${m.user_new_today}`, bg: 'linear-gradient(135deg,#409eff,#53a8ff)' },
    { label: '销售额', value: '¥' + money(m.sales_amount), sub: `今日 ¥${money(m.sales_today)}`, bg: 'linear-gradient(135deg,#67c23a,#95d475)' },
    { label: '订单总量', value: String(m.order_total), sub: `今日 +${m.order_today}`, bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)' },
    { label: '市场 GMV', value: '¥' + money(m.market_gmv), sub: '寄售成交', bg: 'linear-gradient(135deg,#f56c6c,#fab6b6)' },
    { label: '待处理退款', value: String(m.pending_refunds), sub: '待审批', bg: 'linear-gradient(135deg,#909399,#b1b3b8)' },
    { label: '风险告警', value: String(m.risk_alerts), sub: '待处理', bg: 'linear-gradient(135deg,#c45656,#e68b8b)' },
  ]
})

const trendOption = computed<EChartsOption>(() => {
  const t = trends.value as { dates?: string[]; sales?: number[]; orders?: number[]; users?: number[] } | null
  if (!t?.dates?.length) return {}
  return {
    tooltip: { trigger: 'axis' },
    legend: { data: ['销售额', '订单量', '新增用户'] },
    grid: { left: 40, right: 20, top: 40, bottom: 30 },
    xAxis: { type: 'category', data: t.dates },
    yAxis: { type: 'value' },
    series: [
      {
        name: '销售额', type: 'line', smooth: true, data: t.sales ?? [],
        areaStyle: {
          color: {
            type: 'linear', x: 0, y: 0, x2: 0, y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(64,158,255,0.35)' },
              { offset: 1, color: 'rgba(64,158,255,0.02)' },
            ],
          },
        },
        itemStyle: { color: '#409eff' },
      },
      { name: '订单量', type: 'line', smooth: true, data: t.orders ?? [], itemStyle: { color: '#67c23a' } },
      { name: '新增用户', type: 'line', smooth: true, data: t.users ?? [], itemStyle: { color: '#e6a23c' } },
    ],
  }
})

onMounted(async () => {
  try {
    const [m, t, a, act] = await Promise.all([
      getMetrics(),
      getTrends({ days: 14 }).catch(() => null),
      getAlerts({ limit: 5 }).catch(() => null),
      getActivities({ limit: 8 }).catch(() => null),
    ])
    metrics.value = m
    trends.value = t
    alerts.value = a
    activities.value = act
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-loading="loading">
    <!-- 指标卡片 -->
    <el-row :gutter="16">
      <el-col v-for="c in cards" :key="c.label" :xs="12" :sm="8" :lg="4">
        <div class="metric-card" :style="{ background: c.bg }">
          <div class="metric-label">{{ c.label }}</div>
          <div class="metric-value">{{ c.value }}</div>
          <div style="font-size: 12px; opacity: .85; margin-top: 4px">{{ c.sub }}</div>
        </div>
      </el-col>
    </el-row>

    <el-row :gutter="16" style="margin-top: 16px">
      <el-col :xs="24" :lg="16">
        <div class="card">
          <h3 style="margin-top:0">近 14 天趋势</h3>
          <EChartsWrapper :option="trendOption" height="320px" />
        </div>
      </el-col>
      <el-col :xs="24" :lg="8">
        <div class="card">
          <h3 style="margin-top:0">风险告警</h3>
          <el-empty v-if="!alerts?.list?.length" description="暂无告警" :image-size="64" />
          <div v-for="(a, i) in alerts?.list ?? []" :key="i" class="alert-item">
            <el-tag :type="(a.level as number) >= 3 ? 'danger' : 'warning'" size="small">
              {{ a.type_name ?? a.type ?? '告警' }}
            </el-tag>
            <span class="alert-text">{{ a.content ?? a.message ?? '' }}</span>
          </div>
        </div>
        <div class="card">
          <h3 style="margin-top:0">最近动态</h3>
          <el-timeline v-if="activities?.list?.length" style="padding-left: 4px">
            <el-timeline-item
              v-for="(act, i) in activities.list"
              :key="i"
              :timestamp="fmtDateTime(String(act.time ?? act.createtime ?? ''))"
            >
              {{ act.content ?? act.action ?? JSON.stringify(act).slice(0, 60) }}
            </el-timeline-item>
          </el-timeline>
          <el-empty v-else description="暂无动态" :image-size="64" />
        </div>
      </el-col>
    </el-row>
  </div>
</template>

<style scoped lang="scss">
.alert-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 0; border-bottom: 1px dashed #f0f2f5;
  font-size: 13px;
}
.alert-text { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
