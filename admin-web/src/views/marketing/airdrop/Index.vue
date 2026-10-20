<script setup lang="ts">
/** 活动空投（契约 §8.11.6，123~124）：任务列表（分页）+ 抽屉查看发放记录（按 task_id 过滤） */
import { computed, onMounted, ref } from 'vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listAirdropTasks, listAirdropRecords } from '@/api/marketing'
import { fmtDateTime } from '@/utils/date'

/** 空投任务行（airdrop_task 口径；source 固定为 activity） */
type AirdropTaskVM = {
  id?: number
  target_type?: number
  target_name?: string
  quantity?: number
  user_count?: number
  success_count?: number
  fail_count?: number
  activity_type?: string
  activity_id?: number
  reward_type?: number
  admin_name?: string
  create_time?: string
  createtime?: string
}

function asTask(row: unknown): AirdropTaskVM {
  return row as AirdropTaskVM
}

/** 1=藏品 2=盲盒 */
function targetMeta(t: number | undefined): string {
  if (t === 1) return '藏品'
  if (t === 2) return '盲盒'
  return '-'
}

function taskTime(row: AirdropTaskVM): string {
  return fmtDateTime(row.create_time ?? row.createtime ?? '')
}

const rows = ref<Record<string, unknown>[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listAirdropTasks({ page: page.value, page_size: pageSize.value })
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

/* ---------- 发放记录抽屉（124，按 task_id 过滤） ---------- */

/** 发放记录行（后端 airdropRecords 口径） */
type AirdropRecordVM = {
  id: number
  task_id: number
  user_id: number
  phone: string
  target_name: string
  quantity: number
  activity_type: string
  activity_id: number
  reward_type: number
  admin_name: string
  status: number
  fail_reason: string
  create_time: string
}

function asRecord(row: unknown): AirdropRecordVM {
  return row as AirdropRecordVM
}

function recordStatusMeta(s: number): { label: string; type: 'success' | 'danger' } {
  return s === 1 ? { label: '成功', type: 'success' } : { label: '失败', type: 'danger' }
}

const drawerVisible = ref(false)
const drawerTask = ref<AirdropTaskVM | null>(null)
const recRows = ref<Record<string, unknown>[]>([])
const recPage = ref(1)
const recPageSize = ref(20)
const recTotal = ref(0)
const recLoading = ref(false)

const drawerTitle = computed(() =>
  drawerTask.value?.id ? `发放记录 - ${drawerTask.value.target_name || '任务'}（#${drawerTask.value.id}）` : '发放记录',
)

async function fetchRecords(): Promise<void> {
  const taskId = drawerTask.value?.id
  if (!taskId) return
  recLoading.value = true
  try {
    const res = await listAirdropRecords({ task_id: taskId, page: recPage.value, page_size: recPageSize.value })
    recRows.value = res.list
    recTotal.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    recLoading.value = false
  }
}

function openRecords(task: AirdropTaskVM): void {
  drawerTask.value = task
  recPage.value = 1
  drawerVisible.value = true
  void fetchRecords()
}

function onRecPageChange(p: number, s: number): void {
  recPage.value = p
  recPageSize.value = s
  void fetchRecords()
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <div style="margin-bottom: 8px; color: #909399; font-size: 13px">
      活动空投发放任务（发放动作由各活动模块触发，此处为只读台账）
    </div>

    <DataTable :data="rows" :loading="loading" empty-text="暂无空投任务">
      <el-table-column label="任务ID" width="90">
        <template #default="{ row }">{{ asTask(row).id ?? '-' }}</template>
      </el-table-column>
      <el-table-column label="发放对象" min-width="150" show-overflow-tooltip>
        <template #default="{ row }">{{ asTask(row).target_name || '-' }}</template>
      </el-table-column>
      <el-table-column label="对象类型" width="90">
        <template #default="{ row }">{{ targetMeta(asTask(row).target_type) }}</template>
      </el-table-column>
      <el-table-column label="每人数量" width="90">
        <template #default="{ row }">{{ asTask(row).quantity ?? '-' }}</template>
      </el-table-column>
      <el-table-column label="人数" width="80">
        <template #default="{ row }">{{ asTask(row).user_count ?? '-' }}</template>
      </el-table-column>
      <el-table-column label="成功 / 失败" width="110">
        <template #default="{ row }">{{ asTask(row).success_count ?? '-' }} / {{ asTask(row).fail_count ?? '-' }}</template>
      </el-table-column>
      <el-table-column label="关联活动" min-width="140" show-overflow-tooltip>
        <template #default="{ row }">
          {{ asTask(row).activity_type ? `${asTask(row).activity_type} #${asTask(row).activity_id ?? '-'}` : '-' }}
        </template>
      </el-table-column>
      <el-table-column label="操作人" width="110">
        <template #default="{ row }">{{ asTask(row).admin_name || '-' }}</template>
      </el-table-column>
      <el-table-column label="时间" width="170">
        <template #default="{ row }">{{ taskTime(asTask(row)) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="110" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openRecords(asTask(row))">发放记录</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-drawer v-model="drawerVisible" :title="drawerTitle" size="820px">
      <DataTable :data="recRows" :loading="recLoading" empty-text="暂无发放记录">
        <el-table-column label="ID" width="80">
          <template #default="{ row }">{{ asRecord(row).id }}</template>
        </el-table-column>
        <el-table-column label="用户" width="90">
          <template #default="{ row }">{{ asRecord(row).user_id > 0 ? `#${asRecord(row).user_id}` : '-' }}</template>
        </el-table-column>
        <el-table-column label="手机号" width="130">
          <template #default="{ row }">{{ asRecord(row).phone || '-' }}</template>
        </el-table-column>
        <el-table-column label="发放对象" min-width="130" show-overflow-tooltip>
          <template #default="{ row }">{{ asRecord(row).target_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="数量" width="70">
          <template #default="{ row }">{{ asRecord(row).quantity }}</template>
        </el-table-column>
        <el-table-column label="结果" width="90">
          <template #default="{ row }">
            <el-tooltip
              v-if="asRecord(row).status !== 1 && asRecord(row).fail_reason"
              :content="asRecord(row).fail_reason"
              placement="top"
            >
              <el-tag size="small" :type="recordStatusMeta(asRecord(row).status).type">
                {{ recordStatusMeta(asRecord(row).status).label }}
              </el-tag>
            </el-tooltip>
            <el-tag v-else size="small" :type="recordStatusMeta(asRecord(row).status).type">
              {{ recordStatusMeta(asRecord(row).status).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="110">
          <template #default="{ row }">{{ asRecord(row).admin_name || '-' }}</template>
        </el-table-column>
        <el-table-column label="时间" width="170">
          <template #default="{ row }">{{ fmtDateTime(asRecord(row).create_time) }}</template>
        </el-table-column>
      </DataTable>
      <Pagination
        :page="recPage"
        :page-size="recPageSize"
        :total="recTotal"
        :loading="recLoading"
        @change="onRecPageChange"
      />
    </el-drawer>
  </div>
</template>
