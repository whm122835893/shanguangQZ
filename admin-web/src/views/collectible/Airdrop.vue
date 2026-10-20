<script setup lang="ts">
/** 独立空投（契约 §8.5）：手机号清单空投，高风险操作需密码确认，展示成功/失败数量 */
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { airdropCollectible } from '@/api/collectible'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const MOBILE_RE = /^1[3-9]\d{9}$/

const formRef = ref<FormInstance>()
const form = reactive<{ quantity: number | undefined }>({ quantity: 1 })
const phonesText = ref('')
const confirmVisible = ref(false)
const confirmLoading = ref(false)
const result = ref<{ success_count: number; fail_count: number } | null>(null)

/** 文本域按行分割为手机号数组 */
const phoneLines = computed(() =>
  phonesText.value
    .split('\n')
    .map((s) => s.trim())
    .filter((s) => s !== ''),
)

const invalidLines = computed(() => phoneLines.value.filter((s) => !MOBILE_RE.test(s)))

function validateQuantity(_rule: unknown, value: number | undefined, callback: (err?: Error) => void): void {
  if (value === undefined || !Number.isInteger(value) || value <= 0) {
    callback(new Error('每人空投数量必须为正整数'))
  } else {
    callback()
  }
}

const rules: FormRules = {
  quantity: [{ validator: validateQuantity, trigger: 'change' }],
}

async function onSubmit(): Promise<void> {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  if (phoneLines.value.length === 0) {
    ElMessage.error('请输入至少一个手机号（每行一个）')
    return
  }
  if (invalidLines.value.length > 0) {
    ElMessage.error(`存在 ${invalidLines.value.length} 个无效手机号，请检查格式（每行一个 11 位手机号）`)
    return
  }
  confirmVisible.value = true
}

async function onConfirm(payload: { reason: string; password: string }): Promise<void> {
  const quantity = form.quantity
  if (quantity === undefined || quantity <= 0) {
    ElMessage.error('每人空投数量必须为正整数')
    return
  }
  confirmLoading.value = true
  try {
    const r = await airdropCollectible(id, quantity, phoneLines.value, payload.password)
    result.value = r
    confirmVisible.value = false
    phonesText.value = ''
    ElMessage.success('空投任务执行完成')
  } finally {
    confirmLoading.value = false
  }
}
</script>

<template>
  <div>
    <el-page-header class="card" content="独立空投" @back="router.push('/collectible')" />

    <div class="danger-bar">
      ⚠️ 空投为高风险操作：将直接向用户发放藏品，不可撤销，需管理员密码确认并写入审计日志。
    </div>

    <div class="card" style="max-width: 640px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" @submit.prevent="onSubmit">
        <el-form-item label="每人数量">
          <el-input-number v-model="form.quantity" :min="1" :step="1" step-strictly />
        </el-form-item>
        <el-form-item label="手机号清单">
          <el-input
            v-model="phonesText"
            type="textarea"
            :rows="8"
            placeholder="每行一个手机号，如：&#10;13800138000&#10;13900139000"
          />
          <div style="width: 100%; margin-top: 4px; font-size: 12px; color: #909399">
            已识别 {{ phoneLines.length }} 个手机号<span v-if="invalidLines.length > 0" style="color: #f56c6c">
              （{{ invalidLines.length }} 个格式无效）</span>
          </div>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" native-type="submit">执行空投</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div v-if="result" class="card" style="max-width: 640px">
      <h3 style="margin-top: 0">空投结果</h3>
      <div class="result-bar">
        <div class="result-item success">
          <div class="result-label">成功</div>
          <div class="result-value">{{ result.success_count }}</div>
        </div>
        <div class="result-item fail">
          <div class="result-label">失败</div>
          <div class="result-value">{{ result.fail_count }}</div>
        </div>
      </div>
    </div>

    <ConfirmDialog
      v-model="confirmVisible"
      title="空投确认"
      :message="`即将向 ${phoneLines.length} 位用户每人空投 ${form.quantity ?? 0} 个藏品，空投直接发放至用户账户且不可撤销。该操作将写入审计日志。`"
      :need-reason="false"
      :loading="confirmLoading"
      @confirm="onConfirm"
    />
  </div>
</template>

<style scoped>
.result-bar { display: flex; gap: 12px; }
.result-item { flex: 1; border-radius: 6px; padding: 12px 16px; text-align: center; }
.result-item.success { background: #f0f9eb; }
.result-item.fail { background: #fef0f0; }
.result-label { font-size: 13px; color: #909399; }
.result-value { font-size: 22px; font-weight: 700; margin-top: 4px; }
.result-item.success .result-value { color: #67c23a; }
.result-item.fail .result-value { color: #f56c6c; }
</style>
