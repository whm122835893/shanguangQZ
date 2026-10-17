<script setup>
/**
 * 中签记录 —— 1:1 复刻原站 pages/activity/record
 *
 * 路由：#/pages/activity/record
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg-colorfull.plr-16 @0,0 390x844
 *     bg rgb(255,255,255)  padding 0 16px
 *     .u-navbar--fixed       @0,0 390x44  bg 白
 *       back-btn.png         @13,10 24x24
 *       标题「中签记录」
 *     占位空卡「 br-12 pa-16」 @16,44 358x32（白底不可见）
 *     空态                    @16,284 358x100  mt-208
 *       search-empty.png     @134.5,284 121x100
 */
</script>

<template>
  <div class="activity-record">
    <div class="navbar">
      <div class="nav-back" @click="$router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">中签记录</div>
    </div>

    <!-- 占位卡 @16,44 358x32 -->
    <div class="slot"></div>

    <!-- 空态 @16,284 -->
    <div class="empty">
      <img src="/static/empty/search-empty.png" alt="暂无数据" />
    </div>
  </div>
</template>

<style scoped>
.activity-record {
  position: relative;
  width: 390px;
  min-height: 844px;
  padding: 0 16px;
  box-sizing: border-box;
  background: #fff;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* 导航 @0,0 390x44（白底） */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  margin: 0 -16px;
  background: #fff;
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
  top: 0;
  width: 390px;
  height: 44px;
  font-size: 15px;
  font-weight: 400;
  color: rgb(51, 51, 51);
  line-height: 44px;
  text-align: center;
}

/* 占位卡 @16,44 358x32（白底不可见，仅占位） */
.slot {
  width: 358px;
  height: 32px;
}

/* 空态 @16,284 mt-208 */
.empty {
  width: 358px;
  height: 100px;
  margin-top: 208px;
  display: flex;
  justify-content: center;
}
.empty img {
  width: 121px;
  height: 100px;
}
</style>