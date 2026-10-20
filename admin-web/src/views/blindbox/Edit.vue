<script setup lang="ts">
/** 编辑盲盒（契约 §8.6 edit）：回填 detail，仅草稿/已下架可编辑；edition 不可修改 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import UploadImage from '@/components/UploadImage.vue'
import { blindboxDetail, editBlindbox } from '@/api/blindbox'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const formRef = ref<FormInstance>()
const loading = ref(true)
const submitting = ref(false)
const saleStatus = ref(0)
const edition = ref(0)

const form = reactive<{ name: string; image: string; description: string; openDraw: number }>({
  name: '',
  image: '',
  description: '',
  openDraw: 1,
})

const rules: FormRules = {
  name: [{ required: true, message: '请输入盲盒名称', trigger: 'blur' }],
}

/** 仅草稿(0)/已下架(3)可编辑（后端校验一致） */
const editable = computed(() => saleStatus.value === 0 || saleStatus.value === 3)

onMounted(async () => {
  try {
    const d = await blindboxDetail(id)
    saleStatus.value = Number(d.sale_status ?? 0)
    edition.value = Number(d.edition ?? 0)
    form.name = String(d.name ?? '')
    form.image = String(d.image ?? d.cover ?? '')
    form.description = String(d.description ?? '')
    form.openDraw = Number(d.open_draw ?? 1)
  } finally {
    loading.value = false
  }
})

async function onSubmit(): Promise<void> {
  const valid = await formRef.value?.validate().then(() => true).catch(() => false)
  if (!valid) return
  submitting.value = true
  try {
    await editBlindbox(id, {
      name: form.name,
      image: form.image,
      description: form.description,
      open_draw: form.openDraw,
    })
    ElMessage.success('保存成功')
    void router.push(`/blindbox/${id}`)
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div v-loading="loading" class="card">
    <h3 class="page-title">编辑盲盒</h3>
    <el-alert
      v-if="!editable"
      title="仅草稿/已下架状态的盲盒可编辑"
      type="warning"
      :closable="false"
      show-icon
      style="margin-bottom: 16px"
    />
    <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" style="max-width: 560px" :disabled="!editable">
      <el-form-item label="发行总量">
        <span>{{ edition }}（不可修改）</span>
      </el-form-item>
      <el-form-item label="盲盒名称" prop="name">
        <el-input v-model="form.name" maxlength="128" show-word-limit />
      </el-form-item>
      <el-form-item label="封面图片" prop="image">
        <UploadImage v-model="form.image" />
      </el-form-item>
      <el-form-item label="描述" prop="description">
        <el-input v-model="form.description" type="textarea" :rows="4" maxlength="1000" show-word-limit />
      </el-form-item>
      <el-form-item label="开盒抽奖">
        <el-switch v-model="form.openDraw" :active-value="1" :inactive-value="0" active-text="开启" inactive-text="关闭" />
      </el-form-item>
      <el-form-item>
        <el-button type="primary" :loading="submitting" :disabled="!editable" @click="onSubmit">保存</el-button>
        <el-button @click="router.back()">取消</el-button>
      </el-form-item>
    </el-form>
  </div>
</template>

<style scoped>
.page-title { margin: 0 0 16px; font-size: 16px; }
</style>
