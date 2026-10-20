<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAppStore } from '@/store/app'
import Sidebar from './Sidebar.vue'
import HeaderBar from './Header.vue'
import TabsNav from './TabsNav.vue'

const appStore = useAppStore()
const route = useRoute()

const siderWidth = computed(() => (appStore.sidebarCollapsed ? 64 : 220))
const showTabs = computed(() => route.path !== '/login')
</script>

<template>
  <el-container class="main-layout">
    <el-aside :width="siderWidth + 'px'" class="sidebar">
      <Sidebar />
    </el-aside>
    <el-container>
      <el-header class="header" height="56px">
        <HeaderBar />
      </el-header>
      <div v-if="showTabs" class="tabs-nav">
        <TabsNav />
      </div>
      <el-main class="content">
        <router-view v-slot="{ Component }">
          <component :is="Component" :key="route.fullPath" />
        </router-view>
      </el-main>
    </el-container>
  </el-container>
</template>

<style scoped lang="scss">
.main-layout { height: 100vh; }
.sidebar {
  background: #fff;
  border-right: 1px solid #e6e8eb;
  overflow-x: hidden;
  transition: width 0.2s;
}
.header {
  background: #fff;
  border-bottom: 1px solid #e6e8eb;
  padding: 0 16px;
}
.tabs-nav { background: #fff; border-bottom: 1px solid #f0f2f5; }
.content { padding: 16px; overflow-y: auto; background: #f5f7fa; }
</style>
