<script setup>
/**
 * 参与人数（抽奖助力排行）—— 1:1 复刻原站 pages/draw/Leaderboard/Leaderboard
 *
 * 路由：#/pages/draw/Leaderboard/Leaderboard
 *
 * 原站 390x860 实测（uni-page-head 44px + 16px 间距 → body 从 y=60 起）：
 *   uni-page-head @0,0 390x44 bg #fff
 *     返回 i @5,6 27x27；标题「参与人数」@70,7 250x30 16px/700 #000
 *   uni-page-body @0,60 390x800 bg rgb(245,245,245)
 *     深色卡 @0,60 390x57 br20 bg rgb(48,41,32)
 *       「参与人数0人」@150.5,83 … 14px rgb(102,102,102) 居中
 *     .u-gap @0,117 390x122（无数据，高度 0 内容）
 *     .common_box @0,752 390x92 bg #fff
 *       按钮 @7.8,767 374.4x47 br8 bg #000 文字 #fff「邀请好友助力」
 *       .zh @7.8,814 374.4x15（空）
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="leaderboard">
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <svg viewBox="0 0 24 24" width="27" height="27" aria-hidden="true">
          <path
            d="M15 4 L7 12 L15 20"
            fill="none"
            stroke="rgb(51, 51, 51)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
      <div class="nav-title">参与人数</div>
    </div>

    <div class="body">
      <div class="dark-card">
        <span class="dark-label">参与人数</span>
        <span class="dark-num">0</span>
        <span class="dark-unit">人</span>
      </div>

      <div class="gap"></div>

      <div class="bottom-box">
        <div class="cta">邀请好友助力</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.leaderboard {
  position: relative;
  width: 390px;
  height: 860px;
  background: #fff;
  color: rgb(74, 74, 74);
  font-size: 15px;
  overflow: hidden;
}

.navbar {
  position: fixed;
  left: 0;
  top: 0;
  z-index: 10;
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
  color: #000;
  line-height: 30px;
  text-align: center;
}

/* body @0,60 390x800 */
.body {
  position: absolute;
  left: 0;
  top: 60px;
  width: 390px;
  height: 800px;
  background: rgb(245, 245, 245);
}

/* 深色卡 @0,60 390x57 br20 */
.dark-card {
  position: absolute;
  left: 0;
  top: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 390px;
  height: 57px;
  border-radius: 20px;
  background: rgb(48, 41, 32);
  font-size: 14px;
  font-weight: 400;
  color: rgb(102, 102, 102);
}
.dark-num {
  margin: 0 6px;
  font-weight: 700;
}

/* 列表区（无数据） */
.gap {
  position: absolute;
  left: 0;
  top: 57px;
  width: 390px;
  height: 635px;
}

/* 底部 @0,752 390x92 */
.bottom-box {
  position: absolute;
  left: 0;
  top: 692px;
  width: 390px;
  height: 92px;
  background: #fff;
}
.cta {
  position: absolute;
  left: 7.8px;
  top: 15px;
  width: 374.4px;
  height: 47px;
  border-radius: 8px;
  background: #000;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  line-height: 47px;
  text-align: center;
  cursor: pointer;
}
</style>
