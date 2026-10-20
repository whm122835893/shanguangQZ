<script setup lang="ts">
/** Banner 管理（契约 §8.13.1）：列表 + 创建/编辑弹窗 + 删除（cms:banner:*） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import UploadImage from '@/components/UploadImage.vue'
import { listBanners, bannerDetail, createBanner, updateBanner, deleteBanner } from '@/api/cms'
import { fmtDateTime } from '@/utils/date'

type BannerRow = {
  id: number
  image: string
  link: string
  sort_order: number
  status: number
  show_time_start: string
  show_time_end: string
  create_time: string
}

const list = ref<BannerRow[]>([])
const page = ref(1)
const pageSize = ref(20)
const total = ref(0)
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listBanners({ page: page.value, page_size: pageSize.value })
    list.value = (res.list ?? []) as BannerRow[]
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

const form = reactive<{ title: string; image: string; link: string; sort_order: number; status: number }>({
  title: '',
  image: '',
  link: '',
  sort_order: 0,
  status: 1,
})

function openCreate(): void {
  editingId.value = null
  form.title = ''
  form.image = ''
  form.link = ''
  form.sort_order = 0
  form.status = 1
  dialogVisible.value = true
}

async function openEdit(row: BannerRow): Promise<void> {
  editingId.value = row.id
  form.title = ''
  form.image = row.image
  form.link = row.link
  form.sort_order = row.sort_order
  form.status = row.status
  dialogVisible.value = true
  // 列表接口不含标题，编辑回显需拉详情
  try {
    const d = await bannerDetail(row.id)
    form.title = String(d.title ?? '')
  } catch {
    /* request 拦截器已统一提示 */
  }
}

async function onSave(): Promise<void> {
  if (form.image === '') {
    ElMessage.error('请上传 Banner 图片')
    return
  }
  if (form.link === '') {
    ElMessage.error('请填写跳转链接')
    return
  }
  saving.value = true
  try {
    if (editingId.value === null) {
      await createBanner({
        title: form.title,
        image: form.image,
        link: form.link,
        sort_order: form.sort_order,
        status: form.status,
      })
      ElMessage.success('Banner 创建成功')
    } else {
      // 编辑接口不接收标题（仅图片/链接/排序/上下架可改）
      await updateBanner(editingId.value, {
        image: form.image,
        link: form.link,
        sort_order: form.sort_order,
        status: form.status,
      })
      ElMessage.success('Banner 已更新')
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
const deleteTarget = ref<BannerRow | null>(null)

function openDelete(row: BannerRow): void {
  deleteTarget.value = row
  deleteVisible.value = true
}

async function doDelete(_payload: { reason: string; password: string }): Promise<void> {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  try {
    await deleteBanner(deleteTarget.value.id)
    ElMessage.success('Banner 已删除')
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
      <el-button v-permission="'cms:banner:create'" type="primary" @click="openCreate">新增 Banner</el-button>
    </div>

    <DataTable :data="list" :loading="loading" empty-text="暂无 Banner">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column label="图片" width="150">
        <template #default="{ row }">
          <el-image
            v-if="(row as BannerRow).image"
            :src="(row as BannerRow).image"
            :preview-src-list="[(row as BannerRow).image]"
            preview-teleported
            fit="cover"
            class="banner-thumb"
          />
          <span v-else>-</span>
        </template>
      </el-table-column>
      <el-table-column label="跳转链接" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as BannerRow).link || '-' }}</template>
      </el-table-column>
      <el-table-column label="排序" width="90" prop="sort_order" />
      <el-table-column label="状态" width="100">
        <template #default="{ row }">
          <el-tag :type="(row as BannerRow).status === 1 ? 'success' : 'info'" size="small">
            {{ (row as BannerRow).status === 1 ? '上架' : '下架' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="创建时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as BannerRow).create_time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="130" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'cms:banner:edit'" link type="primary" @click="openEdit(row as BannerRow)">编辑</el-button>
          <el-button v-permission="'cms:banner:delete'" link type="danger" @click="openDelete(row as BannerRow)">删除</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />

    <!-- 创建 / 编辑弹窗 -->
    <el-dialog
      v-model="dialogVisible"
      :title="editingId === null ? '新增 Banner' : '编辑 Banner'"
      width="520px"
      :close-on-click-modal="false"
    >
      <el-form label-width="90px">
        <el-form-item label="标题">
          <el-input v-model="form.title" :disabled="editingId !== null" placeholder="Banner 标题（可选）" maxlength="128" />
          <div v-if="editingId !== null" class="form-tip">编辑接口不支持修改标题，仅新建时可设置</div>
        </el-form-item>
        <el-form-item label="图片" required>
          <UploadImage v-model="form.image" />
        </el-form-item>
        <el-form-item label="跳转链接" required>
          <el-input v-model="form.link" placeholder="请输入跳转链接" />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort_order" :min="0" :step="1" :precision="0" />
          <span class="form-tip" style="margin-left: 8px">数值越大越靠前</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="上架" inactive-text="下架" />
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
      title="删除 Banner"
      :message="`将永久删除 Banner #${deleteTarget?.id ?? ''}，删除后不可恢复`"
      :need-reason="false"
      :loading="deleteLoading"
      @confirm="doDelete"
    />
  </div>
</template>

<style scoped>
.toolbar { display: flex; justify-content: flex-end; margin-bottom: 12px; }
.banner-thumb { width: 120px; height: 60px; border-radius: 4px; display: block; }
.form-tip { font-size: 12px; color: #909399; line-height: 1.5; margin-top: 4px; }
</style>
