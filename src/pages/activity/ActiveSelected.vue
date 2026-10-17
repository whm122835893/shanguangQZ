<script setup>
/**
 * 活动中心 —— 1:1 复刻原站 pages/activeSelected/activeSelected
 *
 * 路由：#/pages/activeSelected/activeSelected
 *
 * 原站 390x844 实测：
 *   .container.pa-16.page-bg  @0,0 390x844  bg rgb(250,250,250)
 *     navbar fixed @0,0 390x44  bg rgb(250,250,250)
 *       返回 @16,12 24x24（back-btn.png）
 *       标题「活动中心」rgb(51,51,51) 15px/700
 *     .flex.flex-js-sb @16,60 358x66
 *       .item @16,60 170x66  bg active-banner-bg1.png（加载失败→透明）
 *         error 占位 @48.5,76.5 33x33  bg rgb(243,244,246)
 *         「最新活动」@93.5,82 60x22 15px #fff
 *       .item @204,60 170x66  bg active-banner-bg2.png
 *         error 占位 @236.5,76.5 33x33
 *         「抽奖中心」@281.5,82 60x22 15px #fff
 *     空态：@134.5,242 121x100（search-empty.png），容器 @16,242 358x100
 *
 * 说明：原站两个 banner 背景图托管在华为云 OBS，当前不可访问，
 *      原站自身也渲染为「加载失败」占位态（灰底 + ⓘ 图标），
 *      此处按原站可见结果 1:1 复刻。
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="active-selected">
    <div class="navbar">
      <img class="nav-back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">活动中心</div>
    </div>

    <div class="banner-row">
      <div class="item">
        <div class="item-ico"><span class="ico-inner"></span></div>
        <div class="item-txt">最新活动</div>
      </div>
      <div class="item item--2">
        <div class="item-ico"><span class="ico-inner"></span></div>
        <div class="item-txt">抽奖中心</div>
      </div>
    </div>

    <div class="empty">
      <img src="/static/empty/search-empty.png" alt="" />
    </div>
  </div>
</template>

<style scoped>
.active-selected {
  position: relative;
  width: 390px;
  min-height: 844px;
  padding: 16px;
  box-sizing: border-box;
  background: rgb(250, 250, 250);
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* 导航 fixed @0,0 390x44 */
.navbar {
  position: fixed;
  left: 0;
  top: 0;
  z-index: 10;
  width: 390px;
  height: 44px;
  background: rgb(250, 250, 250);
}
.nav-back {
  position: absolute;
  left: 16px;
  top: 12px;
  width: 24px;
  height: 24px;
  cursor: pointer;
}
.nav-title {
  width: 390px;
  height: 44px;
  font-size: 15px;
  font-weight: 700;
  color: rgb(51, 51, 51);
  line-height: 44px;
  text-align: center;
}

/* banner 行 @16,60 358x66 */
.banner-row {
  display: flex;
  justify-content: space-between;
  width: 358px;
  height: 66px;
  margin-top: 0;
}
.item {
  display: flex;
  align-items: center;
  width: 170px;
  height: 66px;
}
/* 原站 banner 图加载失败：仅显示 33x33 灰色占位 */
.item-ico {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 33px;
  height: 33px;
  flex: none;
  background: rgb(243, 244, 246);
}
/* 占位中心的 ⓘ 图标（原站 u-image__error 内置图标） */
.ico-inner {
  position: relative;
  display: block;
  width: 16px;
  height: 16px;
  border: 1.5px solid rgb(190, 191, 196);
  border-radius: 50%;
}
.ico-inner::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 3px;
  width: 1.5px;
  height: 5px;
  margin-left: -0.75px;
  background: rgb(190, 191, 196);
}
.item-txt {
  margin-left: 12px;
  font-size: 15px;
  font-weight: 400;
  color: rgb(255, 255, 255);
  line-height: 22px;
}

/* 空态：容器 @16,242 358x100，图 @134.5,242 121x100 */
.empty {
  display: flex;
  justify-content: center;
  width: 358px;
  height: 100px;
  margin-top: 104px;
}
.empty img {
  display: block;
  width: 121px;
  height: 100px;
}
</style>
