<script setup lang="ts">
/**
 * 资金守恒校验（契约 §8.12 /wallet/conservation + /wallet/abnormal）
 * 全库口径：diff = SUM(user.money) - SUM(账本恒等式)，非 0 即资金事故；
 * 逐用户校验：余额 ≠ 账本 的用户清单来自 abnormal(type=balance_mismatch)
 */
import { computed, onMounted, ref } from 'vue'
import Pagination from '@/components/Pagination.vue'
import { conservation, walletAbnormal } from '@/api/wallet'
import { money } from '@/utils/decimal'

type ConservationSummary = {
  totalUserBalance: string
  ledgerSum: string
  diff: string
  isConsistent: boolean
  totalRecharge: string
  totalReward: string
  totalFee: string
}

type UnbalancedRow = {
  user_id: number
  balance: string
  ledger_sum: string
  diff: string
  type: string
}

const summary = ref<ConservationSummary | null>(null)
const summaryLoading = ref(true)

const list = ref<UnbalancedRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

const diffNum = computed(() => Number(summary.value?.diff ?? '0'))
const unbalancedCount = computed(() => total.value)

/** 全库账实是否一致：非 0 差额即资金事故，页面显著告警 */
const inconsistent = computed(() => summary.value !== null && !summary.value.isConsistent)

async function loadSummary(): Promise<void> {
  summaryLoading.value = true
  try {
    const res = await conservation()
    summary.value = {
      totalUserBalance: String(res.total_user_balance ?? '0.00'),
      ledgerSum: String(res.ledger_sum ?? '0.00'),
      diff: String(res.diff ?? '0.00'),
      isConsistent: res.is_consistent === true,
      totalRecharge: String(res.total_recharge ?? '0.00'),
      totalReward: String(res.total_reward ?? '0.00'),
      totalFee: String(res.total_fee ?? '0.00'),
    }
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    summaryLoading.value = false
  }
}

async function fetchList(p = page.value, size = pageSize.value): Promise<void> {
  loading.value = true
  try {
    const res = await walletAbnormal({ type: 'balance_mismatch', page: p, page_size: size })
    list.value = (res.list ?? []) as UnbalancedRow[]
    total.value = res.total
    page.value = res.page
    pageSize.value = res.page_size
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function onPageChange(p: number, s: number): void {
  void fetchList(p, s)
}

function rowClass(): string {
  return 'row-risk'
}

onMounted(() => {
  void loadSummary()
  void fetchList()
})
</script>

<template>
  <div v-loading="summaryLoading">
    <el-alert
      v-if="inconsistent"
      title="资金事故告警：账面余额合计与账本恒等式合计不一致，请立即排查异常用户并核对流水！"
      type="error"
      :closable="false"
      show-icon
      style="margin-bottom: 16px"
    />

    <el-row :gutter="16">
      <el-col :xs="24" :sm="6">
        <div class="metric-card">
          <div class="metric-label">账面余额合计（user.money）</div>
          <div class="metric-value">¥{{ money(summary?.totalUserBalance) }}</div>
        </div>
      </el-col>
      <el-col :xs="24" :sm="6">
        <div class="metric-card">
          <div class="metric-label">账本合计（恒等式 SUM）</div>
          <div class="metric-value">¥{{ money(summary?.ledgerSum) }}</div>
        </div>
      </el-col>
      <el-col :xs="24" :sm="6">
        <div class="metric-card" :class="{ 'metric-danger': diffNum !== 0 }">
          <div class="metric-label">全库差额（账面 − 账本）</div>
          <div class="metric-value">¥{{ money(summary?.diff) }}</div>
        </div>
      </el-col>
      <el-col :xs="24" :sm="6">
        <div class="metric-card">
          <div class="metric-label">不平衡用户数</div>
          <div class="metric-value">{{ unbalancedCount }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="card" style="margin-top: 16px">
      <h3 class="block-title">全库口径汇总</h3>
      <el-descriptions v-if="summary" :column="3" border>
        <el-descriptions-item label="总充值入账">¥{{ money(summary.totalRecharge) }}</el-descriptions-item>
        <el-descriptions-item label="奖励类入账合计">¥{{ money(summary.totalReward) }}</el-descriptions-item>
        <el-descriptions-item label="平台手续费合计">¥{{ money(summary.totalFee) }}</el-descriptions-item>
        <el-descriptions-item label="校验结论" :span="3">
          <el-tag :type="summary.isConsistent ? 'success' : 'danger'" effect="dark">
            {{ summary.isConsistent ? '账实一致' : '账实不一致（资金事故）' }}
          </el-tag>
        </el-descriptions-item>
      </el-descriptions>
    </div>

    <div class="card">
      <h3 class="block-title">逐用户校验（余额 ≠ 账本的用户）</h3>
      <el-table v-loading="loading" :data="list" stripe :row-class-name="rowClass" empty-text="全库账实一致，无不平衡用户">
        <el-table-column label="用户ID" width="110" prop="user_id" />
        <el-table-column label="账面余额" width="150" align="right">
          <template #default="{ row }">¥{{ money(String(row.balance)) }}</template>
        </el-table-column>
        <el-table-column label="账本合计" width="150" align="right">
          <template #default="{ row }">¥{{ money(String(row.ledger_sum)) }}</template>
        </el-table-column>
        <el-table-column label="差额" width="140" align="right">
          <template #default="{ row }">¥{{ money(String(row.diff)) }}</template>
        </el-table-column>
        <el-table-column label="是否平衡" width="120">
          <template #default="{ row }">
            <el-tag type="danger" size="small">
              {{ String(row.type) === 'negative' ? '负余额' : '不平衡' }}
            </el-tag>
          </template>
        </el-table-column>
      </el-table>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>
  </div>
</template>

<style scoped>
.metric-card { border-radius: 8px; padding: 16px 18px; background: #f4f7fb; border: 1px solid #e4e9f2; }
.metric-card.metric-danger { background: #fef0f0; border-color: #fbc4c4; }
.metric-label { font-size: 13px; color: #606266; }
.metric-value { font-size: 22px; font-weight: 700; margin-top: 8px; color: #303133; }
.metric-danger .metric-value { color: #f56c6c; }
.block-title { margin: 0 0 14px; font-size: 16px; }

:deep(.el-table .row-risk td) { background-color: #fef0f0 !important; }
</style>
