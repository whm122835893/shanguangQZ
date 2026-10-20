<script setup lang="ts">
/** 备份与恢复（契约 §8.19）：备份分页列表 + 高风险恢复（确认文本「确认恢复」+ 管理员密码） */
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { listBackups, restoreBackup } from '@/api/platform'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { fmtDateTime } from '@/utils/date'

type Dict = Record<string, unknown>

/* ---- 兼容访问工具 ---- */
function pick(row: unknown, keys: string[]): string {
  const r = row as Dict
  for (const k of keys) {
    const v = r[k]
    if (v !== null && v !== undefined && v !== '') return String(v)
  }
  return ''
}
function disp(v: string): string {
  return v === '' ? '-' : v
}
function nv(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

function fmtSize(v: unknown): string {
  const n = nv(v)
  if (n <= 0) return '-'
  if (n < 1024) return `${n} B`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`
  return `${(n / 1024 / 1024).toFixed(2)} MB`
}

function tableCount(row: Dict): number {
  const t = row.table_list
  return Array.isArray(t) ? t.length : 0
}

function statusOf(row: Dict): { text: string; type: 'success' | 'info' } {
  return nv(row.status) === 2 ? { text: '已恢复', type: 'info' } : { text: '可恢复', type: 'success' }
}

/* ---- 备份列表 ---- */
const rows = ref<Dict[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listBackups({ page: page.value, page_size: pageSize.value })
    rows.value = res.list
    total.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function onPageChange(p: number, s: number): void {
  page.value = p
  pageSize.value = s
  void fetchList()
}

/* ---- 恢复（高风险） ---- */
const restoreVisible = ref(false)
const restoreLoading = ref(false)
const restoreRow = ref<Dict | null>(null)

const restoreMessage = computed(() => {
  const name = restoreRow.value ? disp(pick(restoreRow.value, ['name', 'file_path'])) : ''
  return `将使用备份「${name}」覆盖当前全部业务数据，恢复期间数据短暂不可用，且当前未备份的数据将丢失。请输入确认文本「确认恢复」与管理员密码。`
})

function openRestore(row: Dict): void {
  if (nv(row.status) === 2) {
    ElMessage.warning('该备份已恢复过，不可重复恢复')
    return
  }
  restoreRow.value = row
  restoreVisible.value = true
}

async function onRestoreConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = restoreRow.value
  if (!row) return
  restoreLoading.value = true
  try {
    await restoreBackup(nv(row.id), payload.password)
    ElMessage.success('恢复成功，数据已回灌')
    restoreVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    restoreLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div>
    <div class="card">
      <h3 style="margin-top: 0">备份列表</h3>
      <DataTable :data="rows" :loading="loading" empty-text="暂无备份记录">
        <el-table-column label="ID" width="80">
          <template #default="{ row }">{{ disp(pick(row, ['id'])) }}</template>
        </el-table-column>
        <el-table-column label="名称" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">{{ disp(pick(row, ['name', 'file_path'])) }}</template>
        </el-table-column>
        <el-table-column label="类型" width="110">
          <template #default="{ row }">
            <el-tag size="small" type="info">{{ disp(pick(row, ['type'])) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="大小" width="110">
          <template #default="{ row }">{{ fmtSize((row as Dict).file_size) }}</template>
        </el-table-column>
        <el-table-column label="表数" width="80">
          <template #default="{ row }">{{ tableCount(row as Dict) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusOf(row as Dict).type" size="small">{{ statusOf(row as Dict).text }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="180">
          <template #default="{ row }">{{ fmtDateTime(pick(row, ['time', 'createtime'])) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button v-permission="'platform:backup'" link type="danger" @click="openRestore(row as Dict)">
              恢复
            </el-button>
          </template>
        </el-table-column>
      </DataTable>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>

    <ConfirmDialog
      v-model="restoreVisible"
      title="恢复备份确认"
      :message="restoreMessage"
      :need-reason="false"
      confirm-text="确认恢复"
      :loading="restoreLoading"
      @confirm="onRestoreConfirm"
    />
  </div>
</template>
