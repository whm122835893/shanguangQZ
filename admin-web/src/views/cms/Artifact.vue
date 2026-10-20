<script setup lang="ts">
/** 文物展览（契约 §8.13.4）：列表 + 创建/编辑弹窗 + 删除 + 分页（cms:artifact:*） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import UploadImage from '@/components/UploadImage.vue'
import { listArtifacts, createArtifact, updateArtifact, deleteArtifact } from '@/api/cms'
import { fmtDateTime } from '@/utils/date'

type ArtifactRow = {
  id: number
  name: string
  dynasty: string
  material: string
  image: string
  description: string
  sort_order: number
  status: number
  create_time: string
}

const list = ref<ArtifactRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listArtifacts({ page: page.value, page_size: pageSize.value })
    list.value = (res.list ?? []) as ArtifactRow[]
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

const form = reactive<{
  name: string
  dynasty: string
  material: string
  image: string
  description: string
  sort_order: number
  status: number
}>({
  name: '',
  dynasty: '',
  material: '',
  image: '',
  description: '',
  sort_order: 0,
  status: 1,
})

function openCreate(): void {
  editingId.value = null
  form.name = ''
  form.dynasty = ''
  form.material = ''
  form.image = ''
  form.description = ''
  form.sort_order = 0
  form.status = 1
  dialogVisible.value = true
}

function openEdit(row: ArtifactRow): void {
  editingId.value = row.id
  form.name = row.name
  form.dynasty = row.dynasty
  form.material = row.material
  form.image = row.image
  form.description = row.description
  form.sort_order = Number(row.sort_order) || 0
  form.status = row.status
  dialogVisible.value = true
}

async function onSave(): Promise<void> {
  if (form.name.trim() === '') {
    ElMessage.error('请输入文物名称')
    return
  }
  if (form.image === '') {
    ElMessage.error('请上传文物封面图')
    return
  }
  saving.value = true
  try {
    const payload: Record<string, unknown> = {
      name: form.name,
      dynasty: form.dynasty,
      material: form.material,
      image: form.image,
      description: form.description,
      status: form.status,
    }
    if (editingId.value === null) {
      // 列表接口不回显 sort，编辑时不传 sort_order 以免覆盖原排序
      payload.sort_order = form.sort_order
      await createArtifact(payload)
      ElMessage.success('文物创建成功')
    } else {
      await updateArtifact(editingId.value, payload)
      ElMessage.success('文物已更新')
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
const deleteTarget = ref<ArtifactRow | null>(null)

function openDelete(row: ArtifactRow): void {
  deleteTarget.value = row
  deleteVisible.value = true
}

async function doDelete(_payload: { reason: string; password: string }): Promise<void> {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  try {
    await deleteArtifact(deleteTarget.value.id)
    ElMessage.success('文物已删除')
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
      <el-button v-permission="'cms:artifact:create'" type="primary" @click="openCreate">新增文物</el-button>
    </div>

    <DataTable :data="list" :loading="loading" empty-text="暂无展品">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column label="封面" width="110">
        <template #default="{ row }">
          <el-image
            v-if="(row as ArtifactRow).image"
            :src="(row as ArtifactRow).image"
            :preview-src-list="[(row as ArtifactRow).image]"
            preview-teleported
            fit="cover"
            class="artifact-thumb"
          />
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="名称" min-width="160" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as ArtifactRow).name }}</template>
      </el-table-column>
      <el-table-column label="朝代" width="110">
        <template #default="{ row }">{{ (row as ArtifactRow).dynasty || '-' }}</template>
      </el-table-column>
      <el-table-column label="材质" width="110">
        <template #default="{ row }">{{ (row as ArtifactRow).material || '-' }}</template>
      </el-table-column>
      <el-table-column label="描述" min-width="180" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as ArtifactRow).description || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="(row as ArtifactRow).status === 1 ? 'success' : 'info'" size="small">
            {{ (row as ArtifactRow).status === 1 ? '展示' : '隐藏' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as ArtifactRow).create_time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'cms:artifact:edit'" link type="primary" @click="openEdit(row as ArtifactRow)">编辑</el-button>
          <el-button v-permission="'cms:artifact:delete'" link type="danger" @click="openDelete(row as ArtifactRow)">删除</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <!-- 创建 / 编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId === null ? '新增文物' : '编辑文物'"
      width="560px"
      :close-on-click-modal="false"
    >
      <el-form label-width="90px">
        <el-form-item label="名称" required>
          <el-input v-model="form.name" placeholder="请输入文物名称" maxlength="128" />
        </el-form-item>
        <el-form-item label="朝代">
          <el-input v-model="form.dynasty" placeholder="如：商代（可选）" maxlength="64" />
        </el-form-item>
        <el-form-item label="材质">
          <el-input v-model="form.material" placeholder="如：青铜（可选）" maxlength="64" />
        </el-form-item>
        <el-form-item label="封面图" required>
          <UploadImage v-model="form.image" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="4" placeholder="文物介绍（可选）" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" :step="1" :precision="0" :disabled="editingId !== null" />
          <span class="form-tip" style="margin-left: 8px">{{ editingId === null ? '数值越大越靠前' : '列表不回显排序，编辑时不修改原排序' }}</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="展示" inactive-text="隐藏" />
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
      title="删除文物"
      :message="`将永久删除文物「${deleteTarget?.name ?? ''}」，删除后不可恢复`"
      :need-reason="false"
      :loading="deleteLoading"
      @confirm="doDelete"
    />
  </div>
</template>

<style scoped>
.toolbar { display: flex; justify-content: flex-end; margin-bottom: 12px; }
.artifact-thumb { width: 72px; height: 72px; border-radius: 4px; display: block; }
.form-tip { font-size: 12px; color: #909399; line-height: 1.5; }
</style>
