<script setup lang="ts">
/** 子藏品配置（契约 §8.6 items/itemUpdate/itemDelete）
 *  UI/UX §11.20：概率分布可视化（el-progress + 概率总和徽章，总和>100 全部标红警示）
 *  后端约束：单次批量添加 ≥2 条；概率总和（含已有）≤ 100%，差额即空奖概率
 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { blindboxDetail, configItems, updateItem, deleteItem } from '@/api/blindbox'
import { listCollectibles } from '@/api/collectible'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const num = (v: unknown): number => (v === null || v === undefined || v === '' ? 0 : Number(v))

interface ItemRow {
  id: number
  collectibleId: number
  collectibleName: string
  probability: string
  plannedQuantity: number
}

const loading = ref(true)
const items = ref<ItemRow[]>([])
const probSum = ref(0)
const boxName = ref('')

/** §11.20：概率总和 > 100% 时全部标红 */
const over = computed(() => probSum.value > 100)
const emptyRate = computed(() => Math.max(0, Math.round((100 - probSum.value) * 100) / 100))

/** el-progress 百分比钳制到 0~100（数值文本仍显示真实概率） */
function pct(v: string): number {
  const n = Number(v)
  if (!Number.isFinite(n)) return 0
  return Math.min(100, Math.max(0, n))
}

async function fetchDetail(): Promise<void> {
  loading.value = true
  try {
    const d = await blindboxDetail(id)
    boxName.value = String(d.name ?? '')
    probSum.value = num(d.probability_sum)
    const rawItems = Array.isArray(d.items) ? (d.items as Record<string, unknown>[]) : []
    items.value = rawItems.map((it) => ({
      id: num(it.id),
      collectibleId: num(it.collectible_id),
      collectibleName: String(it.collectible_name ?? ''),
      probability: String(it.probability ?? '0'),
      plannedQuantity: num(it.planned_quantity),
    }))
  } finally {
    loading.value = false
  }
}

/* ---------- 子藏品选项 ---------- */
interface Option { label: string; value: number }
const collectibleOptions = ref<Option[]>([])

async function fetchCollectibles(): Promise<void> {
  const data = await listCollectibles({ page: 1, page_size: 200 })
  collectibleOptions.value = data.list.map((r) => ({ label: String(r.name ?? ''), value: num(r.id) }))
}

/* ---------- 添加子藏品（批量 ≥2 条） ---------- */
interface AddRow { collectibleId: number | undefined; probability: string; plannedQuantity: number | undefined }
function emptyRow(): AddRow {
  return { collectibleId: undefined, probability: '', plannedQuantity: undefined }
}
const rows = ref<AddRow[]>([emptyRow(), emptyRow()])
const adding = ref(false)

const newProbSum = computed(() => rows.value.reduce((acc, r) => acc + (Number(r.probability) || 0), 0))
/** 已有概率 + 本次新增概率 */
const totalProb = computed(() => Math.round((probSum.value + newProbSum.value) * 100) / 100)

async function onAdd(): Promise<void> {
  if (rows.value.length < 2) {
    ElMessage.error('盲盒至少关联 2 个子藏品，请至少填写 2 行')
    return
  }
  const cids = new Set<number>()
  for (const r of rows.value) {
    if (!r.collectibleId) {
      ElMessage.error('请为每一行选择子藏品')
      return
    }
    if (cids.has(r.collectibleId) || items.value.some((it) => it.collectibleId === r.collectibleId)) {
      ElMessage.error('子藏品重复，请检查')
      return
    }
    cids.add(r.collectibleId)
    const p = Number(r.probability)
    if (!Number.isFinite(p) || p <= 0 || p > 100) {
      ElMessage.error('每行概率必须大于 0 且不超过 100')
      return
    }
    if (!r.plannedQuantity || r.plannedQuantity < 1) {
      ElMessage.error('计划数量必须大于 0')
      return
    }
  }
  if (totalProb.value > 100) {
    ElMessage.error(`概率总和不得超过 100%（当前合计 ${totalProb.value}%）`)
    return
  }
  adding.value = true
  try {
    await configItems(
      id,
      rows.value.map((r) => ({
        collectible_id: r.collectibleId,
        probability: Number(r.probability),
        planned_quantity: r.plannedQuantity,
      })),
    )
    ElMessage.success('子藏品配置成功')
    rows.value = [emptyRow(), emptyRow()]
    void fetchDetail()
  } finally {
    adding.value = false
  }
}

/* ---------- 编辑子藏品（概率/计划数量，ConfirmDialog + 密码） ---------- */
const editVisible = ref(false)
const editConfirmVisible = ref(false)
const editLoading = ref(false)
const editTarget = ref<ItemRow | null>(null)
const editForm = reactive<{ probability: string; plannedQuantity: number | undefined }>({
  probability: '',
  plannedQuantity: undefined,
})

/** 其余子藏品已占概率 */
const otherProb = computed(() => {
  if (!editTarget.value) return 0
  return Math.round((probSum.value - Number(editTarget.value.probability)) * 100) / 100
})

function openEdit(it: ItemRow): void {
  editTarget.value = it
  editForm.probability = it.probability
  editForm.plannedQuantity = it.plannedQuantity
  editVisible.value = true
}

/** 编辑弹窗「保存」→ 高风险确认（密码）→ 调接口 */
function submitEdit(): void {
  if (!editTarget.value) return
  const p = Number(editForm.probability)
  if (!Number.isFinite(p) || p <= 0 || p > 100) {
    ElMessage.error('中奖概率必须大于 0 且不超过 100')
    return
  }
  if (!editForm.plannedQuantity || editForm.plannedQuantity < 1) {
    ElMessage.error('计划数量必须大于 0')
    return
  }
  if (Math.round((otherProb.value + p) * 100) / 100 > 100) {
    ElMessage.error(`概率总和不得超过 100%（其余子藏品已占 ${otherProb.value}%）`)
    return
  }
  editConfirmVisible.value = true
}

async function doEdit(_payload: { reason: string; password: string }): Promise<void> {
  if (!editTarget.value) return
  editLoading.value = true
  try {
    await updateItem(id, editTarget.value.id, {
      probability: Number(editForm.probability),
      planned_quantity: editForm.plannedQuantity,
    })
    ElMessage.success('修改成功')
    editConfirmVisible.value = false
    editVisible.value = false
    void fetchDetail()
  } finally {
    editLoading.value = false
  }
}

/* ---------- 删除子藏品（ConfirmDialog + 密码） ---------- */
const deleteConfirmVisible = ref(false)
const deleteLoading = ref(false)
const deleteTarget = ref<ItemRow | null>(null)

function openDelete(it: ItemRow): void {
  deleteTarget.value = it
  deleteConfirmVisible.value = true
}

async function doDelete(_payload: { reason: string; password: string }): Promise<void> {
  if (!deleteTarget.value) return
  deleteLoading.value = true
  try {
    await deleteItem(id, deleteTarget.value.id)
    ElMessage.success('删除成功')
    deleteConfirmVisible.value = false
    void fetchDetail()
  } finally {
    deleteLoading.value = false
  }
}

onMounted(async () => {
  try {
    await Promise.all([fetchDetail(), fetchCollectibles()])
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-loading="loading">
    <!-- 概率分布可视化（UI/UX §11.20） -->
    <div class="card">
      <div class="bar">
        <h3 class="page-title">概率分布可视化</h3>
        <el-tag :type="over ? 'danger' : 'success'" effect="dark" size="large">概率总和：{{ probSum }}%</el-tag>
        <el-tag v-if="!over && probSum < 100" type="info">空奖率：{{ emptyRate }}%</el-tag>
        <el-tag v-if="over" type="danger" effect="plain">概率总和超过 100%，无法保存新配置</el-tag>
      </div>
      <div v-for="it in items" :key="it.id" class="prob-row">
        <span class="prob-name" :title="it.collectibleName">
          {{ it.collectibleName || `子藏品#${it.collectibleId}` }}
        </span>
        <el-progress
          class="prob-bar"
          :percentage="pct(it.probability)"
          :color="over ? '#f56c6c' : '#409eff'"
          :stroke-width="14"
        />
        <span class="prob-text" :style="over ? 'color:#f56c6c;font-weight:600' : ''">{{ it.probability }}%</span>
        <span class="prob-qty">计划 ×{{ it.plannedQuantity }}</span>
      </div>
      <el-empty v-if="items.length === 0" description="尚未配置子藏品" :image-size="64" />
    </div>

    <!-- 添加子藏品 -->
    <div class="card">
      <h3 class="page-title">添加子藏品</h3>
      <el-alert
        title="盲盒至少关联 2 个子藏品，单次批量提交；概率总和（含已有）不得超过 100%，差额即空奖概率"
        type="info"
        :closable="false"
        show-icon
        style="margin-bottom: 12px"
      />
      <div v-for="(r, i) in rows" :key="i" class="add-row">
        <span class="add-index">{{ i + 1 }}</span>
        <el-select v-model="r.collectibleId" placeholder="选择子藏品" filterable style="width: 260px">
          <el-option v-for="o in collectibleOptions" :key="o.value" :label="o.label" :value="o.value" />
        </el-select>
        <el-input v-model="r.probability" placeholder="中奖概率" style="width: 150px">
          <template #append>%</template>
        </el-input>
        <el-input-number v-model="r.plannedQuantity" :min="1" :step="1" :precision="0" placeholder="计划数量" style="width: 150px" />
        <el-button link type="danger" :disabled="rows.length <= 2" @click="rows.splice(i, 1)">移除</el-button>
      </div>
      <div class="add-actions">
        <el-button link type="primary" @click="rows.push(emptyRow())">+ 再添加一行</el-button>
        <el-tag :type="totalProb > 100 ? 'danger' : 'success'">
          本次新增概率：{{ newProbSum }}%，合计：{{ totalProb }}%
        </el-tag>
      </div>
      <el-button type="primary" :loading="adding" @click="onAdd">批量添加</el-button>
    </div>

    <!-- 已配置列表 -->
    <div class="card">
      <h3 class="page-title">已配置子藏品</h3>
      <el-table :data="items" stripe>
        <el-table-column label="ID" prop="id" width="80" />
        <el-table-column label="子藏品" min-width="180">
          <template #default="{ row }">{{ row.collectibleName || `#${row.collectibleId}` }}</template>
        </el-table-column>
        <el-table-column label="概率" width="120">
          <template #default="{ row }">
            <span :style="over ? 'color:#f56c6c;font-weight:600' : ''">{{ row.probability }}%</span>
          </template>
        </el-table-column>
        <el-table-column label="计划数量" width="120" prop="plannedQuantity" />
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <el-button v-permission="'blindbox:config'" link type="primary" @click="openEdit(row as ItemRow)">编辑</el-button>
            <el-button v-permission="'blindbox:config'" link type="danger" @click="openDelete(row as ItemRow)">删除</el-button>
          </template>
        </el-table-column>
        <template #empty>
          <el-empty description="暂无子藏品" :image-size="64" />
        </template>
      </el-table>
      <div style="margin-top: 12px">
        <el-button @click="router.push(`/blindbox/${id}`)">返回详情</el-button>
      </div>
    </div>

    <!-- 编辑弹窗 -->
    <el-dialog v-model="editVisible" title="编辑子藏品" width="420px" :close-on-click-modal="false">
      <el-form label-width="100px">
        <el-form-item label="子藏品">
          <span>{{ editTarget?.collectibleName || `#${editTarget?.collectibleId ?? '-'}` }}</span>
        </el-form-item>
        <el-form-item label="中奖概率">
          <el-input v-model="editForm.probability" style="width: 180px">
            <template #append>%</template>
          </el-input>
          <div class="form-tip">其余子藏品已占 {{ otherProb }}%</div>
        </el-form-item>
        <el-form-item label="计划数量">
          <el-input-number v-model="editForm.plannedQuantity" :min="1" :step="1" :precision="0" style="width: 180px" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editVisible = false">取消</el-button>
        <el-button type="primary" @click="submitEdit">下一步确认</el-button>
      </template>
    </el-dialog>

    <!-- 高风险确认：修改概率 -->
    <ConfirmDialog
      v-model="editConfirmVisible"
      title="确认修改子藏品"
      message="修改概率/计划数量将直接影响用户开盒结果，请确认无误"
      :need-reason="false"
      :loading="editLoading"
      @confirm="doEdit"
    />

    <!-- 高风险确认：删除子藏品 -->
    <ConfirmDialog
      v-model="deleteConfirmVisible"
      title="删除子藏品"
      :message="`将从「${boxName}」移除子藏品「${deleteTarget?.collectibleName ?? ''}」，删除后不可恢复`"
      :need-reason="false"
      :loading="deleteLoading"
      @confirm="doDelete"
    />
  </div>
</template>

<style scoped>
.page-title { margin: 0; font-size: 16px; }
.bar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.prob-row { display: flex; align-items: center; gap: 12px; padding: 8px 0; }
.prob-name { width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex-shrink: 0; }
.prob-bar { flex: 1; }
.prob-text { width: 80px; text-align: right; flex-shrink: 0; }
.prob-qty { width: 100px; color: #909399; font-size: 13px; flex-shrink: 0; }
.add-row { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
.add-index { width: 20px; color: #909399; }
.add-actions { display: flex; align-items: center; gap: 12px; margin: 4px 0 16px 32px; }
.form-tip { font-size: 12px; color: #909399; line-height: 1.5; margin-top: 4px; }
</style>
