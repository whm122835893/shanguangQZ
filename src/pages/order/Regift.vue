<script setup>
/**
 * 转赠（regift 发起页）—— 原站 pages/regift/regift
 *
 * 原站实测行为（未携带任何参数时）：
 *   访问 /#/pages/regift/regift 后 URL 被静默替换为 /#/pages/index/index，
 *   直接呈现探索首页（搜索栏 + 运营公告轮播 + 公告条 + 藏品网格 + 底部 tabbar）。
 *   与 pages/product/collection 同属「已废弃 / 不可直达」路由。
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
