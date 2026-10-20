<script setup lang="ts">
/**
 * 高风险操作统一弹窗（§十 高风险操作统一规范）
 * 组合：二次确认 + 原因（可选）+ 管理员密码（必填）
 * confirm 后 emit('confirm', { reason, password })
 */
import { ref, computed } from 'vue'
import { ElMessage } from 'element-plus'

const props = defineProps<{
  modelValue: boolean
  title: string
  /** 弹窗内的警告文案（描述后果） */
  message: string
  /** 是否需要填写原因（默认 true） */
  needReason?: boolean
  /** 是否需要密码（默认 true） */
  needPassword?: boolean
  /** 需要手动输入的确认文本（如平台清库输入「确认清库」） */
  confirmText?: string
  /** 提交中 */
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'confirm', payload: { reason: string; password: string }): void
}>()

const reason = ref('')
const password = ref('')
const confirmInput = ref('')

const visible = computed({
  get: () => props.modelValue,
  set: (v: boolean) => emit('update:modelValue', v),
})

function onConfirm(): void {
  if (props.confirmText && confirmInput.value !== props.confirmText) {
    ElMessage.error(`请输入「${props.confirmText}」以确认`)
    return
  }
  if (props.needReason !== false && reason.value.trim() === '') {
    ElMessage.error('请填写操作原因')
    return
  }
  if (props.needPassword !== false && password.value === '') {
    ElMessage.error('请输入管理员密码')
    return
  }
  emit('confirm', { reason: reason.value.trim(), password: password.value })
}

function onClosed(): void {
  reason.value = ''
  password.value = ''
  confirmInput.value = ''
}
</script>

<template>
  <el-dialog v-model="visible" :title="title" width="440px" @closed="onClosed" :close-on-click-modal="false">
    <el-alert :title="message" type="warning" :closable="false" show-icon style="margin-bottom: 16px" />
    <el-form label-width="90px" @submit.prevent="onConfirm">
      <el-form-item v-if="confirmText" label="确认文本">
        <el-input v-model="confirmInput" :placeholder="`请输入「${confirmText}」`" />
      </el-form-item>
      <el-form-item v-if="needReason !== false" label="操作原因">
        <el-input v-model="reason" type="textarea" :rows="2" placeholder="将写入审计日志" maxlength="200" />
      </el-form-item>
      <el-form-item v-if="needPassword !== false" label="管理员密码">
        <el-input v-model="password" type="password" show-password placeholder="请输入登录密码" autocomplete="new-password" />
      </el-form-item>
    </el-form>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="danger" :loading="loading" @click="onConfirm">确认执行</el-button>
    </template>
  </el-dialog>
</template>
