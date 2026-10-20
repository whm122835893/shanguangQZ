<script setup lang="ts">
/** 盲盒活动数据（契约 §8.11.8，127）：开盒聚合只读展示（标量 → el-descriptions；数组 → 简单 el-table） */
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/DataTable.vue'
import { blindboxActivityStats } from '@/api/marketing'

const stats = ref<Record<string, unknown> | null>(null)
const loading = ref(false)

/** 可选盲盒 ID 过滤（不填则聚合全部盲盒） */
const blindboxId = ref<number | undefined>(undefined)

async function load(): Promise<void> {
  loading.value = true
  try {
    const id = blindboxId.value
    stats.value = await blindboxActivityStats(id !== undefined && id > 0 ? { blindbox_id: id } : undefined)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

/** 标量字段展示格式化（rate 类百分比） */
function fmtStat(key: string, value: unknown): string {
  if (value === null || value === undefined || value === '') return '-'
  if (typeof value === 'boolean') return value ? '是' : '否'
  if (typeof value === 'number') {
    if (key.endsWith('_rate') || key === 'rate') return `${value}%`
    return String(value)
  }
  if (typeof value === 'string') return value
  return JSON.stringify(value)
}

type ScalarItemVM = { key: string; value: string }

/** 标量字段 → descriptions */
const scalarItems = computed<ScalarItemVM[]>(() => {
  const s = stats.value
  if (!s) return []
  return Object.entries(s)
    .filter(([, v]) => v === null || typeof v !== 'object')
    .map(([k, v]) => ({ key: k, value: fmtStat(k, v) }))
})

/** 数组字段块（元素为对象的数组 → 简单表格，兼容 daily / item_distribution 等） */
type ArrayBlockVM = { key: string; columns: string[]; rows: Record<string, unknown>[] }

const arrayBlocks = computed<ArrayBlockVM[]>(() => {
  const s = stats.value
  if (!s) return []
  const blocks: ArrayBlockVM[] = []
  for (const [k, v] of Object.entries(s)) {
    if (
      Array.isArray(v) &&
      v.length > 0 &&
      v.every((it) => typeof it === 'object' && it !== null && !Array.isArray(it))
    ) {
      const rows = v as Record<string, unknown>[]
      blocks.push({ key: k, columns: Object.keys(rows[0]), rows })
    }
  }
  return blocks
})

function hasData(key: string): boolean {
  const s = stats.value
  if (!s) return false
  const v = s[key]
  return Array.isArray(v) && v.length > 0
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <div class="card" style="margin-bottom: 16px">
      <el-form inline @submit.prevent="load">
        <el-form-item label="盲盒 ID">
          <el-input-number v-model="blindboxId" :min="1" :step="1" step-strictly placeholder="留空查全部" style="width: 180px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" native-type="submit">查询</el-button>
          <el-button
            @click="
              () => {
                blindboxId = undefined
                load()
              }
            "
          >
            重置
          </el-button>
        </el-form-item>
      </el-form>
    </div>

    <div v-loading="loading" class="card">
      <h3 style="margin-top: 0">盲盒活动数据（只读）</h3>
      <el-descriptions v-if="scalarItems.length > 0" :column="2" border>
        <el-descriptions-item v-for="item in scalarItems" :key="item.key" :label="item.key">
          {{ item.value }}
        </el-descriptions-item>
      </el-descriptions>
      <el-empty v-else description="暂无数据" />

      <template v-for="block in arrayBlocks" :key="block.key">
        <h4 style="margin: 20px 0 8px">{{ block.key }}</h4>
        <el-table v-if="hasData(block.key)" :data="block.rows" stripe size="small">
          <el-table-column v-for="c in block.columns" :key="c" :prop="c" :label="c" min-width="110" />
        </el-table>
        <DataTable v-else :data="[]" empty-text="该维度暂无数据" />
      </template>
    </div>
  </div>
</template>
