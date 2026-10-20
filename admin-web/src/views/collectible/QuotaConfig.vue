<script setup lang="ts">
/** 配额配置（契约 §8.5）：配额列表 + 添加/删除 + 全量保存，展示已使用数量 */
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { collectibleDetail, configQuotas } from '@/api/collectible'
import DataTable from '@/components/DataTable.vue'
import { required } from '@/utils/validate'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

interface QuotaItem {
  [key: string]: unknown
  quota_type: string
  quota_name: string
  planned_quantity: number
  used_count: number
}

const QUOTA_TYPES = [
  { label: '保留配额', value: 'reserve' },
  { label: '白名单', value: 'whitelist' },
  { label: '优先购', value: 'priority' },
  { label: '空投预留', value: 'airdrop' },
]

function quotaTypeLabel(t: string): string {
  return QUOTA_TYPES.find((x) => x.value === t)?.label ?? t
}

const loading = ref(true)
const saving = ref(false)
const quotas = ref<QuotaItem[]>([])

const showAdd = ref(false)
const addFormRef = ref<FormInstance>()
const addForm = reactive<{
  quota_type: string
  quota_name: string
  planned_quantity: number | undefined
}>({
  quota_type: 'reserve',
  quota_name: '',
  planned_quantity: 1,
})

function validatePositiveInt(_rule: unknown, value: number | undefined, callback: (err?: Error) => void): void {
  if (value === undefined || !Number.isInteger(value) || value <= 0) {
    callback(new Error('计划数量必须为正整数'))
  } else {
    callback()
  }
}

const addRules: FormRules = {
  quota_type: [required('请选择或输入配额类型')],
  quota_name: [required('请输入配额名称')],
  planned_quantity: [{ validator: validatePositiveInt, trigger: 'change' }],
}

function toStr(v: unknown): string {
  return v === null || v === undefined ? '' : String(v)
}

function toNum(v: unknown): number {
  const n = Number(v)
  return Number.isFinite(n) ? n : 0
}

onMounted(async () => {
  try {
    const d = await collectibleDetail(id)
    const raw = d['quotas']
    if (Array.isArray(raw)) {
      quotas.value = (raw as Record<string, unknown>[]).map((q) => ({
        quota_type: toStr(q['quota_type']),
        quota_name: toStr(q['quota_name']),
        planned_quantity: toNum(q['planned_quantity']),
        used_count: toNum(q['used_count']),
      }))
    }
  } finally {
    loading.value = false
  }
})

async function onAdd(): Promise<void> {
  if (!addFormRef.value) return
  try {
    await addFormRef.value.validate()
  } catch {
    return
  }
  quotas.value.push({
    quota_type: addForm.quota_type,
    quota_name: addForm.quota_name,
    planned_quantity: addForm.planned_quantity ?? 0,
    used_count: 0,
  })
  showAdd.value = false
  addForm.quota_name = ''
  addForm.planned_quantity = 1
  ElMessage.success('已添加到列表，请点击「保存配置」提交')
}

function onRemove(idx: number): void {
  quotas.value.splice(idx, 1)
}

async function onSave(): Promise<void> {
  saving.value = true
  try {
    await configQuotas(
      id,
      quotas.value.map((q) => ({
        quota_type: q.quota_type,
        quota_name: q.quota_name,
        planned_quantity: q.planned_quantity,
      })),
    )
    ElMessage.success('配额配置已保存')
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div v-loading="loading">
    <el-page-header class="card" content="配额配置" @back="router.push('/collectible')" />

    <div class="card">
      <div style="margin-bottom: 12px; display: flex; gap: 12px">
        <el-button type="primary" @click="showAdd = true">添加配额</el-button>
        <el-button type="success" :loading="saving" @click="onSave">保存配置</el-button>
      </div>
      <DataTable :data="quotas" :loading="saving" empty-text="暂无配额，点击「添加配额」创建">
        <el-table-column label="配额类型" width="140">
          <template #default="{ row }">
            <el-tag size="small">{{ quotaTypeLabel(String((row as Record<string, unknown>)['quota_type'] ?? '')) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="配额名称" min-width="140">
          <template #default="{ row }">{{ (row as Record<string, unknown>)['quota_name'] ?? '-' }}</template>
        </el-table-column>
        <el-table-column label="计划数量" width="110">
          <template #default="{ row }">{{ (row as Record<string, unknown>)['planned_quantity'] ?? 0 }}</template>
        </el-table-column>
        <el-table-column label="已使用" width="110">
          <template #default="{ row }">{{ (row as Record<string, unknown>)['used_count'] ?? 0 }}</template>
        </el-table-column>
        <el-table-column label="操作" width="90">
          <template #default="{ $index }">
            <el-button link type="danger" @click="onRemove($index)">删除</el-button>
          </template>
        </el-table-column>
      </DataTable>
    </div>

    <el-dialog v-model="showAdd" title="添加配额" width="440px" :close-on-click-modal="false">
      <el-form ref="addFormRef" :model="addForm" :rules="addRules" label-width="90px">
        <el-form-item label="配额类型" prop="quota_type">
          <el-select
            v-model="addForm.quota_type"
            filterable
            allow-create
            default-first-option
            placeholder="选择或输入配额类型"
            style="width: 100%"
          >
            <el-option v-for="t in QUOTA_TYPES" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="配额名称" prop="quota_name">
          <el-input v-model="addForm.quota_name" placeholder="如：Priority-001" maxlength="50" />
        </el-form-item>
        <el-form-item label="计划数量" prop="planned_quantity">
          <el-input-number v-model="addForm.planned_quantity" :min="1" :step="1" step-strictly />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAdd = false">取消</el-button>
        <el-button type="primary" @click="onAdd">添加</el-button>
      </template>
    </el-dialog>
  </div>
</template>
