<script setup lang="ts">
import { reactive } from 'vue'

export interface SearchField {
  key: string
  label: string
  type?: 'input' | 'select' | 'date' | 'daterange'
  options?: Array<{ label: string; value: string | number }>
  placeholder?: string
}

/** 统一搜索表单：搜索 + 重置（UI/UX §11.18） */
const props = defineProps<{ fields: SearchField[]; modelValue: Record<string, unknown> }>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: Record<string, unknown>): void
  (e: 'search'): void
  (e: 'reset'): void
}>()

const local = reactive<Record<string, string | number | null>>({ ...(props.modelValue as Record<string, string | number | null>) })

function doSearch(): void {
  emit('update:modelValue', { ...local } as Record<string, unknown>)
  emit('search')
}

function doReset(): void {
  for (const k of Object.keys(local)) delete local[k]
  emit('update:modelValue', { ...local } as Record<string, unknown>)
  emit('reset')
}
</script>

<template>
  <el-form inline class="search-form" @submit.prevent="doSearch">
    <el-form-item v-for="f in fields" :key="f.key" :label="f.label">
      <el-select
        v-if="f.type === 'select'"
        v-model="local[f.key]"
        :placeholder="f.placeholder ?? '全部'"
        clearable
        style="width: 160px"
      >
        <el-option v-for="o in f.options" :key="String(o.value)" :label="o.label" :value="o.value" />
      </el-select>
      <el-date-picker
        v-else-if="f.type === 'daterange'"
        v-model="local[f.key]"
        type="daterange"
        value-format="YYYY-MM-DD"
        start-placeholder="开始日期"
        end-placeholder="结束日期"
        style="width: 240px"
      />
      <el-input
        v-else
        v-model="local[f.key]"
        :placeholder="f.placeholder ?? `请输入${f.label}`"
        clearable
        style="width: 200px"
        @keyup.enter="doSearch"
      />
    </el-form-item>
    <el-form-item>
      <el-button type="primary" @click="doSearch">搜索</el-button>
      <el-button @click="doReset">重置</el-button>
    </el-form-item>
  </el-form>
</template>

<style scoped>
.search-form { margin-bottom: 4px; }
</style>
