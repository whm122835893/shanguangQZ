<script setup>
/**
 * 转售记录 —— 1:1 复刻原站 pages/regift/record
 *
 * 路由：#/pages/regift/record
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg @0,0 390x844  bg rgb(250,250,250)
 *     .u-navbar--fixed        @0,0 390x44  bg rgb(250,250,250)
 *       返回 back-btn.png     @13,10 24x24
 *       标题「转售记录」      @165,16 60x16  15px/700 rgb(51,51,51)
 *     .u-sticky__content      @0,44 390x54  padding 16px 0
 *       .u-tabs              @61,60 268x22
 *         item0「我转售的」  @61,60 134x22
 *         item1「我收到的」  @195,60 134x22
 *         文案 15px/400 rgb(65,65,65)，居中于 104px 宽容器
 *         .nav__line         @119,77 18x3  bg /static/bg/vector.png  br 100px
 *     .list.plr-16           @0,98
 *       空态容器             @0,114 390x100
 *         search-empty.png   @134.5,114 121x100
 *
 * 交互实测：点击「我收到的」后指示线左移到 x=253（195+67-9=253），
 *          两项文案颜色一致（rgb(65,65,65)），仅指示线位置变化。
 *
 * 数据说明：账号当前「我转售的」「我收到的」均无记录，故两 tab 均为空态。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* 当前选中 tab：0 我转售的 / 1 我收到的 */
const active = ref(0)

/* 两 tab 数据列表：原站接口返回为空 */
const soldList = ref([])
const receivedList = ref([])

const switchTab = (i) => {
  active.value = i
}
</script>

<template>
  <div class="regift-record">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">转售记录</div>
    </div>

    <!-- tab 栏 @0,44 390x54 -->
    <div class="tabs-wrap">
      <div class="tabs">
        <div
          class="tab"
          :class="{ 'tab--active': active === 0 }"
          @click="switchTab(0)"
        >
          <div class="tab-text">我转售的</div>
        </div>
        <div
          class="tab"
          :class="{ 'tab--active': active === 1 }"
          @click="switchTab(1)"
        >
          <div class="tab-text">我收到的</div>
        </div>
        <!-- 指示线 @119,77 18x3 -->
        <div class="tab-line" :style="{ left: active === 0 ? '119px' : '253px' }"></div>
      </div>
    </div>

    <!-- 列表区 @0,98 -->
    <div class="list">
      <!-- 空态 @0,114 390x100 -->
      <div v-if="(active === 0 ? soldList : receivedList).length === 0" class="empty">
        <img src="/static/empty/search-empty.png" alt="暂无记录" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.regift-record {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: rgb(250, 250, 250);
  overflow: hidden;
}

/* ---------- 导航 @0,0 390x44 ---------- */
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
  left: 165px;
  top: 16px;
  width: 60px;
  height: 16px;
  font-size: 15px;
  font-weight: 700;
  color: rgb(51, 51, 51);
  line-height: 16px;
  text-align: center;
}

/* ---------- tab 栏 @0,44 390x54（padding 16px 0） ---------- */
.tabs-wrap {
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  height: 54px;
  padding: 16px 0;
  box-sizing: border-box;
}
.tabs {
  position: relative;
  margin: 0 auto;
  width: 268px;
  height: 22px;
  display: flex;
}
.tab {
  width: 134px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tab-text {
  width: 104px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 400;
  color: rgb(65, 65, 65);
  line-height: 22px;
  text-align: center;
}
/* 指示线 18x3，底图 /static/bg/vector.png */
.tab-line {
  position: absolute;
  top: 17px;
  width: 18px;
  height: 3px;
  border-radius: 100px;
  background: url('/static/bg/vector.png') no-repeat center center;
  background-size: cover;
}

/* ---------- 列表区 ---------- */
.list {
  position: absolute;
  left: 0;
  top: 98px;
  width: 390px;
  padding: 0 16px;
  box-sizing: border-box;
}
/* 空态 @0,114 390x100 */
.empty {
  width: 358px;
  height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.empty img {
  width: 121px;
  height: 100px;
}
</style>
