<script setup>
/**
 * 买入清单 —— 1:1 复刻原站pages/rank/userBuy
 *
 * 路由：#/pages/rank/userBuy
 *
 * 原站 390x844 实测：
 *   .container.page-container.rank-user.plr-13 @0,0 390x844
 *     bg rgb(246,246,246) + /static/image/leaderboard/qd-bg.png (size 390px, pos 0 0)
 *     padding 0 13px
 *     .u-navbar--fixed                @0,0 390x44  bg 透明
 *       back-btn2.png（蓝色返回）      @13,10 24x24
 *       标题「买入清单」                span 居中 15px/400
 *     .list (uni-scroll-view)         @13,77 364x740  bg #fff  br12  mt-33
 *       空态：/static/empty/search-empty.png 121x100 居中 @134.5,~215
 *
 * 说明：本账号无买入记录，原站为空态。
 */
</script>

<template>
  <div class="user-buy">
    <div class="navbar">
      <div class="nav-back" @click="$router.back()">
        <img src="/static/icon/back-btn2.png" alt="返回" />
      </div>
      <div class="nav-title">买入清单</div>
    </div>

    <div class="list">
      <div class="empty">
        <img src="/static/empty/search-empty.png" alt="暂无数据" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.user-buy {
  position: relative;
  width: 390px;
  min-height: 844px;
  padding: 0 13px;
  box-sizing: border-box;
  background: rgb(246, 246, 246) url('/static/image/leaderboard/qd-bg.png') 0 0 / 390px auto no-repeat;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* 导航 @0,0 390x44（透明，压在背景图上） */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  margin: 0 -13px;
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

/* 列表卡 @13,77 364x740 bg #fff br12 */
.list {
  position: relative;
  width: 364px;
  height: 740px;
  margin-top: 33px;
  background: #fff;
  border-radius: 12px;
  box-sizing: border-box;
  overflow: hidden;
}
.empty {
  position: absolute;
  left: 0;
  top: 138px;
  width: 364px;
  height: 100px;
  display: flex;
  justify-content: center;
}
.empty img {
  width: 121px;
  height: 100px;
}
</style>