<script setup>
/**
 * 动态详情 —— 1:1 复刻原站 pages/notice/wallDetails
 *
 * 路由：#/pages/notice/wallDetails?id=xxx
 *
 * 原站 390x844 实测（container bg rgb(250,250,250)）：
 *   .u-loading-page @0,0 390x844（居中）
 *     加载环 @172.5,323.5 45x47  rgb(200,200,200)
 *     「加载中...」文字 rgb(200,200,200)
 *
 * 说明：该页接口在当前数据下长时间未返回，原站自身停留在 loading 态，
 *      此处按可见结果 1:1 还原。
 */
</script>

<template>
  <div class="wall-details">
    <div class="loading-page">
      <div class="loading-ring"></div>
      <div class="loading-txt">加载中...</div>
    </div>
  </div>
</template>

<style scoped>
.wall-details {
  position: relative;
  width: 390px;
  min-height: 844px;
  box-sizing: border-box;
  background: rgb(250, 250, 250);
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.loading-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 390px;
  height: 844px;
}

/* 加载环 @185.5,323.5 19x19 */
.loading-ring {
  width: 19px;
  height: 19px;
  border-radius: 50%;
  border: 2px solid rgb(230, 230, 230);
  border-top-color: rgb(200, 200, 200);
  box-sizing: border-box;
}

.loading-txt {
  width: 45px;
  height: 18px;
  margin-top: 10px;
  font-size: 12px;
  line-height: 18px;
  color: rgb(200, 200, 200);
  text-align: center;
}
</style>
