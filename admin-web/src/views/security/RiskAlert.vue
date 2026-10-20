<script setup lang="ts">
/** 风控告警（契约 §8.16.2）：分页筛选 + 详情抽屉（含证据 JSON）+ 处理（处理结果下拉 + 处理意见） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listRiskAlerts, riskAlertDetail, handleRiskAlert } from '@/api/security'
import { fmtDateTime } from '@/utils/date'

type AlertRow = {
  id: number
  type: number
  level: number
  user_id: number
  target_type: string
  target_id: number
  title: string
  description: string
  status: number
  handler_name: string
  handle_comment: string
  handled_at: string
  create_time: string
}

/** risk_alert.alert_type：1=大额充值 2=频繁小额 3=余额突变 4=高频API 5=异常时间 6=异地登录 7=批量注册 8=异常价格 9=其他 */
const TYPE_MAP: Record<number, string> = {
  1: '大额充值',
  2: '频繁小额',
  3: '余额突变',
  4: '高频API',
  5: '异常时间',
  6: '异地登录',
  7: '批量注册',
  8: '异常价格',
  9: '其他',
}

/** 告警等级 1-4 */
const LEVEL_MAP: Record<number, { label: string; tag: 'info' | 'primary' | 'warning' | 'danger' }> = {
  1: { label: '提示', tag: 'info' },
  2: { label: '关注', tag: 'primary' },
  3: { label: '警告', tag: 'warning' },
  4: { label: '严重', tag: 'danger' },
}

/** risk_alert.status：1=未处理 2=处理中 3=已处理 4=已忽略 */
const STATUS_MAP: Record<number, { label: string; tag: 'danger' | 'warning' | 'success' | 'info' }> = {
  1: { label: '未处理', tag: 'danger' },
  2: { label: '处理中', tag: 'warning' },
  3: { label: '已处理', tag: 'success' },
  4: { label: '已忽略', tag: 'info' },
}

const fields: SearchField[] = [
  {
    key: 'type',
    label: '告警类型',
    type: 'select',
    options: Object.entries(TYPE_MAP).map(([value, label]) => ({ label, value: Number(value) })),
  },
  {
    key: 'level',
    label: '等级',
    type: 'select',
    options: [
      { label: '提示', value: 1 },
      { label: '关注', value: 2 },
      { label: '警告', value: 3 },
      { label: '严重', value: 4 },
    ],
  },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '未处理', value: 1 },
      { label: '处理中', value: 2 },
      { label: '已处理', value: 3 },
      { label: '已忽略', value: 4 },
    ],
  },
]

const query = ref<Record<string, unknown>>({})
const list = ref<AlertRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listRiskAlerts({ page: page.value, page_size: pageSize.value, ...query.value })
    list.value = (res.list ?? []) as AlertRow[]
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

/* ---------- 详情抽屉（evidence 为 JSON 或字符串） ---------- */

const drawerVisible = ref(false)
const drawerLoading = ref(false)
const detail = ref<Record<string, unknown> | null>(null)

function evidenceText(raw: unknown): string {
  if (raw === null || raw === undefined || raw === '') return '-'
  if (typeof raw === 'string') return raw
  try {
    return JSON.stringify(raw, null, 2)
  } catch {
    return '-'
  }
}

async function openDetail(row: AlertRow): Promise<void> {
  drawerVisible.value = true
  drawerLoading.value = true
  detail.value = null
  try {
    detail.value = await riskAlertDetail(row.id)
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    drawerLoading.value = false
  }
}

/* ---------- 处理（处理结果下拉 + 处理意见） ---------- */

const handleVisible = ref(false)
const handleRow = ref<AlertRow | null>(null)
const handleLoading = ref(false)
const handleForm = reactive<{ status: number; handle_comment: string }>({ status: 3, handle_comment: '' })

function openHandle(row: AlertRow): void {
  handleRow.value = row
  handleForm.status = 3
  handleForm.handle_comment = ''
  handleVisible.value = true
}

async function submitHandle(): Promise<void> {
  if (!handleRow.value) return
  if (handleForm.handle_comment.trim() === '') {
    ElMessage.error('请填写处理意见')
    return
  }
  handleLoading.value = true
  try {
    await handleRiskAlert(handleRow.value.id, {
      status: handleForm.status,
      handle_comment: handleForm.handle_comment.trim(),
    })
    ElMessage.success('告警处理完成')
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
      <el-table-column label="类型" width="110">
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
      <el-table-column prop="title" label="标题" min-width="180" show-overflow-tooltip />
      <el-table-column prop="description" label="描述" min-width="200" show-overflow-tooltip />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[Number(row.status)]?.tag ?? 'info'" size="small">
            {{ STATUS_MAP[Number(row.status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="handler_name" label="处理人" width="110" />
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(row.create_time as string) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'security:alert:detail'" link type="primary" @click="openDetail(row as AlertRow)">详情</el-button>
          <el-button
            v-if="[1, 2].includes(Number(row.status))"
            v-permission="'security:alert:handle'"
            link
            type="warning"
            @click="openHandle(row as AlertRow)"
          >处理</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-drawer v-model="drawerVisible" title="告警详情" size="480px">
      <div v-loading="drawerLoading">
        <template v-if="detail">
          <el-descriptions :column="1" border>
            <el-descriptions-item label="ID">{{ detail.id }}</el-descriptions-item>
            <el-descriptions-item label="类型">{{ TYPE_MAP[Number(detail.type)] ?? '未知' }}</el-descriptions-item>
            <el-descriptions-item label="等级">
              <el-tag :type="LEVEL_MAP[Number(detail.level)]?.tag ?? 'info'" size="small">
                {{ LEVEL_MAP[Number(detail.level)]?.label ?? '未知' }}
              </el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="用户ID">{{ detail.user_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="关联对象">
              {{ detail.target_type ? `${detail.target_type} #${detail.target_id}` : '-' }}
            </el-descriptions-item>
            <el-descriptions-item label="标题">{{ detail.title }}</el-descriptions-item>
            <el-descriptions-item label="描述">{{ detail.description || '-' }}</el-descriptions-item>
            <el-descriptions-item label="状态">{{ STATUS_MAP[Number(detail.status)]?.label ?? '未知' }}</el-descriptions-item>
            <el-descriptions-item label="处理人">{{ detail.handler_name || '-' }}</el-descriptions-item>
            <el-descriptions-item label="处理意见">{{ detail.handle_comment || '-' }}</el-descriptions-item>
            <el-descriptions-item label="处理时间">{{ fmtDateTime(detail.handled_at as string) }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ fmtDateTime(detail.create_time as string) }}</el-descriptions-item>
          </el-descriptions>
          <div class="detail-section">
            <div class="section-title">证据数据</div>
            <pre class="json-block">{{ evidenceText(detail.evidence) }}</pre>
          </div>
        </template>
      </div>
    </el-drawer>

    <el-dialog v-model="handleVisible" title="处理告警" width="460px" :close-on-click-modal="false">
      <el-alert
        :title="`将处理告警 #${handleRow?.id ?? ''}（${handleRow?.title ?? ''}），处理结果与意见将留痕审计。`"
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
            <el-option label="已忽略" :value="4" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理意见" required>
          <el-input
            v-model="handleForm.handle_comment"
            type="textarea"
            :rows="3"
            placeholder="请填写处理意见（将写入审计日志）"
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
