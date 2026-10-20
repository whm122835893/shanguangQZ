<script setup lang="ts">
/** 发售配置（契约 §8.5）：价格 / 发售数量 / 开售下售时间 / 每人限购；高风险操作需密码确认 */
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { collectibleDetail, releaseCollectible } from '@/api/collectible'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { amount } from '@/utils/validate'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const formRef = ref<FormInstance>()
const confirmVisible = ref(false)
const confirmLoading = ref(false)

const form = reactive<{
  price: string
  release_quantity: number | undefined
  onsale_at: string | null
  off_sale_at: string | null
  per_user_limit: number | undefined
}>({
  price: '',
  release_quantity: 1,
  onsale_at: '',
  off_sale_at: '',
  per_user_limit: 0,
})

function validatePositiveInt(msg: string) {
  return (_rule: unknown, value: number | undefined, callback: (err?: Error) => void): void => {
    if (value === undefined || !Number.isInteger(value) || value <= 0) {
      callback(new Error(msg))
    } else {
      callback()
    }
  }
}

function validateNonNegativeInt(_rule: unknown, value: number | undefined, callback: (err?: Error) => void): void {
  if (value === undefined || !Number.isInteger(value) || value < 0) {
    callback(new Error('必须为不小于 0 的整数（0 表示不限购）'))
  } else {
    callback()
  }
}

const rules: FormRules = {
  price: amount('请输入发售价格'),
  release_quantity: [{ validator: validatePositiveInt('发售数量必须为正整数'), trigger: 'change' }],
  per_user_limit: [{ validator: validateNonNegativeInt, trigger: 'change' }],
}

onMounted(() => {
  void collectibleDetail(id)
    .then((d) => {
      const price = d['price']
      if (typeof price === 'string' && price !== '') form.price = price
      const limit = Number(d['per_user_limit'])
      if (Number.isFinite(limit) && limit > 0) form.per_user_limit = limit
    })
    .catch(() => undefined)
})

async function onSubmit(): Promise<void> {
  if (!formRef.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  confirmVisible.value = true
}

async function onConfirm(payload: { reason: string; password: string }): Promise<void> {
  confirmLoading.value = true
  try {
    await releaseCollectible(id, {
      price: form.price,
      release_quantity: form.release_quantity,
      onsale_at: form.onsale_at || undefined,
      off_sale_at: form.off_sale_at || undefined,
      per_user_limit: form.per_user_limit,
      reason: payload.reason,
      password: payload.password,
    })
    ElMessage.success('发售配置已提交')
    confirmVisible.value = false
    void router.push('/collectible')
  } finally {
    confirmLoading.value = false
  }
}
</script>

<template>
  <div>
    <el-page-header class="card" content="发售配置" @back="router.push('/collectible')" />

    <div class="danger-bar">
      ⚠️ 发售为高风险操作：配置将决定藏品的发售数量、价格与售卖窗口，提交需管理员密码确认并写入审计日志。
    </div>

    <div class="card" style="max-width: 640px">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" @submit.prevent="onSubmit">
        <el-form-item label="发售价格(元)" prop="price">
          <el-input v-model="form.price" placeholder="请输入发售价格，如 199.00" style="max-width: 240px" />
        </el-form-item>
        <el-form-item label="发售数量" prop="release_quantity">
          <el-input-number v-model="form.release_quantity" :min="1" :step="1" step-strictly />
          <span style="margin-left: 8px; color: #909399; font-size: 12px">本次进入发售的库存数量</span>
        </el-form-item>
        <el-form-item label="开售时间" prop="onsale_at">
          <el-date-picker
            v-model="form.onsale_at"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="留空表示立即开售"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="下售时间" prop="off_sale_at">
          <el-date-picker
            v-model="form.off_sale_at"
            type="datetime"
            value-format="YYYY-MM-DD HH:mm:ss"
            placeholder="留空表示不自动下售"
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="每人限购" prop="per_user_limit">
          <el-input-number v-model="form.per_user_limit" :min="0" :step="1" step-strictly />
          <span style="margin-left: 8px; color: #909399; font-size: 12px">0 表示不限购</span>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" native-type="submit">提交发售配置</el-button>
          <el-button @click="router.push('/collectible')">取消</el-button>
        </el-form-item>
      </el-form>
    </div>

    <ConfirmDialog
      v-model="confirmVisible"
      title="发售配置确认"
      message="发售配置提交后藏品将进入发售流程，直接影响销售与库存分配，请确认参数无误。该操作将写入审计日志。"
      :loading="confirmLoading"
      @confirm="onConfirm"
    />
  </div>
</template>
