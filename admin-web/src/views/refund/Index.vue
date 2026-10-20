<script setup lang="ts">
/** 退款列表（契约 §8.8）：状态/日期筛选 + 审批（approve 为真实资金动作，需密码） */
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listRefunds, approveRefund, rejectRefund } from '@/api/refund'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

type RefundRow = {
  id: number
  order_sn: string
  user_id: number
  amount: string
  reason: string
  status: number
  createtime: string
}

const REFUND_STATUS: Record<number, { label: string; type: 'warning' | 'success' | 'danger' }> = {
  0: { label: '待审批', type: 'warning' },
  1: { label: '已通过', type: 'success' },
  2: { label: '已拒绝', type: 'danger' },
}

const fields: SearchField[] = [
  {
    key: 'status',
    label: '退款状态',
    type: 'select',
    options: [
      { label: '待审批', value: 0 },
      { label: '已通过', value: 1 },
      { label: '已拒绝', value: 2 },
    ],
  },
  { key: 'date', label: '申请时间', type: 'daterange' },
]

const query = ref<Record<string, unknown>>({})
const list = ref<RefundRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

/** 日期范围拆分为 date_start / date_end（契约 §12） */
function buildParams(): Record<string, unknown> {
  const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value, ...query.value }
  const range = query.value.date
  if (Array.isArray(range) && range.length === 2 && range[0] && range[1]) {
    params.date_start = String(range[0])
    params.date_end = String(range[1])
  }
  delete params.date
  return params
}

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listRefunds(buildParams())
    list.value = (res.list ?? []) as RefundRow[]
    total.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  page.value = 1
  void fetchList()
}

function onReset(): void {
  page.value = 1
  void fetchList()
}

function onPageChange(p: number, s: number): void {
  page.value = p
  pageSize.value = s
  void fetchList()
}

/* ---------- 审批：批准 / 拒绝（共用确认弹窗，comment + 密码） ---------- */

type AuditAction = 'approve' | 'reject'

const auditVisible = ref(false)
const auditAction = ref<AuditAction>('approve')
const auditRow = ref<RefundRow | null>(null)
const auditLoading = ref(false)

const auditTitle = computed(() => (auditAction.value === 'approve' ? '批准退款' : '拒绝退款'))
const auditMessage = computed(() => {
  const row = auditRow.value
  if (!row) return ''
  return auditAction.value === 'approve'
    ? `将批准退款单 #${row.id} 并向用户（ID ${row.user_id}）退回 ¥${money(row.amount)}。` +
        '批准为真实资金动作：余额即时入账并写入资金流水，操作不可逆，请谨慎执行。'
    : `将拒绝退款单 #${row.id}（订单 ${row.order_sn}）的退款申请，拒绝后不可再次审批。`
})

function openAudit(action: AuditAction, row: RefundRow): void {
  auditAction.value = action
  auditRow.value = row
  auditVisible.value = true
}

async function onAuditConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = auditRow.value
  if (!row) return
  auditLoading.value = true
  try {
    if (auditAction.value === 'approve') {
      await approveRefund(row.id, payload.reason, payload.password)
      ElMessage.success('退款已批准，金额已退入用户余额')
    } else {
      await rejectRefund(row.id, payload.reason, payload.password)
      ElMessage.success('已拒绝该退款申请')
    }
    auditVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    auditLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <DataTable :data="list" :loading="loading">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="order_sn" label="订单号" min-width="180" show-overflow-tooltip />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column label="金额" width="120">
        <template #default="{ row }">¥{{ money(row.amount) }}</template>
      </el-table-column>
      <el-table-column prop="reason" label="原因" min-width="160" show-overflow-tooltip />
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="REFUND_STATUS[Number(row.status)]?.type ?? 'info'" size="small">
            {{ REFUND_STATUS[Number(row.status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="申请时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(row.createtime) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <template v-if="Number(row.status) === 0">
            <el-button v-permission="'refund:approve'" link type="success" @click="openAudit('approve', row as RefundRow)">批准</el-button>
            <el-button v-permission="'refund:approve'" link type="danger" @click="openAudit('reject', row as RefundRow)">拒绝</el-button>
          </template>
          <span v-else class="handled-text">已处理</span>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <ConfirmDialog v-model="auditVisible" :title="auditTitle" :message="auditMessage" :loading="auditLoading" @confirm="onAuditConfirm" />
  </div>
</template>

<style scoped>
.handled-text { color: #909399; font-size: 12px; }
</style>
