<script setup lang="ts">
/** 交易密码锁定（契约 §8.16.4）：分页（只读）+ 手动解锁（原因+密码，审计留痕） */
import { onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listTxLocks, unlockTxLock } from '@/api/security'
import { fmtDateTime } from '@/utils/date'

type TxLockRow = {
  id: number
  user_id: number
  nickname: string
  mobile: string
  fail_count: number
  locked_until: string
  status: number
  unlock_reason: string
  unlocked_by: number
  create_time: string
}

/** tx_lock.status：1=锁定中 0=已解锁 */
const STATUS_MAP: Record<number, { label: string; tag: 'danger' | 'info' }> = {
  1: { label: '锁定中', tag: 'danger' },
  0: { label: '已解锁', tag: 'info' },
}

const fields: SearchField[] = [{ key: 'user_id', label: '用户ID' }]

const query = ref<Record<string, unknown>>({})
const list = ref<TxLockRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listTxLocks({ page: page.value, page_size: pageSize.value, ...query.value })
    list.value = (res.list ?? []) as TxLockRow[]
    total.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

function onSearch(): void {
  page.value = 1
  void fetchList()
}

function onReset(): void {
  page.value = 1
  void fetchList()
}

function onPageChange(p: number, s: number): void {
  page.value = p
  pageSize.value = s
  void fetchList()
}

/* ---------- 手动解锁（原因 + 管理员密码） ---------- */

const unlockVisible = ref(false)
const unlockRow = ref<TxLockRow | null>(null)
const unlockLoading = ref(false)

const unlockMessage = (): string => {
  const row = unlockRow.value
  if (!row) return ''
  return `将解锁用户（ID ${row.user_id} / ${row.nickname || '未知'}）的交易密码锁定，` +
    `已连续输错 ${row.fail_count} 次。解锁后用户可立即重设并使用交易密码，操作写入审计日志。`
}

function openUnlock(row: TxLockRow): void {
  unlockRow.value = row
  unlockVisible.value = true
}

async function onUnlockConfirm(payload: { reason: string; password: string }): Promise<void> {
  if (!unlockRow.value) return
  unlockLoading.value = true
  try {
    await unlockTxLock(unlockRow.value.id, payload.reason, payload.password)
    ElMessage.success('已解锁')
    unlockVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    unlockLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <DataTable :data="list" :loading="loading">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column prop="nickname" label="昵称" min-width="120" show-overflow-tooltip />
      <el-table-column prop="mobile" label="手机号" width="130" />
      <el-table-column prop="fail_count" label="失败次数" width="100" />
      <el-table-column label="锁定截止时间" width="200">
        <template #default="{ row }">
          <el-tag v-if="Number(row.status) === 1" type="danger" size="small">{{ fmtDateTime(row.locked_until as string) }}</el-tag>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[Number(row.status)]?.tag ?? 'info'" size="small">
            {{ STATUS_MAP[Number(row.status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="unlock_reason" label="解锁原因" min-width="150" show-overflow-tooltip />
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(row.create_time as string) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <el-button
            v-if="Number(row.status) === 1"
            v-permission="'security:txlock:manage'"
            link
            type="primary"
            @click="openUnlock(row as TxLockRow)"
          >解锁</el-button>
          <span v-else class="handled-text">-</span>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <ConfirmDialog
      v-model="unlockVisible"
      title="手动解锁交易密码"
      :message="unlockMessage()"
      :loading="unlockLoading"
      @confirm="onUnlockConfirm"
    />
  </div>
</template>

<style scoped>
.handled-text { color: #909399; font-size: 12px; }
</style>
