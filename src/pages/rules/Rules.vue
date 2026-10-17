<script setup>
/**
 * 活动规则 —— 1:1 复刻原站 pages/rules/rules
 *
 * 原站实测：
 *   .container 390x844 白底
 *   返回 back-btn.png @13,10 24x24
 *   标题「活动规则」@165,16 60x16  15px/700 rgb(51,51,51) ta:center
 *   导航以下无任何内容元素（规则正文由接口/富文本注入，当前原站为空）
 *
 * 注：本页仅导航，正文区域原站当前为空，不臆造内容。
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="rules-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">活动规则</div>
    </div>
  </div>
</template>

<style scoped>
/* .container 390x844 白底 */
.rules-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航标题「活动规则」@165,16 60x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}
</style>