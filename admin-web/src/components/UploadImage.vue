<script setup lang="ts">
/** OSS 直传占位上传组件（§11.7：上传支持预览删除） */
import { ref } from 'vue'
import { ElMessage } from 'element-plus'

const props = defineProps<{ modelValue?: string; action?: string }>()
const emit = defineEmits<{ (e: 'update:modelValue', url: string): void }>()

const previewUrl = ref(props.modelValue ?? '')
const uploading = ref(false)

async function onChange(uploadFile: { raw?: File }): Promise<void> {
  const raw = uploadFile.raw
  if (!raw) return
  if (!raw.type.startsWith('image/')) {
    ElMessage.error('仅支持图片文件')
    return
  }
  if (raw.size > 5 * 1024 * 1024) {
    ElMessage.error('图片不能超过 5MB')
    return
  }
  uploading.value = true
  try {
    // 部署时 action 指向 OSS 直传地址；本地开发回退 multipart 管理端点
    if (props.action) {
      const form = new FormData()
      form.append('file', raw)
      const resp = await fetch(props.action, { method: 'POST', body: form })
      const body = (await resp.json()) as { data?: { url?: string } }
      previewUrl.value = body.data?.url ?? ''
    } else {
      // 无 OSS 配置时本地预览
      previewUrl.value = URL.createObjectURL(raw)
    }
    emit('update:modelValue', previewUrl.value)
  } finally {
    uploading.value = false
  }
}

function onRemove(): void {
  previewUrl.value = ''
  emit('update:modelValue', '')
}
</script>

<template>
  <div>
    <el-upload
      :show-file-list="false"
      :auto-upload="false"
      accept="image/*"
      @change="onChange"
    >
      <img v-if="previewUrl" :src="previewUrl" class="preview" alt="预览" />
      <el-button v-else :loading="uploading">上传图片</el-button>
    </el-upload>
    <el-button v-if="previewUrl" link type="danger" @click="onRemove">删除</el-button>
  </div>
</template>

<style scoped>
.preview { width: 120px; height: 120px; object-fit: cover; border-radius: 6px; display: block; }
</style>
