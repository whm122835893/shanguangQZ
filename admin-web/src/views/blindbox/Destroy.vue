<script setup lang="ts">
/** 销毁盲盒（契约 §8.6 destroy）：销毁表单（quantity）+ destroy 记录表 */
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import DataTable from '@/components/DataTable.vue'
import Pagination from '@/components/Pagination.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { blindboxDetail, destroyBlindbox, blindboxDestroyRecords } from '@/api/blindbox'
import { fmtDateTime } from '@/utils/date'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const num = (v: unknown): number => (v === null || v === undefined || v === '' ? 0 : Number(v))
const str = (v: unknown): string => (v === null || v === undefined ? '' : String(v))

const loading = ref(true)
const boxName = ref('')
const stockPool = ref(0)

const form = reactive<{ quantity: number | undefined; reason: string }>({
  quantity: undefined,
  reason: '',
})

async function fetchDetail(): Promise<void> {
  const d = await blindboxDetail(id)
  boxName.value = String(d.name ?? '')
  stockPool.value = num(d.stock_pool)
}

/* ---------- 提交销毁（高风险：ConfirmDialog 原因 + 密码） ---------- */
const confirmVisible = ref(false)
const submitting = ref(false)

function onSubmit(): void {
  const qty = num(form.quantity)
  if (!Number.isInteger(qty) || qty <= 0) {
    ElMessage.error('销毁数量必须为正整数')
    return
  }
  if (qty > stockPool.value) {
    ElMessage.error(`销毁数量不得超过盲盒库存池（${stockPool.value}）`)
    return
  }
  if (form.reason.trim() === '') {
    ElMessage.error('请填写销毁原因')
    return
  }
  confirmVisible.value = true
}

async function doDestroy(payload: { reason: string; password: string }): Promise<void> {
  submitting.value = true
  try {
    await destroyBlindbox(id, num(form.quantity), payload.reason, payload.password)
    ElMessage.success('销毁成功')
    confirmVisible.value = false
    form.quantity = undefined
    form.reason = ''
    await Promise.all([fetchDetail(), fetchRecords()])
  } finally {
    submitting.value = false
  }
}

/* ---------- 销毁记录 ---------- */
const records = ref<Record<string, unknown>[]>([])
const total = ref(0)
const page = ref(1)
const pageSize = ref(10)
const recordsLoading = ref(false)

async function fetchRecords(): Promise<void> {
  recordsLoading.value = true
  try {
    const data = await blindboxDestroyRecords(id, { page: page.value, page_size: pageSize.value })
    records.value = data.list
    total.value = data.total
  } finally {
    recordsLoading.value = false
  }
}

function onPageChange(p: number, s: number): void {
  page.value = p
  pageSize.value = s
  void fetchRecords()
}

onMounted(async () => {
  try {
    await Promise.all([fetchDetail(), fetchRecords()])
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-loading="loading">
    <div class="card">
      <div class="bar">
        <h3 class="page-title">销毁盲盒</h3>
        <el-tag>{{ boxName }}</el-tag>
        <el-tag type="warning" effect="plain">盲盒库存池：{{ stockPool }}</el-tag>
      </div>
      <div class="danger-bar">⚠️ 销毁为高风险操作：销毁后库存池永久扣减，不可恢复</div>

      <el-form label-width="110px" style="max-width: 560px">
        <el-form-item label="销毁数量" required>
          <el-input-number v-model="form.quantity" :min="1" :step="1" :precision="0" style="width: 200px" />
          <span class="form-tip">不超过库存池 {{ stockPool }}</span>
        </el-form-item>
        <el-form-item label="销毁原因" required>
          <el-input v-model="form.reason" type="textarea" :rows="2" maxlength="200" placeholder="将写入审计日志" />
        </el-form-item>
        <el-form-item>
          <el-button type="danger" @click="onSubmit">提交销毁</el-button>
          <el-button @click="router.push(`/blindbox/${id}`)">返回详情</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card">
      <h3 class="page-title">销毁记录</h3>
      <DataTable :data="records" :loading="recordsLoading" empty-text="暂无销毁记录">
        <el-table-column label="ID" prop="id" width="80" />
        <el-table-column label="销毁数量" width="100">
          <template #default="{ row }">{{ num(row.quantity) }}</template>
        </el-table-column>
        <el-table-column label="原因" min-width="200" show-overflow-tooltip>
          <template #default="{ row }">{{ str(row.reason) || '-' }}</template>
        </el-table-column>
        <el-table-column label="操作人" width="120">
          <template #default="{ row }">{{ str(row.admin_name) || '-' }}</template>
        </el-table-column>
        <el-table-column label="时间" width="170">
          <template #default="{ row }">{{ fmtDateTime(str(row.time)) }}</template>
        </el-table-column>
      </DataTable>
      <Pagination :page="page" :page-size="pageSize" :total="total" :loading="recordsLoading" @change="onPageChange" />
    </div>

    <!-- 高风险确认 -->
    <ConfirmDialog
      v-model="confirmVisible"
      title="确认销毁盲盒"
      :message="`确认从「${boxName}」库存池销毁 ${num(form.quantity)} 个盲盒？销毁后不可恢复`"
      :loading="submitting"
      @confirm="doDestroy"
    />
  </div>
</template>

<style scoped>
.page-title { margin: 0 0 16px; font-size: 16px; }
.bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
.form-tip { font-size: 12px; color: #909399; margin-left: 10px; }
</style>
