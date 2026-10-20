<script setup lang="ts">
/** 藏品列表（契约 §8.5）：搜索 + 列表 + 操作下拉 + 分页 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowDown } from '@element-plus/icons-vue'
import { listCollectibles, type CollectibleRow } from '@/api/collectible'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import { money } from '@/utils/decimal'

const router = useRouter()

/** 售卖状态映射（契约 §8.5：0草稿/1待售/2在售/3售罄/4下架） */
const STATUS_META: Record<number, { label: string; type: 'info' | 'warning' | 'success' | 'danger' }> = {
  0: { label: '草稿', type: 'info' },
  1: { label: '待售', type: 'warning' },
  2: { label: '在售', type: 'success' },
  3: { label: '售罄', type: 'danger' },
  4: { label: '下架', type: 'info' },
}

function statusMeta(status: number): { label: string; type: 'info' | 'warning' | 'success' | 'danger' } {
  return STATUS_META[status] ?? { label: `未知(${status})`, type: 'info' }
}

/** 表格插槽 row → 业务行（el-table 作用域 row 为宽类型） */
function asRow(row: unknown): CollectibleRow {
  return row as CollectibleRow
}

const searchFields: SearchField[] = [
  { key: 'name', label: '藏品名称' },
  {
    key: 'sale_status',
    label: '售卖状态',
    type: 'select',
    options: [
      { label: '草稿', value: 0 },
      { label: '待售', value: 1 },
      { label: '在售', value: 2 },
      { label: '售罄', value: 3 },
      { label: '下架', value: 4 },
    ],
  },
  {
    key: 'is_resaleable',
    label: '寄售开关',
    type: 'select',
    options: [
      { label: '关闭', value: 0 },
      { label: '开启', value: 1 },
    ],
  },
  {
    key: 'is_qualification_enabled',
    label: '资格购',
    type: 'select',
    options: [
      { label: '关闭', value: 0 },
      { label: '开启', value: 1 },
    ],
  },
]

const query = ref<Record<string, unknown>>({})
const rows = ref<Record<string, unknown>[]>([])
const loading = ref(false)
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await listCollectibles({ page: page.value, page_size: pageSize.value, ...query.value })
    // CollectibleRow（interface）无隐式索引签名，转 DataTable 所需的通用行类型
    rows.value = data.list as unknown as Record<string, unknown>[]
    total.value = data.total
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  page.value = 1
  void load()
}

function onReset(): void {
  page.value = 1
  void load()
}

function onPageChange(p: number, ps: number): void {
  page.value = p
  pageSize.value = ps
  void load()
}

/** 操作下拉命令 → 子页面路径（契约 §8.5 / 路由 §9.2） */
const ACTION_PATHS: Record<string, string> = {
  detail: '',
  edit: '/edit',
  release: '/release',
  quota: '/quota',
  airdrop: '/airdrop',
  destroy: '/destroy',
  audit: '/audit',
}

function onCommand(cmd: unknown, row: CollectibleRow): void {
  const suffix = ACTION_PATHS[String(cmd)]
  if (suffix === undefined) return
  void router.push(`/collectible/${row.id}${suffix}`)
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <div class="card">
      <SearchForm v-model="query" :fields="searchFields" @search="onSearch" @reset="onReset" />
      <div style="margin-bottom: 12px">
        <el-button v-permission="'collectible:create'" type="primary" @click="router.push('/collectible/create')">
          创建藏品
        </el-button>
      </div>
      <DataTable :data="rows" :loading="loading" empty-text="暂无藏品">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="封面" width="86">
          <template #default="{ row }">
            <el-image
              :src="asRow(row).cover"
              :preview-src-list="[asRow(row).cover]"
              preview-teleported
              fit="cover"
              style="width: 60px; height: 60px; border-radius: 4px; display: block"
            />
          </template>
        </el-table-column>
        <el-table-column prop="name" label="名称" min-width="150" show-overflow-tooltip />
        <el-table-column prop="edition" label="发行总量" width="100" />
        <el-table-column label="价格" width="110">
          <template #default="{ row }">¥{{ money(asRow(row).price) }}</template>
        </el-table-column>
        <el-table-column prop="stock_pool" label="库存池" width="90" />
        <el-table-column prop="sold_count" label="已售" width="90" />
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag size="small" :type="statusMeta(asRow(row).sale_status).type">
              {{ statusMeta(asRow(row).sale_status).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="寄售开关" width="90">
          <template #default="{ row }">
            <el-switch :model-value="asRow(row).is_resaleable === 1" disabled />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-dropdown @command="(cmd: unknown) => onCommand(cmd, asRow(row))">
              <el-button link type="primary">
                操作
                <el-icon style="margin-left: 4px"><ArrowDown /></el-icon>
              </el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item v-permission="'collectible:detail'" command="detail">详情</el-dropdown-item>
                  <el-dropdown-item v-permission="'collectible:edit'" command="edit">编辑</el-dropdown-item>
                  <el-dropdown-item v-permission="'collectible:release'" command="release">发售配置</el-dropdown-item>
                  <el-dropdown-item v-permission="'collectible:quota'" command="quota">配额</el-dropdown-item>
                  <el-dropdown-item v-permission="'collectible:airdrop'" command="airdrop">空投</el-dropdown-item>
                  <el-dropdown-item v-permission="'collectible:destroy'" command="destroy">销毁</el-dropdown-item>
                  <el-dropdown-item v-permission="'collectible:audit'" command="audit">审计</el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </DataTable>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>
  </div>
</template>
