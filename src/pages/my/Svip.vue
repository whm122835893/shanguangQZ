<script setup>
/**
 * 会员中心 —— 1:1 复刻原站 pages/my/svip
 *
 * 原站实测（页面总高 1002，深色主题）：
 *   .container 390x1002  bg rgb(13,14,18) + bg.png背景图
 *   导航栏 .u-navbar--fixed @0,0 390x44  bg #fff 配bg.png
 *     返回 back-btn-white.png @13,10 24x24   ← 白色返回箭头，专用素材
 *     标题「会员中心」@165,16 60x16  15px/700 rgb(255,255,255)
 *
 *   jwsz-image.png @155,81 80x40        （原图154x77）
 *   tqryz-image.png @123.5,155 143x26 （原图552x100）
 *   视频容器 .tqryz-video-v @18.5,165 353x192  bg #000  border 1px rgb(51,25x)
 *     播放按钮 bofang.png @175.5,241.5 39x39 （原图76x76）
 *
 *   .time-v @18.5,370 353x140  bg time-bg.png（原图1360x574）
 *     time-title.png @85,388 220x46      （原图848x180）
 *     时分秒格子 .time-bg @87,460 27x35  bg rgb(19,22,29)  15px/700 #fff
 *       ① @87,460  ② @150,460  ③ @213,460  ④ @276,460
 *     单位 .time-title 12px/700 rgb(204,204,204)
 *       「天」@126,468.5 12x18
 *       「时」@189,468.5 12x18
 *       「分」@252,468.5 12x18
 *     注：末尾秒位@276后无单位标签（与实测一致）
 *
 *   bdtq-image.png @96.5,531 197x16    （原图658x68）
 *   tq-image.png @18.5,564 353x332     （原图1360x1220）
 *   ljkt-image.png @21,918 348x46      （原图1380x204）
 *
 * 倒计时为「剩余有效期」，原站当前全 00，按原站显示即可。
 */
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'

const router = useRouter()

/* 原站实测值：非会员剩余天/时/分/秒均为 00 */
const days = ref('00')
const hours = ref('00')
const minutes = ref('00')
const seconds = ref('00')
let timer = null

onMounted(() => {
  timer = setInterval(() => {
    let s = Number(seconds.value)
    let m = Number(minutes.value)
    let h = Number(hours.value)
    s += 1
    if (s >= 60) { s = 0; m += 1 }
    if (m >= 60) { m = 0; h += 1 }
    if (h >= 24) { h = 0 }
    seconds.value = String(s).padStart(2, '0')
    minutes.value = String(m).padStart(2, '0')
    hours.value = String(h).padStart(2, '0')
  }, 1000)
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

function onJoin() {
  showToast({ icon: 'none', message: '原站未开通会员入口' })
}
</script>

<template>
  <div class="svip-page">
    <!-- 导航：白色返回箭头 back-btn-white.png @13,10 24x24 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn-white.png" alt="返回" @click="router.back()" />
      <div class="nav-title">会员中心</div>
    </div>

    <!-- jwsz @155,81 80x40 -->
    <img class="jwsz" src="/static/image/my/svip/jwsz-image.png" alt="会员" />

    <!-- tqryz @123.5,155 143x26 -->
    <img class="tqryz" src="/static/image/my/svip/tqryz-image.png" alt="特权认证" />

    <!-- 视频容器@18.5,165 353x192 bg #000，播放按钮居中 @175.5,241.5 39x39 -->
    <div class="video-box">
      <img class="play-btn" src="/static/image/my/svip/bofang.png" alt="播放" @click="onJoin" />
    </div>

    <!-- 倒计时区@18.5,370 353x140 -->
    <div class="time-v">
      <img class="time-title-img" src="/static/image/my/svip/time-title.png" alt="" />
      <div class="time-cell" style="left: 87px">{{ days }}</div>
      <div class="time-unit" style="left: 126px">天</div>
      <div class="time-cell" style="left: 150px">{{ hours }}</div>
      <div class="time-unit" style="left: 189px">时</div>
      <div class="time-cell" style="left: 213px">{{ minutes }}</div>
      <div class="time-unit" style="left: 252px">分</div>
      <div class="time-cell" style="left: 276px">{{ seconds }}</div>
    </div>

    <!-- bdtq @96.5,531 197x16 -->
    <img class="bdtq" src="/static/image/my/svip/bdtq-image.png" alt="不到到期" />

    <!-- tq@18.5,564 353x332 -->
    <img class="tq" src="/static/image/my/svip/tq-image.png" alt="特权" />

    <!-- ljkt @21,918 348x46 -->
    <img class="ljkt" src="/static/image/my/svip/ljkt-image.png" alt="立即开通" @click="onJoin" />
  </div>
</template>

<style scoped>
/* .container 390x1002 深色底+背景图 */
.svip-page {
  position: relative;
  width: 390px;
  height: 1002px;
  background: rgb(13, 14, 18) url('/static/image/my/svip/bg.png') no-repeat center top;
  background-size: 100% auto;
  overflow: hidden;
}

/* 导航@0,0 390x44 */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  background: rgb(255, 255, 255) url('/static/image/my/svip/bg.png') no-repeat center top;
  background-size: 100% auto;
  z-index: 2;
}
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 3; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #fff; font-size: 15px; font-weight: 700;
}

/* jwsz @155,81 80x40 */
.jwsz { position: absolute; left: 155px; top: 81px; width: 80px; height: 40px; }

/* tqryz @123.5,155 143x26 */
.tqryz { position: absolute; left: 123.5px; top: 155px; width: 143px; height: 26px; z-index: 1; }

/* 视频容器@18.5,165 353x192 bg #000 */
.video-box {
  position: absolute;
  left: 18.5px;
  top: 165px;
  width: 353px;
  height: 192px;
  box-sizing: border-box;
  background: #000;
  border: 1px solid rgb(51, 51, 58);
  display: flex;
  align-items: center;
  justify-content: center;
}
/* 播放按钮 @175.5,241.5 39x39 */
.play-btn { width: 39px; height: 39px; cursor: pointer; }

/* 倒计时区@18.5,370 353x140 */
.time-v {
  position: absolute;
  left: 18.5px;
  top: 370px;
  width: 353px;
  height: 140px;
  background: url('/static/image/my/svip/time-bg.png') no-repeat center top;
  background-size: 100% auto;   /* 原图 1360x574 等比 → 353x149，容器 140 裁切底部 */
}
/* time-title 图@85,388 220x46 */
.time-title-img { position: absolute; left: 85px; top: 18px; width: 220px; height: 46px; }
/* 数字格 27x35 bg rgb(19,22,29) 15px/700 #fff */
.time-cell {
  position: absolute;
  top: 90px;             /* 370+90=460 */
  width: 27px;
  height: 35px;
  line-height: 35px;
  text-align: center;
  background: rgb(19, 22, 29);
  color: #fff;
  font-size: 15px;
  font-weight: 700;
}
/* 单位 12px/700 rgb(204,204,204) */
.time-unit {
  position: absolute;
  top: 98.5px;           /* 370+98.5=468.5 */
  width: 12px;
  height: 18px;
  line-height: 18px;
  color: rgb(204, 204, 204);
  font-size: 12px;
  font-weight: 700;
}

/* bdtq @96.5,531 197x16 */
.bdtq { position: absolute; left: 96.5px; top: 531px; width: 197px; height: 16px; }

/* tq @18.5,564 353x332 */
.tq { position: absolute; left: 18.5px; top: 564px; width: 353px; height: 332px; }

/* ljkt @21,918 348x46 */
.ljkt { position: absolute; left: 21px; top: 918px; width: 348px; height: 46px; cursor: pointer; }
</style>