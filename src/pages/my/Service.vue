<script setup>
/**
 * 我的客服 —— 1:1 复刻原站 pages/my/service
 * 由「其他功能」页「我的客服」进入。
 *
 * 原站实测：
 *   .container 390x844 白底
 *   返回 back-btn.png @13,10 24x24
 *   导航标题「客服」@180,16 30x16  15px/700 rgb(51,51,51)（居中于 195）
 *
 *   行1 .pa-16 @0,44 390x54  15px/400 rgb(153,153,153)
 *     文案「客服：待定」 左侧 padding 16
 *     右侧箭头 u-icon @95,61 20x20  20px/400 rgb(223,0,7)
 *   行2 .pa-16 @0,98 390x54（行距 54）
 *     文案「客服邮箱：待定」
 *     右侧箭头 u-icon @125,115 20x20  20px/400 rgb(223,0,7)
 *
 * 注：原站客服信息由接口返回，当前均为「待定」占位，故文案固定。
 *     箭头为 uview 字体图标 uicon-file-text（红色），此处用内联 SVG 语义等价绘制，
 *     遵循 P0 规则：不使用 emoji / 字体图标。
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="service-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">客服</div>
    </div>

    <!-- 行1@0,44 390x54 -->
    <div class="row" style="top: 44px">
      <span class="row-text">客服：待定</span>
      <!-- 箭头@95,61 20x20（内联 SVG，等价原站 uicon-file-text） -->
      <svg class="row-arrow" viewBox="0 0 20 20" width="20" height="20">
        <path d="M7 4 L13 10 L7 16" fill="none" stroke="rgb(223,0,7)" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>

    <!-- 行2@0,98 390x54 -->
    <div class="row" style="top: 98px">
      <span class="row-text">客服邮箱：待定</span>
      <svg class="row-arrow" viewBox="0 0 20 20" width="20" height="20">
        <path d="M7 4 L13 10 L7 16" fill="none" stroke="rgb(223,0,7)" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" />
      </svg>
    </div>
  </div>
</template>

<style scoped>
/* .container 390x844 白底 */
.service-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航标题「客服」@180,16 30x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 行.pa-16 390x54，垂直居中 */
.row {
  position: absolute;
  left: 0;
  width: 390px;
  height: 54px;
  box-sizing: border-box;
  padding: 0 16px;
  display: flex;
  align-items: center;
}
/* 文案 15px/400 rgb(153,153,153) */
.row-text {
  color: #999;
  font-size: 15px;
  font-weight: 400;
  line-height: 20px;
}
/* 箭头 20x20，与文案间距 4px */
.row-arrow {
  margin-left: 4px;
  flex-shrink: 0;
  cursor: pointer;
}
</style>