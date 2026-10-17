<script setup>
/**
 * 成交记录 —— 1:1 复刻原站 pages/market/tradingInformation
 *
 * 原站实测：
 *   .container 390x844  bg rgb(245,245,245) + bg/page-bg.png背景
 *   返回 back-btn-white.png @13,10 24x24（浅色底故用白色箭头）
 *   标题「成交记录」@165,16 60x16  15px/700 rgb(255,255,255) ta:center
 *
 *   「CNY」@71.9,99 41.2x22  19px/900 rgb(0,0,0)
 *   今日最高     @145.1,61 55x16  11px/500 rgb(116,116,116) ta:center
 *   ￥0         @145.1,84 55x19  13px/700 rgb(101,201,24)  ta:center（绿色）
 *   今日成交量   @145.1,121 55x16 11px/500 rgb(116,116,116) ta:center
 *   0           @145.1,143 55x16  13px/700 rgb(10,10,10)     ta:center
 *   今日地板价   @263.1,61 55x16  11px/500 rgb(116,116,116)
 *   ￥0         @263.1,84 55x19  13px/700 rgb(10,10,10)
 *   今日成交量   @263.1,121 55x16
 *   0           @263.1,143 55x16
 *
 *   Tab「当前成交」@71.5,198.5 52x19  13px/700 rgb(0,0,0)     （选中态）
 *   Tab「当前寄售」@266.5,198.5 52x19 13px/500 rgb(153,153,153)（未选中）
 *
 *   空态 search-empty.png @134.5,277 121x100
 *
 * 注：原站当前无藏品上架（getMarketList 返回 data:[]），
 *     故统计全为0、列表为空态。CNY 标签由接口返回原币种，按实测照抄。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* Tab：当前成交 / 当前寄售 */
const activeTab = ref('trade')
</script>

<template>
  <div class="trading-page">
    <!-- 导航：浅色底用白色返回箭头 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn-white.png" alt="返回" @click="router.back()" />
      <div class="nav-title">成交记录</div>
    </div>

    <!-- 统计白卡@16.5,52 356x121.5 圆角10（实测） -->
    <div class="stat-card">
      <!-- 币种标签@71.9,99 -->
      <div class="currency">CNY</div>

      <!-- 左列：今日最高@145.1,61/84 今日成交量@145.1,121/143 -->
      <div class="stat-col stat-left">
        <div class="stat-title">今日最高</div>
        <div class="stat-value stat-green">￥0</div>
        <div class="stat-title stat-title-2">今日成交量</div>
        <div class="stat-value stat-dark">0</div>
      </div>

      <!-- 右列：今日地板价@263.1,61/84 今日成交量@263.1,121/143 -->
      <div class="stat-col stat-right">
        <div class="stat-title">今日地板价</div>
        <div class="stat-value stat-dark">￥0</div>
        <div class="stat-title stat-title-2">今日成交量</div>
        <div class="stat-value stat-dark">0</div>
      </div>
    </div>

    <!-- Tab：当前成交@71.5,198.5 / 当前寄售@266.5,198.5 -->
    <div class="tabs">
      <div class="tab" :class="{ active: activeTab === 'trade' }" @click="activeTab = 'trade'">当前成交</div>
      <div class="tab tab-2" :class="{ active: activeTab === 'consign' }" @click="activeTab = 'consign'">当前寄售</div>
      <!-- 选中下划线@71.5,221 34x3黑 -->
      <div class="tab-line"></div>
    </div>

    <!-- 空态@134.5,277 121x100 -->
    <img class="empty-img" src="/static/empty/search-empty.png" alt="暂无记录" />
  </div>
</template>

<style scoped>
/* .container 390x844 bg rgb(245,245,245) + page-bg 背景 */
.trading-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: rgb(245, 245, 245) url('/static/bg/page-bg.png') no-repeat center top;
  background-size: 100% auto;
  overflow: hidden;
}

/* 导航：标题白字 */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #fff; font-size: 15px; font-weight: 700;
}

/* 统计白卡@16.5,52 356x121.5 圆角10（实测） */
.stat-card {
  position: absolute;
  left: 16.5px;
  top: 52px;
  width: 356px;
  height: 121.5px;
  background: #fff;
  border-radius: 10px;
}

/* 币种「CNY」@71.9,99 41.2x22  19px/900 */
.currency {
  position: absolute;
  left: 71.9px;
  top: 99px;
  width: 60px;
  height: 22px;
  color: #000;
  font-size: 19px;
  font-weight: 900;
  line-height: 22px;
}

/* 统计列：宽55，居中对齐 */
.stat-col {
  position: absolute;
  top: 9px;              /* 52+9=61 */
  width: 55px;
  text-align: center;
}
.stat-left { left: 128.6px; }   /* 16.5+128.6=145.1 */
.stat-right { left: 246.6px; }  /* 16.5+246.6=263.1 */

/* 小标题 11px/500 rgb(116,116,116) */
.stat-title {
  height: 16px;
  color: rgb(116, 116, 116);
  font-size: 11px;
  font-weight: 500;
  line-height: 16px;
  white-space: nowrap;
}
/* 第二个小标题下移至@121（84+19+18≈121） */
.stat-title-2 { margin-top: 18px; }

/* 数值 13px/700 */
.stat-value {
  margin-top: 7px;
  height: 19px;
  font-size: 13px;
  font-weight: 700;
  line-height: 19px;
}
/* 今日最高为绿色 rgb(101,201,24) */
.stat-green { color: rgb(101, 201, 24); margin-top: 7px; }
/* 其余深色 rgb(10,10,10) */
.stat-dark { color: rgb(10, 10, 10); }

/* Tab：当前成交@71.5,198.5 当前寄售@266.5,198.5 */
.tabs { position: absolute; left: 0; top: 198.5px; width: 390px; height: 19px; }
.tab {
  position: absolute;
  left: 71.5px;
  width: 52px;
  height: 19px;
  color: rgb(153, 153, 153);
  font-size: 13px;
  font-weight: 500;
  line-height: 19px;
  cursor: pointer;
}
.tab-2 { left: 266.5px; }
/* 选中态 700 rgb(0,0,0) */
.tab.active { color: #000; font-weight: 700; }

/* 选中下划线@71.5,221 34x3 黑（实测） */
.tab-line {
  position: absolute;
  left: 71.5px;
  top: 22.5px;           /* 198.5+22.5=221 */
  width: 34px;
  height: 3px;
  background: #000;
  border-radius: 1.5px;
}

/* 空态@134.5,277 121x100 */
.empty-img {
  position: absolute;
  left: 134.5px;
  top: 277px;
  width: 121px;
  height: 100px;
}
</style>