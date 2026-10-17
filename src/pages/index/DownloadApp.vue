<script setup>
/**
 * APP下载 —— 1:1 复刻原站 pages/index/downloadApp
 *
 * 路由：#/pages/index/downloadApp
 *
 * 原站 390x2086 实测：
 *   .app-view @0,0 390x2086 bg url(/static/bg/app-bg.png) 0 0 / 390px auto no-repeat
 *     原图 704x3456，按 390 宽等比缩放 → 渲染高度 1914.55px；下方 2086-1915=171px 为纯色延展
 *   u-navbar @0,0 390x44（fixed）
 *     返回 i @13,10 24x24（白色 back-btn-white.png）
 *     标题「APP下载」@91,11 208x22  15px/700 #fff
 *   两个 .btn @78,275 234x52（mt 231px）与 @78,363 234x52 —— 空容器，
 *     视觉内容（logo、iOS下载、安卓下载、推荐提示）全部绘制在 app-bg.png 背景图内
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="download-app">
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
          <path
            d="M15 4 L7 12 L15 20"
            fill="none"
            stroke="#fff"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
      <div class="nav-title">APP下载</div>
    </div>

    <div class="btn-slot btn-slot--ios"></div>
    <div class="btn-slot btn-slot--android"></div>
  </div>
</template>

<style scoped>
.download-app {
  position: relative;
  width: 390px;
  height: 2086px;
  box-sizing: border-box;
  background: rgb(255, 255, 255) url('/static/bg/app-bg.png') 0 0 / 390px auto no-repeat;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.navbar {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 10;
  width: 390px;
  height: 44px;
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.nav-title {
  position: absolute;
  left: 91px;
  top: 11px;
  width: 208px;
  height: 22px;
  font-size: 15px;
  font-weight: 700;
  line-height: 22px;
  color: #fff;
  text-align: center;
}

/* 原站两个空 .btn，视觉全在背景图内 —— 仅保留几何占位 */
.btn-slot {
  position: absolute;
  left: 78px;
  width: 234px;
  height: 52px;
  cursor: pointer;
}
.btn-slot--ios {
  top: 275px;
}
.btn-slot--android {
  top: 363px;
}
</style>