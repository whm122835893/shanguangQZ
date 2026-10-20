<script setup lang="ts">
/** 盲盒列表（契约 §8.6）：搜索 + 列表 + 操作下拉跳转子页 + 分页 */
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowDown } from '@element-plus/icons-vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import SearchForm from '@/components/SearchForm.vue'
import { listBlindboxes, deleteBlindbox } from '@/api/blindbox'
import { money } from '@/utils/decimal'

const router = useRouter()

const str = (v: unknown): string => (v === null || v === undefined ? '' : String(v))
const num = (v: unknown): number => (v === null || v === undefined || v === '' ? 0 : Number(v))

/** 状态机：0=草稿 1=发售中 2=已售罄 3=已下架（BlindboxController） */
const STATUS_MAP: Record<number, { label: string; tag: 'info' | 'success' | 'warning' | 'danger' }> = {
  0: { label: '草稿', tag: 'info' },
  1: { label: '发售中', tag: 'success' },
  2: { label: '已售罄', tag: 'warning' },
  3: { label: '已下架', tag: 'danger' },
}

const searchFields = [
  { key: 'name', label: '盲盒名称', type: 'input' as const, placeholder: '请输入盲盒名称' },
  {
    key: 'status', label: '状态', type: 'select' as const,
    options: [
      { label: '草稿', value: 0 },
      { label: '发售中', value: 1 },
      { label: '已售罄', value: 2 },
      { label: '已下架', value: 3 },
    ],
  },
]

const search = ref<Record<string, unknown>>({})
const list = ref<Record<string, unknown>[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(20)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const data = await listBlindboxes({ page: page.value, page_size: pageSize.value, ...search.value })
    list.value = data.list
    total.value = data.total
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  page.value = 1
  void fetchList()
}

function onPageChange(p: number, s: number): void {
  page.value = p
  pageSize.value = s
  void fetchList()
}

/** 操作下拉 → 跳转子页 / 删除 */
interface BoxAction { action: string; id: number }
function onAction(cmd: BoxAction): void {
  if (cmd.action === 'delete') {
    deleteId.value = cmd.id
    deleteVisible.value = true
    return
  }
  const routes: Record<string, string> = {
    detail: `/blindbox/${cmd.id}`,
    edit: `/blindbox/${cmd.id}/edit`,
    items: `/blindbox/${cmd.id}/items`,
    release: `/blindbox/${cmd.id}/release`,
    airdrop: `/blindbox/${cmd.id}/airdrop`,
    destroy: `/blindbox/${cmd.id}/destroy`,
    audit: `/blindbox/${cmd.id}/audit`,
  }
  void router.push(routes[cmd.action] ?? `/blindbox/${cmd.id}`)
}

/** 删除（仅草稿，密码确认） */
const deleteVisible = ref(false)
const deleteLoading = ref(false)
const deleteId = ref(0)
async function onDelete(payload: { reason: string; password: string }): Promise<void> {
  deleteLoading.value = true
  try {
    await deleteBlindbox(deleteId.value, payload.password)
    ElMessage.success('删除成功')
    deleteVisible.value = false
    void fetchList()
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div>
    <div class="card">
      <div class="head-bar">
        <h3 class="head-title">盲盒管理</h3>
        <el-button v-permission="'blindbox:create'" type="primary" @click="router.push('/blindbox/create')">
          创建盲盒
        </el-button>
      </div>
      <SearchForm v-model="search" :fields="searchFields" @search="onSearch" @reset="onSearch" />
      <DataTable :data="list" :loading="loading">
        <el-table-column label="封面" width="90">
          <template #default="{ row }">
            <el-image
              v-if="str(row.cover)"
              :src="str(row.cover)"
              :preview-src-list="[str(row.cover)]"
              preview-teleported
              fit="cover"
              style="width: 48px; height: 48px; border-radius: 4px; display: block"
            />
            <span v-else>-</span>
          </template>
        </el-table-column>
        <el-table-column label="名称" prop="name" min-width="160" show-overflow-tooltip />
        <el-table-column label="价格" width="100">
          <template #default="{ row }">¥{{ money(str(row.price)) }}</template>
        </el-table-column>
        <el-table-column label="库存池" width="90">
          <template #default="{ row }">{{ num(row.stock_pool) }}</template>
        </el-table-column>
        <el-table-column label="已售" width="90">
          <template #default="{ row }">{{ num(row.sold) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="STATUS_MAP[num(row.sale_status)]?.tag ?? 'info'" size="small">
              {{ STATUS_MAP[num(row.sale_status)]?.label ?? '未知' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-dropdown trigger="click" @command="onAction">
              <el-button link type="primary">
                操作
                <el-icon class="el-icon--right"><ArrowDown /></el-icon>
              </el-button>
              <template #dropdown>
                <el-dropdown-menu>
                  <el-dropdown-item v-permission="'blindbox:detail'" :command="{ action: 'detail', id: num(row.id) }">详情</el-dropdown-item>
                  <el-dropdown-item v-permission="'blindbox:edit'" :command="{ action: 'edit', id: num(row.id) }">编辑</el-dropdown-item>
                  <el-dropdown-item v-permission="'blindbox:config'" :command="{ action: 'items', id: num(row.id) }">子藏品配置</el-dropdown-item>
                  <el-dropdown-item v-permission="'blindbox:release'" :command="{ action: 'release', id: num(row.id) }">发售配置</el-dropdown-item>
                  <el-dropdown-item v-permission="'blindbox:airdrop'" :command="{ action: 'airdrop', id: num(row.id) }">空投</el-dropdown-item>
                  <el-dropdown-item v-permission="'blindbox:destroy'" :command="{ action: 'destroy', id: num(row.id) }">销毁</el-dropdown-item>
                  <el-dropdown-item v-permission="'blindbox:audit'" :command="{ action: 'audit', id: num(row.id) }">库存审计</el-dropdown-item>
                  <el-dropdown-item v-permission="'blindbox:delete'" divided :command="{ action: 'delete', id: num(row.id) }">
                    <span style="color: #f56c6c">删除</span>
                  </el-dropdown-item>
                </el-dropdown-menu>
              </template>
            </el-dropdown>
          </template>
        </el-table-column>
      </DataTable>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>

    <ConfirmDialog
      v-model="deleteVisible"
      title="删除盲盒"
      message="删除后不可恢复（仅草稿且无子藏品/开启记录关联的盲盒可删除）"
      :need-reason="false"
      :loading="deleteLoading"
      @confirm="onDelete"
    />
  </div>
</template>

<style scoped>
.head-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.head-title { margin: 0; font-size: 16px; }
</style>
