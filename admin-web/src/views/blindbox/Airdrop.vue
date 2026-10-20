<script setup lang="ts">
/** 盲盒独立空投（契约 §8.6 airdrop）：quantity + phones（按行分割），结果展示 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { blindboxDetail, airdropBlindbox } from '@/api/blindbox'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const num = (v: unknown): number => (v === null || v === undefined || v === '' ? 0 : Number(v))

const loading = ref(true)
const boxName = ref('')
const stockPool = ref(0)

const form = reactive<{ quantity: number | undefined; phonesText: string }>({
  quantity: undefined,
  phonesText: '',
})

/** 手机号按行分割，去空行、去重 */
const phones = computed<string[]>(() => {
  const seen = new Set<string>()
  for (const line of form.phonesText.split(/\r?\n/)) {
    const p = line.trim()
    if (p !== '') seen.add(p)
  }
  return [...seen]
})

/** 空投总量 = 数量 × 人数 */
const totalQuantity = computed(() => num(form.quantity) * phones.value.length)

const overPool = computed(() => stockPool.value > 0 && totalQuantity.value > stockPool.value)

async function fetchDetail(): Promise<void> {
  loading.value = true
  try {
    const d = await blindboxDetail(id)
    boxName.value = String(d.name ?? '')
    stockPool.value = num(d.stock_pool)
  } finally {
    loading.value = false
  }
}

/* ---------- 提交（高风险：ConfirmDialog + 密码） ---------- */
const confirmVisible = ref(false)
const submitting = ref(false)
const result = ref<{ task_id: number; success_count: number; fail_count: number } | null>(null)

function onSubmit(): void {
  const qty = num(form.quantity)
  if (!Number.isInteger(qty) || qty <= 0) {
    ElMessage.error('每人空投数量必须为正整数')
    return
  }
  if (phones.value.length === 0) {
    ElMessage.error('请填写至少一个手机号')
    return
  }
  if (phones.value.length > 1000) {
    ElMessage.error('单次空投人数不得超过 1000')
    return
  }
  if (overPool.value) {
    ElMessage.error(`空投总量（${totalQuantity.value}）超过盲盒库存池（${stockPool.value}）`)
    return
  }
  confirmVisible.value = true
}

async function doAirdrop(_payload: { reason: string; password: string }): Promise<void> {
  submitting.value = true
  try {
    result.value = await airdropBlindbox(id, num(form.quantity), phones.value, _payload.password)
    ElMessage.success('空投任务执行完成')
    confirmVisible.value = false
    void fetchDetail()
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  void fetchDetail()
})
</script>

<template>
  <div v-loading="loading">
    <div class="card">
      <div class="bar">
        <h3 class="page-title">盲盒空投</h3>
        <el-tag>{{ boxName }}</el-tag>
        <el-tag type="warning" effect="plain">盲盒库存池：{{ stockPool }}</el-tag>
      </div>
      <div class="danger-bar">⚠️ 空投为高风险操作：盲盒将直接发放至用户账户并写入审计日志</div>

      <el-form label-width="110px" style="max-width: 640px">
        <el-form-item label="每人数量" required>
          <el-input-number v-model="form.quantity" :min="1" :step="1" :precision="0" style="width: 200px" />
        </el-form-item>
        <el-form-item label="手机号列表" required>
          <el-input
            v-model="form.phonesText"
            type="textarea"
            :rows="8"
            placeholder="每行一个手机号，自动去重"
          />
          <div class="form-tip">
            已识别 <b>{{ phones.length }}</b> 个手机号，空投总量 {{ num(form.quantity) }} × {{ phones.length }} =
            <b :style="overPool ? 'color:#f56c6c' : ''">{{ totalQuantity }}</b>
            <template v-if="overPool">（超过库存池 {{ stockPool }}，无法提交）</template>
          </div>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :disabled="overPool" @click="onSubmit">提交空投</el-button>
          <el-button @click="router.push(`/blindbox/${id}`)">返回详情</el-button>
        </el-form-item>
      </el-form>
    </div>

    <!-- 结果展示 -->
    <div v-if="result" class="card">
      <h3 class="page-title">空投结果</h3>
      <el-alert
        :title="`任务 #${result.task_id} 执行完成：成功 ${result.success_count} 人，失败 ${result.fail_count} 人`"
        :type="result.fail_count > 0 ? 'warning' : 'success'"
        :closable="false"
        show-icon
        style="margin-bottom: 12px"
      />
      <el-descriptions :column="3" border>
        <el-descriptions-item label="任务 ID">{{ result.task_id }}</el-descriptions-item>
        <el-descriptions-item label="成功人数">{{ result.success_count }}</el-descriptions-item>
        <el-descriptions-item label="失败人数">{{ result.fail_count }}</el-descriptions-item>
      </el-descriptions>
    </div>

    <!-- 高风险确认 -->
    <ConfirmDialog
      v-model="confirmVisible"
      title="确认空投盲盒"
      :message="`确认向 ${phones.length} 位用户每人空投 ${num(form.quantity)} 个盲盒（共 ${totalQuantity} 个）？操作将写入审计日志`"
      :need-reason="false"
      :loading="submitting"
      @confirm="doAirdrop"
    />
  </div>
</template>

<style scoped>
.page-title { margin: 0; font-size: 16px; }
.bar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.form-tip { font-size: 12px; color: #909399; line-height: 1.6; }
</style>
