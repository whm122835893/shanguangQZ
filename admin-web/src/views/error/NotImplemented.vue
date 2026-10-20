<script setup lang="ts">
import { ref } from 'vue'
import { ElResult } from 'element-plus'

defineProps<{ title?: string }>()
const shown = ref(false)
</script>

<template>
  <el-result icon="info" title="功能建设中" :sub-title="`该页面（${title ?? ''}）尚未实现，请后续补充`">
    <template #extra>
      <el-button type="primary" @click="shown = !shown">知道了</el-button>
    </template>
  </el-result>
</template>
