<script setup lang="ts">
/** 订单列表（契约 §8.7）：搜索 / 导出 / 详情跳转 + 强制取消 / 标记已支付 / 申请退款 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listOrders, exportOrders, cancelOrder, markOrderPaid, refundOrder } from '@/api/order'
import { money, moneyGt } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

/** 列表行（对齐后端 OrderController::brief 输出） */
type OrderRowVM = {
  id: number
  order_sn: string
  user_id: number
  total_price: string
  order_status: number
  order_type: number
  time: string
}

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

const PAY_STATUS: Record<number, { label: string; type: TagType }> = {
  1: { label: '待付款', type: 'warning' },
  2: { label: '已付款', type: 'success' },
  3: { label: '已发货', type: 'primary' },
  4: { label: '已完成', type: 'success' },
  5: { label: '已取消', type: 'info' },
  6: { label: '退款中', type: 'danger' },
}

const ORDER_TYPES: Record<number, string> = { 1: '首发购买', 2: '市场寄售' }

const fields: SearchField[] = [
  { key: 'order_sn', label: '订单号', type: 'input' },
  { key: 'user_id', label: '用户ID', type: 'input' },
  {
    key: 'status',
    label: '支付状态',
    type: 'select',
    options: [
      { label: '待付款', value: 1 },
      { label: '已付款', value: 2 },
      { label: '已发货', value: 3 },
      { label: '已完成', value: 4 },
      { label: '已取消', value: 5 },
      { label: '退款中', value: 6 },
    ],
  },
  { key: 'date', label: '下单时间', type: 'daterange' },
]

const router = useRouter()
const query = ref<Record<string, unknown>>({})
const list = ref<OrderRowVM[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

/** 日期范围拆分为 date_start / date_end（契约 §12） */
function withDateRange(params: Record<string, unknown>): Record<string, unknown> {
  const range = query.value.date
  if (Array.isArray(range) && range.length === 2 && range[0] && range[1]) {
    params.date_start = String(range[0])
    params.date_end = String(range[1])
  }
  delete params.date
  return params
}

function buildParams(): Record<string, unknown> {
  return withDateRange({ page: page.value, page_size: pageSize.value, ...query.value })
}

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listOrders(buildParams())
    list.value = (res.list ?? []) as OrderRowVM[]
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

async function onExport(): Promise<void> {
  await exportOrders(withDateRange({ ...query.value }))
  ElMessage.success('导出文件已开始下载')
}

function goDetail(row: OrderRowVM): void {
  void router.push(`/order/${row.id}`)
}

/* ---------- 高风险操作：强制取消 / 标记已支付（共用确认弹窗） ---------- */

type DangerAction = 'cancel' | 'markPaid'

const dangerVisible = ref(false)
const dangerAction = ref<DangerAction>('cancel')
const dangerRow = ref<OrderRowVM | null>(null)
const dangerLoading = ref(false)

const dangerTitle = computed(() => (dangerAction.value === 'cancel' ? '强制取消订单' : '标记已支付'))
const dangerMessage = computed(() => {
  const sn = dangerRow.value?.order_sn ?? ''
  return dangerAction.value === 'cancel'
    ? `将强制取消订单 ${sn}（仅限未支付订单），操作不可恢复，请谨慎执行。`
    : `将把订单 ${sn} 标记为已支付并完成库存流转（补支付时间 + 入账），该操作为高风险修复动作。`
})

function openDanger(action: DangerAction, row: OrderRowVM): void {
  dangerAction.value = action
  dangerRow.value = row
  dangerVisible.value = true
}

async function onDangerConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = dangerRow.value
  if (!row) return
  dangerLoading.value = true
  try {
    if (dangerAction.value === 'cancel') {
      await cancelOrder(row.id, payload.reason, payload.password)
      ElMessage.success('订单已强制取消')
    } else {
      await markOrderPaid(row.id, payload.reason, payload.password)
      ElMessage.success('订单已标记为已支付')
    }
    dangerVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    dangerLoading.value = false
  }
}

/* ---------- 申请退款（弹窗输入金额 + 原因） ---------- */

const refundVisible = ref(false)
const refundRow = ref<OrderRowVM | null>(null)
const refundAmount = ref('')
const refundReason = ref('')
const refundLoading = ref(false)

function openRefund(row: OrderRowVM): void {
  refundRow.value = row
  refundAmount.value = ''
  refundReason.value = ''
  refundVisible.value = true
}

async function onRefundSubmit(): Promise<void> {
  const row = refundRow.value
  if (!row) return
  const amount = refundAmount.value.trim()
  if (amount === '' || !/^\d+(\.\d{1,2})?$/.test(amount) || Number(amount) <= 0) {
    ElMessage.error('请输入正确的退款金额')
    return
  }
  if (moneyGt(amount, row.total_price)) {
    ElMessage.error('退款金额不得超过订单实付金额')
    return
  }
  const reason = refundReason.value.trim()
  if (reason === '') {
    ElMessage.error('请填写退款原因')
    return
  }
  refundLoading.value = true
  try {
    await refundOrder(row.id, amount, reason)
    ElMessage.success('退款申请已提交，请到退款管理中审批')
    refundVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    refundLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <div class="toolbar">
      <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />
      <div class="toolbar-actions">
        <el-button v-permission="'order:export'" type="primary" plain @click="onExport">导出</el-button>
      </div>
    </div>

    <DataTable :data="list" :loading="loading">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="order_sn" label="订单号" min-width="180" show-overflow-tooltip />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column label="金额" width="120">
        <template #default="{ row }">¥{{ money((row as OrderRowVM).total_price) }}</template>
      </el-table-column>
      <el-table-column label="支付状态" width="100">
        <template #default="{ row }">
          <el-tag :type="PAY_STATUS[Number((row as OrderRowVM).order_status)]?.type ?? 'info'" size="small">
            {{ PAY_STATUS[Number((row as OrderRowVM).order_status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="类型" width="100">
        <template #default="{ row }">{{ ORDER_TYPES[Number((row as OrderRowVM).order_type)] ?? '未知' }}</template>
      </el-table-column>
      <el-table-column label="下单时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as OrderRowVM).time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="270" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="goDetail(row as OrderRowVM)">详情</el-button>
          <el-button v-permission="'order:manage'" link type="warning" @click="openDanger('cancel', row as OrderRowVM)">强制取消</el-button>
          <el-button v-permission="'order:manage'" link type="primary" @click="openDanger('markPaid', row as OrderRowVM)">标记已支付</el-button>
          <el-button v-permission="'order:refund'" link type="danger" @click="openRefund(row as OrderRowVM)">申请退款</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <ConfirmDialog
      v-model="dangerVisible"
      :title="dangerTitle"
      :message="dangerMessage"
      :loading="dangerLoading"
      @confirm="onDangerConfirm"
    />

    <el-dialog v-model="refundVisible" title="申请退款" width="440px" :close-on-click-modal="false">
      <el-form label-width="90px" @submit.prevent="onRefundSubmit">
        <el-form-item label="订单号">
          <span>{{ refundRow?.order_sn ?? '-' }}</span>
        </el-form-item>
        <el-form-item label="实付金额">
          <span>¥{{ money(refundRow?.total_price) }}</span>
        </el-form-item>
        <el-form-item label="退款金额">
          <el-input v-model="refundAmount" placeholder="不超过实付金额" />
        </el-form-item>
        <el-form-item label="退款原因">
          <el-input v-model="refundReason" type="textarea" :rows="2" maxlength="200" placeholder="将写入审计日志" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="refundVisible = false">取消</el-button>
        <el-button type="primary" :loading="refundLoading" @click="onRefundSubmit">提交申请</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.toolbar { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
.toolbar-actions { flex-shrink: 0; }
</style>
