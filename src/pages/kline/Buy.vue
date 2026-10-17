<script setup>
/**
 * 行情购买 —— 1:1 复刻原站 pages/kline/buy
 *
 * 原站 390x844 实测（深色行情页，与 kline/info 同背景 rgb(35,39,68)）：
 *   .u-navbar--fixed          @0,0 390x44
 *     返回 back-btn-white.png @13,10 24x24
 *     标题「购买」             @180,16 30x16 15px/700 #fff
 *     右侧「切换行情」          @329,15.5 48x14 12px #fff
 *
 *   横滑卡片 .u-swiper.mlr-13 @16,44 358x123
 *
 *   藏品信息
 *     「凤吹晚霞」            @16,184 72x20  18px/600 #fff
 *     「闪光」               @102,186 24x14 12px rgb(122,122,122)
 *     「112.34CNY」          @277.5,180.5 96.5x22 20px rgb(51,51,51)
 *     「-0.53%」            @235.5,184.5 35x14 12px rgb(51,51,51)
 *
 *   Tab 行 y=217
 *     「流通」                @16,217 30x16  15px #fff（选中）
 *     「相关公告」            @58,217 60x16  15px rgb(122,122,122)
 *     「成交额」             @130,217 45x16 15px rgb(122,122,122)
 *
 *   内容区（原站无数据，空白深色底）
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const cards = [
  { img: '/static/image/kline/card.jpg' },
  { img: '/static/image/kline/card2.jpg' }
]
const cardIdx = ref(0)

const tabs = ['流通', '相关公告', '成交额']
const idx = ref(0)

const name = ref('凤吹晚霞')
const author = ref('闪光')
const price = ref('112.34')
const change = ref('-0.53%')
</script>

<template>
  <div class="container page-container">
    <!-- 导航 @0,0 390x44 -->
    <div class="u-navbar--fixed">
      <div class="u-navbar__content">
        <div class="u-navbar__content__left">
          <div class="flex-center" @click="router.back()">
            <img class="back-img" src="/static/icon/back-btn-white.png" alt="返回" />
          </div>
        </div>
        <span class="nav-title">购买</span>
        <div class="u-navbar__content__right">
          <div class="right-text">切换行情</div>
        </div>
      </div>
    </div>

    <!-- 横滑卡片 @16,44 358x123 -->
    <div class="u-swiper">
      <div class="swiper-track" :style="{ transform: `translateX(-${cardIdx * 371}px)` }">
        <div v-for="(c, i) in cards" :key="i" class="swiper-item">
          <img :src="c.img" alt="" />
        </div>
      </div>
    </div>

    <!-- 藏品信息 @0,167 -->
    <div class="goods-info">
      <div class="name-row">
        <span class="g-name">{{ name }}</span>
        <span class="g-author">{{ author }}</span>
        <span class="spacer" />
        <span class="g-change">{{ change }}</span>
        <span class="g-price">{{ price }}CNY</span>
      </div>
    </div>

    <!-- Tab 行 @16,217 -->
    <div class="tabs-row">
      <div
        v-for="(t, i) in tabs"
        :key="t"
        class="tabs-item"
        :class="{ active: i === idx }"
        @click="idx = i"
      >
        {{ t }}
      </div>
    </div>

    <!-- 内容区（原站无数据） -->
    <div class="content-body" />
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

.flex-center { display: flex; align-items: center; justify-content: center; }

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
  right: 13px;
  top: 15.5px;
  width: 48px;
  height: 14px;
}

.right-text {
  font-size: 12px;
  line-height: 14px;
  color: #fff;
  text-align: right;
  white-space: nowrap;
}

/* 横滑卡片 @16,44 358x123 */
.u-swiper {
  position: absolute;
  left: 16px;
  top: 44px;
  width: 358px;
  height: 123px;
}

.swiper-track {
  display: flex;
  height: 123px;
  transition: transform 0.3s ease;
}

.swiper-item {
  width: 358px;
  height: 123px;
  flex-shrink: 0;
  overflow: hidden;
}

.swiper-item img {
  width: 358px;
  height: 123px;
  object-fit: fill;
  display: block;
}

/* 藏品信息 */
.goods-info {
  position: absolute;
  left: 16px;
  top: 176px;
  width: 358px;
  height: 29px;
}

.name-row {
  display: flex;
  align-items: center;
  width: 358px;
  height: 29px;
}

.g-name {
  font-size: 18px;
  font-weight: 600;
  line-height: 20px;
  color: #fff;
}

.g-author {
  margin-left: 14px;
  font-size: 12px;
  line-height: 14px;
  color: rgb(122, 122, 122);
}

.spacer { flex: 1; }

.g-change {
  font-size: 12px;
  line-height: 14px;
  color: rgb(51, 51, 51);
  white-space: nowrap;
}

.g-price {
  margin-left: 7px;
  font-size: 20px;
  line-height: 22px;
  color: rgb(51, 51, 51);
  white-space: nowrap;
}

/* Tab 行 @16,217 */
.tabs-row {
  position: absolute;
  left: 16px;
  top: 217px;
  width: 358px;
  height: 16px;
  display: flex;
}

.tabs-item {
  margin-right: 12px;
  font-size: 15px;
  line-height: 16px;
  color: rgb(122, 122, 122);
  white-space: nowrap;
}

.tabs-item.active {
  color: #fff;
}

/* 内容区（原站无数据） */
.content-body {
  position: absolute;
  left: 0;
  top: 233px;
  width: 390px;
  height: 611px;
}
</style>
