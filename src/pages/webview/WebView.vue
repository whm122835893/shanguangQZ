<script setup>
/**
 * 通用 webview —— 1:1 复刻原站 pages/webview/webview
 *
 * 路由：#/pages/webview/webview?url=xxx
 *
 * 原站 390x844 实测（整页 bg #fff）：
 *   uni-page-head @0,0 390x44 bg #fff
 *     返回 i @5,6 27x27；标题为空（@70,7 250x0）
 *   uni-web-view（fullscreen）@0,44 390x800
 *     无 url 参数时原站渲染为空白 webview
 *
 * 说明：原站通过 query 参数 url 加载外部页面；无参数时为空白。
 *       本实现保留 iframe 容器，有 url 时正常加载外部页面。
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()
const url = ref('')

onMounted(() => {
  url.value = route.query.url || ''
})
</script>

<template>
  <div class="webview-page">
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <svg viewBox="0 0 24 24" width="27" height="27" aria-hidden="true">
          <path
            d="M15 4 L7 12 L15 20"
            fill="none"
            stroke="rgb(0, 0, 0)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
    </div>

    <iframe v-if="url" class="web-view" :src="url" frameborder="0"></iframe>
    <div v-else class="web-view"></div>
  </div>
</template>

<style scoped>
.webview-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  padding-top: 44px;
  box-sizing: border-box;
  background: #fff;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.navbar {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 44px;
  background: #fff;
}
.nav-back {
  position: absolute;
  left: 5px;
  top: 6px;
  width: 27px;
  height: 27px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.web-view {
  display: block;
  width: 390px;
  height: 800px;
  border: none;
  background: #fff;
}
</style>
