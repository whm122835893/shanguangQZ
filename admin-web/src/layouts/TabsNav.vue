<script setup lang="ts">
import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAppStore } from '@/store/app'

const appStore = useAppStore()
const route = useRoute()
const router = useRouter()

// 路由变化 → 添加标签页（首页 affix 不可关闭）
watch(
  () => route.fullPath,
  () => {
    if (route.path === '/login') return
    const affix = route.path === '/dashboard'
    appStore.addTab({ path: route.path, title: String(route.meta.title ?? '未命名'), affix })
    if (affix) appStore.initAffixTabs({ path: '/dashboard', title: '数据大盘', affix: true })
  },
  { immediate: true },
)

function onClick(path: string): void {
  void router.push(path)
}

function onClose(path: string): void {
  const next = appStore.removeTab(path)
  if (next) void router.push(next)
}
</script>

<template>
  <div class="tabs-nav">
    <el-tag
      v-for="tab in appStore.tabs"
      :key="tab.path"
      :effect="appStore.activeTab === tab.path ? 'dark' : 'plain'"
      class="tab-item"
      :closable="!tab.affix"
      @click="onClick(tab.path)"
      @close="onClose(tab.path)"
    >
      {{ tab.title }}
    </el-tag>
  </div>
</template>

<style scoped lang="scss">
.tabs-nav {
  padding: 6px 12px;
  display: flex;
  gap: 8px;
  overflow-x: auto;
}
.tab-item { cursor: pointer; user-select: none; }
</style>
