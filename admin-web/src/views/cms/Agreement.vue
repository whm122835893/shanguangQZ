<script setup lang="ts">
/** 协议管理（契约 §8.13.3）：每类型一份文本，只读列表 + 编辑弹窗（cms:agreement:edit） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import DataTable from '@/components/DataTable.vue'
import { listAgreements, updateAgreement } from '@/api/cms'
import { fmtDateTime } from '@/utils/date'

type AgreementRow = {
  id: number
  type: string
  title: string
  content: string
  version: string
  status: number
  update_time: string
}

/** 协议类型（cms_agreement.type：每类型唯一一份） */
const TYPE_MAP: Record<string, string> = {
  user: '用户协议',
  privacy: '隐私政策',
  policy: '平台规则',
}

const list = ref<AgreementRow[]>([])
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listAgreements()
    list.value = (res.list ?? []) as AgreementRow[]
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

/* ---------- 编辑弹窗（title / content） ---------- */

const dialogVisible = ref(false)
const saving = ref(false)
const editing = ref<AgreementRow | null>(null)

const form = reactive<{ title: string; content: string }>({ title: '', content: '' })

function openEdit(row: AgreementRow): void {
  editing.value = row
  form.title = row.title
  form.content = row.content
  dialogVisible.value = true
}

async function onSave(): Promise<void> {
  if (form.title.trim() === '') {
    ElMessage.error('请输入协议标题')
    return
  }
  if (!editing.value) return
  saving.value = true
  try {
    await updateAgreement(editing.value.id, { title: form.title, content: form.content })
    ElMessage.success('协议已更新')
    dialogVisible.value = false
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div class="card">
    <el-alert
      title="协议文本每类型仅一份，编辑保存后立即对 C 端生效；版本号随协议修订由后端记录"
      type="info"
      :closable="false"
      show-icon
      style="margin-bottom: 12px"
    />

    <DataTable :data="list" :loading="loading" empty-text="暂无协议">
      <el-table-column prop="id" label="ID" width="80" />
      <el-table-column label="类型" width="120">
        <template #default="{ row }">
          <el-tag size="small">{{ TYPE_MAP[(row as AgreementRow).type] ?? (row as AgreementRow).type }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="标题" min-width="200" show-overflow-tooltip>
        <template #default="{ row }">{{ (row as AgreementRow).title }}</template>
      </el-table-column>
      <el-table-column label="版本" width="110">
        <template #default="{ row }">{{ (row as AgreementRow).version || '-' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="(row as AgreementRow).status === 1 ? 'success' : 'info'" size="small">
            {{ (row as AgreementRow).status === 1 ? '启用' : '停用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="更新时间" width="170">
        <template #default="{ row }">{{ fmtDateTime((row as AgreementRow).update_time) }}</template>
      </el-table-column>
      <el-table-column label="操作" width="90" fixed="right">
        <template #default="{ row }">
          <el-button v-permission="'cms:agreement:edit'" link type="primary" @click="openEdit(row as AgreementRow)">编辑</el-button>
        </template>
      </el-table-column>
    </DataTable>

    <!-- 编辑弹窗 -->
    <el-dialog v-model="dialogVisible" title="编辑协议" width="640px" :close-on-click-modal="false">
      <el-form label-width="90px">
        <el-form-item label="协议类型">
          <el-tag>{{ editing ? (TYPE_MAP[editing.type] ?? editing.type) : '-' }}</el-tag>
        </el-form-item>
        <el-form-item label="标题" required>
          <el-input v-model="form.title" placeholder="请输入协议标题" maxlength="128" show-word-limit />
        </el-form-item>
        <el-form-item label="内容">
          <el-input v-model="form.content" type="textarea" :rows="14" placeholder="请输入协议正文（支持富文本标记）" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>
