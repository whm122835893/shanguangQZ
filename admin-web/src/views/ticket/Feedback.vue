<script setup lang="ts">
/** 用户反馈（契约 §8.17.6/197）：分页筛选（类型/状态）+ 处理（状态 + 处理回复） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import SearchForm, { type SearchField } from '@/components/SearchForm.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { listFeedbacks, handleFeedback } from '@/api/ticket'
import { fmtDateTime } from '@/utils/date'

type FeedbackRow = {
  id: number
  user_id: number
  nickname: string
  mobile: string
  type: number
  content: string
  contact: string
  status: number
  reply: string
  time: string
}

/** feedback.type：1=反馈 2=举报 3=建议 */
const TYPE_MAP: Record<number, { label: string; tag: 'primary' | 'warning' | 'info' }> = {
  1: { label: '反馈', tag: 'primary' },
  2: { label: '举报', tag: 'warning' },
  3: { label: '建议', tag: 'info' },
}

/** feedback.status：1=待处理 2=已处理 */
const STATUS_MAP: Record<number, { label: string; tag: 'danger' | 'success' }> = {
  1: { label: '待处理', tag: 'danger' },
  2: { label: '已处理', tag: 'success' },
}

const fields: SearchField[] = [
  { key: 'user_id', label: '用户ID' },
  {
    key: 'type',
    label: '类型',
    type: 'select',
    options: [
      { label: '反馈', value: 1 },
      { label: '举报', value: 2 },
      { label: '建议', value: 3 },
    ],
  },
  {
    key: 'status',
    label: '状态',
    type: 'select',
    options: [
      { label: '待处理', value: 1 },
      { label: '已处理', value: 2 },
    ],
  },
]

const query = ref<Record<string, unknown>>({})
const list = ref<FeedbackRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listFeedbacks({ page: page.value, page_size: pageSize.value, ...query.value })
    list.value = (res.list ?? []) as FeedbackRow[]
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

/* ---------- 处理（状态 + 处理回复，提交字段为 reply） ---------- */

const handleVisible = ref(false)
const handleRow = ref<FeedbackRow | null>(null)
const handleLoading = ref(false)
const handleForm = reactive<{ status: number; reply: string }>({ status: 2, reply: '' })

function openHandle(row: FeedbackRow): void {
  handleRow.value = row
  handleForm.status = 2
  handleForm.reply = ''
  handleVisible.value = true
}

async function submitHandle(): Promise<void> {
  if (!handleRow.value) return
  if (handleForm.reply.trim() === '') {
    ElMessage.error('请填写处理回复')
    return
  }
  handleLoading.value = true
  try {
    await handleFeedback(handleRow.value.id, {
      status: handleForm.status,
      reply: handleForm.reply.trim(),
    })
    ElMessage.success('反馈处理完成')
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
      <el-table-column prop="user_id" label="用户ID" width="90" />
      <el-table-column prop="nickname" label="昵称" min-width="120" show-overflow-tooltip />
      <el-table-column label="类型" width="90">
        <template #default="{ row }">
          <el-tag :type="TYPE_MAP[Number(row.type)]?.tag ?? 'info'" size="small">
            {{ TYPE_MAP[Number(row.type)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="content" label="内容" min-width="220" show-overflow-tooltip />
      <el-table-column prop="contact" label="联系方式" width="140" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[Number(row.status)]?.tag ?? 'info'" size="small">
            {{ STATUS_MAP[Number(row.status)]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="reply" label="处理回复" min-width="160" show-overflow-tooltip />
      <el-table-column label="时间" width="170">
        <template #default="{ row }">{{ fmtDateTime(row.time as string) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <el-button
            v-if="Number(row.status) === 1"
            v-permission="'ticket:feedback'"
            link
            type="primary"
            @click="openHandle(row as FeedbackRow)"
          >处理</el-button>
          <span v-else class="handled-text">已处理</span>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <el-dialog v-model="handleVisible" title="处理反馈" width="460px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="反馈内容">
          <div class="feedback-content">{{ handleRow?.content }}</div>
        </el-form-item>
        <el-form-item label="处理状态" required>
          <el-select v-model="handleForm.status" style="width: 100%">
            <el-option label="待处理" :value="1" />
            <el-option label="已处理" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="处理回复" required>
          <el-input v-model="handleForm.reply" type="textarea" :rows="3" placeholder="请填写处理回复（将展示给用户）" maxlength="500" show-word-limit />
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
.handled-text { color: #909399; font-size: 12px; }
.feedback-content { color: #606266; line-height: 1.6; white-space: pre-wrap; word-break: break-word; }
</style>
