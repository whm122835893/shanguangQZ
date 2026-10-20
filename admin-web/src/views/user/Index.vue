<script setup lang="ts">
/** 用户列表（契约 §8.3）：搜索 + 表格 + 冻结/解冻（带原因确认）+ 分页 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listUsers, freezeUser, unfreezeUser } from '@/api/user'
import { money } from '@/utils/decimal'
import { fmtDateTime } from '@/utils/date'

/** 列表行：mobile_masked/reg_time 为后端口径，mobile/jointime 为契约口径（二选一） */
type UserRowVM = {
  id: number
  nickname: string
  money: string
  status: string
  mobile?: string
  mobile_masked?: string
  jointime?: string
  reg_time?: string
  is_blacklisted?: number
}

const fields: SearchField[] = [
  { key: 'id', label: '用户ID', type: 'input' },
  { key: 'mobile', label: '手机号', type: 'input' },
  { key: 'nickname', label: '昵称', type: 'input' },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '正常', value: 'normal' },
      { label: '已冻结', value: 'frozen' },
      { label: '黑名单', value: 'black' },
    ],
  },
]

const query = ref<Record<string, unknown>>({})
const list = ref<UserRowVM[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

/** 查询参数：id/mobile 同时下发 uid/phone（后端实参口径）；black → is_blacklisted */
function buildParams(): Record<string, unknown> {
  const q = query.value
  const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value }
  const id = q.id !== undefined && q.id !== null ? String(q.id).trim() : ''
  if (id !== '') {
    params.id = id
    params.uid = id
  }
  const mobile = q.mobile !== undefined && q.mobile !== null ? String(q.mobile).trim() : ''
  if (mobile !== '') {
    params.mobile = mobile
    params.phone = mobile
  }
  const nickname = q.nickname !== undefined && q.nickname !== null ? String(q.nickname).trim() : ''
  if (nickname !== '') params.nickname = nickname
  const status = typeof q.status === 'string' ? q.status : ''
  if (status === 'black') params.is_blacklisted = 1
  else if (status === 'frozen') params.status = 'freeze'
  else if (status === 'normal') params.status = 'normal'
  return params
}

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listUsers(buildParams())
    list.value = res.list
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

function statusInfo(row: UserRowVM): { text: string; type: 'success' | 'warning' | 'danger' } {
  if (row.is_blacklisted === 1 || row.status === 'black') return { text: '黑名单', type: 'danger' }
  if (isFrozen(row)) return { text: '已冻结', type: 'warning' }
  return { text: '正常', type: 'success' }
}

function isFrozen(row: UserRowVM): boolean {
  return row.status === 'frozen' || row.status === 'freeze'
}

const router = useRouter()

function goDetail(row: UserRowVM): void {
  void router.push(`/user/${row.id}`)
}

/* ---------- 冻结 / 解冻（带原因确认，无需密码） ---------- */

type FreezeMode = 'freeze' | 'unfreeze'

const freezeVisible = ref(false)
const freezeMode = ref<FreezeMode>('freeze')
const freezeRow = ref<UserRowVM | null>(null)
const freezeLoading = ref(false)

const freezeTitle = computed(() => (freezeMode.value === 'freeze' ? '冻结用户' : '解冻用户'))
const freezeMessage = computed(() => {
  const row = freezeRow.value
  const who = row ? `#${row.id}（${row.nickname || '该用户'}）` : '该用户'
  return freezeMode.value === 'freeze'
    ? `将冻结用户 ${who}：其 C 端全部会话立即失效且无法登录，操作不可自动恢复（可再解冻），将写入审计日志。`
    : `将解冻用户 ${who}：解冻后即可正常登录使用，将写入审计日志。`
})

function openFreeze(row: UserRowVM): void {
  freezeRow.value = row
  freezeMode.value = isFrozen(row) ? 'unfreeze' : 'freeze'
  freezeVisible.value = true
}

async function onFreezeConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = freezeRow.value
  if (!row) return
  freezeLoading.value = true
  try {
    if (freezeMode.value === 'freeze') {
      await freezeUser(row.id, payload.reason)
      ElMessage.success('已冻结，该用户 C 端会话已全部吊销')
    } else {
      await unfreezeUser(row.id, payload.reason)
      ElMessage.success('已解冻')
    }
    freezeVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    freezeLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <DataTable :data="list" :loading="loading" empty-text="暂无用户">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column prop="nickname" label="昵称" min-width="140" show-overflow-tooltip>
        <template #default="{ row }">{{ row.nickname || '-' }}</template>
      </el-table-column>
      <el-table-column label="手机号" width="140">
        <template #default="{ row }">{{ row.mobile_masked || row.mobile || '-' }}</template>
      </el-table-column>
      <el-table-column label="余额" width="120">
        <template #default="{ row }">¥{{ money(row.money) }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="statusInfo(row as UserRowVM).type" size="small">
            {{ statusInfo(row as UserRowVM).text }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="注册时间" width="180">
        <template #default="{ row }">{{ fmtDateTime(row.reg_time || row.jointime) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="goDetail(row as UserRowVM)">详情</el-button>
          <el-button
            v-permission="'user:freeze'"
            link
            :type="isFrozen(row as UserRowVM) ? 'success' : 'warning'"
            @click="openFreeze(row as UserRowVM)"
          >
            {{ isFrozen(row as UserRowVM) ? '解冻' : '冻结' }}
          </el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <ConfirmDialog
      v-model="freezeVisible"
      :title="freezeTitle"
      :message="freezeMessage"
      :need-password="false"
      :loading="freezeLoading"
      @confirm="onFreezeConfirm"
    />
  </div>
</template>
