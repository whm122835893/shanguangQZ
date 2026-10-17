<script setup>
/**
 * 流转记录 —— 1:1 复刻原站 pages/my/flowDoc
 *
 * 原站实测：
 *   .container 390x844 白底
 *   返回 back-btn.png @13,10 24x24
 *   导航标题「流转记录」@165,16 60x16  15px/700 rgb(51,51,51)（居中于 195）
 *
 *   空态图 search-empty.png @134.5,148 121x100
 *     原图612x504（2倍图），显示尺寸 121x100
 *
 * 注：本页标题是「流转记录」，不是「流程文档」（与路由名 flowDoc 字面不同，
 *     按原站实际文案实现）。原站当前无流转数据，仅空态。
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="flowdoc-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">流转记录</div>
    </div>

    <!-- 空态图@134.5,148 121x100 -->
    <img class="empty-img" src="/static/empty/search-empty.png" alt="暂无流转记录" />
  </div>
</template>

<style scoped>
/* .container 390x844 白底 */
.flowdoc-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航标题「流转记录」@165,16 60x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 空态图@134.5,148 121x100 */
.empty-img {
  position: absolute;
  left: 134.5px;
  top: 148px;
  width: 121px;
  height: 100px;
}
</style>