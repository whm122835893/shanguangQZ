<script setup lang="ts">
/** 编辑藏品（契约 §8.5）：加载详情回填，仅草稿（sale_status=0）可编辑 */
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import type { FormInstance, FormRules } from 'element-plus'
import { collectibleDetail, editCollectible } from '@/api/collectible'
import UploadImage from '@/components/UploadImage.vue'
import { required } from '@/utils/validate'

const route = useRoute()
const router = useRouter()
const id = Number(route.params.id)

const loading = ref(true)
const submitting = ref(false)
const formRef = ref<FormInstance>()
const saleStatus = ref(-1)
const canEdit = computed(() => saleStatus.value === 0)

const STATUS_LABELS: Record<number, string> = {
  0: '草稿',
  1: '待售',
  2: '在售',
  3: '售罄',
  4: '下架',
}

const form = reactive<{
  name: string
  edition: number | undefined
  main_image: string
  story: string
  royalty_rate: number | undefined
}>({
  name: '',
  edition: 1,
  main_image: '',
  story: '',
  royalty_rate: 0,
})

function validateEdition(_rule: unknown, value: number | undefined, callback: (err?: Error) => void): void {
  if (value === undefined || !Number.isInteger(value) || value <= 0) {
    callback(new Error('发行总量必须为正整数'))
  } else {
    callback()
  }
}

function validateRoyalty(_rule: unknown, value: number | undefined, callback: (err?: Error) => void): void {
  if (value === undefined) return callback()
  if (value < 0 || value > 100) return callback(new Error('版税率范围 0 - 100'))
  callback()
}

const rules: FormRules = {
  name: [required('请输入藏品名称')],
  edition: [
    { required: true, message: '请输入发行总量', trigger: 'change' },
    { validator: validateEdition, trigger: 'change' },
  ],
  royalty_rate: [{ validator: validateRoyalty, trigger: 'change' }],
}

onMounted(async () => {
  try {
    const d = await collectibleDetail(id)
    saleStatus.value = Number(d['sale_status'] ?? -1)
    form.name = typeof d['name'] === 'string' ? d['name'] : ''
    form.edition = Number(d['edition'] ?? 1) || 1
    const img = d['main_image'] ?? d['cover']
    form.main_image = typeof img === 'string' ? img : ''
    form.story = typeof d['story'] === 'string' ? d['story'] : ''
    form.royalty_rate = Number(d['royalty_rate'] ?? 0) || 0
  } finally {
    loading.value = false
  }
})

async function onSubmit(): Promise<void> {
  if (!formRef.value || !canEdit.value) return
  try {
    await formRef.value.validate()
  } catch {
    return
  }
  submitting.value = true
  try {
    await editCollectible(id, { ...form })
    ElMessage.success('保存成功')
    void router.push('/collectible')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div v-loading="loading">
    <el-page-header class="card" content="编辑藏品" @back="router.push('/collectible')" />

    <el-alert
      v-if="!canEdit && !loading"
      class="card"
      type="warning"
      show-icon
      :closable="false"
      :title="`当前状态「${STATUS_LABELS[saleStatus] ?? '未知'}」，仅草稿状态藏品可编辑`"
    />

    <div class="card" style="max-width: 640px">
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="100px"
        :disabled="!canEdit"
        @submit.prevent="onSubmit"
      >
        <el-form-item label="藏品名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入藏品名称" maxlength="50" show-word-limit />
        </el-form-item>
        <el-form-item label="发行总量" prop="edition">
          <el-input-number v-model="form.edition" :min="1" :step="1" step-strictly />
        </el-form-item>
        <el-form-item label="主图" prop="main_image">
          <UploadImage v-model="form.main_image" />
        </el-form-item>
        <el-form-item label="藏品故事" prop="story">
          <el-input
            v-model="form.story"
            type="textarea"
            :rows="4"
            maxlength="2000"
            show-word-limit
            placeholder="请输入藏品介绍 / 故事"
          />
        </el-form-item>
        <el-form-item label="版税率(%)" prop="royalty_rate">
          <el-input-number v-model="form.royalty_rate" :min="0" :max="100" :precision="1" />
          <span style="margin-left: 8px; color: #909399; font-size: 12px">寄售成交版税，如 5 表示 5%</span>
        </el-form-item>
        <el-form-item v-if="canEdit">
          <el-button type="primary" :loading="submitting" native-type="submit">保存修改</el-button>
          <el-button @click="router.push('/collectible')">取消</el-button>
        </el-form-item>
      </el-form>
    </div>
  </div>
</template>
