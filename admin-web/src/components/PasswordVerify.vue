<script setup lang="ts">
/** 密码二次验证小组件（嵌入表单，用于高风险操作字段） */
const model = defineModel<string>({ default: '' })
defineProps<{ placeholder?: string }>()
</script>

<template>
  <el-input
    v-model="model"
    type="password"
    show-password
    :placeholder="placeholder ?? '请输入管理员密码以确认'"
    autocomplete="new-password"
  />
</template>
