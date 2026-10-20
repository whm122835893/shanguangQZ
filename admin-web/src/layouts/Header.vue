<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { useAppStore } from '@/store/app'
import { useAdminStore } from '@/store/admin'

const appStore = useAppStore()
const adminStore = useAdminStore()
const route = useRoute()
const router = useRouter()

const breadcrumbs = computed(() => {
  const paths: Array<{ path?: string; title: string }> = []
  if (route.meta.activeMenu) {
    // 隐藏详情页：显示上级菜单 + 当前标题
    const parent = router.resolve(String(route.meta.activeMenu))
    paths.push({ path: String(route.meta.activeMenu), title: String(parent.meta.title ?? '') })
    paths.push({ title: String(route.meta.title) })
  } else {
    paths.push({ title: String(route.meta.title ?? '') })
  }
  return paths
})

const fullName = computed(() => `${adminStore.realName || adminStore.username}（${adminStore.roleName}）`)

async function onLogout(): Promise<void> {
  await ElMessageBox.confirm('确定退出登录？', '提示', { type: 'warning' })
  await adminStore.logout()
  ElMessage.success('已退出')
  void router.push('/login')
}

function onFullscreen(): void {
  if (document.fullscreenElement) {
    void document.exitFullscreen()
  } else {
    void document.documentElement.requestFullscreen()
  }
}
</script>

<template>
  <div class="header-bar">
    <div class="left">
      <el-icon class="collapse-btn" @click="appStore.toggleSidebar">
        <Expand v-if="appStore.sidebarCollapsed" />
        <Fold v-else />
      </el-icon>
      <el-breadcrumb separator="/">
        <el-breadcrumb-item v-for="(b, i) in breadcrumbs" :key="i" :to="b.path ? { path: b.path } : undefined">
          {{ b.title }}
        </el-breadcrumb-item>
      </el-breadcrumb>
    </div>
    <div class="right">
      <el-icon class="action" @click="onFullscreen"><FullScreen /></el-icon>
      <el-icon class="action" @click="appStore.toggleTheme"><Sunny /></el-icon>
      <el-dropdown>
        <span class="admin-name">
          <el-avatar :size="28" class="avatar">{{ adminStore.username.slice(0, 1).toUpperCase() }}</el-avatar>
          {{ fullName }}
          <el-icon><ArrowDown /></el-icon>
        </span>
        <template #dropdown>
          <el-dropdown-menu>
            <el-dropdown-item disabled>修改密码（个人中心）</el-dropdown-item>
            <el-dropdown-item divided @click="onLogout">退出登录</el-dropdown-item>
          </el-dropdown-menu>
        </template>
      </el-dropdown>
    </div>
  </div>
</template>

<style scoped lang="scss">
.header-bar {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.left { display: flex; align-items: center; gap: 12px; }
.collapse-btn { font-size: 20px; cursor: pointer; color: #606266; }
.right { display: flex; align-items: center; gap: 18px; }
.action { font-size: 18px; cursor: pointer; color: #606266; }
.admin-name { display: flex; align-items: center; gap: 8px; cursor: pointer; color: #303133; }
.avatar { background: #409eff; }
</style>
