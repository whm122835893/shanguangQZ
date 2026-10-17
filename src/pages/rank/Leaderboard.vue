<script setup>
/**
 * 光子荣誉榜（魅力榜）—— 1:1 复刻原站 pages/leaderboard/leaderboard
 *
 * 路由：#/pages/leaderboard/leaderboard
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg @0,0 390x844
 *     bg rgb(243,243,243) + /static/image/leaderboard/bg.png（size 100%，pos 0 0）
 *     .u-navbar--fixed（透明）   @0,0 390x44
 *       返回 back-btn-gray.png   @13,13 19x19
 *     顶部标题图区               @0,10 390x228（背景图内自带「光子荣誉榜」艺术字）
 *     .user-list                @18,383 354x201
 *       .pdsx-num               @18,383 354x63  padding-top 26
 *         「榜单每10分钟刷新」   @121,409 149x37
 *           bg /static/image/leaderboard/sx-time-bg.png（size 100%）
 *           13px/700 #fff
 *       .head-title             @18,446 354x18  padding 0 18px
 *         .user-paiming「排名」  @36,446 55x18  12px/700 rgb(70,66,76)
 *         .user-info「用户」     @91,446 202x18
 *         .user-value            @293,446 61x18
 *           「魅力值」           @303,449 36x14  12px/700 rgb(70,66,76)
 *           bangzhu.png          @342,448 12x12
 *       空态                     @18,484 354x100
 *         search-empty.png       @135,484 121x100
 *     .bottom-ph-bg              @0,761 390x83  bg rgb(75,148,255)
 *       .my-user-info           @29,782 56x41
 *         头像（默认头像 base64）@29,783 39x39  圆形
 *         昵称「--」           @75,782 10x17  15px/700 #fff
 *         魅力值「0」           @75,807 10x16  13px/700 #fff
 *       「暂未上榜」             @309,793 52x19  13px/700 #fff
 *
 * 数据说明：账号当前魅力值 0、未上榜，故榜单为���态、底栏显示「暂未上榜」。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* 榜单数据：原站接口当前返回空 */
const rankList = ref([])
/* 我的排名信息：未上榜时为默认值 */
const myRank = ref({ nickname: '--', value: '0', ranked: false })

const switchTab = () => {
  /* 原站在此切换榜单类型 */
}
</script>

<template>
  <div class="leaderboard">
    <!-- 导航 @0,0 390x44（透明） -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn-gray.png" alt="返回" />
      </div>
    </div>

    <!-- 榜单区@18,383 -->
    <div class="user-list">
      <!-- 刷新提示 @121,409 -->
      <div class="refresh-tip">榜单每10分钟刷新</div>

      <!-- 表头 @18,446 -->
      <div class="head-title">
        <span class="col-paiming">排名</span>
        <span class="col-info">用户</span>
        <span class="col-value">魅力值
          <img class="bangzhu" src="/static/image/leaderboard/bangzhu.png" alt="" />
        </span>
      </div>

      <!-- 空态 @18,484 -->
      <div v-if="rankList.length === 0" class="empty">
        <img src="/static/empty/search-empty.png" alt="暂无榜单" />
      </div>
    </div>

    <!-- 底栏 @0,761 390x83 -->
    <div class="bottom-bar">
      <div class="my-info">
        <div class="avatar"></div>
        <div class="my-text">
          <div class="my-nickname">{{ myRank.nickname }}</div>
          <div class="my-value">{{ myRank.value }}</div>
        </div>
      </div>
      <div class="my-state">{{ myRank.ranked ? myRank.ranking : '暂未上榜' }}</div>
    </div>
  </div>
</template>

<style scoped>
.leaderboard {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: rgb(243, 243, 243) url('/static/image/leaderboard/bg.png') no-repeat 0 0;
  background-size: 100%;
  overflow: hidden;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* ---------- 导航 @0,0 390x44（透明） ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 13px;
  width: 19px;
  height: 19px;
  cursor: pointer;
}
.nav-back img {
  display: block;
  width: 19px;
  height: 19px;
}

/* ---------- 榜单区 ---------- */
.user-list {
  position: absolute;
  left: 18px;
  top: 383px;
  width: 354px;
  height: 201px;
}
/* 刷新提示 @121,409 149x37 */
.refresh-tip {
  width: 149px;
  height: 37px;
  margin: 26px auto 0;
  background: url('/static/image/leaderboard/sx-time-bg.png') no-repeat center center;
  background-size: 100% 100%;
  font-size: 13px;
  font-weight: 700;
  color: #fff;
  line-height: 37px;
  text-align: center;
  box-sizing: border-box;
}
/* 表头 @18,446 354x18 */
.head-title {
  display: flex;
  align-items: center;
  width: 354px;
  height: 18px;
  padding: 0 18px;
  box-sizing: border-box;
  font-size: 12px;
  font-weight: 700;
  color: rgb(70, 66, 76);
}
.col-paiming {
  width: 55px;
}
.col-info {
  width: 202px;
}
.col-value {
  display: flex;
  align-items: center;
  width: 61px;
}
.bangzhu {
  display: block;
  width: 12px;
  height: 12px;
  margin-left: 3px;
}
/* 空态 @18,484 354x100 */
.empty {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 354px;
  height: 100px;
}
.empty img {
  display: block;
  width: 121px;
  height: 100px;
}

/* ---------- 底栏 @0,761 390x83 ---------- */
.bottom-bar {
  position: absolute;
  left: 0;
  top: 761px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 390px;
  height: 83px;
  padding: 0 29px;
  background: rgb(75, 148, 255);
  box-sizing: border-box;
}
.my-info {
  display: flex;
  align-items: center;
  width: 56px;
  height: 41px;
}
/* 头像 @29,783 39x39 —— 默认灰底 */
.avatar {
  width: 39px;
  height: 39px;
  border-radius: 50%;
  background: #d5d5d5;
  flex: 0 0 39px;
}
.my-text {
  margin-left: 7px;
}
.my-nickname {
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  line-height: 17px;
  white-space: nowrap;
}
.my-value {
  font-size: 13px;
  font-weight: 700;
  color: #fff;
  line-height: 16px;
}
/* 「暂未上榜」@309,793 */
.my-state {
  width: 52px;
  font-size: 13px;
  font-weight: 700;
  color: #fff;
  line-height: 19px;
  text-align: center;
  white-space: nowrap;
}
</style>