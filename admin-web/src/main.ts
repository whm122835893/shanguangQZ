import { createApp } from 'vue'
import { createPinia } from 'pinia'
import ElementPlus from 'element-plus'
import zhCn from 'element-plus/es/locale/lang/zh-cn'
import 'element-plus/dist/index.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'
import App from './App.vue'
import router from './router'
import '@/assets/styles/index.scss'

const app = createApp(App)

// 全量注册图标（菜单 icon 动态引用需要）
for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component)
}

app.use(createPinia())
app.use(router)
app.use(ElementPlus, { locale: zhCn })

/** v-permission 按钮级权限指令（契约 §13.3） */
import { useAdminStore } from '@/store/admin'
app.directive('permission', {
  mounted(el: HTMLElement, binding) {
    const permission = binding.value as string | undefined
    if (!permission) return
    const store = useAdminStore()
    if (!store.hasPerm(permission)) {
      el.parentNode?.removeChild(el)
    }
  },
})

app.mount('#app')
