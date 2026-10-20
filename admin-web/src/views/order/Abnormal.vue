<script setup lang="ts">
/** 异常订单列表（契约 §8.7 GET /orders/abnormal）：类型筛选 + 详情跳转 + 修复 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listAbnormalOrders, repairOrder } from '@/api/order'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

type AbnormalRow = {
  id: number
  order_sn: string
  user_id: number
  total_price: string
  order_status: number
  time: string
  abnormal_type?: string
  age_minutes?: number
}

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

const ABNORMAL_TYPES: Record<string, { label: string; type: TagType }> = {
  unpaid_timeout: { label: '超时未支付', type: 'warning' },
  stock_conflict: { label: '库存冲突', type: 'danger' },
  pay_unknown: { label: '支付结果未知', type: 'primary' },
  other: { label: '其他异常', type: 'info' },
}

const fields: SearchField[] = [
  {
    key: 'type',
    label: '异常类型',
    type: 'select',
    options: [
      { label: '超时未支付', value: 'unpaid_timeout' },
      { label: '库存冲突', value: 'stock_conflict' },
      { label: '支付结果未知', value: 'pay_unknown' },
      { label: '其他', value: 'other' },
    ],
  },
]

const router = useRouter()
const query = ref<Record<string, unknown>>({})
const list = ref<AbnormalRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listAbnormalOrders({ page: page.value, page_size: pageSize.value, ...query.value })
    list.value = (res.list ?? []) as AbnormalRow[]
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

function goDetail(row: AbnormalRow): void {
  void router.push(`/order/${row.id}`)
}

/* ---------- 修复（ConfirmDialog：原因 + 管理员密码） ---------- */

/** 异常类型 → 修复方式：超时未支付仅关单，其余补发货入账 */
function repairTypeOf(row: AbnormalRow): string {
  return row.abnormal_type === 'unpaid_timeout' ? 'timeout_cancel' : 'supplement_delivery'
}

const repairVisible = ref(false)
const repairRow = ref<AbnormalRow | null>(null)
const repairLoading = ref(false)

const repairMessage = computed(() => {
  const row = repairRow.value
  if (!row) return ''
  return repairTypeOf(row) === 'timeout_cancel'
    ? `将关闭超时未支付订单 ${row.order_sn}（置为已取消终态），操作不可逆，请谨慎执行。`
    : `将为订单 ${row.order_sn} 补发货入账（补齐用户库存持有记录），请先核实支付与库存状态。`
})

function openRepair(row: AbnormalRow): void {
  repairRow.value = row
  repairVisible.value = true
}

async function onRepairConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = repairRow.value
  if (!row) return
  repairLoading.value = true
  try {
    await repairOrder(row.id, repairTypeOf(row), payload.reason, payload.password)
    ElMessage.success('修复操作已执行')
    repairVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    repairLoading.value = false
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
        <template #default="{ row }">¥{{ money((row as AbnormalRow).total_price) }}</template>
      </el-table-column>
      <el-table-column label="异常类型" width="130">
        <template #default="{ row }">
          <el-tag :type="ABNORMAL_TYPES[String((row as AbnormalRow).abnormal_type)]?.type ?? 'info'" size="small">
            {{ ABNORMAL_TYPES[String((row as AbnormalRow).abnormal_type)]?.label ?? ((row as AbnormalRow).abnormal_type || '未知') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="下单时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as AbnormalRow).time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="160" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="goDetail(row as AbnormalRow)">详情</el-button>
          <el-button v-permission="'order:manage'" link type="danger" @click="openRepair(row as AbnormalRow)">修复</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <ConfirmDialog v-model="repairVisible" title="修复异常订单" :message="repairMessage" :loading="repairLoading" @confirm="onRepairConfirm" />
  </div>
</template>
