<script setup lang="ts">
/** 财务对账（契约 §8.18.5）：汇总统计 + 资金恒等式逐用户校验（不平账户红色行） */
import { computed, onMounted, ref } from 'vue'
import { reportFinance } from '@/api/report'
import EmptyState from '@/components/EmptyState.vue'
import { money, moneySub } from '@/utils/decimal'

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

const recharge = computed<Dict>(() => asDict(asDict(data.value).recharge))
const fee = computed<Dict>(() => asDict(asDict(data.value).fee))
const refund = computed<Dict>(() => asDict(asDict(data.value).refund))
const balance = computed<Dict>(() => asDict(asDict(data.value).balance))
const rec = computed<Dict>(() => asDict(asDict(data.value).reconciliation))

/** 兼容 mismatch_users / list 字段名 */
const mismatchRows = computed<Dict[]>(() => asRows(rec.value.mismatch_users ?? rec.value.list))
const checkedUsers = computed(() => nv(rec.value.checked_users))

const balancedTag = computed<{ text: string; type: 'success' | 'danger' }>(() =>
  rec.value.balanced === true || mismatchRows.value.length === 0
    ? { text: '全部平衡', type: 'success' }
    : { text: '存在不平账户', type: 'danger' },
)

function diffOf(r: Dict): string {
  return moneySub(sv(r.money), sv(r.delta))
}

function rowClass({ row }: { row: unknown }): string {
  return diffOf(asDict(row)) !== '0.00' ? 'finance-mismatch' : ''
}

async function fetchReport(): Promise<void> {
  loading.value = true
  try {
    data.value = await reportFinance()
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
      <el-col :xs="12" :lg="6">
        <div class="metric-card" style="background: linear-gradient(135deg,#409eff,#53a8ff)">
          <div class="metric-label">充值总额</div>
          <div class="metric-value">¥{{ money(sv(recharge.total)) }}</div>
          <div style="font-size: 12px; opacity: .85; margin-top: 4px">共 {{ nv(recharge.count) }} 笔</div>
        </div>
      </el-col>
      <el-col :xs="12" :lg="6">
        <div class="metric-card" style="background: linear-gradient(135deg,#67c23a,#95d475)">
          <div class="metric-label">手续费收入</div>
          <div class="metric-value">¥{{ money(sv(fee.total)) }}</div>
        </div>
      </el-col>
      <el-col :xs="12" :lg="6">
        <div class="metric-card" style="background: linear-gradient(135deg,#e6a23c,#f3d19e)">
          <div class="metric-label">退款总额</div>
          <div class="metric-value">¥{{ money(sv(refund.total)) }}</div>
          <div style="font-size: 12px; opacity: .85; margin-top: 4px">共 {{ nv(refund.count) }} 笔</div>
        </div>
      </el-col>
      <el-col :xs="12" :lg="6">
        <div class="metric-card" style="background: linear-gradient(135deg,#f56c6c,#fab6b6)">
          <div class="metric-label">用户余额合计</div>
          <div class="metric-value">¥{{ money(sv(balance.total)) }}</div>
        </div>
      </el-col>
    </el-row>

    <div class="card" style="margin-top: 16px">
      <div class="rec-head">
        <h3 style="margin: 0">资金恒等式逐用户校验</h3>
        <el-tag :type="balancedTag.type" size="small">{{ balancedTag.text }}</el-tag>
      </div>
      <el-descriptions :column="3" border style="margin-top: 12px">
        <el-descriptions-item label="校验用户数">{{ checkedUsers }}</el-descriptions-item>
        <el-descriptions-item label="平衡账户">{{ Math.max(0, checkedUsers - mismatchRows.length) }}</el-descriptions-item>
        <el-descriptions-item label="不平账户">{{ mismatchRows.length }}</el-descriptions-item>
      </el-descriptions>

      <el-table
        :data="mismatchRows"
        v-loading="loading"
        style="margin-top: 12px"
        :row-class-name="rowClass"
      >
        <el-table-column label="用户ID" width="120">
          <template #default="{ row }">{{ sv((row as Dict).user_id) || sv((row as Dict).id) || '-' }}</template>
        </el-table-column>
        <el-table-column label="账面余额" min-width="140">
          <template #default="{ row }">¥{{ money(sv((row as Dict).money)) }}</template>
        </el-table-column>
        <el-table-column label="账本合计（流水推算）" min-width="180">
          <template #default="{ row }">¥{{ money(sv((row as Dict).delta)) }}</template>
        </el-table-column>
        <el-table-column label="差额" min-width="140">
          <template #default="{ row }">¥{{ diffOf(row as Dict) }}</template>
        </el-table-column>
        <el-table-column label="校验结果" width="110">
          <template #default="{ row }">
            <el-tag :type="diffOf(row as Dict) === '0.00' ? 'success' : 'danger'" size="small">
              {{ diffOf(row as Dict) === '0.00' ? '平衡' : '不平' }}
            </el-tag>
          </template>
        </el-table-column>
        <template #empty>
          <EmptyState text="校验通过，未发现不平账户" />
        </template>
      </el-table>
    </div>
  </div>
</template>

<style scoped>
.rec-head { display: flex; align-items: center; gap: 10px; }
:deep(.el-table .finance-mismatch td.el-table__cell) { background: #fef0f0 !important; }
</style>
