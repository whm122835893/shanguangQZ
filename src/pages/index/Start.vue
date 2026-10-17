<script setup>
/**
 * 启动页 —— 复刻原站 pages/index/start
 *
 * 路由：#/pages/index/start
 *
 * 原站实测行为：进入该路由后会自动跳转到首页（等效 /pages/index/index）。
 * 该页仅在冷启动第一帧短暂显示 start.jpg（闪光 logo + 「路浪而归 予光成冠」），
 * 等待期间不响应交互，随后replace 到首页。
 */
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

onMounted(() => {
  // 原站行为：加载完配置后 replace 到首页
  router.replace('/pages/index/index')
})
</script>

<template>
  <div class="start-page">
    <div class="bottom-strip"></div>
  </div>
</template>

<style scoped>
.start-page {
  width: 390px;
  min-height: 851px;
  background: #fff url('/static/bg/start.jpg') 0 0 / 100% 100% no-repeat;
}
/* 底部浅灰带 @0,844 390x7 */
.bottom-strip {
  position: absolute;
  left: 0;
  top: 844px;
  width: 390px;
  height: 7px;
  background: linear-gradient(to bottom, rgb(226, 225, 225), rgb(242, 241, 241));
}
</style>