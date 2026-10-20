<script setup lang="ts">
/** 手续费配置（契约 §8.9.84/85）：读取展示 + 高风险修改（market:config，密码 + 审计） */
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getFeeConfig, updateFeeConfig } from '@/api/market'
import ConfirmDialog from '@/components/ConfirmDialog.vue'

/** el-input-number 的 update:modelValue 载荷为 number | undefined，模型需对齐 */
const form = reactive<{ fee_rate: number | undefined; fee_min: number | undefined; fee_max: number | undefined }>({
  fee_rate: 5,
  fee_min: 1,
  fee_max: 500,
})
const loading = ref(false)
const dialogVisible = ref(false)
const submitting = ref(false)

async function load(): Promise<void> {
  loading.value = true
  try {
    const data = await getFeeConfig()
    form.fee_rate = Number(data.fee_rate) || 0
    form.fee_min = Number(data.fee_min) || 0
    form.fee_max = Number(data.fee_max) || 0
  } finally {
    loading.value = false
  }
}

function openSave(): void {
  const rate = form.fee_rate ?? 0
  const min = form.fee_min ?? 0
  const max = form.fee_max ?? 0
  if (rate <= 0 || rate > 100) {
    ElMessage.error('手续费率需在 (0, 100] 区间')
    return
  }
  if (min < 0 || max < min) {
    ElMessage.error('最低/最高手续费不合法（需 0 ≤ 最低 ≤ 最高）')
    return
  }
  dialogVisible.value = true
}

async function onConfirmSave(payload: { reason: string; password: string }): Promise<void> {
  submitting.value = true
  try {
    await updateFeeConfig({
      fee_rate: String(form.fee_rate ?? 0),
      fee_min: (form.fee_min ?? 0).toFixed(2),
      fee_max: (form.fee_max ?? 0).toFixed(2),
      password: payload.password,
    })
    ElMessage.success('手续费配置已更新')
    dialogVisible.value = false
    await load()
  } catch {
    /* 失败已由请求拦截器提示，弹窗保持打开便于修正 */
  } finally {
    submitting.value = false
  }
}

onMounted(() => {
  void load()
})
</script>

<template>
  <div>
    <div v-loading="loading" class="card" style="max-width: 560px">
      <h3 style="margin-top: 0">市场手续费配置</h3>
      <el-form label-width="140px" @submit.prevent="openSave">
        <el-form-item label="手续费率（%）">
          <el-input-number v-model="form.fee_rate" :min="0.01" :max="100" :precision="2" :step="0.5" />
        </el-form-item>
        <el-form-item label="最低手续费（元）">
          <el-input-number v-model="form.fee_min" :min="0" :precision="2" :step="1" />
        </el-form-item>
        <el-form-item label="最高手续费（元）">
          <el-input-number v-model="form.fee_max" :min="0" :precision="2" :step="1" />
        </el-form-item>
        <el-form-item>
          <el-button v-permission="'market:config'" type="primary" @click="openSave">保存配置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <ConfirmDialog
      v-model="dialogVisible"
      title="修改手续费配置"
      message="修改将影响全部市场交易的结算费用，请确认数值无误；操作需管理员密码验证并写入审计日志。"
      :need-reason="false"
      :loading="submitting"
      @confirm="onConfirmSave"
    />
  </div>
</template>
