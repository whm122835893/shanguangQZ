<script setup lang="ts">
/** 表格 + 横向滚动 + 操作列固定（UI/UX §11.5）+ 空状态（§11.12） */
import EmptyState from './EmptyState.vue'

defineProps<{
  data: Record<string, unknown>[]
  loading?: boolean
  emptyText?: string
}>()
</script>

<template>
  <div v-loading="loading" class="table-wrap">
    <el-table :data="data" stripe>
      <slot />
      <template #empty>
        <EmptyState :text="emptyText ?? '暂无数据'" />
      </template>
    </el-table>
  </div>
</template>
