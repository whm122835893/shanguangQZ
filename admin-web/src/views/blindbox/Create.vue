<script setup lang="ts">
/** 创建盲盒（契约 §8.6 create）：name/image/description/edition/price → 草稿态，成功后跳详情 */
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import UploadImage from '@/components/UploadImage.vue'
import { createBlindbox } from '@/api/blindbox'

const router = useRouter()
const formRef = ref<FormInstance>()
const submitting = ref(false)

const form = reactive<{
  name: string
  image: string
  description: string
  edition: number | undefined
  price: string
}>({
  name: '',
  image: '',
  description: '',
  edition: undefined,
  price: '',
})

const rules: FormRules = {
  name: [{ required: true, message: '请输入盲盒名称', trigger: 'blur' }],
  edition: [{ required: true, message: '请输入发行总量', trigger: 'blur' }],
}

async function onSubmit(): Promise<void> {
  const valid = await formRef.value?.validate().then(() => true).catch(() => false)
  if (!valid) return
  const edition = Number(form.edition)
  if (!Number.isInteger(edition) || edition <= 0) {
    ElMessage.error('发行总量必须为正整数')
    return
  }
  if (form.price !== '' && (Number.isNaN(Number(form.price)) || Number(form.price) < 0)) {
    ElMessage.error('价格必须为非负数字')
    return
  }
  submitting.value = true
  try {
    const res = await createBlindbox({
      name: form.name,
      image: form.image,
      description: form.description,
      edition,
      price: form.price,
    })
    ElMessage.success('创建成功，已生成草稿')
    void router.push(`/blindbox/${res.id}`)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="card">
    <h3 class="page-title">创建盲盒</h3>
    <el-alert
      title="创建后为草稿状态，需先配置子藏品与发售参数后方可发售"
      type="info"
      :closable="false"
      show-icon
      style="margin-bottom: 16px"
    />
    <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" style="max-width: 560px">
      <el-form-item label="盲盒名称" prop="name">
        <el-input v-model="form.name" placeholder="请输入盲盒名称" maxlength="128" show-word-limit />
      </el-form-item>
      <el-form-item label="封面图片" prop="image">
        <UploadImage v-model="form.image" />
      </el-form-item>
      <el-form-item label="描述" prop="description">
        <el-input
          v-model="form.description"
          type="textarea"
          :rows="4"
          maxlength="1000"
          show-word-limit
          placeholder="盲盒描述（选填）"
        />
      </el-form-item>
      <el-form-item label="发行总量" prop="edition">
        <el-input-number v-model="form.edition" :min="1" :step="1" :precision="0" placeholder="发行总量" style="width: 200px" />
      </el-form-item>
      <el-form-item label="价格（元）" prop="price">
        <el-input v-model="form.price" placeholder="0.00" style="width: 200px">
          <template #append>元</template>
        </el-input>
      </el-form-item>
      <el-form-item>
        <el-button type="primary" :loading="submitting" @click="onSubmit">创建</el-button>
        <el-button @click="router.back()">取消</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<style scoped>
.page-title { margin: 0 0 16px; font-size: 16px; }
</style>
