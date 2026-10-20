<script setup lang="ts">
/** 实名认证列表（契约 §8.4）：列表全脱敏；完整查看需二次密码 + 审计；审计日志抽屉 */
import { onMounted, ref } from 'vue'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listRealnames, realnameFull, realnameAuditLogs } from '@/api/realname'
import { fmtDateTime } from '@/utils/date'

/** 列表行：name_masked/idcard_masked/submit_time 为后端口径，name/idcard/time 为契约口径（二选一） */
type RealnameRowVM = {
  id: number
  user_id: number
  name?: string
  name_masked?: string
  idcard?: string
  idcard_masked?: string
  status: number | string
  time?: string
  submit_time?: string
}

type StatusMeta = { text: string; type: 'warning' | 'success' | 'danger' | 'info' }

const STATUS_META: Record<number, StatusMeta | undefined> = {
  0: { text: '待审核', type: 'warning' },
  1: { text: '已通过', type: 'success' },
  2: { text: '已拒绝', type: 'danger' },
  3: { text: '待补充', type: 'info' },
}

const fields: SearchField[] = [
  { key: 'user_id', label: '用户ID', type: 'input' },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '待审核', value: 0 },
      { label: '已通过', value: 1 },
      { label: '已拒绝', value: 2 },
      { label: '待补充', value: 3 },
    ],
  },
]

const query = ref<Record<string, unknown>>({})
const list = ref<RealnameRowVM[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const q = query.value
    const params: Record<string, unknown> = { page: page.value, page_size: pageSize.value }
    const userId = q.user_id !== undefined && q.user_id !== null ? String(q.user_id).trim() : ''
    if (userId !== '') params.user_id = userId
    if (q.status !== undefined && q.status !== null && q.status !== '') params.status = q.status
    const res = await listRealnames(params)
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

function statusMeta(row: RealnameRowVM): StatusMeta {
  const key = Number(row.status)
  return STATUS_META[key] ?? { text: String(row.status), type: 'info' }
}

/* ---------- 查看完整信息：二次密码（审计由后端落库），结果明文弹窗展示 ---------- */

type FullInfo = {
  user_id: number
  name: string
  idcard: string
  phone: string
  idcard_front: string
  idcard_back: string
  submit_time: string
}

const fullRow = ref<RealnameRowVM | null>(null)
const confirmVisible = ref(false)
const fullLoading = ref(false)
const fullVisible = ref(false)
const fullInfo = ref<FullInfo | null>(null)

function openFull(row: RealnameRowVM): void {
  fullRow.value = row
  confirmVisible.value = true
}

async function onFullConfirm(payload: { reason: string; password: string }): Promise<void> {
  const row = fullRow.value
  if (!row) return
  fullLoading.value = true
  try {
    fullInfo.value = (await realnameFull(row.id, payload.password)) as FullInfo
    confirmVisible.value = false
    fullVisible.value = true
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    fullLoading.value = false
  }
}

/* ---------- 审计日志抽屉 ---------- */

type LogRowVM = {
  id: number
  admin: string
  ip: string
  time: string
  detail: string
}

const drawerVisible = ref(false)
const logRow = ref<RealnameRowVM | null>(null)
const logRows = ref<LogRowVM[]>([])
const logPage = ref(1)
const logPageSize = ref(20)
const logTotal = ref(0)
const logLoading = ref(false)

async function fetchLogs(): Promise<void> {
  const row = logRow.value
  if (!row) return
  logLoading.value = true
  try {
    const res = await realnameAuditLogs(row.id, { page: logPage.value, page_size: logPageSize.value })
    logRows.value = (res.list ?? []).map((r) => {
      const detail = r.detail
      const t = r.time
      return {
        id: Number(r.id ?? 0),
        admin: r.admin === null || r.admin === undefined ? '-' : String(r.admin),
        ip: r.ip === null || r.ip === undefined ? '-' : String(r.ip),
        time: typeof t === 'string' || typeof t === 'number' ? fmtDateTime(t) : '-',
        detail: detail === null || detail === undefined ? '-' : JSON.stringify(detail),
      }
    })
    logTotal.value = res.total
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    logLoading.value = false
  }
}

function openLogs(row: RealnameRowVM): void {
  logRow.value = row
  logPage.value = 1
  drawerVisible.value = true
  void fetchLogs()
}

function onLogPageChange(p: number, s: number): void {
  logPage.value = p
  logPageSize.value = s
  void fetchLogs()
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <SearchForm v-model="query" :fields="fields" @search="onSearch" @reset="onReset" />

    <DataTable :data="list" :loading="loading" empty-text="暂无实名认证记录">
      <el-table-column prop="id" label="ID" width="90" />
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column label="姓名" width="120">
        <template #default="{ row }">{{ row.name_masked || row.name || '-' }}</template>
      </el-table-column>
      <el-table-column label="证件号" min-width="180">
        <template #default="{ row }">{{ row.idcard_masked || row.idcard || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="statusMeta(row as RealnameRowVM).type" size="small">
            {{ statusMeta(row as RealnameRowVM).text }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="提交时间" width="180">
        <template #default="{ row }">{{ fmtDateTime(row.submit_time || row.time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="190" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'realname:full'" link type="primary" @click="openFull(row as RealnameRowVM)">
            查看完整信息
          </el-button>
          <el-button v-permission="'realname:audit'" link @click="openLogs(row as RealnameRowVM)">审计日志</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <!-- 高风险确认：查看完整实名信息（明文），写入审计日志 -->
    <ConfirmDialog
      v-model="confirmVisible"
      title="查看完整实名信息"
      message="查看完整实名信息将写入审计日志（谁/何时/查了谁），明文信息仅限本次查看，请输入管理员密码确认。"
      :need-reason="false"
      :loading="fullLoading"
      @confirm="onFullConfirm"
    />

    <!-- 明文信息展示弹窗 -->
    <el-dialog v-model="fullVisible" title="完整实名信息（本次查看已写入审计日志）" width="560px" :close-on-click-modal="false">
      <el-descriptions v-if="fullInfo" :column="1" border>
        <el-descriptions-item label="用户ID">{{ fullInfo.user_id }}</el-descriptions-item>
        <el-descriptions-item label="姓名">{{ fullInfo.name || '-' }}</el-descriptions-item>
        <el-descriptions-item label="证件号">{{ fullInfo.idcard || '-' }}</el-descriptions-item>
        <el-descriptions-item label="手机号">{{ fullInfo.phone || '-' }}</el-descriptions-item>
        <el-descriptions-item label="证件照（人像面）">
          <el-image
            v-if="fullInfo.idcard_front"
            :src="fullInfo.idcard_front"
            :preview-src-list="[fullInfo.idcard_front]"
            preview-teleported
            fit="cover"
            class="idcard-img"
          />
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="证件照（国徽面）">
          <el-image
            v-if="fullInfo.idcard_back"
            :src="fullInfo.idcard_back"
            :preview-src-list="[fullInfo.idcard_back]"
            preview-teleported
            fit="cover"
            class="idcard-img"
          />
          <span v-else>-</span>
        </el-descriptions-item>
        <el-descriptions-item label="提交时间">{{ fmtDateTime(fullInfo.submit_time) }}</el-descriptions-item>
      </el-descriptions>
      <el-alert title="明文信息严禁截图外传，泄露将追责" type="warning" :closable="false" show-icon style="margin-top: 12px" />
    </el-dialog>

    <!-- 审计日志抽屉 -->
    <el-drawer
      v-model="drawerVisible"
      :title="logRow ? `实名查看审计日志（用户 #${logRow.id}）` : '实名查看审计日志'"
      size="680px"
    >
      <DataTable :data="logRows" :loading="logLoading" empty-text="暂无审计日志">
        <el-table-column prop="id" label="日志ID" width="80" />
        <el-table-column prop="admin" label="操作人" width="120" show-overflow-tooltip />
        <el-table-column prop="ip" label="IP" width="130" />
        <el-table-column prop="time" label="时间" width="170" />
        <el-table-column prop="detail" label="详情" min-width="150" show-overflow-tooltip />
      </DataTable>
      <Pagination :page="logPage" :page-size="logPageSize" :total="logTotal" :loading="logLoading" @change="onLogPageChange" />
    </el-drawer>
  </div>
</template>

<style scoped>
.idcard-img { width: 160px; height: 100px; border-radius: 4px; }
</style>
