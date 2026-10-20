<script setup lang="ts">
/** 价格预警（契约 §8.9.83）：实时扫描低价/高价挂单，只读列表 + 分页 */
import { onMounted, ref } from 'vue'
import { listPriceAlerts } from '@/api/market'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

type TagType = 'primary' | 'success' | 'warning' | 'danger' | 'info'

const rows = ref<Record<string, unknown>[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const loading = ref(false)

function sv(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

/** 偏离度：ratio = 当前价 / 参考价（发行价，后端 4 位小数） */
function deviation(row: Record<string, unknown>): { label: string; type: TagType } {
  const ratio = Number(row.ratio ?? 0)
  const pct = Math.round((ratio - 1) * 100)
  const kind = sv(row.kind)
  if (kind === 'low') return { label: `低于参考 ${Math.abs(pct)}%`, type: 'danger' }
  if (kind === 'high') return { label: `高于参考 ${pct}%`, type: 'warning' }
  return { label: `${pct}%`, type: 'info' }
}

async function load(p = page.value, size = pageSize.value): Promise<void> {
  loading.value = true
  try {
    const data = await listPriceAlerts({ page: p, page_size: size })
    rows.value = data.list
    total.value = data.total
    page.value = data.page
    pageSize.value = data.page_size
  } finally {
    loading.value = false
  }
}

function onPageChange(p: number, size: number): void {
  void load(p, size)
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div class="card">
    <DataTable :data="rows" :loading="loading" empty-text="暂无异常价格挂单">
      <el-table-column prop="listing_id" label="ID" width="90" />
      <el-table-column label="藏品" min-width="180">
        <template #default="{ row }">
          <div>{{ sv(row.collection_name) }}</div>
          <div class="sub-text">#{{ sv(row.collectible_id) }}</div>
        </template>
      </el-table-column>
      <el-table-column label="当前价" width="120" align="right">
        <template #default="{ row }">¥{{ money(sv(row.price)) }}</template>
      </el-table-column>
      <el-table-column label="参考价" width="120" align="right">
        <template #default="{ row }">¥{{ money(sv(row.base_price)) }}</template>
      </el-table-column>
      <el-table-column label="偏离度" width="160">
        <template #default="{ row }">
          <el-tag :type="deviation(row).type" size="small">{{ deviation(row).label }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="检测时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(sv(row.detected_at)) }}</template>
      </el-table-column>
    </DataTable>
    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
  </div>
</template>

<style scoped>
.sub-text { font-size: 12px; color: #909399; }
</style>
