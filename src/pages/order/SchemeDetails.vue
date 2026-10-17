<script setup>
/**
 * 合成方案详情 —— 1:1 复刻原站 pages/compound/schemeDetails
 *
 * 路由：#/pages/compound/schemeDetails
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle）：
 *   .container.page-container.page-bg @0,0 390x844  bg #f5f5f5
 *     padding-bottom 104px（给底部按钮留白）
 *     .u-navbar--fixed（透明）   @0,0 390x44
 *       返回 back-btn.png       @13,10 24x24
 *     .mian-img                 @0,44 390x395  bg #fff
 *       uni-image               @0,44 390x390（无图，占位）
 *     .plr-16.ptb-16.title-info @0,439 390x76  bg #fff  padding 16
 *       .title-time             @16,463 358x15  10px/400 #999「开始时间:」
 *       .title-time.mt-6        @16,484 358x15  10px/400 #999「结束时间:」
 *     .materials-all            @0,526 390x35  bg #fff  padding-top 13
 *       .title-value            @0,539 103x22  15px/700 #000  padding-left 18
 *         cailiao.png           @18,542 17x17
 *         「兑换元素」          @43,539 60x22  15px/700 #000
 *     .plr-16.ptb-16.title-info.mt-10 @0,571 390x63  bg #fff  padding 16
 *       .flex.flex-ai-center    @16,587 358x21
 *         hecheng.png           @34,589 17x17
 *         「兑换成品」          @59,587 56x21  14px/600 #000
 *     .hc_button                @0,757 390x87  bg #fff  padding 20px 16px
 *       「立即合成」            @16,777 358x47  bg #000  br23  14px/700 #fff
 *
 * 空态说明：无 scheme 参数进入，方案图为空、时间文案仅前缀（与原站一致）。
 */
import { useRouter } from 'vue-router'

const router = useRouter()

/* 方案数据：原站由页面参数传入，无参数时为空 */
const startTime = ''
const endTime = ''

const toRecord = () => {
  router.push('/pages/compound/record')
}

const doCompound = () => {
  /* 原站在此校验持仓并发起合成，无方案数据时不可提交 */
}
</script>

<template>
  <div class="scheme">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
    </div>

    <!-- 方案大图 @0,44 390x395 -->
    <div class="main-img"></div>

    <!-- 时间信息 @0,439 390x76 -->
    <div class="title-info">
      <div class="title-time">开始时间:{{ startTime }}</div>
      <div class="title-time title-time--mt">结束时间:{{ endTime }}</div>
    </div>

    <!-- 兑换元素 @0,526 390x35 -->
    <div class="materials-all">
      <div class="title-value">
        <img class="mat-icon" src="/static/image/compound/cailiao.png" alt="" />
        <span class="mat-text">兑换元素</span>
      </div>
    </div>

    <!-- 兑换成品 @0,571 390x63 -->
    <div class="title-info title-info--mt">
      <div class="product-row">
        <img class="mat-icon" src="/static/image/compound/hecheng.png" alt="" />
        <span class="product-text">兑换成品</span>
      </div>
    </div>

    <!-- 底部按钮 @0,757 390x87 -->
    <div class="hc-button">
      <div class="hc-btn" @click="doCompound">立即合成</div>
    </div>
  </div>
</template>

<style scoped>
.scheme {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #f5f5f5;
  overflow: hidden;
}

/* ---------- 导航 @0,0 390x44（透明） ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  cursor: pointer;
}
.nav-back img {
  display: block;
  width: 24px;
  height: 24px;
}

/* ---------- 方案大图 @0,44 390x395 ---------- */
.main-img {
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  height: 395px;
  background: #fff;
}

/* ---------- 时间信息 @0,439 390x76 ---------- */
.title-info {
  position: absolute;
  left: 0;
  top: 439px;
  width: 390px;
  height: 76px;
  padding: 16px;
  background: #fff;
  box-sizing: border-box;
}
.title-time {
  width: 358px;
  height: 15px;
  font-size: 10px;
  font-weight: 400;
  color: #999;
  line-height: 15px;
}
.title-time--mt {
  margin-top: 6px;
}

/* ---------- 兑换元素 @0,526 390x35 ---------- */
.materials-all {
  position: absolute;
  left: 0;
  top: 526px;
  width: 390px;
  height: 35px;
  padding-top: 13px;
  background: #fff;
  box-sizing: border-box;
}
.title-value {
  display: flex;
  align-items: center;
  width: 103px;
  height: 22px;
  padding-left: 18px;
  box-sizing: border-box;
}
.mat-icon {
  display: block;
  width: 17px;
  height: 17px;
  flex: 0 0 17px;
}
.mat-text {
  margin-left: 8px;
  font-size: 15px;
  font-weight: 700;
  color: #000;
  line-height: 22px;
}

/* ---------- 兑换成品 @0,571 390x63 ---------- */
.title-info--mt {
  top: 571px;
  height: 63px;
}
.product-row {
  display: flex;
  align-items: center;
  width: 358px;
  height: 21px;
  padding-left: 18px;
  box-sizing: border-box;
}
.product-text {
  margin-left: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #000;
  line-height: 21px;
}

/* ---------- 底部按钮 @0,757 390x87 ---------- */
.hc-button {
  position: absolute;
  left: 0;
  top: 757px;
  width: 390px;
  height: 87px;
  padding: 20px 16px;
  background: #fff;
  box-sizing: border-box;
}
.hc-btn {
  width: 358px;
  height: 47px;
  background: #000;
  border-radius: 23px;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  line-height: 47px;
  text-align: center;
  box-sizing: border-box;
}
</style>