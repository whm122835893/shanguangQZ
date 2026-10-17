<script setup>
/**
 * 返佣排行榜 —— 1:1 复刻原站 pages/rebate/leaderboard
 *
 * 路由：#/pages/rebate/leaderboard
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg.pa-16.pb-16 @0,0 390x844  bg rgb(250,250,250)  padding 16px
 *     .u-navbar--fixed       @0,0 390x44  bg 透明
 *       back-btn.png         @13,10 24x24
 *       标题（居中 15px）
 *     .u-tabs                @16,60 358x22
 *       nav__item             每项 134x22  @16 / @150 / @284
 *         item__text          104x22  15px/400 rgb(65,65,65)  文字不随选中变色
 *       nav__line             @74,77 18x3  /static/bg/vector.png
 *     表头.item               @16,82 358x41  color rgb(153,153,153)  padding 0 16px
 *       .ranking  「排名」@32,91.5   65.2x22
 *       .niceName 「用户」@97.2,91.5 97.8x22
 *       .price   「返佣金额」@195,91.5 163x22（右对齐）
 *     空态                     @16,227 358x100  mt-104
 *       search-empty.png     @134.5,227 121x100
 */
import { ref } from 'vue'

const tabs = ['日排行', '周排行', '月排行']
const active = ref(0)
</script>

<template>
  <div class="rebate-board">
    <div class="navbar">
      <div class="nav-back" @click="$router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
    </div>

    <!-- tab @16,60 -->
    <div class="tabs">
      <div
        v-for="(t, i) in tabs"
        :key="t"
        class="tab"
        @click="active = i"
      >{{ t }}</div>
      <div class="tab-line" :style="{ transform: `translateX(${active * 134}px)` }"></div>
    </div>

    <!-- 表头 @16,82 -->
    <div class="thead">
      <span class="c-ranking">排名</span>
      <span class="c-user">用户</span>
      <span class="c-price">返佣金额</span>
    </div>

    <!-- 空态 @16,227 -->
    <div class="empty">
      <img src="/static/empty/search-empty.png" alt="暂无数据" />
    </div>
  </div>
</template>

<style scoped>
.rebate-board {
  position: relative;
  width: 390px;
  min-height: 844px;
  padding: 16px 16px 16px;
  box-sizing: border-box;
  background: rgb(250, 250, 250);
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* 导航 fixed 占位 @0,16 358x44（container padding-top 16） */
.navbar {
  position: relative;
  width: 358px;
  height: 44px;
  margin: 0 -16px;
  padding-left: 16px;
  box-sizing: content-box;
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

/* tab @16,60 358x22 每项 134 */
.tabs {
  position: relative;
  width: 358px;
  height: 22px;
  display: flex;
  margin-top: 0;
  overflow: hidden;
}
.tab {
  width: 134px;
  height: 22px;
  flex: none;
  font-size: 15px;
  font-weight: 400;
  color: rgb(65, 65, 65);
  line-height: 22px;
  text-align: center;
  cursor: pointer;
}
.tab-line {
  position: absolute;
  left: 58px;
  top: 17px;
  width: 18px;
  height: 3px;
  background: url('/static/bg/vector.png') 100% 100% / cover no-repeat;
  transition: transform 0.2s;
}

/* 表头 @16,82 358x41 */
.thead {
  width: 358px;
  height: 41px;
  margin-top: 0;
  padding: 0 16px;
  display: flex;
  align-items: center;
  box-sizing: border-box;
  color: rgb(153, 153, 153);
  font-size: 15px;
}
.c-ranking {
  width: 65.2px;
  flex: none;
}
.c-user {
  width: 97.8px;
  flex: none;
}
.c-price {
  flex: 1;
  text-align: right;
}

/* 空态 @16,227 mt-104 */
.empty {
  width: 358px;
  height: 100px;
  margin-top: 104px;
  display: flex;
  justify-content: center;
}
.empty img {
  width: 121px;
  height: 100px;
}
</style>