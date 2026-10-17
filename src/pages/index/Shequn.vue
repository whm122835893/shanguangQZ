<script setup>
/**
 * 加入社群（社群列表页）—— 1:1 复刻原站 pages/index/shequn
 *
 * 路由：#/pages/index/shequn
 *
 * 原站 390x844 实测（整页 bg #fff）：
 *   uni-page-head @0,0 390x44 bg #fff
 *     返回 i @5,6 27x27；标题「加入社群」@70,7 250x30 16px/700 #000
 *   uni-placeholder @0,0 390x44（占位，撑开 body）
 *   列表区：原站社群数据为空，body 内容高度 0，页面下半部为纯白
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="shequn">
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <svg viewBox="0 0 24 24" width="27" height="27" aria-hidden="true">
          <path
            d="M15 4 L7 12 L15 20"
            fill="none"
            stroke="rgb(0, 0, 0)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
      <div class="nav-title">加入社群</div>
    </div>
  </div>
</template>

<style scoped>
.shequn {
  position: relative;
  width: 390px;
  min-height: 844px;
  padding-top: 44px;
  box-sizing: border-box;
  background: #fff;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.navbar {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 44px;
  background: #fff;
}
.nav-back {
  position: absolute;
  left: 5px;
  top: 6px;
  width: 27px;
  height: 27px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.nav-title {
  position: absolute;
  left: 70px;
  top: 7px;
  width: 250px;
  height: 30px;
  font-size: 16px;
  font-weight: 700;
  line-height: 30px;
  color: #000;
  text-align: center;
}
</style>