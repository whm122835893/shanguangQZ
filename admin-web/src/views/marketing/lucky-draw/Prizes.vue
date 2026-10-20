<script setup lang="ts">
/** 奖项池（契约 §8.11.4，113~116）：全量奖项列表 + 添加/编辑 + 删除；
 *  概率闭环：probability ∈ (0,100]，prize_type=6 为空奖不计入非空奖概率总和（>100 标红警示） */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import DataTable from '@/components/DataTable.vue'
import { listPrizes, createPrize, updatePrize, deletePrize } from '@/api/marketing'
import Decimal from 'decimal.js'

const PRIZE_EMPTY = 6

/** 奖项类型：1=积分 2=实物 3=藏品 4=盲盒 5=余额 6=空奖（空奖为差额兜底） */
const PRIZE_TYPE_META: Record<number, { label: string; empty: boolean }> = {
  1: { label: '积分(point)', empty: false },
  2: { label: '实物(goods)', empty: false },
  3: { label: '藏品(collection)', empty: false },
  4: { label: '盲盒(blindbox)', empty: false },
  5: { label: '余额(balance)', empty: false },
  6: { label: '空奖', empty: true },
}

const PRIZE_TYPE_OPTIONS = Object.entries(PRIZE_TYPE_META).map(([v, m]) => ({
  label: m.label,
  value: Number(v),
}))

function prizeTypeMeta(t: number): { label: string; empty: boolean } {
  return PRIZE_TYPE_META[t] ?? { label: `未知(${t})`, empty: false }
}

/** 奖项行（后端 prizeBrief 口径） */
type PrizeRowVM = {
  id: number
  lottery_id: number
  name: string
  prize_type: number
  prize_config: unknown
  probability: string
  planned_quantity: number
  sent_quantity: number
  is_empty_prize: number
  status: number
}

function asRow(row: unknown): PrizeRowVM {
  return row as PrizeRowVM
}

function statusMeta(status: number): { label: string; type: 'success' | 'info' } {
  return status === 1 ? { label: '启用', type: 'success' } : { label: '停用', type: 'info' }
}

const route = useRoute()
const router = useRouter()
const activityId = Number(route.params.id)

const rows = ref<Record<string, unknown>[]>([])
const loading = ref(false)

async function fetchList(): Promise<void> {
  loading.value = true
  try {
    const res = await listPrizes(activityId)
    rows.value = res.list
  } catch {
    /* request 拦截器已统一提示 */
  } finally {
    loading.value = false
  }
}

/** 非空奖概率总和（prize_type=6 空奖不计入） */
const totalProb = computed<string>(() => {
  let sum = new Decimal(0)
  for (const r of rows.value) {
    const p = asRow(r)
    if (p.prize_type === PRIZE_EMPTY) continue
    try {
      sum = sum.plus(new Decimal(String(p.probability ?? 0)))
    } catch {
      /* 忽略非法概率值 */
    }
  }
  return sum.toFixed(2)
})

const overLimit = computed(() => new Decimal(totalProb.value).greaterThan(100))

/* ---------- 添加 / 编辑（114/115） ---------- */

const dialogVisible = ref(false)
const editingPid = ref<number | null>(null)
const submitting = ref(false)

const form = reactive<{
  name: string
  prize_type: number
  probability: number | undefined
  planned_quantity: number | undefined
  status: number
}>({ name: '', prize_type: 1, probability: undefined, planned_quantity: 0, status: 1 })

const configText = ref('{}')

const dialogTitle = computed(() => (editingPid.value === null ? '添加奖项' : '编辑奖项'))

function openCreate(): void {
  editingPid.value = null
  Object.assign(form, { name: '', prize_type: 1, probability: undefined, planned_quantity: 0, status: 1 })
  configText.value = '{}'
  dialogVisible.value = true
}

function openEdit(row: PrizeRowVM): void {
  editingPid.value = row.id
  Object.assign(form, {
    name: row.name,
    prize_type: row.prize_type,
    probability: Number(row.probability),
    planned_quantity: row.planned_quantity,
    status: row.status,
  })
  const cfg = row.prize_config
  configText.value = cfg === null || cfg === undefined ? '{}' : JSON.stringify(cfg, null, 2)
  dialogVisible.value = true
}

async function onSubmit(): Promise<void> {
  if (form.name.trim() === '') {
    ElMessage.error('请填写奖项名称')
    return
  }
  const prob = form.probability
  if (prob === undefined || !(prob > 0) || prob > 100) {
    ElMessage.error('probability 必须 ∈ (0, 100]')
    return
  }
  let config: unknown = {}
  if (configText.value.trim() !== '') {
    try {
      config = JSON.parse(configText.value)
    } catch {
      ElMessage.error('prize_config JSON 解析失败，请检查格式')
      return
    }
  }
  const data: Record<string, unknown> = {
    name: form.name.trim(),
    prize_type: form.prize_type,
    prize_config: config,
    probability: prob,
    planned_quantity: form.planned_quantity ?? 0,
    status: form.status,
  }
  submitting.value = true
  try {
    if (editingPid.value === null) {
      await createPrize(activityId, data)
      ElMessage.success('奖项已添加')
    } else {
      await updatePrize(activityId, editingPid.value, data)
      ElMessage.success('奖项已更新')
    }
    dialogVisible.value = false
    void fetchList()
  } catch {
    /* 后端 409（非空奖概率总和超 100）等已由拦截器提示 */
  } finally {
    submitting.value = false
  }
}

/* ---------- 删除（116） ---------- */

async function onDelete(row: PrizeRowVM): Promise<void> {
  try {
    await ElMessageBox.confirm(`将删除奖项「${row.name}」，删除后不可恢复。`, '删除确认', {
      type: 'warning',
      confirmButtonText: '删除',
      cancelButtonText: '取消',
    })
  } catch {
    return
  }
  try {
    await deletePrize(activityId, row.id)
    ElMessage.success('已删除')
    void fetchList()
  } catch {
    /* request 拦截器已统一提示 */
  }
}

onMounted(() => {
  void fetchList()
})
</script>

<template>
  <div>
    <el-page-header class="card" content="抽奖奖项池" @back="router.push('/marketing/lucky-draw')" />

    <div class="card">
      <div class="toolbar">
        <el-tag :type="overLimit ? 'danger' : 'success'" size="large" effect="dark">
          非空奖概率总和：{{ totalProb }}%
        </el-tag>
        <span v-if="overLimit" style="color: #f56c6c; font-size: 13px">
          ⚠️ 非空奖概率总和已超过 100%，保存时后端将拦截（409）；空奖（类型 6）为差额兜底，不计入总和
        </span>
        <span v-else style="color: #909399; font-size: 13px">
          奖项概率 ∈ (0, 100]；空奖（类型 6）不计入总和，概率 = 100 - 非空奖总和
        </span>
        <el-button
          v-permission="'marketing:lucky:prize'"
          type="primary"
          style="margin-left: auto"
          @click="openCreate"
        >
          添加奖项
        </el-button>
      </div>

      <DataTable :data="rows" :loading="loading" empty-text="暂无奖项，请先添加">
        <el-table-column prop="id" label="ID" width="70" />
        <el-table-column label="奖项名称" min-width="140" show-overflow-tooltip>
          <template #default="{ row }">{{ asRow(row).name || '-' }}</template>
        </el-table-column>
        <el-table-column label="类型" width="140">
          <template #default="{ row }">
            <el-tag
              size="small"
              :type="asRow(row).prize_type === 6 ? 'info' : 'primary'"
            >
              {{ prizeTypeMeta(asRow(row).prize_type).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="prize_config" min-width="180" show-overflow-tooltip>
          <template #default="{ row }">
            {{ JSON.stringify(asRow(row).prize_config ?? {}) }}
          </template>
        </el-table-column>
        <el-table-column label="概率" width="100">
          <template #default="{ row }">{{ asRow(row).probability }}%</template>
        </el-table-column>
        <el-table-column prop="planned_quantity" label="计划数量" width="100" />
        <el-table-column prop="sent_quantity" label="已发数量" width="100" />
        <el-table-column label="状态" width="90">
          <template #default="{ row }">
            <el-tag size="small" :type="statusMeta(asRow(row).status).type">
              {{ statusMeta(asRow(row).status).label }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button v-permission="'marketing:lucky:prize'" link type="primary" @click="openEdit(asRow(row))">
              编辑
            </el-button>
            <el-button v-permission="'marketing:lucky:prize'" link type="danger" @click="onDelete(asRow(row))">
              删除
            </el-button>
          </template>
        </el-table-column>
      </DataTable>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" :close-on-click-modal="false">
      <el-form label-width="140px" @submit.prevent="onSubmit">
        <el-form-item label="奖项名称" required>
          <el-input v-model="form.name" maxlength="100" placeholder="请输入奖项名称" />
        </el-form-item>
        <el-form-item label="奖项类型" required>
          <el-select v-model="form.prize_type" style="width: 220px">
            <el-option v-for="o in PRIZE_TYPE_OPTIONS" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
          <span v-if="prizeTypeMeta(form.prize_type).empty" style="margin-left: 8px; font-size: 12px; color: #909399">
            空奖不计入概率总和
          </span>
        </el-form-item>
        <el-form-item label="prize_config（JSON）">
          <el-input
            v-model="configText"
            type="textarea"
            :rows="4"
            placeholder='按类型填写发放配置，如积分：{"amount":"10.00"}；藏品：{"collectible_id":1}'
          />
        </el-form-item>
        <el-form-item label="概率（%）" required>
          <el-input-number v-model="form.probability" :min="0" :max="100" :precision="4" :step="1" />
          <span style="margin-left: 8px; font-size: 12px; color: #909399">∈ (0, 100]</span>
        </el-form-item>
        <el-form-item label="计划数量">
          <el-input-number v-model="form.planned_quantity" :min="0" :step="1" step-strictly />
          <span style="margin-left: 8px; font-size: 12px; color: #909399">0 表示不限</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="onSubmit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<style scoped>
.toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
</style>
