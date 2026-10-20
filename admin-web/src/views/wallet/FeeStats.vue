<script setup lang="ts">
/** 手续费统计（契约 §8.12 /wallet/fee-stats）：统计卡 + 汇总 descriptions + 渠道分布 + 每日明细 */
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/DataTable.vue'
import { feeStats } from '@/api/wallet'
import { money, moneyAdd } from '@/utils/decimal'

type DailyFee = { date: string; fee: string; count: number }
type ChannelFee = { channel: string; fee: string }
type FeeSummary = { days: number; totalFee: string; daily: DailyFee[]; channels: ChannelFee[] }

const DAYS_OPTIONS = [7, 14, 30, 90]

const days = ref(30)
const data = ref<FeeSummary | null>(null)
const loading = ref(false)

/** 近 N 天手续费合计（窗口内每日求和） */
const windowFee = computed(() => (data.value ? moneyAdd(...data.value.daily.map((d) => d.fee)) : '0.00'))
const windowCount = computed(() => (data.value ? data.value.daily.reduce((acc, d) => acc + d.count, 0) : 0))
/** 窗口内单日最高手续费 */
const peakFee = computed(() => {
  if (!data.value || !data.value.daily.length) return '0.00'
  return data.value.daily.reduce((acc, d) => (Number(d.fee) > Number(acc) ? d.fee : acc), '0.00')
})
const windowFeeSum = computed(() => Number(windowFee.value))

const cards = computed(() => [
  {
    label: `累计手续费总额（全部）`,
    value: data.value ? `¥${money(data.value.totalFee)}` : '-',
    bg: 'linear-gradient(135deg,#409eff,#53a8ff)',
  },
  {
    label: `近 ${data.value?.days ?? days.value} 天手续费`,
    value: data.value ? `¥${money(windowFee.value)}` : '-',
    bg: 'linear-gradient(135deg,#67c23a,#95d475)',
  },
  {
    label: `近 ${data.value?.days ?? days.value} 天计费笔数`,
    value: data.value ? String(windowCount.value) : '-',
    bg: 'linear-gradient(135deg,#e6a23c,#f3d19e)',
  },
])

async function load(): Promise<void> {
  loading.value = true
  try {
    const res = await feeStats({ days: days.value })
    const rawDaily = Array.isArray(res.daily_stats) ? (res.daily_stats as Record<string, unknown>[]) : []
    const rawChannels = Array.isArray(res.channel_distribution)
      ? (res.channel_distribution as Record<string, unknown>[])
      : []
    data.value = {
      days: Number(res.days) || days.value,
      totalFee: String(res.total_fee ?? '0.00'),
      daily: rawDaily.map((d) => ({ date: String(d.date ?? ''), fee: String(d.fee ?? '0.00'), count: Number(d.count) || 0 })),
      channels: rawChannels.map((c) => ({ channel: String(c.channel ?? ''), fee: String(c.fee ?? '0.00') })),
    }
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function onDaysChange(): void {
  void load()
}

onMounted(() => {
  void load()
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
      <div class="bar">
        <h3 class="block-title">手续费汇总</h3>
        <el-radio-group v-model="days" size="small" @change="onDaysChange">
          <el-radio-button v-for="d in DAYS_OPTIONS" :key="d" :value="d">{{ d }} 天</el-radio-button>
        </el-radio-group>
      </div>
      <el-descriptions v-if="data" :column="2" border>
        <el-descriptions-item label="统计窗口">近 {{ data.days }} 天（按自然日聚合）</el-descriptions-item>
        <el-descriptions-item label="累计手续费总额">¥{{ money(data.totalFee) }}</el-descriptions-item>
        <el-descriptions-item label="窗口内手续费">¥{{ money(windowFee) }}</el-descriptions-item>
        <el-descriptions-item label="窗口内计费笔数">{{ windowCount }} 笔</el-descriptions-item>
        <el-descriptions-item label="单日最高手续费">¥{{ money(peakFee) }}</el-descriptions-item>
        <el-descriptions-item label="涉及支付渠道">{{ data.channels.length }} 个</el-descriptions-item>
      </el-descriptions>
    </div>

    <div class="card">
      <h3 class="block-title">渠道分布（窗口内）</h3>
      <DataTable :data="data?.channels ?? []" :loading="loading" empty-text="窗口内暂无计费成交">
        <el-table-column label="支付渠道" min-width="160">
          <template #default="{ row }">{{ String(row.channel) || 'unknown' }}</template>
        </el-table-column>
        <el-table-column label="手续费" width="150" align="right">
          <template #default="{ row }">¥{{ money(String(row.fee)) }}</template>
        </el-table-column>
        <el-table-column label="占比" width="140">
          <template #default="{ row }">
            <el-progress :percentage="windowFeeSum > 0 ? Math.min(100, (Number(row.fee) / windowFeeSum) * 100) : 0" :stroke-width="10" />
          </template>
        </el-table-column>
      </DataTable>
    </div>

    <div class="card">
      <h3 class="block-title">每日明细（窗口内）</h3>
      <DataTable :data="data?.daily ?? []" :loading="loading" empty-text="窗口内暂无计费成交">
        <el-table-column label="日期" min-width="140">
          <template #default="{ row }">{{ String(row.date) }}</template>
        </el-table-column>
        <el-table-column label="手续费" width="150" align="right">
          <template #default="{ row }">¥{{ money(String(row.fee)) }}</template>
        </el-table-column>
        <el-table-column label="计费笔数" width="120" prop="count" />
      </DataTable>
    </div>
  </div>
</template>

<style scoped>
.metric-card { border-radius: 8px; padding: 18px 20px; color: #fff; }
.metric-label { font-size: 13px; opacity: 0.9; }
.metric-value { font-size: 26px; font-weight: 700; margin-top: 8px; }
.block-title { margin: 0 0 14px; font-size: 16px; }
.bar { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
</style>
