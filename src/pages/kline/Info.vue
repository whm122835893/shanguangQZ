<script setup>
/**
 * 行情详情 —— 1:1 复刻原站 pages/kline/info
 *
 * 原站 390x844 实测（深色行情页）：
 *   .container.page-container  390x844 bg rgb(35,39,68)
 *   .u-navbar--fixed          @0,0 390x44 bg rgb(35,39,68)
 *     返回 back-btn2.png      @13,10 24x24
 *     标题「详情」             @180,16 30x16 15px/700 #fff
 *     右侧「切换购买」          @329,13 48x18 12px #fff（容器 @316,0 74x44）
 *
 *   横滑卡片 .u-swiper.mlr-13.mt-8 @13,52 364x123
 *     指示器 @13,42 46x2（dot 15x2 bg rgb(54,153,255) br1 margin 0 4px）
 *     卡片图 364x123 br10
 *
 *   信息卡 .info_card @0,175 390x133 padding 0 13px margin-bottom 20
 *     .name @13,175 364x41
 *       「藏品名称」            @13,189.5 64x17  16px #fff
 *       「比太」               @87,190.5 24x14  12px rgb(122,122,122)
 *     价格行 .flex.flex-ai-center @13,216 364x18
 *       「112.34CNY」          @13,216 77.2x17 16px rgb(51,51,51)（低对比度占位）
 *       「-0.53%」            @100.2,218.5 35x14 12px rgb(51,51,51)
 *     .num @13,242 364x66 margin-top 8
 *       行1 @13,242「流通：30000」+「最近5min成交量：30000」
 *       行2 @13,264「24h最高：30000」+「最近1h成交量：30000」
 *       行3 @13,289「24h最低：30000」+「今日成交量：30000」
 *       标签 12px rgb(122,122,122)，值 12px #fff
 *
 *   内容 tab 行（行情/相关公告/成交记录/换手榜）y≈430
 *   底部操作栏：开启行情提醒 / 批量买卖 / 关注(sc3.png)
 *
 * 注：原站 K 线图与列表区域当前无数据（原站渲染为空白深色底），
 *     故复刻保持同样的空态。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const cards = [
  { img: '/static/image/kline/card.jpg' },
  { img: '/static/image/kline/card2.jpg' }
]
/* 原站首屏 swiper 初始停在第 2 张（卡片起始偏移 364px） */
const cardIdx = ref(0)

const tabs = ['行情', '相关公告', '成交记录', '换手榜']
const idx = ref(0)

/* 原站该页为占位行情数据（藏品名/比太、112.34CNY、-0.53%、各项 30000） */
const name = ref('藏品名称')
const author = ref('比太')
const price = ref('112.34')
const change = ref('-0.53%')

const stats = [
  { k1: '流通：', v1: '30000', k2: '最近5min成交量：', v2: '30000' },
  { k1: '24h最高：', v1: '30000', k2: '最近1h成交量：', v2: '30000' },
  { k1: '24h最低：', v1: '30000', k2: '今日成交量：', v2: '30000' }
]
</script>

<template>
  <div class="container page-container">
    <!-- 导航 @0,0 390x44 -->
    <div class="u-navbar--fixed">
      <div class="u-navbar__content">
        <div class="u-navbar__content__left">
          <div class="flex-center" @click="router.back()">
            <img class="back-img" src="/static/icon/back-btn2.png" alt="返回" />
          </div>
        </div>
        <span class="nav-title">详情</span>
        <div class="u-navbar__content__right">
          <div class="font-size-12 right-text">切换购买</div>
        </div>
      </div>
    </div>

    <!-- 横滑卡片 @13,52 364x123 -->
    <div class="u-swiper">
      <div class="swiper-track" :style="{ transform: `translateX(-${cardIdx * 364}px)` }">
        <div v-for="(c, i) in cards" :key="i" class="swiper-item">
          <img :src="c.img" alt="" />
        </div>
      </div>
      <div class="u-swiper-indicator">
        <div class="indicator-wrapper">
          <div
            v-for="(c, i) in cards"
            :key="i"
            class="indicator-dot"
            :class="{ active: i === cardIdx }"
          />
        </div>
      </div>
    </div>

    <!-- 信息卡 @0,175 390x133 -->
    <div class="info_card">
      <div class="name">
        <span class="name-main">{{ name }}</span>
        <span class="name-sub">{{ author }}</span>
      </div>
      <div class="flex flex-ai-center price-row">
        <span class="price-text">{{ price }}CNY</span>
        <span class="change-text">{{ change }}</span>
      </div>
      <div class="num">
        <div v-for="(s, i) in stats" :key="i" class="num-row">
          <div class="num-item">
            <span class="num-k">{{ s.k1 }}</span>
            <span class="num-v">{{ s.v1 }}</span>
          </div>
          <div class="num-item">
            <span class="num-k">{{ s.k2 }}</span>
            <span class="num-v">{{ s.v2 }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 内容 tab -->
    <div class="content-tabs">
      <div
        v-for="(t, i) in tabs"
        :key="t"
        class="content-tab"
        :class="{ active: i === idx }"
        @click="idx = i"
      >
        {{ t }}
      </div>
    </div>

    <!-- 内容区（原站无数据，保持空白） -->
    <div class="content-body" />

    <!-- 底部操作栏 -->
    <div class="bottom-bar">
      <div class="bt-1">开启行情提醒</div>
      <div class="bt-2">批量买卖</div>
      <div class="bt-3">
        <img class="follow-icon" src="/static/icon/sc3.png" alt="关注" />
        <span class="follow-txt">关注</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.container {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: rgb(35, 39, 68);
  box-sizing: border-box;
  overflow: hidden;
  color: #fff;
}

.flex { display: flex; }
.flex-center { display: flex; align-items: center; justify-content: center; }
.flex-ai-center { align-items: center; }
.font-size-12 { font-size: 12px; }

/* 导航 @0,0 390x44 */
.u-navbar--fixed {
  position: fixed;
  left: 0;
  top: 0;
  width: 100%;
  height: 44px;
  z-index: 20;
}

.u-navbar__content {
  position: relative;
  height: 44px;
  display: flex;
  align-items: center;
  background: rgb(35, 39, 68);
}

.u-navbar__content__left {
  position: absolute;
  left: 0;
  top: 0;
  width: 50px;
  height: 44px;
  padding: 0 13px;
  box-sizing: border-box;
}

.back-img {
  width: 24px;
  height: 24px;
  object-fit: fill;
  display: block;
}

.nav-title {
  position: absolute;
  left: 50%;
  top: 16px;
  transform: translateX(-50%);
  font-size: 15px;
  line-height: 16px;
  font-weight: 700;
  color: #fff;
  white-space: nowrap;
}

.u-navbar__content__right {
  position: absolute;
  right: 0;
  top: 0;
  width: 74px;
  height: 44px;
  padding: 0 13px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.right-text {
  width: 48px;
  height: 18px;
  font-size: 12px;
  line-height: 18px;
  color: #fff;
  white-space: nowrap;
}

/* 横滑卡片 @13,52 364x123 */
.u-swiper {
  position: absolute;
  left: 13px;
  top: 52px;
  width: 364px;
  height: 123px;
}

.swiper-track {
  display: flex;
  height: 123px;
  transition: transform 0.3s ease;
}

.swiper-item {
  width: 364px;
  height: 123px;
  flex-shrink: 0;
  overflow: hidden;
}

.swiper-item img {
  width: 364px;
  height: 123px;
  object-fit: fill;
  display: block;
}

/* 指示器 @13,42 46x2 —— 原站位于卡片上方 */
.u-swiper-indicator {
  position: absolute;
  left: 0;
  top: -10px;
  width: 364px;
  height: 2px;
}

.indicator-wrapper {
  display: flex;
  height: 2px;
}

.indicator-dot {
  width: 15px;
  height: 2px;
  margin: 0 4px;
  border-radius: 1px;
  background: rgb(122, 122, 122);
}

.indicator-dot.active {
  background: rgb(54, 153, 255);
}

/* 信息卡 @0,175 390x133 */
.info_card {
  position: absolute;
  left: 0;
  top: 175px;
  width: 390px;
  height: 133px;
  padding: 0 13px;
  box-sizing: border-box;
}

.name {
  width: 364px;
  height: 41px;
  display: flex;
  align-items: center;
}

.name-main {
  font-size: 16px;
  line-height: 17px;
  color: #fff;
}

.name-sub {
  margin-left: 10px;
  font-size: 12px;
  line-height: 14px;
  color: rgb(122, 122, 122);
}

/* 价格行 @13,216 */
.price-row {
  width: 364px;
  height: 18px;
}

.price-text {
  font-size: 16px;
  line-height: 17px;
  color: rgb(51, 51, 51);
}

.change-text {
  margin-left: 10px;
  font-size: 12px;
  line-height: 14px;
  color: rgb(51, 51, 51);
}

/* 数据区 @13,242 */
.num {
  width: 364px;
  height: 66px;
  margin-top: 8px;
}

.num-row {
  display: flex;
  justify-content: space-between;
  width: 364px;
  height: 18px;
  margin-bottom: 4px;
}

.num-item {
  display: flex;
  align-items: center;
  height: 18px;
}

.num-k {
  font-size: 12px;
  line-height: 14px;
  color: rgb(122, 122, 122);
}

.num-v {
  font-size: 12px;
  line-height: 14px;
  color: #fff;
}

/* 内容 tab y≈430 */
.content-tabs {
  position: absolute;
  left: 0;
  top: 424px;
  width: 390px;
  height: 46px;
  display: flex;
  align-items: center;
  padding: 0 13px;
  box-sizing: border-box;
}

.content-tab {
  margin-right: 42px;
  font-size: 15px;
  line-height: 20px;
  color: rgb(122, 122, 122);
  white-space: nowrap;
}

.content-tab.active {
  color: #fff;
  border-bottom: 2px solid #fff;
}

/* 内容区（原站无数据） */
.content-body {
  position: absolute;
  left: 0;
  top: 470px;
  width: 390px;
  height: 322px;
}

/* 底部操作栏 */
.bottom-bar {
  position: absolute;
  left: 0;
  bottom: 0;
  width: 390px;
  height: 72px;
  display: flex;
  align-items: center;
  padding: 0 11px 12px;
  box-sizing: border-box;
  background: rgb(35, 39, 68);
}

.bt-1 {
  width: 190px;
  height: 44px;
  border-radius: 22px;
  background: rgb(233, 148, 213);
  font-size: 16px;
  line-height: 44px;
  color: #fff;
  text-align: center;
}

.bt-2 {
  width: 190px;
  height: 44px;
  margin-left: 14px;
  border-radius: 22px;
  background: rgb(249, 178, 76);
  font-size: 16px;
  line-height: 44px;
  color: #fff;
  text-align: center;
}

.bt-3 {
  width: 36px;
  margin-left: 12px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.follow-icon {
  width: 22px;
  height: 22px;
  object-fit: fill;
}

.follow-txt {
  font-size: 10px;
  line-height: 12px;
  color: #fff;
}
</style>
