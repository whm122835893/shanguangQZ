<script setup lang="ts">
/** 安全事件（契约 §8.16.3）：分页筛选（类型/状态/IP）+ 详情抽屉（请求上下文）+ 处理（状态 + 处理备注） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listSecurityEvents, securityEventDetail, handleSecurityEvent } from '@/api/security'
import { fmtDateTime } from '@/utils/date'

type EventRow = {
  id: number
  type: number
  level: number
  user_id: number
  ip_address: string
  request_path: string
  description: string
  status: number
  handler_name: string
  handle_comment: string
  handled_at: string
  create_time: string
}

/** security_event.event_type：1=IDOR 2=越权 3=回调篡改 4=Token异常 5=暴力破解 6=SQLi 7=XSS 8=其他 */
const TYPE_MAP: Record<number, string> = {
  1: 'IDOR',
  2: '越权',
  3: '回调篡改',
  4: 'Token异常',
  5: '暴力破解',
  6: 'SQLi',
  7: 'XSS',
  8: '其他',
}

/** 事件等级 1-4 */
const LEVEL_MAP: Record<number, { label: string; tag: 'info' | 'primary' | 'warning' | 'danger' }> = {
  1: { label: '提示', tag: 'info' },
  2: { label: '关注', tag: 'primary' },
  3: { label: '警告', tag: 'warning' },
  4: { label: '严重', tag: 'danger' },
}

/** security_event.status：1=未处理 2=处理中 3=已处理 */
const STATUS_MAP: Record<number, { label: string; tag: 'danger' | 'warning' | 'success' }> = {
  1: { label: '未处理', tag: 'danger' },
  2: { label: '处理中', tag: 'warning' },
  3: { label: '已处理', tag: 'success' },
}

const fields: SearchField[] = [
  {
    key: 'type',
    label: '事件类型',
    type: 'select',
    options: Object.entries(TYPE_MAP).map(([value, label]) => ({ label, value: Number(value) })),
  },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '未处理', value: 1 },
      { label: '处理中', value: 2 },
      { label: '已处理', value: 3 },
    ],
  },
  { key: 'ip', label: 'IP地址' },
]

const query = ref<Record<string, unknown>>({})
const list = ref<EventRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listSecurityEvents({ page: page.value, page_size: pageSize.value, ...query.value })
    list.value = (res.list ?? []) as EventRow[]
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

/* ---------- 详情抽屉 ---------- */

const drawerVisible = ref(false)
const drawerLoading = ref(false)
const detail = ref<Record<string, unknown> | null>(null)

async function openDetail(row: EventRow): Promise<void> {
  drawerVisible.value = true
  drawerLoading.value = true
  detail.value = null
  try {
    detail.value = await securityEventDetail(row.id)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    drawerLoading.value = false
  }
}

/* ---------- 处理（处理结果下拉 + 处理备注，提交字段为 comment） ---------- */

const handleVisible = ref(false)
const handleRow = ref<EventRow | null>(null)
const handleLoading = ref(false)
const handleForm = reactive<{ status: number; comment: string }>({ status: 3, comment: '' })

function openHandle(row: EventRow): void {
  handleRow.value = row
  handleForm.status = 3
  handleForm.comment = ''
  handleVisible.value = true
}

async function submitHandle(): Promise<void> {
  if (!handleRow.value) return
  if (handleForm.comment.trim() === '') {
    ElMessage.error('请填写处理备注')
    return
  }
  handleLoading.value = true
  try {
    await handleSecurityEvent(handleRow.value.id, {
      status: handleForm.status,
      comment: handleForm.comment.trim(),
    })
    ElMessage.success('事件处理完成')
    handleVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    handleLoading.value = false
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
      <el-table-column label="事件类型" width="110">
        <template #default="{ row }">{{ TYPE_MAP[Number(row.type)] ?? '未知' }}</template>
      </el-table-column>
      <el-table-column label="等级" width="90">
        <template #default="{ row }">
          <el-tag :type="LEVEL_MAP[Number(row.level)]?.tag ?? 'info'" size="small">
            {{ LEVEL_MAP[Number(row.level)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column prop="ip_address" label="IP" width="140" />
      <el-table-column prop="request_path" label="请求路径" min-width="160" show-overflow-tooltip />
      <el-table-column prop="description" label="详情" min-width="200" show-overflow-tooltip />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[Number(row.status)]?.tag ?? 'info'" size="small">
            {{ STATUS_MAP[Number(row.status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(row.create_time as string) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'security:event:detail'" link type="primary" @click="openDetail(row as EventRow)">详情</el-button>
          <el-button
            v-if="Number(row.status) !== 3"
            v-permission="'security:event:handle'"
            link
            type="warning"
            @click="openHandle(row as EventRow)"
          >处理</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-drawer v-model="drawerVisible" title="事件详情" size="480px">
      <div v-loading="drawerLoading">
        <template v-if="detail">
          <el-descriptions :column="1" border>
            <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
            <el-descriptions-item label="事件类型">{{ TYPE_MAP[Number(detail.type)] ?? '未知' }}</el-descriptions-item>
            <el-descriptions-item label="等级">
              <el-tag :type="LEVEL_MAP[Number(detail.level)]?.tag ?? 'info'" size="small">
                {{ LEVEL_MAP[Number(detail.level)]?.label ?? '未知' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="用户ID">{{ detail.user_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="IP">{{ detail.ip_address || '-' }}</el-descriptions-item>
            <el-descriptions-item label="请求方法">{{ detail.request_method || '-' }}</el-descriptions-item>
            <el-descriptions-item label="请求路径">{{ detail.request_path || '-' }}</el-descriptions-item>
            <el-descriptions-item label="响应状态码">{{ detail.response_status || '-' }}</el-descriptions-item>
            <el-descriptions-item label="详情">{{ detail.description || '-' }}</el-descriptions-item>
            <el-descriptions-item label="User-Agent">{{ detail.user_agent || '-' }}</el-descriptions-item>
            <el-descriptions-item label="状态">{{ STATUS_MAP[Number(detail.status)]?.label ?? '未知' }}</el-descriptions-item>
            <el-descriptions-item label="处理人">{{ detail.handler_name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="处理备注">{{ detail.handle_comment || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ fmtDateTime(detail.create_time as string) }}</el-descriptions-item>
          </el-descriptions>
          <div class="detail-section">
            <div class="section-title">请求参数</div>
            <pre class="json-block">{{ detail.request_params || '-' }}</pre>
          </div>
        </template>
      </div>
    </el-drawer>

    <el-dialog v-model="handleVisible" title="处理事件" width="460px" :close-on-click-modal="false">
      <el-alert
        :title="`将处理安全事件 #${handleRow?.id ?? ''}（${TYPE_MAP[Number(handleRow?.type)] ?? '未知'}），处理结果将留痕审计。`"
        type="warning"
        :closable="false"
        show-icon
        style="margin-bottom: 16px"
      />
      <el-form label-width="90px">
        <el-form-item label="处理结果" required>
          <el-select v-model="handleForm.status" style="width: 100%">
            <el-option label="处理中" :value="2" />
            <el-option label="已处理" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理备注" required>
          <el-input
            v-model="handleForm.comment"
            type="textarea"
            :rows="3"
            placeholder="请填写处理备注（将写入审计日志）"
            maxlength="255"
            show-word-limit
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="handleVisible = false">取消</el-button>
        <el-button type="primary" :loading="handleLoading" @click="submitHandle">确定</el-button>
      </template>
    </el-dialog>
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
  max-height: 260px;
  overflow: auto;
}
</style>
