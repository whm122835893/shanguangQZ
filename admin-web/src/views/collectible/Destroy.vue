<script setup lang="ts">
/** 销毁库存（契约 §8.5）：高风险操作（原因 + 密码确认）；下方展示销毁记录 */
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormRules } from 'element-plus'
import { destroyStock, destroyRecords } from '@/api/collectible'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import { fmtDateTime } from '@/utils/date'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const form = reactive<{ quantity: number | undefined }>({ quantity: 1 })
const confirmVisible = ref(false)
const confirmLoading = ref(false)

const rows = ref<Record<string, unknown>[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(10)
const loading = ref(false)

const rules: FormRules = {
  quantity: [
    {
      validator: (_rule: unknown, value: number | undefined, callback: (err?: Error) => void) => {
        if (value === undefined || !Number.isInteger(value) || value <= 0) {
          callback(new Error('销毁数量必须为正整数'))
        } else {
          callback()
        }
      },
      trigger: 'change',
    },
  ],
}

async function loadRecords(): Promise<void> {
  loading.value = true
  try {
    const d = await destroyRecords(id, { page: page.value, page_size: pageSize.value })
    rows.value = d.list
    total.value = d.total
  } finally {
    loading.value = false
  }
}

function onPageChange(p: number, ps: number): void {
  page.value = p
  pageSize.value = ps
  void loadRecords()
}

async function onConfirm(payload: { reason: string; password: string }): Promise<void> {
  const quantity = form.quantity
  if (quantity === undefined || quantity <= 0) {
    ElMessage.error('销毁数量必须为正整数')
    return
  }
  confirmLoading.value = true
  try {
    await destroyStock(id, quantity, payload.reason, payload.password)
    ElMessage.success('销毁成功')
    confirmVisible.value = false
    page.value = 1
    void loadRecords()
  } finally {
    confirmLoading.value = false
  }
}

function cellStr(row: unknown, key: string): string {
  const v = (row as Record<string, unknown>)[key]
  if (v === null || v === undefined || v === '') return '-'
  return String(v)
}

function cellTime(row: unknown): string {
  return fmtDateTime(cellStr(row, 'created_at'))
}

onMounted(() => {
  void loadRecords()
})
</script>

<template>
  <div>
    <el-page-header class="card" content="销毁库存" @back="router.push('/collectible')" />

    <div class="danger-bar">
      ⚠️ 销毁为高风险不可逆操作：库存将从库存池永久核销（不影响已售出藏品），需填写原因并经管理员密码确认。
    </div>

    <div class="card" style="max-width: 640px">
      <el-form :model="form" :rules="rules" label-width="90px" @submit.prevent="confirmVisible = true">
        <el-form-item label="销毁数量" prop="quantity">
          <el-input-number v-model="form.quantity" :min="1" :step="1" step-strictly />
        </el-form-item>
        <el-form-item>
          <el-button type="danger" native-type="submit">销毁库存</el-button>
          <el-button @click="router.push('/collectible')">返回列表</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card">
      <h3 style="margin-top: 0">销毁记录</h3>
      <DataTable :data="rows" :loading="loading" empty-text="暂无销毁记录">
        <el-table-column label="ID" width="80">
          <template #default="{ row }">{{ cellStr(row, 'id') }}</template>
        </el-table-column>
        <el-table-column label="数量" width="90">
          <template #default="{ row }">{{ cellStr(row, 'quantity') }}</template>
        </el-table-column>
        <el-table-column label="销毁原因" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">{{ cellStr(row, 'reason') }}</template>
        </el-table-column>
        <el-table-column label="操作人" width="120">
          <template #default="{ row }">{{ cellStr(row, 'operator') }}</template>
        </el-table-column>
        <el-table-column label="销毁时间" width="170">
          <template #default="{ row }">{{ cellTime(row) }}</template>
        </el-table-column>
      </DataTable>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="loading" @change="onPageChange" />
    </div>

    <ConfirmDialog
      v-model="confirmVisible"
      title="销毁库存确认"
      :message="`即将从库存池永久核销 ${form.quantity ?? 0} 个藏品，该操作不可恢复，请确认库存数量无误。`"
      :loading="confirmLoading"
      @confirm="onConfirm"
    />
  </div>
</template>
