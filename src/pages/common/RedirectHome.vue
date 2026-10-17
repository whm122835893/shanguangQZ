<script setup>
/**
 * 原站静默重定向页 —— 已实测确认以下路由在原站均router.replace('/pages/index/index')：
 *   /pages/compound/compound
 *   /pages/compound/batchcompound
 *   /pages/shop/detail/box
 *   /pages/shop/detail/collection
 *   /pages/shop/detail/goods
 *   /pages/product/box
 *
 * 原站表现：无任何过渡渲染，直接落到首页。
 */
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

onMounted(() => {
  router.replace('/pages/index/index')
})
</script>

<template>
  <div class="redirect-stub"></div>
</template>

<style scoped>
.redirect-stub {
  width: 100%;
  min-height: 100vh;
  background: #fff;
}
</style>