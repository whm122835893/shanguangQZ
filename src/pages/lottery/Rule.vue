<script setup>
/**
 * 活动规则 —— 1:1 复刻原站 pages/lottery/rule
 * 由抽奖页右上角 hdgz-image.png @316,72 74x36 点击进入。
 *
 * 原站登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container.page-container @0,0 390x844
 *     background linear-gradient(136deg, rgb(69,69,67), rgb(20,17,13))
 *     padding 0 16px 16px
 *   导航标题「活动规则」          @165,16 60x16  15px/700 #fff
 *   返回 back-btn-white.png      @13,10 24x24
 *   .content@16,59 355x602
 *     background rule-bg.png (684x1158)  background-size 100% 100%
 *     padding 20px 29px
 *   「规则详情」            @159.5,84 68x19  17px/700 #fff
 *   左侧箭头 19.png         @93.5,85 50x13
 *   右侧箭头 20.png         @243.5,85 50x13
 *
 * 规则内容区当前为空 —— 原站无进行中的抽奖活动，
 * 接口 /lottery/info 返回「暂无抽奖活动」，故此处不编造规则文案。
 */
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="rule-page">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn-white.png" alt="返回" @click="router.back()" />
      <div class="nav-title">活动规则</div>
    </div>

    <!-- 面板 @16,59 355x602 -->
    <div class="content">
      <!-- 标题行：19.png @93.5,85 50x13 | 「规则详情」| 20.png @243.5,85 50x13 -->
      <div class="rule-head">
        <img class="arrow left" src="/static/image/lottery/19.png" alt="" />
        <div class="rule-title">规则详情</div>
        <img class="arrow right" src="/static/image/lottery/20.png" alt="" />
      </div>
      <!-- 规则正文：原站当前为空（无进行中的抽奖活动），按原站 1:1 保持空 -->
      <div class="rule-body"></div>
    </div>
  </div>
</template>

<style scoped>
/* .container.page-container @0,0 390x844 */
.rule-page {
  position: relative;
  min-height: 844px;
  background: linear-gradient(136deg, rgb(69, 69, 67), rgb(20, 17, 13));
  padding: 0 16px 16px;
  box-sizing: border-box;
}

/* 导航栏：标题「活动规则」@165,16 60x16 */
.navbar {
  position: relative;
  width: 358px;
  height: 59px;
}
/* 返回 @13,10 24x24 —— 相对页面左边距 16px 溢出 3px，故 left:-3px */
.back {
  position: absolute;
  left: -3px;
  top: 10px;
  width: 24px;
  height: 24px;
  z-index: 2;
}
.nav-title {
  position: absolute;
  left: 0;
  top: 16px;
  width: 358px;
  height: 16px;
  line-height: 16px;
  text-align: center;
  color: #fff;
  font-size: 15px;
  font-weight: 700;
}

/* .content @16,59 355x602 bg rule-bg.png padding 20px 29px */
.content {
  position: relative;
  width: 355px;
  height: 602px;
  padding: 20px 29px;
  box-sizing: border-box;
  background: url('/static/image/lottery/rule-bg.png') no-repeat center;
  background-size: 100% 100%;
}

/* 标题行
   注意：.content 有 padding 20px 29px，绝对定位子元素的原点是
   padding box 左上角（即 .content 的 border box 内侧，已含 padding），
   因此 left/top 直接等于「相对 .content 左上角」的偏移。
   原站实测：19.png @93.5,85(页面) -> 相对 .content(16,59) = 77.5,26
             「规则详情」@159.5,84   -> 143.5,25
             20.png @243.5,85       -> 227.5,26 */
.rule-head {
  position: absolute;
  left: 0;
  top: 0;
  width: 355px;
  height: 50px;
}
/* 19.png / 20.png 原图 98x28 / 98x30 -> 原站实测显示 50x13 */
.arrow {
  position: absolute;
  top: 26px;
  left: 0;
  width: 50px;
  height: 13px;
}
.arrow.left { left: 77.5px; }
/* 20.png 原图 98x30 等比后为 50x15.3，原站实测高 13，此处按实测取 13 */
.arrow.right { left: 227.5px; height: 13px; }
/* 「规则详情」@159.5,84 -> 相对 .content = 143.5,25
   注：.rule-head 的定位原点即 .content 的 padding box 左上角（top:0/left:0），
   故子元素 top/left 即为相对 .content 左上角的偏移，与原站实测页面坐标减 (16,59) 一致。 */
.rule-title {
  position: absolute;
  left: 143.5px;
  top: 25px;
  width: 68px;
  height: 19px;
  line-height: 19px;
  text-align: center;
  color: #fff;
  font-size: 17px;
  font-weight: 700;
}

/* 规则正文：原站无活动时为空。padding box 原点为 .content 内侧，故 top 从 66 起 */
.rule-body { position: absolute; left: 29px; top: 66px; width: 297px; }
</style>