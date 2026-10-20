<script setup lang="ts">
/** 寄售监控（契约 §8.9.80/81）：挂单列表筛选 + 强制下架（market:manage，密码 + 原因 + 审计） */
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { listListings, delistListing } from '@/api/market'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import SearchForm from '@/components/SearchForm.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

type TagType = 'primary' | 'success' | 'warning' | 'danger' | 'info'

/** market_goods.status（后端枚举）：1=在售 2=已售 3=已下架 4=已锁定 */
const STATUS_OPTIONS = [
  { label: '在售', value: 1 },
  { label: '已售', value: 2 },
  { label: '已下架', value: 3 },
  { label: '已锁定', value: 4 },
]
const STATUS_MAP: Record<number, { label: string; type: TagType }> = {
  1: { label: '在售', type: 'success' },
  2: { label: '已售', type: 'info' },
  3: { label: '已下架', type: 'danger' },
  4: { label: '已锁定', type: 'warning' },
}

const fields = [
  { key: 'collectible_id', label: '藏品ID' },
  { key: 'status', label: '状态', type: 'select' as const, options: STATUS_OPTIONS },
  { key: 'price_min', label: '最低价格' },
  { key: 'price_max', label: '最高价格' },
  { key: 'date_range', label: '挂单时间', type: 'daterange' as const },
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

function rangePair(v: unknown): [string, string] {
  return Array.isArray(v) && v.length >= 2 ? [String(v[0] ?? ''), String(v[1] ?? '')] : ['', '']
}

function buildParams(p: number, size: number): Record<string, unknown> {
  const q = query.value
  const out: Record<string, unknown> = { page: p, page_size: size }
  for (const key of ['collectible_id', 'status', 'price_min', 'price_max'] as const) {
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
    const data = await listListings(buildParams(p, size))
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

/* ---- 强制下架（仅「在售」可下架，后端强校验） ---- */
const dialogVisible = ref(false)
const target = ref<Record<string, unknown> | null>(null)
const submitting = ref(false)
const dialogMessage = computed(() =>
  target.value
    ? `将对挂单 #${nv(target.value.id)} 执行强制下架，该挂单将立即从市场隐藏，操作将写入审计日志。`
    : '',
)

function openDelist(row: Record<string, unknown>): void {
  target.value = row
  dialogVisible.value = true
}

async function onConfirmDelist(payload: { reason: string; password: string }): Promise<void> {
  const row = target.value
  if (!row) return
  submitting.value = true
  try {
    await delistListing(nv(row.id), payload.reason, payload.password)
    ElMessage.success('挂单已强制下架')
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
        <el-table-column label="藏品" min-width="160">
          <template #default="{ row }">
            <div>#{{ sv(row.collectible_id) }}</div>
            <div class="sub-text">{{ sv(row.collection_name) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="卖家" min-width="140">
          <template #default="{ row }">
            <div>#{{ sv(row.user_id) }}</div>
            <div class="sub-text">{{ sv(row.seller_nickname) }}</div>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="110" align="right">
          <template #default="{ row }">¥{{ money(sv(row.price)) }}</template>
        </el-table-column>
        <el-table-column prop="num" label="数量" width="80" />
        <el-table-column label="状态" width="150">
          <template #default="{ row }">
            <el-tag :type="statusOf(row).type" size="small">{{ statusOf(row).label }}</el-tag>
            <el-tooltip
              v-if="sv(row.is_system_delisted) === '1'"
              :content="`下架原因：${sv(row.delist_reason) || '-'}`"
              placement="top"
            >
              <el-tag type="danger" size="small" style="margin-left: 6px">系统下架</el-tag>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column label="挂单时间" width="170">
          <template #default="{ row }">{{ fmtDateTime(sv(row.create_time)) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="110" fixed="right">
          <template #default="{ row }">
            <el-button
              v-if="nv(row.status) === 1"
              v-permission="'market:manage'"
              type="danger"
              link
              size="small"
              @click="openDelist(row)"
            >强制下架</el-button>
          </template>
        </el-table-column>
      </DataTable>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>

    <ConfirmDialog
      v-model="dialogVisible"
      title="强制下架"
      :message="dialogMessage"
      :loading="submitting"
      @confirm="onConfirmDelist"
    />
  </div>
</template>

<style scoped>
.sub-text { font-size: 12px; color: #909399; }
</style>
