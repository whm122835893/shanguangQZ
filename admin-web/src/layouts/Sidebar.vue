<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useAppStore } from '@/store/app'
import { usePermissionStore } from '@/store/permission'

const appStore = useAppStore()
const permissionStore = usePermissionStore()
const route = useRoute()

const menus = permissionStore.menus
</script>

<template>
  <div class="logo">
    <span v-if="!appStore.sidebarCollapsed">司南后台</span>
    <span v-else>南</span>
  </div>
  <el-scrollbar>
    <el-menu
      :default-active="route.meta.activeMenu as string || route.path"
      :collapse="appStore.sidebarCollapsed"
      :collapse-transition="false"
      router
      background-color="#ffffff"
      text-color="#303133"
      active-text-color="#409eff"
    >
      <template v-for="menu in menus" :key="menu.path">
        <!-- 分组菜单 -->
        <el-sub-menu v-if="menu.children && menu.children.length" :index="menu.path">
          <template #title>
            <el-icon><component :is="menu.children[0].icon" /></el-icon>
            <span>{{ menu.title }}</span>
          </template>
          <el-menu-item v-for="child in menu.children" :key="child.path" :index="child.path">
            {{ child.title }}
          </el-menu-item>
        </el-sub-menu>
        <!-- 一级菜单 -->
        <el-menu-item v-else :index="menu.path">
          <el-icon v-if="menu.icon"><component :is="menu.icon" /></el-icon>
          <template #title>{{ menu.title }}</template>
        </el-menu-item>
      </template>
    </el-menu>
  </el-scrollbar>
</template>

<style scoped lang="scss">
.logo {
  height: 56px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 700;
  color: #409eff;
  border-bottom: 1px solid #e6e8eb;
}
.el-menu { border-right: none; }
</style>
