<script setup>
/**
 * 抽签发售（活动详情）—— 1:1 复刻原站 pages/activity/draw
 *
 * 路由：#/pages/activity/draw
 *
 * 原站 390x1243 实测：
 *   .container.draw  @0,0 390x1243  bg rgb(246,246,246)
 *     navbar fixed @0,0 390x44（返回 @13,7 30x30）
 *     top-light.png  @0,0 390x230
 *     .descCard      @0,301 390x156  渐变展台 + product-tz.png
 *       kuang 描边框 @83,67 225x225 br26  2px solid rgb(223,224,235)
 *       藏品图 @94,78 203x203 br26
 *       title-1.png @63,453 264x19
 *     .plr-12 区 @0,484
 *       时间线卡 @14,484 362x182 bg #fff br8
 *         「抽签发售时间线」@146,505 98x21 14px/700 rgb(35,38,47)
 *         time-bg-line.png @14,532 362x32
 *         time-bg.png     @40,578 310x55
 *       阶段标签行 @14,645 362x0（time-bg 内文字）
 *       抽签码卡 @14,676 362x162 bg #fff br8
 *         「我的抽签码」@14,692 362x21 14px/700
 *         tab 行 @14,725 362x43
 *           「抽签码」active @30,731 85x31 br15 bg #000 12px/700 #fff
 *           「中签码」@127,731 85x31 br15 1px solid rgb(151,151,151) 10px/500 #000
 *         「暂无申购码」@14,788 362x15 10px rgb(35,38,47)
 *       title-2.png @75.5,860 239x14
 *       邀请卡 @14,886 362x228 bg #fff br8
 *         「我的邀请」@45,902 56x21 14px/700
 *         「已成功邀请 0 个人参与」@215,903 130x19 13px/500 rgb(153,153,153)
 *         invite.png @14,947 362x65
 *         「保存二维码」@34.5,1059 151x39 br19 1px solid #000 15px/700 #000
 *         「复制邀请链接」@205.5,1059 150x39 br19 bg #000 15px/700 #fff
 *     .tips @12,1140 366x72  10px/500 #000
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const codeTab = ref(0)
</script>

<template>
  <div class="draw">
    <div class="navbar">
      <img class="nav-back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
    </div>

    <!-- 顶部光柱 + 展台 + 藏品空框 + 标题 -->
    <div class="stage">
      <img class="stage-light" src="/static/draw/top-light.png" alt="" />
      <div class="kuang"></div>
      <div class="product-box"></div>
      <img class="title-1" src="/static/draw/title-1.png" alt="" />
    </div>

    <div class="content">
      <!-- 时间线 -->
      <div class="card card--timeline">
        <div class="card-title">抽签发售时间线</div>
        <img class="tl-line" src="/static/draw/time-bg-line.png" alt="" />
        <img class="tl-bg" src="/static/draw/time-bg.png" alt="" />
      </div>

      <!-- 抽签码 -->
      <div class="card card--code">
        <div class="card-title">我的抽签码</div>
        <div class="code-tabs">
          <div
            class="code-tab"
            :class="{ active: codeTab === 0 }"
            @click="codeTab = 0"
          >抽签码</div>
          <div
            class="code-tab"
            :class="{ active: codeTab === 1 }"
            @click="codeTab = 1"
          >中签码</div>
        </div>
        <div class="code-empty">暂无申购码</div>
      </div>

      <img class="title-2" src="/static/draw/title-2.png" alt="" />

      <!-- 邀请 -->
      <div class="card card--invite">
        <div class="invite-head">
          <span class="invite-title">我的邀请</span>
          <span class="invite-sub">已成功邀请 0 个人参与</span>
        </div>
        <img class="invite-img" src="/static/draw/invite.png" alt="" />
        <div class="invite-btns">
          <div class="invite-btn invite-btn--line">保存二维码</div>
          <div class="invite-btn invite-btn--fill">复制邀请链接</div>
        </div>
      </div>
    </div>

    <div class="tips">
      *数元文创风险提示:数元文创数藏平台发行的数字藏品仅具备收藏欣赏价值，官方对藏品价格不构成任何指导意义请谨慎购买，严防炒作。<br />
      *数元文创运营中心有权根据运营策略做出调整，最终解释权归数元文创数藏平台所有。
    </div>
  </div>
</template>

<style scoped>
.draw {
  position: relative;
  width: 390px;
  height: 1243px;
  background:
    linear-gradient(rgb(190, 191, 196) 30%, rgb(246, 246, 246)) 0 0 / 100% 548px no-repeat,
    rgb(246, 246, 246);
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
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 7px;
  width: 30px;
  height: 30px;
  cursor: pointer;
}

/* 顶部光效 @0,0 390x230 */
.stage {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 484px;
}
.stage-light {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 230px;
}
/* 展台 @0,301 390x156，背景图 product-tz.png 定位 26px 0 / 335x163 */
.stage::after {
  content: '';
  position: absolute;
  left: 0;
  top: 301px;
  width: 390px;
  height: 156px;
  background: url('/static/draw/product-tz.png') 26px 0 / 335px 163px no-repeat;
}
/* 藏品描边框 @83,67 225x225 br26（无背景） */
.kuang {
  position: absolute;
  left: 83px;
  top: 67px;
  width: 225px;
  height: 225px;
  border: 2px solid rgb(223, 224, 235);
  border-radius: 26px;
  box-sizing: border-box;
}
/* 藏品图占位 @94,78 203x203（当前藏品为空，原站亦无内容） */
.product-box {
  position: absolute;
  left: 94px;
  top: 78px;
  width: 203px;
  height: 203px;
  border-radius: 26px;
}
.title-1 {
  position: absolute;
  left: 63px;
  top: 453px;
  width: 264px;
  height: 19px;
}

/* 内容区 @0,484 */
.content {
  position: absolute;
  left: 0;
  top: 484px;
  width: 390px;
}

.card {
  position: absolute;
  left: 14px;
  width: 362px;
  background: #fff;
  border-radius: 8px;
  box-sizing: border-box;
}
.card-title {
  width: 362px;
  height: 21px;
  font-size: 14px;
  font-weight: 700;
  line-height: 21px;
  color: rgb(35, 38, 47);
  text-align: center;
}

/* 时间线卡 @14,484 362x182 */
.card--timeline {
  top: 0;
  height: 182px;
  padding-top: 21px;
  box-sizing: border-box;
}
.tl-line {
  position: absolute;
  left: 0;
  top: 48px;
  width: 362px;
  height: 32px;
}
.tl-bg {
  position: absolute;
  left: 26px;
  top: 94px;
  width: 310px;
  height: 55px;
}

/* 抽签码卡 @14,676 362x162 */
.card--code {
  top: 192px;
  height: 162px;
  padding-top: 16px;
  box-sizing: border-box;
}
.code-tabs {
  display: flex;
  margin-top: 13px;
  padding: 6px 16.5px;
  box-sizing: border-box;
}
.code-tab {
  width: 85px;
  height: 31px;
  margin-right: 12px;
  border: 1px solid rgb(151, 151, 151);
  border-radius: 15px;
  box-sizing: border-box;
  font-size: 10px;
  font-weight: 500;
  line-height: 29px;
  color: #000;
  text-align: center;
  cursor: pointer;
}
.code-tab.active {
  background: #000;
  border-color: #000;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
}
.code-empty {
  width: 362px;
  height: 15px;
  margin-top: 21px;
  font-size: 10px;
  line-height: 15px;
  color: rgb(35, 38, 47);
  text-align: center;
}

/* 邀请好友标题 @75.5,860 */
.title-2 {
  position: absolute;
  left: 75.5px;
  top: 376px;
  width: 239px;
  height: 14px;
}

/* 邀请卡 @14,886 362x228 */
.card--invite {
  top: 402px;
  height: 228px;
}
.invite-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 362px;
  height: 37px;
  padding: 16px 33px 0 31px;
  box-sizing: border-box;
}
.invite-title {
  font-size: 14px;
  font-weight: 700;
  line-height: 21px;
  color: rgb(74, 74, 74);
}
.invite-sub {
  font-size: 13px;
  font-weight: 500;
  line-height: 19px;
  color: rgb(153, 153, 153);
}
.invite-img {
  position: absolute;
  left: 0;
  top: 61px;
  width: 362px;
  height: 65px;
}
.invite-btns {
  position: absolute;
  left: 21px;
  top: 173px;
  display: flex;
  justify-content: space-between;
  width: 320.5px;
  height: 39px;
}
.invite-btn {
  width: 150.5px;
  height: 39px;
  border-radius: 19px;
  font-size: 15px;
  font-weight: 700;
  line-height: 37px;
  text-align: center;
  cursor: pointer;
  box-sizing: border-box;
}
.invite-btn--line {
  border: 1px solid #000;
  color: #000;
}
.invite-btn--fill {
  width: 149.5px;
  background: #000;
  color: #fff;
}

/* 风险提示 @12,1140 366x72 */
.tips {
  position: absolute;
  left: 12px;
  top: 1140px;
  width: 366px;
  height: 72px;
  padding: 0 8px;
  box-sizing: border-box;
  font-size: 10px;
  font-weight: 500;
  line-height: 18px;
  color: #000;
}
</style>
