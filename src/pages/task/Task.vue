<script setup>
/**
 * 活动中心 —— 100% 复刻原站 pages/task/index
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle）：
 *   .container.page-container          @0,0  390x844  底 rgb(246,246,246)
 *     标题「活动中心」                @14,31  362x26   18px 居中 #222
 *     四宫格入口区                     @14,81  362x148
 *       入口1 t-compose.png            @14,81  172x64  bg 100% 100%
 *       入口2 t-gold.png               @204,81 172x64
 *       入口3 t-draw.png               @14,155 172x64
 *       入口4 t-class.png              @204,155 172x64
 *     「最新活动」卡                   @14,243 362x180  bg #fff r12
 *       task-title-1.png               @28,259 75x21
 *       swiper @28,295 334x112（横向轮播，一屏一张）
 *         活动图                       @37,304 93x93
 *         标题                         @140,315.5 128x24  16px/700
 *         「开始时间：10/14 18:00」     @140,347.5 128x19  13px
 *         「结束时间：10/14 18:30」     @140,366.5 128x19  13px
 *         右上角状态「合成」             @306,295 56x23  蓝色胶囊
 *     「精选商城」卡                   @14,437 362x189  bg #fff r12
 *       task-title-2.png               @24,453 75x21
 *       三个 shop-item                 各 93x121，间距 25.5
 *         @30,489 / @148.5,489 / @267,489
 *         图 93x93 → 文案 @y=592 93x18 12px → GO 胶囊 @x+57,y594.5 31x13 11px
 *   u-tabbar（底部）                   fixed @0,782 390x62
 *
 * 接口（cmp1 实测）：
 *   POST /index/affichetypelist  —— 入口配置（本页静态四宫格，原站为背景图）
 *   POST /index/afficheList       —— 公告（本页未用到）
 *   活动列表来自活动接口，字段：title / start_time / end_time / img / type
 */
import { ref, onMounted } from 'vue'
import TabBar from '@/components/TabBar.vue'

/* 四宫格入口：整块为原站切图（@172x64），此处按原样引用 */
const entries = [
  { key: 'compose', img: '/static/icon/t-compose.png', path: '/pages/compound/compound', name: '合成兑换' },
  { key: 'gold',    img: '/static/icon/t-gold.png',    path: '/pages/rank/masterRank',  name: '名人榜' },
  { key: 'draw',    img: '/static/icon/t-draw.png',    path: '/pages/activity/draw',    name: '幸运抽签' },
  { key: 'class',   img: '/static/icon/t-class.png',   path: '/pages/invitation/invitation', name: '邀请好礼' }
]

/* 「最新活动」轮播：原站 swiper 一屏 334 宽，卡片间距 9（第二张 x=362） */
const activities = ref([
  {
    title: '飞黄腾达分解活动',
    start: '开始时间：10/14 18:00',
    end: '结束时间：10/14 18:30',
    status: '合成',
    img: '/static/oss/uploads/20261014/896f22fa517fa48996e7ac86631219af.webp'
  },
  {
    title: '丈八蛇矛合成',
    start: '开始时间：10/14 20:00',
    end: '结束时间：10/14 21:00',
    status: '合成',
    img: '/static/oss/uploads/20261014/a4a8c5a770e9993c3ea0518d4666d930.jpg'
  }
])

/* 「精选商城」三分类（原站 shop-3 / shop-1 / shop-2 顺序） */
const shops = ref([
  { name: '严选服装', img: '/static/icon/shop-3.png' },
  { name: '潮酷数码', img: '/static/icon/shop-1.png' },
  { name: '潮流玩物', img: '/static/icon/shop-2.png' }
])

/* swiper 当前索引（触摸左右滑动切换，步进 343） */
const activeIdx = ref(0)
const startX = ref(0)
const swiperEl = ref(null)

function onTouchStart (e) {
  const t = e.touches ? e.touches[0] : e
  startX.value = t.clientX
}

function onTouchEnd (e) {
  const t = e.changedTouches ? e.changedTouches[0] : e
  const dx = t.clientX - startX.value
  if (Math.abs(dx) < 30) return
  let next = activeIdx.value
  if (dx < 0) next = Math.min(activities.value.length - 1, next + 1)
  else next = Math.max(0, next - 1)
  activeIdx.value = next
}

/* 原站为原生swiper，手势/滚轮均可切页；此处同时支持滚轮 */
function onWheel (e) {
  if (Math.abs(e.deltaX) < 18 && Math.abs(e.deltaY) < 18) return
  const goNext = (e.deltaX || e.deltaY) > 0
  let next = activeIdx.value
  if (goNext) next = Math.min(activities.value.length - 1, next + 1)
  else next = Math.max(0, next - 1)
  activeIdx.value = next
}

onMounted(() => {
  /* 原站本页入口与精选分类均为静态切图，无需额外请求 */
})
</script>

<template>
  <div class="task-page">
    <!-- 标题 @14,31 362x26 -->
    <div class="page-title">活动中心</div>

    <!-- 四宫格入口 @14,81 362x148 -->
    <div class="entries">
      <div
        v-for="e in entries"
        :key="e.key"
        class="entry"
        :style="{ backgroundImage: `url(${e.img})` }"
        @click="$router.push(e.path)"
      ></div>
    </div>

    <!-- 最新活动卡 @14,243 362x180 -->
    <div class="card act-card">
      <img class="card-tag" src="/static/icon/task-title-1.png" alt="最新活动" />
      <div
        ref="swiperEl"
        class="swiper"
        @touchstart.passive="onTouchStart"
        @touchend.passive="onTouchEnd"
        @wheel.prevent="onWheel"
      >
        <div class="swiper-track" :style="{ transform: `translateX(${-activeIdx * 334}px)` }">
          <div v-for="(a, i) in activities" :key="i" class="act">
            <img class="act-img" :src="a.img" :alt="a.title" />
            <div class="act-info">
              <div class="act-title">{{ a.title }}</div>
              <div class="act-time">{{ a.start }}</div>
              <div class="act-time">{{ a.end }}</div>
            </div>
            <div class="act-status">{{ a.status }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 精选商城卡 @14,437 362x189 -->
    <div class="card shop-card">
      <img class="card-tag" src="/static/icon/task-title-2.png" alt="精选商城" />
      <div class="shop-list">
        <div v-for="(s, i) in shops" :key="i" class="shop-item">
          <img class="shop-img" :src="s.img" :alt="s.name" />
          <div class="shop-name">
            {{ s.name }}
            <span class="go">GO</span>
          </div>
        </div>
      </div>
    </div>

    <TabBar active="none" />
  </div>
</template>

<style scoped>
.task-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  min-height: 844px;
  background: rgb(250, 250, 250);
  color: rgb(74, 74, 74);
  font-family: 'Times New Roman', serif;
  font-size: 15px;
  overflow: hidden;
}

/* 标题 @14,31 362x26 18px/700 居中 —— 原站 .font-size-18.font-color-4a4a4a.font-weight-700 */
.page-title {
  position: absolute;
  left: 14px;
  top: 31px;
  width: 362px;
  height: 26px;
  line-height: 26px;
  text-align: center;
  font-size: 18px;
  font-weight: 700;
  color: rgb(74, 74, 74);
}

/* 四宫格 @14,81 362x148（justify-content: space-between，每项 172x64，mb-10） */
.entries {
  position: absolute;
  left: 14px;
  top: 81px;
  width: 362px;
  height: 148px;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-content: flex-start;
  border-radius: 12px;
  box-sizing: border-box;
}
.entry {
  width: 172px;
  height: 64px;
  margin-bottom: 10px;
  background-repeat: no-repeat;
  background-position: center;
  background-size: 100% 100%;
  cursor: pointer;
}

/* 卡片通用：bg-color-item + br-12 */
.card {
  position: absolute;
  left: 14px;
  width: 362px;
  background: #fff;
  border-radius: 12px;
  box-sizing: border-box;
}
/* 最新活动 @14,243 362x180（padding 16px 14px） */
.act-card {
  top: 243px;
  height: 180px;
  padding: 16px 14px;
}
/* 精选商城 @14,437 362x189（padding 16px 10px，mt-10） */
.shop-card {
  top: 437px;
  height: 189px;
  padding: 16px 10px;
}

/* 角标切图 75x21（mb-10） */
.card-tag {
  display: block;
  width: 75px;
  height: 21px;
  margin-bottom: 15px;
}

/* swiper @28,295 334x112，容器 bg rgb(247,248,250) br10 */
.swiper {
  position: relative;
  width: 334px;
  height: 112px;
  overflow: hidden;
  border-radius: 10px;
  background: rgb(247, 248, 250);
  box-sizing: border-box;
}
.swiper-track {
  display: flex;
  width: 334px;
  height: 112px;
  transition: transform 0.3s ease;
}
/* 单屏 334 宽，步进 334（原站第二张 x=362，父 28 + 334 = 362） */
.act {
  position: relative;
  flex: 0 0 334px;
  width: 334px;
  height: 111px;
  display: flex;
  align-items: center;
  padding: 9px;
  box-sizing: border-box;
}
/* 活动图 93x93 br10，占位底色 rgb(112,112,112)，原图等比拉伸铺满 */
.act-img {
  width: 93px;
  height: 93px;
  flex-shrink: 0;
  border-radius: 10px;
  background: rgb(112, 112, 112);
  object-fit: fill;
}
/* 文案区 ml-10 128x70 */
.act-info {
  width: 128px;
  margin-left: 10px;
  overflow: hidden;
}
.act-title {
  height: 24px;
  margin-bottom: 8px;
  font-size: 16px;
  font-weight: 700;
  line-height: 24px;
  color: rgb(74, 74, 74);
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.act-time {
  height: 19px;
  font-size: 13px;
  font-weight: 500;
  line-height: 19px;
  color: rgb(75, 75, 75);
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
/* 状态胶囊 @306,295 56x23 —— 原站为 base64 内联背景图，右上角 */
.act-status {
  position: absolute;
  right: 0;
  top: 0;
  width: 56px;
  height: 23px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #fff;
  background: url('/static/icon/task-status.png') no-repeat center / 100% 100%;
}

/* 精选商城列表 @24,489 342x121（padding 0 6px，space-between） */
.shop-list {
  display: flex;
  justify-content: space-between;
  width: 342px;
  height: 121px;
  padding: 0 6px;
  box-sizing: border-box;
}
.shop-item {
  width: 93px;
  height: 121px;
  display: flex;
  flex-direction: column;
}
.shop-img {
  width: 93px;
  height: 93px;
  flex-shrink: 0;
  object-fit: fill;
}
/* 文案行 @y=592 93x18 12px/500；GO 胶囊 31x13 11px bg rgb(255,28,28) br6 */
.shop-name {
  margin-top: 10px;
  width: 93px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 500;
  color: rgb(74, 74, 74);
  white-space: nowrap;
}
.go {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-left: 4px;
  width: 31px;
  height: 13px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 500;
  color: #fff;
  background: rgb(255, 28, 28);
  flex-shrink: 0;
}
</style>
