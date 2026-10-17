<script setup>
/**
 * 发售日历 —— 1:1 复刻原站 pages/index/calendar
 *
 * 路由：#/pages/index/calendar
 *
 * 原站 390x844 实测（container bg rgb(243,242,245)）：
 *   .diy-nav @0,0 390x44（flex 水平居中）
 *     返回 @5,6 27x27
 *     标题「发售日历」居中 16px/700 #000
 *   .content @16,44 358x28（当前无数据，接口返回空）
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="calendar">
    <div class="diy-nav">
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
      <div class="nav-title">发售日历</div>
    </div>

    <div class="content"></div>
  </div>
</template>

<style scoped>
.calendar {
  position: relative;
  width: 390px;
  min-height: 844px;
  box-sizing: border-box;
  background: rgb(243, 242, 245);
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.diy-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  width: 390px;
  height: 44px;
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
  font-size: 16px;
  font-weight: 700;
  line-height: 44px;
  color: #000;
  text-align: center;
}

.content {
  width: 358px;
  height: 28px;
  margin: 0 16px;
}
</style>