<script setup lang="ts">
/** 公告管理（契约 §8.13.2）：列表 + 创建/编辑弹窗 + 删除（cms:announcement:*） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { listAnnouncements, createAnnouncement, updateAnnouncement, deleteAnnouncement } from '@/api/cms'
import { fmtDateTime } from '@/utils/date'

type AnnouncementRow = {
  id: number
  title: string
  content: string
  type: number
  is_top: number
  status: number
  publish_time: string
  create_time: string
}

type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

/** 公告类型：1=系统 2=活动 3=维护（cms_announcement.type） */
const TYPE_MAP: Record<number, { label: string; type: TagType }> = {
  1: { label: '系统', type: 'primary' },
  2: { label: '活动', type: 'success' },
  3: { label: '维护', type: 'warning' },
}

/** 公告状态：0=草稿 1=已发布 2=已下线 */
const STATUS_MAP: Record<number, { label: string; type: TagType }> = {
  0: { label: '草稿', type: 'info' },
  1: { label: '已发布', type: 'success' },
  2: { label: '已下线', type: 'info' },
}

const list = ref<AnnouncementRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listAnnouncements({ page: page.value, page_size: pageSize.value })
    list.value = (res.list ?? []) as AnnouncementRow[]
    total.value = res.total
    page.value = res.page
    pageSize.value = res.page_size
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

/* ---------- 创建 / 编辑弹窗 ---------- */

const dialogVisible = ref(false)
const editingId = ref<number | null>(null)
const saving = ref(false)

const form = reactive<{ title: string; content: string; type: number; is_top: number; status: number }>({
  title: '',
  content: '',
  type: 1,
  is_top: 0,
  status: 0,
})

function openCreate(): void {
  editingId.value = null
  form.title = ''
  form.content = ''
  form.type = 1
  form.is_top = 0
  form.status = 0
  dialogVisible.value = true
}

function openEdit(row: AnnouncementRow): void {
  editingId.value = row.id
  form.title = row.title
  form.content = row.content
  form.type = row.type
  form.is_top = row.is_top
  form.status = row.status
  dialogVisible.value = true
}

async function onSave(): Promise<void> {
  if (form.title.trim() === '') {
    ElMessage.error('请输入公告标题')
    return
  }
  if (form.content.trim() === '') {
    ElMessage.error('请输入公告内容')
    return
  }
  saving.value = true
  try {
    const payload = {
      title: form.title,
      content: form.content,
      type: form.type,
      is_top: form.is_top,
      status: form.status,
    }
    if (editingId.value === null) {
      await createAnnouncement(payload)
      ElMessage.success('公告创建成功')
    } else {
      await updateAnnouncement(editingId.value, payload)
      ElMessage.success('公告已更新')
    }
    dialogVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    saving.value = false
  }
}

/* ---------- 删除（高风险：二次确认 + 密码） ---------- */

const deleteVisible = ref(false)
const deleteLoading = ref(false)
const deleteTarget = ref<AnnouncementRow | null>(null)

function openDelete(row: AnnouncementRow): void {
  deleteTarget.value = row
  deleteVisible.value = true
}

async function doDelete(_payload: { reason: string; password: string }): Promise<void> {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  try {
    await deleteAnnouncement(deleteTarget.value.id)
    ElMessage.success('公告已删除')
    deleteVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    deleteLoading.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <div class="toolbar">
      <el-button v-permission="'cms:announcement:create'" type="primary" @click="openCreate">发布公告</el-button>
    </div>

    <DataTable :data="list" :loading="loading" empty-text="暂无公告">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column label="标题" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as AnnouncementRow).title }}</template>
      </el-table-column>
      <el-table-column label="类型" width="90">
        <template #default="{ row }">
          <el-tag :type="TYPE_MAP[(row as AnnouncementRow).type]?.type ?? 'info'" size="small">
            {{ TYPE_MAP[(row as AnnouncementRow).type]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="置顶" width="80">
        <template #default="{ row }">
          <el-tag v-if="(row as AnnouncementRow).is_top === 1" type="warning" size="small">置顶</el-tag>
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="STATUS_MAP[(row as AnnouncementRow).status]?.type ?? 'info'" size="small">
            {{ STATUS_MAP[(row as AnnouncementRow).status]?.label ?? '未知' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="发布时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as AnnouncementRow).publish_time) }}</template>
      </el-table-column>
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as AnnouncementRow).create_time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'cms:announcement:edit'" link type="primary" @click="openEdit(row as AnnouncementRow)">编辑</el-button>
          <el-button v-permission="'cms:announcement:delete'" link type="danger" @click="openDelete(row as AnnouncementRow)">删除</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <!-- 创建 / 编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId === null ? '发布公告' : '编辑公告'"
      width="560px"
      :close-on-click-modal="false"
    >
      <el-form label-width="90px">
        <el-form-item label="标题" required>
          <el-input v-model="form.title" placeholder="请输入公告标题" maxlength="128" show-word-limit />
        </el-form-item>
        <el-form-item label="内容" required>
          <el-input v-model="form.content" type="textarea" :rows="6" placeholder="请输入公告内容" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="form.type" style="width: 200px">
            <el-option label="系统公告" :value="1" />
            <el-option label="活动公告" :value="2" />
            <el-option label="维护公告" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="置顶">
          <el-switch v-model="form.is_top" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="form.status" style="width: 200px">
            <el-option label="草稿" :value="0" />
            <el-option label="已发布" :value="1" />
            <el-option label="已下线" :value="2" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
      </template>
    </el-dialog>

    <!-- 删除确认 -->
    <ConfirmDialog
      v-model="deleteVisible"
      title="删除公告"
      :message="`将永久删除公告「${deleteTarget?.title ?? ''}」，删除后不可恢复`"
      :need-reason="false"
      :loading="deleteLoading"
      @confirm="doDelete"
    />
  </div>
</template>

<style scoped>
.toolbar { display: flex; justify-content: flex-end; margin-bottom: 12px; }
</style>
