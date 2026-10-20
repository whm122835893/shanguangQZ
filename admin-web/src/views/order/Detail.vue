<script setup lang="ts">
/** 订单详情（契约 §8.7）：orderDetail 全字段 descriptions + 退款记录 + 危险修复区 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { orderDetail, repairOrder } from '@/api/order'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

type DetailData = Record<string, unknown>

type RefundItem = {
  id: number
  refund_no: string
  amount: string
  status: number
  reason: string
  time: string
}

const STATUS_LABELS: Record<number, string> = {
  1: '待付款',
  2: '已付款',
  3: '已发货',
  4: '已完成',
  5: '已取消',
  6: '退款中',
}

const ORDER_TYPES: Record<number, string> = { 1: '首发购买', 2: '市场寄售' }

const REFUND_LABELS: Record<number, { label: string; type: 'warning' | 'success' | 'danger' }> = {
  0: { label: '待审批', type: 'warning' },
  1: { label: '已退款', type: 'success' },
  2: { label: '已拒绝', type: 'danger' },
}

/** descriptions 字段中文映射，未收录字段展示原 key */
const FIELD_LABELS: Record<string, string> = {
  id: '订单ID',
  order_sn: '订单号',
  user_id: '用户ID',
  user_name: '用户账号',
  order_type: '订单类型',
  order_status: '订单状态',
  pay_status: '支付状态',
  is_paid: '是否已支付',
  goods_id: '商品ID',
  goods_name: '商品名称',
  num: '数量',
  total_amount: '订单金额',
  total_price: '实付金额',
  fee: '手续费',
  pay_channel: '支付渠道',
  source: '来源',
  createtime: '下单时间',
  time: '下单时间',
  pay_time: '支付时间',
  ip_address: 'IP 地址',
}

const route = useRoute()
const router = useRouter()
const orderId = Number(route.params.id)

const detail = ref<DetailData | null>(null)
const loading = ref(true)

function fmtField(key: string, value: unknown): string {
  if (value === null || value === undefined || value === '') return '-'
  if (/amount|price|fee/.test(key)) return '¥' + money(String(value))
  if (/time$/.test(key)) return fmtDateTime(String(value))
  if (typeof value === 'boolean') return value ? '是' : '否'
  if (key === 'order_status' || key === 'pay_status') return STATUS_LABELS[Number(value)] ?? String(value)
  if (key === 'order_type') return ORDER_TYPES[Number(value)] ?? String(value)
  return String(value)
}

const entries = computed<Array<{ key: string; label: string; value: string }>>(() => {
  const d = detail.value
  if (!d) return []
  return Object.entries(d)
    .filter(([k]) => k !== 'refunds')
    .map(([k, v]) => ({ key: k, label: FIELD_LABELS[k] ?? k, value: fmtField(k, v) }))
})

const refunds = computed<RefundItem[]>(() => {
  const arr = detail.value?.refunds
  return Array.isArray(arr) ? (arr as RefundItem[]) : []
})

async function load(): Promise<void> {
  loading.value = true
  try {
    detail.value = await orderDetail(orderId)
  } catch {
    /* request 拦截器已统一提示（404 等） */
  } finally {
    loading.value = false
  }
}

function goBack(): void {
  void router.push('/order')
}

/* ---------- 危险操作区：修复异常订单 ---------- */

const REPAIR_TYPES: Array<{ label: string; value: string; desc: string }> = [
  { label: '补发货', value: 'supplement_delivery', desc: '为已支付订单补齐库存持有记录（入账）' },
  { label: '补入账', value: 'supplement_entry', desc: '仅修正账务入账状态，不动库存' },
  { label: '仅关单', value: 'timeout_cancel', desc: '将超时未支付订单关闭为已取消终态' },
]

const repairType = ref('supplement_delivery')
const repairVisible = ref(false)
const repairLoading = ref(false)

const currentRepair = computed(() => REPAIR_TYPES.find((t) => t.value === repairType.value) ?? REPAIR_TYPES[0])

const repairMessage = computed(
  () =>
    `将按「${currentRepair.value.label}」方式修复订单 #${orderId}：${currentRepair.value.desc}。` +
    '该操作直接修改订单数据且不可逆，将写入审计日志，请确认后执行。',
)

function openRepair(): void {
  repairVisible.value = true
}

async function onRepairConfirm(payload: { reason: string; password: string }): Promise<void> {
  repairLoading.value = true
  try {
    await repairOrder(orderId, repairType.value, payload.reason, payload.password)
    ElMessage.success('修复操作已执行')
    repairVisible.value = false
    void load()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    repairLoading.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div v-loading="loading">
    <div class="card">
      <div class="detail-header">
        <h3 style="margin: 0">订单详情 #{{ orderId }}</h3>
        <el-button @click="goBack">返回列表</el-button>
      </div>
      <el-descriptions :column="3" border>
        <el-descriptions-item v-for="e in entries" :key="e.key" :label="e.label">
          {{ e.value }}
        </el-descriptions-item>
      </el-descriptions>
    </div>

    <div v-if="refunds.length" class="card">
      <h3 style="margin-top: 0">退款记录</h3>
      <el-table :data="refunds" size="small" stripe>
        <el-table-column prop="id" label="退款ID" width="90" />
        <el-table-column prop="refund_no" label="退款单号" min-width="180" show-overflow-tooltip />
        <el-table-column label="金额" width="110">
          <template #default="{ row }">¥{{ money(row.amount) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="REFUND_LABELS[Number(row.status)]?.type ?? 'info'" size="small">
              {{ REFUND_LABELS[Number(row.status)]?.label ?? '未知' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="reason" label="原因" show-overflow-tooltip />
        <el-table-column label="时间" width="170">
          <template #default="{ row }">{{ fmtDateTime(row.time) }}</template>
        </el-table-column>
      </el-table>
    </div>

    <div class="card">
      <div class="danger-bar">危险操作区：以下操作将直接修改订单数据并写入审计日志，请谨慎执行。</div>
      <el-form inline @submit.prevent="openRepair">
        <el-form-item label="修复方式">
          <el-select v-model="repairType" style="width: 220px">
            <el-option v-for="t in REPAIR_TYPES" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <span class="repair-desc">{{ currentRepair.desc }}</span>
        </el-form-item>
        <el-form-item>
          <el-button v-permission="'order:manage'" type="danger" @click="openRepair">执行修复</el-button>
        </el-form-item>
      </el-form>
    </div>

    <ConfirmDialog v-model="repairVisible" title="修复异常订单" :message="repairMessage" :loading="repairLoading" @confirm="onRepairConfirm" />
  </div>
</template>

<style scoped>
.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}
.repair-desc { color: #909399; font-size: 13px; }
</style>
