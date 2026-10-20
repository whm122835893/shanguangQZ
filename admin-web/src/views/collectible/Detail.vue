<script setup lang="ts">
/** 藏品详情（契约 §8.5）：全字段描述 + 库存恒等式统计条（UI/UX §11.13）+ 空投/销毁记录 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { collectibleDetail, airdropRecords, destroyRecords } from '@/api/collectible'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const loading = ref(true)
const detail = ref<Record<string, unknown> | null>(null)

function pickNum(obj: Record<string, unknown>, keys: string[]): number {
  for (const k of keys) {
    const v = obj[k]
    if (v !== undefined && v !== null && v !== '') {
      const n = Number(v)
      if (Number.isFinite(n)) return n
    }
  }
  return 0
}

const STATUS_META: Record<number, { label: string; type: 'info' | 'warning' | 'success' | 'danger' }> = {
  0: { label: '草稿', type: 'info' },
  1: { label: '待售', type: 'warning' },
  2: { label: '在售', type: 'success' },
  3: { label: '售罄', type: 'danger' },
  4: { label: '下架', type: 'info' },
}

/** 字段中文名（未列出的键用原键名展示） */
const FIELD_LABELS: Record<string, string> = {
  id: 'ID',
  name: '名称',
  cover: '封面',
  main_image: '主图',
  edition: '发行总量',
  price: '价格(元)',
  sale_status: '售卖状态',
  stock_pool: '库存池',
  sold_count: '已售出发售',
  is_resaleable: '允许寄售',
  is_qualification_enabled: '资格购',
  story: '藏品故事',
  royalty_rate: '版税率(%)',
  onsale_at: '开售时间',
  off_sale_at: '下售时间',
  per_user_limit: '每人限购',
  quota_total: '已配置配额',
  quota_allocated: '已配置配额',
  airdrop_count: '已空投数量',
  airdropped: '已空投数量',
  destroy_count: '已销毁数量',
  destroyed: '已销毁数量',
  circulation: '流通量',
  created_at: '创建时间',
  updated_at: '更新时间',
}

function fmtValue(key: string, v: unknown): string {
  if (v === null || v === undefined || v === '') return '-'
  if (key === 'price') return `¥${money(String(v))}`
  if (key === 'sale_status') return STATUS_META[Number(v)]?.label ?? String(v)
  if (key === 'is_resaleable' || key === 'is_qualification_enabled') return Number(v) === 1 ? '开启' : '关闭'
  if (key.endsWith('_at')) return fmtDateTime(String(v))
  if (typeof v === 'object') return '-'
  return String(v)
}

interface DescItem {
  key: string
  label: string
  value: string
  raw: unknown
}

/** 全字段描述项（对象/数组字段跳过） */
const descItems = computed<DescItem[]>(() => {
  const d = detail.value
  if (!d) return []
  return Object.keys(d)
    .filter((k) => d[k] === null || typeof d[k] !== 'object')
    .map((k) => ({ key: k, label: FIELD_LABELS[k] ?? k, value: fmtValue(k, d[k]), raw: d[k] }))
})

/** 库存恒等式统计（UI/UX §11.13） */
const stats = computed(() => {
  const d = detail.value
  if (!d) return null
  const sold = pickNum(d, ['sold_count'])
  const airdrop = pickNum(d, ['airdrop_count', 'airdropped'])
  return {
    edition: pickNum(d, ['edition']),
    sold,
    quota: pickNum(d, ['quota_total', 'quota_allocated', 'quota_count']),
    pool: pickNum(d, ['stock_pool']),
    circulation: pickNum(d, ['circulation']) || sold + airdrop,
  }
})

const statItems = computed(() => {
  const s = stats.value
  if (!s) return []
  return [
    { label: '发行总量', value: s.edition },
    { label: '已售出发售', value: s.sold },
    { label: '已配置配额', value: s.quota },
    { label: '库存池', value: s.pool },
    { label: '流通量', value: s.circulation },
  ]
})

/* ---------- 空投 / 销毁记录 ---------- */
const airdropRows = ref<Record<string, unknown>[]>([])
const airdropTotal = ref(0)
const airdropPage = ref(1)
const airdropPageSize = ref(10)
const destroyRows = ref<Record<string, unknown>[]>([])
const destroyTotal = ref(0)
const destroyPage = ref(1)
const destroyPageSize = ref(10)

async function loadAirdrop(): Promise<void> {
  const d = await airdropRecords(id, { page: airdropPage.value, page_size: airdropPageSize.value })
  airdropRows.value = d.list
  airdropTotal.value = d.total
}

async function loadDestroy(): Promise<void> {
  const d = await destroyRecords(id, { page: destroyPage.value, page_size: destroyPageSize.value })
  destroyRows.value = d.list
  destroyTotal.value = d.total
}

function onAirdropPage(p: number, ps: number): void {
  airdropPage.value = p
  airdropPageSize.value = ps
  void loadAirdrop()
}

function onDestroyPage(p: number, ps: number): void {
  destroyPage.value = p
  destroyPageSize.value = ps
  void loadDestroy()
}

/** 记录表格单元格取值 */
function cellStr(row: unknown, key: string): string {
  const v = (row as Record<string, unknown>)[key]
  if (v === null || v === undefined || v === '') return '-'
  return String(v)
}

function cellTime(row: unknown, key = 'created_at'): string {
  return fmtDateTime(cellStr(row, key))
}

onMounted(async () => {
  try {
    await Promise.all([
      collectibleDetail(id).then((d) => {
        detail.value = d
      }),
      loadAirdrop(),
      loadDestroy(),
    ])
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-loading="loading">
    <el-page-header class="card" content="藏品详情" @back="router.push('/collectible')" />

    <!-- 库存恒等式统计条（UI/UX §11.13） -->
    <div v-if="stats" class="card">
      <div class="stat-bar">
        <div v-for="s in statItems" :key="s.label" class="stat-item">
          <div class="stat-label">{{ s.label }}</div>
          <div class="stat-value">{{ s.value }}</div>
        </div>
      </div>
    </div>

    <!-- 全字段描述 -->
    <div v-if="detail" class="card">
      <el-descriptions :column="3" border>
        <el-descriptions-item
          v-for="item in descItems"
          :key="item.key"
          :label="item.label"
          :span="item.key === 'story' ? 3 : 1"
        >
          <el-image
            v-if="(item.key === 'cover' || item.key === 'main_image') && item.raw"
            :src="String(item.raw)"
            :preview-src-list="[String(item.raw)]"
            preview-teleported
            fit="cover"
            style="width: 80px; height: 80px; border-radius: 4px"
          />
          <span v-else>{{ item.value }}</span>
        </el-descriptions-item>
      </el-descriptions>
    </div>

    <!-- 空投记录 -->
    <div class="card">
      <h3 style="margin-top: 0">空投记录</h3>
      <DataTable :data="airdropRows" empty-text="暂无空投记录">
        <el-table-column label="ID" width="80">
          <template #default="{ row }">{{ cellStr(row, 'id') }}</template>
        </el-table-column>
        <el-table-column label="接收手机号" min-width="130">
          <template #default="{ row }">{{ cellStr(row, 'phone') }}</template>
        </el-table-column>
        <el-table-column label="数量" width="90">
          <template #default="{ row }">{{ cellStr(row, 'quantity') }}</template>
        </el-table-column>
        <el-table-column label="状态" width="110">
          <template #default="{ row }">{{ cellStr(row, 'status') }}</template>
        </el-table-column>
        <el-table-column label="空投时间" width="170">
          <template #default="{ row }">{{ cellTime(row) }}</template>
        </el-table-column>
      </DataTable>
      <Pagination
        :page="airdropPage"
        :page-size="airdropPageSize"
        :total="airdropTotal"
        @change="onAirdropPage"
      />
    </div>

    <!-- 销毁记录 -->
    <div class="card">
      <h3 style="margin-top: 0">销毁记录</h3>
      <DataTable :data="destroyRows" empty-text="暂无销毁记录">
        <el-table-column label="ID" width="80">
          <template #default="{ row }">{{ cellStr(row, 'id') }}</template>
        </el-table-column>
        <el-table-column label="数量" width="90">
          <template #default="{ row }">{{ cellStr(row, 'quantity') }}</template>
        </el-table-column>
        <el-table-column label="销毁原因" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ cellStr(row, 'reason') }}</template>
        </el-table-column>
        <el-table-column label="操作人" width="120">
          <template #default="{ row }">{{ cellStr(row, 'operator') }}</template>
        </el-table-column>
        <el-table-column label="销毁时间" width="170">
          <template #default="{ row }">{{ cellTime(row) }}</template>
        </el-table-column>
      </DataTable>
      <Pagination
        :page="destroyPage"
        :page-size="destroyPageSize"
        :total="destroyTotal"
        @change="onDestroyPage"
      />
    </div>
  </div>
</template>

<style scoped>
.stat-bar { display: flex; flex-wrap: wrap; gap: 12px; }
.stat-item { flex: 1; min-width: 120px; background: #f5f7fa; border-radius: 6px; padding: 12px 16px; text-align: center; }
.stat-label { font-size: 13px; color: #909399; }
.stat-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
</style>
