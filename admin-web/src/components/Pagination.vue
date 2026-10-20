<script setup lang="ts">
/** 统一分页组件（契约 §12：page / page_size） */
import { computed } from 'vue'

const props = defineProps<{ page: number; pageSize: number; total: number; loading?: boolean }>()
const emit = defineEmits<{ (e: 'change', page: number, pageSize: number): void }>()

const innerPage = computed({ get: () => props.page, set: () => undefined })

function onSizeChange(size: number): void {
  emit('change', 1, size)
}
function onCurrentChange(page: number): void {
  emit('change', page, props.pageSize)
}
</script>

<template>
  <div class="pagination-bar">
    <el-pagination
      v-model:current-page="innerPage"
      :page-size="pageSize"
      :total="total"
      :page-sizes="[10, 20, 50, 100]"
      :background="true"
      layout="total, sizes, prev, pager, next, jumper"
      :disabled="loading"
      @size-change="onSizeChange"
      @current-change="onCurrentChange"
    />
  </div>
</template>

<style scoped>
.pagination-bar { display: flex; justify-content: flex-end; padding: 12px 0 0; }
</style>
