<script setup>
/**
 * 被邀请人清单 —— 1:1 复刻原站 pages/rebate/rebateInviteDetail
 *
 * 路由：#/pages/rebate/rebateInviteDetail
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg.plr-5.pt-16.pb-16 @0,0 390x844
 *     bg rgb(250,250,250)  padding 16px 5px  font-size 8px  color rgb(51,51,51)
 *     .u-navbar--fixed@0,0 390x44  back-btn.png @13,10 24x24  标题「被邀请人清单」
 *     表头.flex.flex-ai-center  @5,60 380x48
 *       .nickName.height.border         @5,60     34.6x48   「昵称」
 *       .join_time.height.border        @39.6,60  69.1x48「加入时间」
 *       .index_pay_time.height.border   @108.8,60 86.3x48  「首次交易日期」
 *       .all_pay_price.height.border    @195.1,60 86.3x48  「总交易金额」
 *       .month_pay_price.height.border  @281.4,60 103.6x48「本自然月交易金额」
 *     空态                             @5,212 380x100  mt-104
 *       search-empty.png               @134.5,212 121x100
 *
 * 说明：本账号无邀请记录，原站为空态。
 */
const COLS = [
  { key: 'nickName', label: '昵称', w: 34.6 },
  { key: 'join_time', label: '加入时间', w: 69.1 },
  { key: 'index_pay_time', label: '首次交易日期', w: 86.3 },
  { key: 'all_pay_price', label: '总交易金额', w: 86.3 },
  { key: 'month_pay_price', label: '本自然月交易金额', w: 103.6 }
]
</script>

<template>
  <div class="invite-detail">
    <div class="navbar">
      <div class="nav-back" @click="$router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">被邀请人清单</div>
    </div>

    <!-- 表头 @5,60 380x48 -->
    <div class="thead">
      <div v-for="c in COLS" :key="c.key" class="th" :style="{ width: c.w + 'px' }">
        {{ c.label }}
      </div>
    </div>

    <!-- 空态 @5,212 -->
    <div class="empty">
      <img src="/static/empty/search-empty.png" alt="暂无数据" />
    </div>
  </div>
</template>

<style scoped>
.invite-detail {
  position: relative;
  width: 390px;
  min-height: 844px;
  padding: 16px 5px;
  box-sizing: border-box;
  background: rgb(250, 250, 250);
  color: rgb(51, 51, 51);
  font-size: 8px;
}

/* 导航 @0,16 380x44（container padding-top 16，左右 5 内缩） */
.navbar {
  position: relative;
  width: 380px;
  height: 44px;
}
.nav-back {
  position: absolute;
  left: 8px;
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
  width: 380px;
  height: 44px;
  font-size: 15px;
  font-weight: 400;
  color: rgb(51, 51, 51);
  line-height: 44px;
  text-align: center;
}

/* 表头 @5,60 380x48，五列 flex-none 居中 */
.thead {
  width: 380px;
  height: 48px;
  display: flex;
  align-items: center;
}
.th {
  flex: none;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 8px;
  color: rgb(51, 51, 51);
}

/* 空态 @5,212 mt-104 */
.empty {
  width: 380px;
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