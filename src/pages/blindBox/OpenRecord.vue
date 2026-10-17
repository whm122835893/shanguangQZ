<script setup>
/**
 * 开盒记录 —— 1:1 复刻原站 pages/blindBox/openRecord
 *
 * 路由：#/pages/blindBox/openRecord
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg.flex.flex-dir-col @0,0 390x844  bg rgb(250,250,250)
 *     .u-navbar--fixed        @0,0 390x44  bg rgb(250,250,250)
 *       back-btn.png          @13,10 24x24
 *       标题「开盒记录」
 *     .flex.flex-dir-col.flex-ai-center  @0,60 390x100  mt-16
 *       search-empty.png      @134.5,60 121x100
 */
</script>

<template>
  <div class="open-record">
    <div class="navbar">
      <div class="nav-back" @click="$router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">开盒记录</div>
    </div>

    <div class="empty-wrap">
      <img src="/static/empty/search-empty.png" alt="暂无数据" />
    </div>
  </div>
</template>

<style scoped>
.open-record {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: rgb(250, 250, 250);
  box-sizing: border-box;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* 导航 @0,0 390x44 */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  background: rgb(250, 250, 250);
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

/* 空态 @0,60 390x100（mt-16） */
.empty-wrap {
  width: 390px;
  height: 100px;
  margin-top: 16px;
  display: flex;
  justify-content: center;
}
.empty-wrap img {
  width: 121px;
  height: 100px;
}
</style>