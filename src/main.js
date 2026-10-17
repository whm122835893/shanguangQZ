import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import './styles/tokens.css'
import 'vant/lib/index.css'
// tokens 需在 Vant 之后引入，确保字体等基础样式不被 Vant 覆盖
import './styles/overrides.css'

createApp(App).use(createPinia()).use(router).mount('#app')