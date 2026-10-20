<script setup lang="ts">
/** 转赠记录（契约 §8.10.87/89/90）：列表筛选 + 撤销 / 强制回退（transfer:manage，密码 + 原因 + 审计） */
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { listTransfers, revokeTransfer, cancelTransfer } from '@/api/transfer'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import SearchForm from '@/components/SearchForm.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { fmtDateTime } from '@/utils/date'

type TagType = 'primary' | 'success' | 'warning' | 'danger' | 'info'

/** transfer_record.status（后端枚举）：0=待接收(转赠中) 1=已完成(已接受) 2=已拒绝 3=已撤销 4=已强制回退 */
const STATUS_OPTIONS = [
  { label: '转赠中', value: 0 },
  { label: '已接受', value: 1 },
  { label: '已拒绝', value: 2 },
  { label: '已撤销', value: 3 },
  { label: '已强制回退', value: 4 },
]
const STATUS_MAP: Record<number, { label: string; type: TagType }> = {
  0: { label: '转赠中', type: 'warning' },
  1: { label: '已接受', type: 'success' },
  2: { label: '已拒绝', type: 'danger' },
  3: { label: '已撤销', type: 'info' },
  4: { label: '已强制回退', type: 'danger' },
}
/** type：1=藏品 2=盲盒 */
const TYPE_OPTIONS = [
  { label: '藏品', value: 1 },
  { label: '盲盒', value: 2 },
]
const TYPE_MAP: Record<number, { label: string; type: TagType }> = {
  1: { label: '藏品', type: 'primary' },
  2: { label: '盲盒', type: 'warning' },
}

const fields = [
  { key: 'sender_id', label: '发起人ID' },
  { key: 'receiver_id', label: '接收人ID' },
  { key: 'status', label: '状态', type: 'select' as const, options: STATUS_OPTIONS },
  { key: 'type', label: '类型', type: 'select' as const, options: TYPE_OPTIONS },
  { key: 'date_range', label: '发起时间', type: 'daterange' as const },
]

const query = ref<Record<string, unknown>>({})
const rows = ref<Record<string, unknown>[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const loading = ref(false)

function sv(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}
function nv(v: unknown): number {
  return Number(v ?? 0) || 0
}
function statusOf(row: Record<string, unknown>): { label: string; type: TagType } {
  return STATUS_MAP[nv(row.status)] ?? { label: '未知', type: 'info' }
}
function typeOf(row: Record<string, unknown>): { label: string; type: TagType } {
  return TYPE_MAP[nv(row.type)] ?? { label: '未知', type: 'info' }
}

function rangePair(v: unknown): [string, string] {
  return Array.isArray(v) && v.length >= 2 ? [String(v[0] ?? ''), String(v[1] ?? '')] : ['', '']
}

function buildParams(p: number, size: number): Record<string, unknown> {
  const q = query.value
  const out: Record<string, unknown> = { page: p, page_size: size }
  for (const key of ['sender_id', 'receiver_id', 'status', 'type'] as const) {
    const v = q[key]
    if (v !== undefined && v !== null && v !== '') out[key] = v
  }
  const [start, end] = rangePair(q.date_range)
  if (start !== '') out.start_date = start
  if (end !== '') out.end_date = end
  return out
}

async function load(p = page.value, size = pageSize.value): Promise<void> {
  loading.value = true
  try {
    const data = await listTransfers(buildParams(p, size))
    rows.value = data.list
    total.value = data.total
    page.value = data.page
    pageSize.value = data.page_size
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  void load(1)
}
function onPageChange(p: number, size: number): void {
  void load(p, size)
}

/* ---- 撤销（仅「转赠中」）/ 强制回退（仅「已接受」） ---- */
type Action = 'revoke' | 'cancel'
const dialogVisible = ref(false)
const action = ref<Action>('revoke')
const target = ref<Record<string, unknown> | null>(null)
const submitting = ref(false)

const dialogTitle = computed(() => (action.value === 'revoke' ? '撤销转赠' : '强制取消（回退）'))
const dialogMessage = computed(() => {
  const row = target.value
  if (!row) return ''
  const id = nv(row.id)
  return action.value === 'revoke'
    ? `将撤销转赠单 #${id}（转赠中），资产将回退至发起人，操作将写入审计日志。`
    : `将强制回退已完成的转赠单 #${id}，资产将从接收人真实回滚至发起人；若接收人已二次流转将被后端拦截，操作将写入审计日志。`
})

function openAction(row: Record<string, unknown>, act: Action): void {
  target.value = row
  action.value = act
  dialogVisible.value = true
}

async function onConfirmAction(payload: { reason: string; password: string }): Promise<void> {
  const row = target.value
  if (!row) return
  submitting.value = true
  try {
    if (action.value === 'revoke') {
      await revokeTransfer(nv(row.id), payload.reason, payload.password)
      ElMessage.success('转赠已撤销')
    } else {
      await cancelTransfer(nv(row.id), payload.reason, payload.password)
      ElMessage.success('转赠已强制回退')
    }
    dialogVisible.value = false
    await load()
  } catch {
    /* 失败已由请求拦截器提示，弹窗保持打开便于修正 */
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <div class="card">
      <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onSearch" />
      <DataTable :data="rows" :loading="loading">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column label="发起人" min-width="140">
          <template #default="{ row }">
            <div>#{{ sv(row.sender_id) }}</div>
            <div class="sub-text">{{ sv(row.sender_nickname) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="接收人" min-width="140">
          <template #default="{ row }">
            <div>#{{ sv(row.receiver_id) }}</div>
            <div class="sub-text">{{ sv(row.receiver_nickname) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="typeOf(row).type" size="small">{{ typeOf(row).label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="120">
          <template #default="{ row }">
            <el-tag :type="statusOf(row).type" size="small">{{ statusOf(row).label }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="170">
          <template #default="{ row }">{{ fmtDateTime(sv(row.create_time)) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="150" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="nv(row.status) === 0"
              v-permission="'transfer:manage'"
              type="warning"
              link
              size="small"
              @click="openAction(row, 'revoke')"
            >撤销转赠</el-button>
            <el-button
              v-if="nv(row.status) === 1"
              v-permission="'transfer:manage'"
              type="danger"
              link
              size="small"
              @click="openAction(row, 'cancel')"
            >强制取消</el-button>
          </template>
        </el-table-column>
      </DataTable>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>

    <ConfirmDialog
      v-model="dialogVisible"
      :title="dialogTitle"
      :message="dialogMessage"
      :loading="submitting"
      @confirm="onConfirmAction"
    />
  </div>
</template>

<style scoped>
.sub-text { font-size: 12px; color: #909399; }
</style>
