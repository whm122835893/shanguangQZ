<script setup lang="ts">
/** 登录日志（契约 §8.15）：只读分页列表（成功/失败、IP、UA、失败原因） */
import { onMounted, ref } from 'vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listLoginLogs } from '@/api/permission'
import { fmtDateTime } from '@/utils/date'

type LoginLogRow = {
  id: number
  admin_name?: string
  username?: string
  account?: string
  ip?: string
  user_agent?: string
  ua?: string
  is_success?: boolean | number | string
  success?: boolean | number | string
  fail_reason?: string
  reason?: string
  created_at?: string
  login_at?: string
}

const list = ref<LoginLogRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

function isSuccess(row: LoginLogRow): boolean {
  const v = row.is_success ?? row.success
  return v === true || v === 1 || v === '1' || v === 'true'
}

function failReason(row: LoginLogRow): string {
  return toStr(row.fail_reason) || toStr(row.reason)
}

function userAgent(row: LoginLogRow): string {
  return toStr(row.user_agent) || toStr(row.ua)
}

function account(row: LoginLogRow): string {
  return toStr(row.username) || toStr(row.account)
}

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listLoginLogs({ page: page.value, page_size: pageSize.value })
    list.value = res.list as LoginLogRow[]
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

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <h3 style="margin-top: 0">登录日志</h3>

    <DataTable :data="list" :loading="loading" empty-text="暂无登录日志">
      <el-table-column prop="id" label="ID" width="90" />
      <el-table-column label="管理员" width="120">
        <template #default="{ row }">{{ (row as LoginLogRow).admin_name || '-' }}</template>
      </el-table-column>
      <el-table-column label="账号" width="140" show-overflow-tooltip>
        <template #default="{ row }">{{ account(row as LoginLogRow) || '-' }}</template>
      </el-table-column>
      <el-table-column label="IP" width="140">
        <template #default="{ row }">{{ (row as LoginLogRow).ip || '-' }}</template>
      </el-table-column>
      <el-table-column label="User-Agent" min-width="240" show-overflow-tooltip>
        <template #default="{ row }">{{ userAgent(row as LoginLogRow) || '-' }}</template>
      </el-table-column>
      <el-table-column label="结果" width="90">
        <template #default="{ row }">
          <el-tag :type="isSuccess(row as LoginLogRow) ? 'success' : 'danger'" size="small">
            {{ isSuccess(row as LoginLogRow) ? '成功' : '失败' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="失败原因" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ failReason(row as LoginLogRow) || '-' }}</template>
      </el-table-column>
      <el-table-column label="时间" width="180">
        <template #default="{ row }">{{ fmtDateTime((row as LoginLogRow).created_at ?? (row as LoginLogRow).login_at) }}</template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
  </div>
</template>
