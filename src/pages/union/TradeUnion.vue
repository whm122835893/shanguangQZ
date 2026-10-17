<script setup>
/**
 * 闪光工会 —— 1:1 复刻原站 pages/union/tradeUnion
 *
 * 路由：#/pages/union/tradeUnion
 *
 * 原站 390x844 实测（DOM + computed style，登录后已加入无公会状态）：
 *   .container @0,0 390x844  bg rgb(246,246,246) + /static/icon/gh/d-bg.png (size 100% 100%)
 *     flex 列居中，font-weight 500
 *     navbar 占位 @0,78 390x44（fixed navbar @0,0 390x44 透明）
 *       back-btn-white.png  @13,10 24x24
 *       标题「闪光工会」白 15px/700 span @165,16
 *     公会头像 gh-0.png      @166.5,103 57x57
 *       lock-1.png          @187,122 16x19
 *     「您的公会未解锁」     @139,172 112x24  16px/700 #fff
 *     「赶快购买公会藏品解锁您的公会吧~」@101.8,212 186.5x18  12px/500 #fff
 *     .tabbar               @0,271 390x62（3 tab，指示线在第1项下@57,318 18x3）
 *     .card-view            @17,357 356x191.5  bg #fff br10
 *       行1.flex.flex-center @43,383 304x38
 *         圆形图标底@125,388.5 30x27（内含 44x44 图标，居中裁切）
 *         「手续费减免」@185,383 80x24 16px/700 #000
 *       分隔线@43,441 318x0.5
 *       行2.flex.flex-center @43,466.5 304x56
 *         圆形图标底@43,481 30x27（内含 44x44 图标）
 *         「公会成员人数」@103,466.5 244x24 16px/700 #000
 *         「每位公会会长最多可绑定邀请20人。」@103,504.5 244x18 12px/500 #666
 *     右侧浮标 .tips        @340,31 40x114
 *       「公会排行」rank.png @347,31 26x26 + 文案 10px #fff
 *       「消息通知」ding.png @347,98 26x26（mt-20）
 *     左侧浮标 .tips2       @10,62 40x67
 *       「工会商城」ghsc.png @17,82 26x26
 *
 * 接口：POST /api/union/user/myunion -> 当前账号未加入公会
 */
import { ref } from 'vue'

const tabs = ['个人权益', '我的工会', '申请入会']
const active = ref(0)
</script>

<template>
  <div class="trade-union">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="$router.back()">
        <img src="/static/icon/back-btn-white.png" alt="返回" />
      </div>
      <div class="nav-title">闪光工会</div>
    </div>

    <!-- 公会头像 @166.5,103 57x57 -->
    <div class="avatar">
      <img src="/static/icon/gh/gh-0.png" alt="公会" />
      <img class="lock" src="/static/icon/gh/lock-1.png" alt="未解锁" />
    </div>

    <!-- @139,172 / @101.8,212 -->
    <div class="unlock-title">您的公会未解锁</div>
    <div class="unlock-sub">赶快购买公会藏品解锁您的公会吧~</div>

    <!-- 右侧浮标 @340,31 -->
    <div class="float right">
      <div class="fi">
        <img src="/static/icon/gh/rank.png" alt="公会排行" />
        <span>公会排行</span>
      </div>
      <div class="fi fi--gap">
        <img src="/static/icon/gh/ding.png" alt="消息通知" />
        <span>消息通知</span>
      </div>
    </div>
    <!-- 左侧浮标 @10,62 -->
    <div class="float left">
      <div class="fi fi--gap">
        <img src="/static/icon/gh/ghsc.png" alt="工会商城" />
        <span>工会商城</span>
      </div>
    </div>

    <!-- tabbar @0,271 390x62 -->
    <div class="tabbar">
      <div
        v-for="(t, i) in tabs"
        :key="t"
        class="tab"
        @click="active = i"
      >{{ t }}</div>
      <div class="tab-line" :style="{ transform: `translateX(${active * 130}px)` }"></div>
    </div>

    <!-- 权益卡 @17,357 356x191.5 -->
    <div class="card-view">
      <div class="row row--1">
        <div class="row-ico">
          <img src="/static/icon/gh/gh-1.png" alt="手续费减免" />
        </div>
        <div class="row-txt"><div class="row-title">手续费减免</div></div>
      </div>

      <div class="divider"></div>

      <div class="row row--2">
        <div class="row-ico">
          <img src="/static/icon/gh/gh-2.png" alt="公会成员人数" />
        </div>
        <div class="row-txt">
          <div class="row-title">公会成员人数</div>
          <div class="row-desc">每位公会会长最多可绑定邀请20人。</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.trade-union {
  position: relative;
  width: 390px;
  height: 844px;
  background: rgb(246, 246, 246) url('/static/icon/gh/d-bg.png') 0 0 / 100% auto no-repeat;
  color: rgb(74, 74, 74);
  font-size: 15px;
  font-weight: 500;
  overflow: hidden;
}

/* 导航 @0,0 390x44（fixed，透明） */
.navbar {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 44px;
  z-index: 3;
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
.nav-title {
  position: absolute;
  left: 0;
  top: 15px;
  width: 390px;
  height: 18px;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  line-height: 18px;
  text-align: center;
}

/* 公会头像 @166.5,103 57x57 */
.avatar {
  position: absolute;
  left: 166.5px;
  top: 103px;
  width: 57px;
  height: 57px;
}
.avatar img {
  display: block;
  width: 57px;
  height: 57px;
}
.avatar .lock {
  position: absolute;
  left: 20.5px;
  top: 19px;
  width: 16px;
  height: 19px;
}

/* 「您的公会未解锁」@139,172 */
.unlock-title {
  position: absolute;
  left: 139px;
  top: 175px;
  width: 112px;
  height: 24px;
  font-size: 16px;
  font-weight: 700;
  color: #fff;
  line-height: 24px;
  text-align: center;
}
/* 副标题 @101.8,212 */
.unlock-sub {
  position: absolute;
  left: 101.75px;
  top: 212px;
  width: 186.5px;
  height: 18px;
  font-size: 12px;
  font-weight: 500;
  color: #fff;
  line-height: 18px;
  text-align: center;
}

/* 悬浮入口 */
.float {
  position: absolute;
  width: 40px;
  font-size: 10px;
  font-weight: 500;
  color: #fff;
}
.float.right {
  left: 340px;
  top: 31px;
}
.float.left {
  left: 10px;
  top: 62px;
}
.fi {
  height: 47px;
  display: flex;
  flex-direction: column;
  align-items: center;
}
.fi--gap {
  margin-top: 20px;
}
.fi img {
  display: block;
  width: 26px;
  height: 26px;
  margin-bottom: 6px;
}
.fi span {
  display: block;
  line-height: 15px;
  white-space: nowrap;
}

/* tabbar @0,271 390x62 */
.tabbar {
  position: absolute;
  left: 0;
  top: 271px;
  width: 390px;
  height: 62px;
  display: flex;
  overflow: hidden;
}
.tab {
  width: 130px;
  height: 62px;
  flex: none;
  font-size: 15px;
  font-weight: 500;
  color: #fff;
  line-height: 68px;
  text-align: center;
  cursor: pointer;
}
.tab-line {
  position: absolute;
  left: 57px;
  top: 49px;
  width: 18px;
  height: 3px;
  background: #fff;
  transition: transform 0.2s;
}

/* 权益卡 @17,357 356x191.5 */
.card-view {
  position: absolute;
  left: 17px;
  top: 357px;
  width: 356px;
  height: 191.5px;
  background: #fff;
  border-radius: 10px;
  box-sizing: border-box;
}
/* 行1：图标 @125,388.5 44x44；文字块 @185,383 80x38 */
.row {
  position: absolute;
  display: flex;
  align-items: center;
}
.row--1 {
  left: 26px;
  top: 26px;
  width: 304px;
  height: 38px;
  justify-content: center;
  gap: 30px;
}
/* 行2：图标 @43,481 44x44；文字块 @103,466.5 244x56 */
.row--2 {
  left: 26px;
  top: 109.5px;
  width: 304px;
  height: 56px;
}
/* 图标容器 30x27，内部 img 44x44 溢出（原站无裁剪） */
.row-ico {
  width: 30px;
  height: 27px;
  flex: none;
}
.row-ico img {
  display: block;
  width: 44px;
  height: 44px;
  flex: none;
}
.row-txt {
  flex: none;
  width: 80px;
}

.row--2 .row-txt {
  flex: 1;
  width: auto;
  min-width: 0;
  margin-left: 30px;
}
.row--2 .row-txt {
  margin-left: 30px;
}
.row-title {
  font-size: 16px;
  font-weight: 700;
  color: #000;
  line-height: 24px;
  white-space: nowrap;
}
.row--2 .row-title {
  margin-bottom: 14px;
}
.row-desc {
  font-size: 12px;
  font-weight: 500;
  color: rgb(102, 102, 102);
  line-height: 18px;
  white-space: nowrap;
}
/* 分隔线 @43,441 318x0.5 */
.divider {
  position: absolute;
  left: 26px;
  top: 84px;
  width: 318px;
  height: 0.5px;
  background: #eee;
}
</style>