<script setup>
/**
 * 公告详情 —— 1:1 复刻原站 pages/notice/newsDetails
 *
 * 路由：#/pages/notice/newsDetails?id=xxx
 *
 * 原站 390x844 实测（uni-page-body bg #fff）：
 *   页面无任何可见节点：uni-page-body @0,0 390x844 bg #fff，内部仅有全局
 *   actionsheet / kefu 等浮层（尺寸为 0），整页为纯白。
 *
 * 说明：该页接口返回 data:null，原站自身渲染为空白页，
 *      此处按可见结果 1:1 还原（空页面容器）。
 */
</script>

<template>
  <div class="news-details"></div>
</template>

<style scoped>
.news-details {
  position: relative;
  width: 390px;
  min-height: 844px;
  box-sizing: border-box;
  background: #fff;
  color: rgb(74, 74, 74);
  font-size: 15px;
}
</style>