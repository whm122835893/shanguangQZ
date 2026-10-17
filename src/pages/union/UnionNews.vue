<script setup>
/**
 * 消息通知 —— 1:1 复刻原站 pages/union/news
 *
 * 路由：#/pages/union/news
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg @0,0 390x844
 *     bg /static/icon/gh/rank-bg.png（size 100% 100%）
 *     navbar 占位@0,36 390x44（上方留 36px）
 *     .u-navbar--fixed       @0,0 390x44  bg 透明
 *       back-btn-white.png   @13,10 24x24
 *       标题「消息通知」白色
 *     .list-view.plr-16.mt-24 @0,104 390x0  mt-24（无消息，高度 0）
 *
 * 接口：POST /api/union/user/message -> 本账号暂无消息
 */
</script>

<template>
  <div class="union-news">
    <div class="top-gap"></div>
    <div class="navbar">
      <div class="nav-back" @click="$router.back()">
        <svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true">
          <path
            d="M15 4 L7 12 L15 20"
            fill="none"
            stroke="rgb(255, 255, 255)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
      <div class="nav-title">消息通知</div>
    </div>

    <div class="list-view"></div>
  </div>
</template>

<style scoped>
.union-news {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #000 url('/static/icon/gh/rank-bg.png') 0 0 / 100% 100% no-repeat;
  color: rgb(255, 255, 255);
  font-size: 15px;
}

/* 顶部留白 36px */
.top-gap {
  width: 390px;
  height: 36px;
}

/* 导航占位 @0,36 390x44 */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
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
  color: #fff;
  line-height: 44px;
  text-align: center;
}

/* 列表 @0,104（mt-24，无数据高度 0） */
.list-view {
  width: 390px;
  height: 0;
  margin-top: 24px;
  padding: 0 16px;
  box-sizing: border-box;
}
</style>