<script setup>
/**
 * 关于我们 —— 1:1 复刻原站 pages/my/about
 * 由「其他功能」页「关于我们」进入。
 *
 * 原站实测（getBoundingClientRect + getComputedStyle）：
 *   .container 390x844 白底
 *   返回 back-btn.png @13,8 28x28   ← 注意：本页返回按钮 28x28，
 *                与其他页（24x24 @13,10）不同，按实测值硬编码
 *   导航栏无标题（只有返回按钮）
 *
 *   card-view 卡片 @13,96 364x194
 *     标题「关于我们」@25,116 340x35  24px/500 rgb(35,38,47)
 *     描述「文创数字藏品平台，为原创艺术作品链上确权，弘扬文化，仅供观赏收藏。」
 *       @25,168 330x38  15px/400 rgb(134,134,134)
 *     协议行 .flex-js-center @25,248 340x22
 *       「《用户服务协议》」@90,253 120x16  15px/400 rgb(51,51,51)
 *       「《隐私政策》」  @210,253 90x16   15px/400 rgb(51,51,51)
 *       （flex 居中排列，两项间距自动分配）
 */
import { useRouter } from 'vue-router'
import { showToast } from 'vant'

const router = useRouter()

function onAgreement() {
  showToast({ icon: 'none', message: '原站未提供协议页' })
}
</script>

<template>
  <div class="about-page">
    <!-- 导航：本页无标题，返回按钮实测 28x28 @13,8 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
    </div>

    <!-- 卡片@13,96 364x194 -->
    <div class="card">
      <div class="card-title">关于我们</div>
      <p class="card-desc">文创数字藏品平台，为原创艺术作品链上确权，弘扬文化，仅供观赏收藏。</p>
      <div class="agreement-row">
        <span class="agreement" style="left: 65px" @click="onAgreement">《用户服务协议》</span>
        <span class="agreement" style="left: 185px" @click="onAgreement">《隐私政策》</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* .container 390x844 白底 */
.about-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航高 44，只有返回按钮（本页实测 28x28 @13,8） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 8px; width: 28px; height: 28px; z-index: 2; }

/* card-view 卡片@13,96 364x194（padding 12 → 内容左边界 25） */
.card {
  position: absolute;
  left: 13px;
  top: 96px;
  width: 364px;
  height: 194px;
  box-sizing: border-box;
  padding: 12px;
  background: #fff;
}

/* 标题「关于我们」@25,116 340x35  24px/500 rgb(35,38,47)
   原站卡片内文字居中（@25 起 340 宽，中心 195） */
.card-title {
  width: 340px;
  height: 35px;
  color: rgb(35, 38, 47);
  font-size: 24px;
  font-weight: 500;
  line-height: 58px;
  text-align: center;
}

/* 描述@25,168 330x38  15px/400 rgb(134,134,134)
   （外层 mt-12 @25,163 340x44，文本在框内居中 → 文本落168） */
.card-desc {
  width: 330px;
  height: 44px;
  margin: 22px 0 0;
  color: rgb(134, 134, 134);
  font-size: 15px;
  font-weight: 400;
  line-height: 22px;
}

/* 协议行@25,248 340x22
   「《用户服务协议》」@90,253 120x16 → 相对行 left=65
   「《隐私政策》」  @210,253 90x16  → 相对行 left=185
   按原站实测坐标绝对定位（flex space-around 会因两段文字宽度不同产生偏移） */
.agreement-row {
  position: absolute;
  left: 12px;
  top: 152px;             /* 96+152=248 */
  width: 340px;
  height: 22px;
}
/* 15px/400 rgb(51,51,51) */
.agreement {
  position: absolute;
  top: 5px;              /* 248+5=253 */
  color: #333;
  font-size: 15px;
  font-weight: 400;
  line-height: 16px;
  cursor: pointer;
}
</style>