<script setup>
/**
 * 工会成员排名 —— 1:1 复刻原站 pages/union/userRank
 *
 * 路由：#/pages/union/userRank
 *
 * 原站 390x844 实测：
 *   .container.page-container @0,0 390x844
 *     bg /static/icon/gh/rank-bg.png（size 100% 100%）
 *     navbar 占位 @0,36 390x44（上方留 36px）
 *     .u-navbar--fixed         @0,0 390x44  bg 透明
 *       back-btn-white.png     @13,10 24x24
 *       标题「工会成员排名」白 15px/700
 *     .list-view.plr-16       @0,80 390x196
 *       .l-head.plr-16.flex    @16,80 358x18  12px/500 #fff
 *         .number  「排名」  @32,80   62x18
 *         .people  「成员」  @94,80   24x18
 *         .name    「荣誉值」@118,80 240x18（右对齐 flex-js-end）
 *         右侧 q 图标 12x12 @346,83
 *       .l-item.card-bg-1      @16,112 356x75  br7 mt-14（2 条，卡片背景 rgba(0,0,0,0) 不可见）
 *       .l-item                @16,201 356x75
 *
 * 接口：POST /api/union/user/logs -> 会员卡片内容为空（背景透明），按可见结果复刻
 */
</script>

<template>
  <div class="user-rank">
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
      <div class="nav-title">工会成员排名</div>
    </div>

    <div class="list-view">
      <!-- 表头 @16,80 358x18 -->
      <div class="l-head">
        <span class="number">排名</span>
        <span class="people">成员</span>
        <span class="name">荣誉值</span>
        <img class="q" src="/static/icon/gh/q.png" alt="说明" />
      </div>
      <!-- 两条会员卡片 @16,112 / @16,201 356x75（背景透明） -->
      <div class="l-item"></div>
      <div class="l-item"></div>
    </div>
  </div>
</template>

<style scoped>
.user-rank {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #000 url('/static/icon/gh/rank-bg.png') 0 0 / 100% 100% no-repeat;
  color: #fff;
  font-size: 15px;
}

/* 顶部留白 36px */
.top-gap {
  width: 390px;
  height: 36px;
}

/* 导航占位 @0,36 */
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
  font-weight: 700;
  color: #fff;
  line-height: 44px;
  text-align: center;
}

/* 列表 @0,80 */
.list-view {
  width: 390px;
  padding: 0 16px;
  box-sizing: border-box;
}

/* 表头 @16,80 358x18 */
.l-head {
  position: relative;
  width: 358px;
  height: 18px;
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 500;
  color: #fff;
}
.number {
  width: 62px;
  flex: none;
}
.people {
  width: 24px;
  flex: none;
}
.name {
  flex: 1;
  text-align: right;
}
.q {
  position: absolute;
  right: 0;
  top: 3px;
  width: 12px;
  height: 12px;
}

/* 会员卡片 @16,112 / @16,201 356x75 br7 mt-14（背景透明不可见） */
.l-item {
  width: 356px;
  height: 75px;
  margin-top: 14px;
  border-radius: 7px;
  box-sizing: border-box;
}
</style>