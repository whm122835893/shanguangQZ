<script setup lang="ts">
/** 工单列表（契约 §8.17.1）：分页筛选 + 详情跳转 + 分配客服（assignee_id）+ 状态更新（1待处理/2处理中/3待用户确认/4已解决/5已关闭） */
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listTickets, assignTicket, updateTicketStatus } from '@/api/ticket'
import { fmtDateTime } from '@/utils/date'

type TicketRow = {
  id: number
  ticket_no: string
  user_id: number
  user_phone: string
  ticket_type: number
  priority: number
  title: string
  status: number
  status_text: string
  assignee_id: number
  assignee_name: string
  time: string
}

/** support_ticket.ticket_type：1=支付异常 2=藏品丢失 3=盲盒问题 4=转赠纠纷 5=账号问题 6=其他 */
const TYPE_MAP: Record<number, string> = {
  1: '支付异常',
  2: '藏品丢失',
  3: '盲盒问题',
  4: '转赠纠纷',
  5: '账号问题',
  6: '其他',
}

/** support_ticket.status：1=待处理 2=处理中 3=待用户确认 4=已解决 5=已关闭 */
const STATUS_MAP: Record<number, { label: string; tag: 'warning' | 'primary' | 'success' | 'info' }> = {
  1: { label: '待处理', tag: 'warning' },
  2: { label: '处理中', tag: 'primary' },
  3: { label: '待用户确认', tag: 'warning' },
  4: { label: '已解决', tag: 'success' },
  5: { label: '已关闭', tag: 'info' },
}

const fields: SearchField[] = [
  { key: 'user_id', label: '用户ID' },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '待处理', value: 1 },
      { label: '处理中', value: 2 },
      { label: '待用户确认', value: 3 },
      { label: '已解决', value: 4 },
      { label: '已关闭', value: 5 },
    ],
  },
]

const router = useRouter()

const query = ref<Record<string, unknown>>({})
const list = ref<TicketRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listTickets({ page: page.value, page_size: pageSize.value, ...query.value })
    list.value = (res.list ?? []) as TicketRow[]
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

function goDetail(row: TicketRow): void {
  void router.push(`/ticket/${row.id}`)
}

/* ---------- 分配客服（输入客服 admin id） ---------- */

const assignVisible = ref(false)
const assignRow = ref<TicketRow | null>(null)
const assignLoading = ref(false)
const assigneeId = ref<number | null>(null)

function openAssign(row: TicketRow): void {
  assignRow.value = row
  assigneeId.value = row.assignee_id > 0 ? row.assignee_id : null
  assignVisible.value = true
}

async function submitAssign(): Promise<void> {
  if (!assignRow.value) return
  if (!assigneeId.value || assigneeId.value <= 0) {
    ElMessage.error('请输入正确的客服 admin ID')
    return
  }
  assignLoading.value = true
  try {
    await assignTicket(assignRow.value.id, assigneeId.value)
    ElMessage.success('工单已分配')
    assignVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    assignLoading.value = false
  }
}

/* ---------- 状态更新（状态下拉 + 备注） ---------- */

const statusVisible = ref(false)
const statusRow = ref<TicketRow | null>(null)
const statusLoading = ref(false)
const statusForm = reactive<{ status: number; note: string }>({ status: 1, note: '' })

function openStatus(row: TicketRow): void {
  statusRow.value = row
  statusForm.status = Number(row.status)
  statusForm.note = ''
  statusVisible.value = true
}

async function submitStatus(): Promise<void> {
  if (!statusRow.value) return
  if (statusForm.status === Number(statusRow.value.status)) {
    ElMessage.warning('状态未变更')
    return
  }
  statusLoading.value = true
  try {
    await updateTicketStatus(statusRow.value.id, statusForm.status, statusForm.note.trim() || undefined)
    ElMessage.success('工单状态已更新')
    statusVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    statusLoading.value = false
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
      <el-table-column prop="ticket_no" label="工单号" min-width="170" show-overflow-tooltip />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
      <el-table-column label="分类" width="110">
        <template #default="{ row }">{{ TYPE_MAP[Number(row.ticket_type)] ?? '未知' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="110">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[Number(row.status)]?.tag ?? 'info'" size="small">
            {{ STATUS_MAP[Number(row.status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="客服" width="120">
        <template #default="{ row }">{{ row.assignee_name || '-' }}</template>
      </el-table-column>
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(row.time as string) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'ticket:detail'" link type="primary" @click="goDetail(row as TicketRow)">详情</el-button>
          <el-button
            v-if="Number(row.status) !== 5"
            v-permission="'ticket:assign'"
            link
            type="success"
            @click="openAssign(row as TicketRow)"
          >分配</el-button>
          <el-button
            v-if="Number(row.status) !== 5"
            v-permission="'ticket:manage'"
            link
            type="warning"
            @click="openStatus(row as TicketRow)"
          >状态</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-dialog v-model="assignVisible" title="分配客服" width="420px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="工单">
          <span>{{ assignRow ? `#${assignRow.id} ${assignRow.title}` : '' }}</span>
        </el-form-item>
        <el-form-item label="客服ID" required>
          <el-input-number v-model="assigneeId" :min="1" :controls="false" placeholder="请输入客服 admin ID" style="width: 100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="assignVisible = false">取消</el-button>
        <el-button type="primary" :loading="assignLoading" @click="submitAssign">确定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="statusVisible" title="更新工单状态" width="440px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="工单">
          <span>{{ statusRow ? `#${statusRow.id} ${statusRow.title}` : '' }}</span>
        </el-form-item>
        <el-form-item label="目标状态" required>
          <el-select v-model="statusForm.status" style="width: 100%">
            <el-option v-for="(v, k) in STATUS_MAP" :key="k" :label="v.label" :value="Number(k)" />
          </el-select>
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="statusForm.note" type="textarea" :rows="3" placeholder="选填，将以系统回复形式追加到工单" maxlength="255" show-word-limit />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="statusVisible = false">取消</el-button>
        <el-button type="primary" :loading="statusLoading" @click="submitStatus">确定</el-button>
      </template>
    </el-dialog>
  </div>
</template>
