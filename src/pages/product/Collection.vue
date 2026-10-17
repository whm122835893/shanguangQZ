<script setup>
/**
 * 藏品详情（market 域）—— 原站 pages/product/collection
 *
 * 原站实测行为：
 *   该路由已废弃 / 不可直达 —— 无论携带何种 id（id=4 / 8 / 142 均实测），
 *   原站均不做任何渲染，直接呈现探索首页（container.page-container.home-bg，
 *   含搜索栏、运营公告轮播、公告条、藏品网格、底部 tabbar）。
 *   即：这是原站的「静默重定向到首页」行为。
 *
 * 复刻策略：
 *   立即 router.replace('/pages/index/index')，与原站可见结果完全一致。
 *   保留本页独立文件与路由，便于后续若原站恢复该路由时按真实内容重建。
 */
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

onMounted(() => {
  router.replace('/pages/index/index')
})
</script>

<template>
  <div class="redirect-page"></div>
</template>

<style scoped>
.redirect-page {
  width: 390px;
  max-width: 100%;
  min-height: 100vh;
  background: #f6f5f5;
}
</style>
