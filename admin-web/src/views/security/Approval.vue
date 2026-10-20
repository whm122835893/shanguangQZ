<script setup lang="ts">
/** 敏感操作审批（契约 §8.16.5）：分页筛选 + 详情抽屉（请求快照 JSON）+ 批准/拒绝（comment + 密码，status 1待审/2通过/3拒绝） */
import { computed, onMounted, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listApprovals, approvalDetail, approveOrReject } from '@/api/security'
import { fmtDateTime } from '@/utils/date'

type ApprovalRow = {
  id: number
  type: number
  target_type: string
  target_id: number
  title: string
  description: string
  applicant_id: number
  applicant_name: string
  approver_id: number
  approver_name: string
  status: number
  comment: string
  create_time: string
  approved_at: string
}

/** approval.approval_type：1=大额退款 2=强制修改资产 3=修改支付配置 4=平台清库 5=其他 */
const TYPE_MAP: Record<number, string> = {
  1: '大额退款',
  2: '强制修改资产',
  3: '修改支付配置',
  4: '平台清库',
  5: '其他',
}

/** approval.status：1=待审批 2=已批准 3=已拒绝 */
const STATUS_MAP: Record<number, { label: string; tag: 'warning' | 'success' | 'danger' }> = {
  1: { label: '待审批', tag: 'warning' },
  2: { label: '已批准', tag: 'success' },
  3: { label: '已拒绝', tag: 'danger' },
}

const fields: SearchField[] = [
  {
    key: 'type',
    label: '审批类型',
    type: 'select',
    options: Object.entries(TYPE_MAP).map(([value, label]) => ({ label, value: Number(value) })),
  },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '待审批', value: 1 },
      { label: '已批准', value: 2 },
      { label: '已拒绝', value: 3 },
    ],
  },
  { key: 'applicant_id', label: '发起人ID' },
]

const query = ref<Record<string, unknown>>({})
const list = ref<ApprovalRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listApprovals({ page: page.value, page_size: pageSize.value, ...query.value })
    list.value = (res.list ?? []) as ApprovalRow[]
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

/* ---------- 详情抽屉（展示请求快照 JSON） ---------- */

const drawerVisible = ref(false)
const drawerLoading = ref(false)
const detail = ref<Record<string, unknown> | null>(null)

function snapshotText(raw: unknown): string {
  if (raw === null || raw === undefined || raw === '') return '-'
  if (typeof raw === 'string') {
    try {
      return JSON.stringify(JSON.parse(raw), null, 2)
    } catch {
      return raw
    }
  }
  try {
    return JSON.stringify(raw, null, 2)
  } catch {
    return '-'
  }
}

async function openDetail(row: ApprovalRow): Promise<void> {
  drawerVisible.value = true
  drawerLoading.value = true
  detail.value = null
  try {
    detail.value = await approvalDetail(row.id)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    drawerLoading.value = false
  }
}

/* ---------- 批准 / 拒绝（共用确认弹窗：comment + 密码） ---------- */

type AuditAction = 'approve' | 'reject'

const auditVisible = ref(false)
const auditAction = ref<AuditAction>('approve')
const auditRow = ref<ApprovalRow | null>(null)
const auditLoading = ref(false)

const auditTitle = computed(() => (auditAction.value === 'approve' ? '批准审批' : '拒绝审批'))
const auditMessage = computed(() => {
  const row = auditRow.value
  if (!row) return ''
  return auditAction.value === 'approve'
    ? `将批准「${TYPE_MAP[row.type] ?? '未知'}」审批 #${row.id}（${row.title}），` +
        '批准后相关敏感操作将立即执行，操作不可逆，请谨慎执行。'
    : `将拒绝「${TYPE_MAP[row.type] ?? '未知'}」审批 #${row.id}（${row.title}），拒绝后不可再次审批。`
})

function openAudit(action: AuditAction, row: ApprovalRow): void {
  auditAction.value = action
  auditRow.value = row
  auditVisible.value = true
}

async function onAuditConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = auditRow.value
  if (!row) return
  auditLoading.value = true
  try {
    await approveOrReject(row.id, auditAction.value, payload.reason, payload.password)
    ElMessage.success(auditAction.value === 'approve' ? '审批已通过' : '审批已拒绝')
    auditVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    auditLoading.value = false
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
      <el-table-column label="类型" width="130">
        <template #default="{ row }">{{ TYPE_MAP[Number(row.type)] ?? '未知' }}</template>
      </el-table-column>
      <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
      <el-table-column label="发起人" width="140">
        <template #default="{ row }">
          {{ row.applicant_name ? `${row.applicant_name}（ID ${row.applicant_id}）` : `ID ${row.applicant_id}` }}
        </template>
      </el-table-column>
      <el-table-column label="目标" width="150">
        <template #default="{ row }">
          {{ row.target_type ? `${row.target_type} #${row.target_id}` : '-' }}
        </template>
      </el-table-column>
      <el-table-column prop="description" label="快照摘要" min-width="200" show-overflow-tooltip />
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[Number(row.status)]?.tag ?? 'info'" size="small">
            {{ STATUS_MAP[Number(row.status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(row.create_time as string) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="170" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'security:approval:detail'" link type="primary" @click="openDetail(row as ApprovalRow)">详情</el-button>
          <template v-if="Number(row.status) === 1">
            <el-button v-permission="'security:approval:handle'" link type="success" @click="openAudit('approve', row as ApprovalRow)">批准</el-button>
            <el-button v-permission="'security:approval:handle'" link type="danger" @click="openAudit('reject', row as ApprovalRow)">拒绝</el-button>
          </template>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-drawer v-model="drawerVisible" title="审批详情" size="480px">
      <div v-loading="drawerLoading">
        <template v-if="detail">
          <el-descriptions :column="1" border>
            <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
            <el-descriptions-item label="类型">{{ TYPE_MAP[Number(detail.type)] ?? '未知' }}</el-descriptions-item>
            <el-descriptions-item label="标题">{{ detail.title }}</el-descriptions-item>
            <el-descriptions-item label="描述">{{ detail.description || '-' }}</el-descriptions-item>
            <el-descriptions-item label="目标">
              {{ detail.target_type ? `${detail.target_type} #${detail.target_id}` : '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="发起人">
              {{ detail.applicant_name ? `${detail.applicant_name}（ID ${detail.applicant_id}）` : `ID ${detail.applicant_id}` }}
            </el-descriptions-item>
            <el-descriptions-item label="状态">{{ STATUS_MAP[Number(detail.status)]?.label ?? '未知' }}</el-descriptions-item>
            <el-descriptions-item label="审批人">
              {{ detail.approver_name ? `${detail.approver_name}（ID ${detail.approver_id}）` : '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="审批意见">{{ detail.comment || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ fmtDateTime(detail.create_time as string) }}</el-descriptions-item>
            <el-descriptions-item label="审批时间">{{ fmtDateTime(detail.approved_at as string) }}</el-descriptions-item>
          </el-descriptions>
          <div class="detail-section">
            <div class="section-title">请求快照</div>
            <pre class="json-block">{{ snapshotText(detail.request_data) }}</pre>
          </div>
        </template>
      </div>
    </el-drawer>

    <ConfirmDialog v-model="auditVisible" :title="auditTitle" :message="auditMessage" :loading="auditLoading" @confirm="onAuditConfirm" />
  </div>
</template>

<style scoped>
.detail-section { margin-top: 16px; }
.section-title { font-weight: 600; margin-bottom: 8px; }
.json-block {
  margin: 0;
  padding: 12px;
  background: #f5f7fa;
  border-radius: 4px;
  font-size: 12px;
  line-height: 1.6;
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 300px;
  overflow: auto;
}
</style>
