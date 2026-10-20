import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface TabItem {
  path: string
  title: string
  affix?: boolean
}

/** 主题 / 侧边栏折叠 / 标签页（UI/UX §11.16） */
export const useAppStore = defineStore('app', () => {
  const sidebarCollapsed = ref(false)
  const isDark = ref(false)
  const tabs = ref<TabItem[]>([])
  const activeTab = ref('')

  function toggleSidebar(): void {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function toggleTheme(): void {
    isDark.value = !isDark.value
    document.documentElement.classList.toggle('dark', isDark.value)
  }

  function addTab(tab: TabItem): void {
    if (tabs.value.some((t) => t.path === tab.path)) {
      activeTab.value = tab.path
      return
    }
    tabs.value.push(tab)
    activeTab.value = tab.path
  }

  function removeTab(path: string): string | null {
    const idx = tabs.value.findIndex((t) => t.path === path)
    if (idx === -1 || tabs.value[idx]?.affix) return null
    tabs.value.splice(idx, 1)
    // 关闭当前激活页时跳到相邻标签
    if (activeTab.value === path) {
      const next = tabs.value[idx] ?? tabs.value[idx - 1]
      activeTab.value = next?.path ?? '/'
      return activeTab.value
    }
    return null
  }

  function initAffixTabs(affix: TabItem): void {
    if (!tabs.value.some((t) => t.path === affix.path)) tabs.value.unshift(affix)
  }

  return { sidebarCollapsed, isDark, tabs, activeTab, toggleSidebar, toggleTheme, addTab, removeTab, initAffixTabs }
})
