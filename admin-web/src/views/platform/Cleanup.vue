<script setup lang="ts">
/** 平台清库（契约 §8.19）：四重确认（风险勾选 + 确认文本 + 原因 + 管理员密码），先自动备份再清库；下方清库记录分页 */
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { cleanupDatabase, listCleanupLogs } from '@/api/platform'
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

/* ---- 四重确认：① 风险勾选（页面） ② 确认文本 ③ 原因 ④ 密码（ConfirmDialog） ---- */
const riskAck = ref(false)
const confirmVisible = ref(false)
const confirmLoading = ref(false)

async function onConfirm(payload: { reason: string; password: string }): Promise<void> {
  confirmLoading.value = true
  try {
    const res = await cleanupDatabase(payload.password, '确认清库', payload.reason)
    ElMessage.success(`清库成功，系统已自动备份（备份ID：${nv(res.backup_id)}），可在「清库/备份记录」中恢复`)
    confirmVisible.value = false
    riskAck.value = false
    page.value = 1
    void fetchLogs()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    confirmLoading.value = false
  }
}

/* ---- 清库记录 ---- */
const rows = ref<Dict[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchLogs(): Promise<void> {
  loading.value = true
  try {
    const res = await listCleanupLogs({ page: page.value, page_size: pageSize.value })
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
  void fetchLogs()
}

function statusOf(row: Dict): { text: string; type: 'success' | 'danger' } {
  return nv(row.status) === 1 ? { text: '成功', type: 'success' } : { text: '失败', type: 'danger' }
}

onMounted(() => {
  void fetchLogs()
})
</script>

<template>
  <div>
    <div class="danger-bar danger-bar--red">
      ⚠️ 此操作将清空业务数据并不可逆，系统会先自动备份；备份可用于恢复（见「清库/备份记录」）。
    </div>

    <div class="card">
      <h3 style="margin-top: 0">一键清库</h3>
      <el-checkbox v-model="riskAck" style="margin-bottom: 12px">
        我已了解：清库将清空全部 C 端业务数据（用户/订单/钱包/藏品/盲盒等），且不可逆
      </el-checkbox>
      <div>
        <el-button type="danger" :disabled="!riskAck" @click="confirmVisible = true">清空业务数据</el-button>
        <span class="confirm-hint">需输入确认文本「确认清库」+ 操作原因 + 管理员密码</span>
      </div>
    </div>

    <div class="card">
      <h3 style="margin-top: 0">清库记录</h3>
      <DataTable :data="rows" :loading="loading" empty-text="暂无清库记录">
        <el-table-column label="时间" width="180">
          <template #default="{ row }">{{ fmtDateTime(pick(row, ['time', 'createtime'])) }}</template>
        </el-table-column>
        <el-table-column label="操作人" width="140">
          <template #default="{ row }">{{ disp(pick(row, ['admin_name', 'operator', 'admin_id'])) }}</template>
        </el-table-column>
        <el-table-column label="原因" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">{{ disp(pick(row, ['reason'])) }}</template>
        </el-table-column>
        <el-table-column label="备份ID" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">{{ disp(pick(row, ['backup_id', 'backup_path'])) }}</template>
        </el-table-column>
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag :type="statusOf(row as Dict).type" size="small">{{ statusOf(row as Dict).text }}</el-tag>
          </template>
        </el-table-column>
      </DataTable>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>

    <ConfirmDialog
      v-model="confirmVisible"
      title="平台清库确认"
      message="即将清空全部业务数据，操作不可逆（系统会先自动备份）。请输入确认文本「确认清库」、操作原因与管理员密码。"
      confirm-text="确认清库"
      :loading="confirmLoading"
      @confirm="onConfirm"
    />
  </div>
</template>

<style scoped>
.danger-bar--red {
  background: #fef0f0;
  color: #f56c6c;
  border-color: #fde2e2;
}
.confirm-hint { margin-left: 12px; color: #909399; font-size: 12px; }
</style>
