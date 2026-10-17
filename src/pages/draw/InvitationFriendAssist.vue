<script setup>
/**
 * 邀请好友助力 —— 1:1 复刻原站 pages/draw/InvitationFriendAssist/InvitationFriendAssist
 *
 * 路由：#/pages/draw/InvitationFriendAssist/InvitationFriendAssist
 *
 * 原站 390x844 实测（uni-page-head 44px，body 从 y=44 起，bg rgb(245,245,245)）：
 *   uni-page-head @0,0 390x44 bg #fff  返回 i @5,6 27x27；标题「邀请好友助力」@70,7 250x30
 *   .pa-16 @0,44 390x413
 *     「邀请」按钮 @16,70 358x47 br8 bg #000  14px/700 #fff（mt 10）
 *     深色卡 @16,132 358x51 br20 bg rgb(48,41,32)（mt 15）
 *       文案 @31,147 328x21 text-center 14px rgb(102,102,102)「您已成功获得助力0次」
 *     数据区 @16,183 358x187
 *       表头 @32,199 326x18  12px rgb(151,153,151)（padding 16 左右，故左 32）
 *         「日期」@32 宽75.3 |「用户类型」@123.1 宽58.5 |「为您助力用户」@198 宽64.5 |「结果」@279.1 宽78.9
 *       空态 @32,233 326x121：图 90x90；文案 14px rgb(192,196,204)
 *     规则卡 @16,375 358x66 br20 bg rgb(48,41,32)（mt 5）
 *       行 @31,390 328x24；「助力规则」15px rgb(74,74,74)
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="assist">
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <svg viewBox="0 0 24 24" width="27" height="27" aria-hidden="true">
          <path
            d="M15 4 L7 12 L15 20"
            fill="none"
            stroke="rgb(51, 51, 51)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
      <div class="nav-title">邀请好友助力</div>
    </div>

    <div class="pa16">
      <div class="invite-btn">邀请</div>

      <div class="dark-card">您已成功获得助力<span class="num">0</span>次</div>

      <div class="data-box">
        <div class="th">
          <span class="th-1">日期</span>
          <span class="th-2">用户类型</span>
          <span class="th-3">为您助力用户</span>
          <span class="th-4">结果</span>
        </div>
        <div class="empty">
<img class="empty-icon" src="/static/empty/empty-data.png" alt="" />
          <div class="empty-txt">数据为空</div>
        </div>
      </div>

      <div class="rule-card">助力规则</div>
    </div>
  </div>
</template>

<style scoped>
.assist {
  position: relative;
  width: 390px;
  min-height: 844px;
  box-sizing: border-box;
  background: rgb(245, 245, 245);
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.navbar {
  position: fixed;
  left: 0;
  top: 0;
  z-index: 10;
  width: 390px;
  height: 44px;
  background: #fff;
}
.nav-back {
  position: absolute;
  left: 5px;
  top: 6px;
  width: 27px;
  height: 27px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.nav-title {
  position: absolute;
  left: 70px;
  top: 7px;
  width: 250px;
  height: 30px;
  font-size: 16px;
  font-weight: 700;
  color: #000;
  line-height: 30px;
  text-align: center;
}

.pa16 {
  display: flex;
  flex-direction: column;
  padding: 16px;
  padding-top: 60px;
  box-sizing: border-box;
}

/* 邀请按钮 @16,70 358x47 br8 */
.invite-btn {
  width: 358px;
  height: 47px;
  margin-top: 10px;
  border-radius: 8px;
  background: #000;
  font-size: 14px;
  font-weight: 700;
  line-height: 47px;
  color: #fff;
  text-align: center;
  cursor: pointer;
}

/* 深色卡 @16,132 358x51 br20 */
.dark-card {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 358px;
  height: 51px;
  margin-top: 15px;
  box-sizing: border-box;
  border-radius: 20px;
  background: rgb(48, 41, 32);
  font-size: 14px;
  line-height: 21px;
  color: rgb(102, 102, 102);
  text-align: center;
}
.dark-card .num {
  margin: 0 4px;
}

/* 数据区 @16,183 358x187 */
.data-box {
  display: flex;
  flex-direction: column;
  width: 358px;
  height: 187px;
  margin-top: 0;
  padding: 16px;
  box-sizing: border-box;
}
.th {
  position: relative;
  width: 326px;
  height: 18px;
  margin-bottom: 16px;
  font-size: 12px;
  line-height: 18px;
  color: rgb(151, 153, 151);
  white-space: nowrap;
}
.th-1 {
  position: absolute;
  left: 0;
  top: 0;
  transform: translateX(-50%);
}
.th-2 {
  position: absolute;
  left: 106px;
  top: 0;
  transform: translateX(-50%);
}
.th-3 {
  position: absolute;
  left: 203.5px;
  top: 0;
  transform: translateX(-50%);
}
.th-4 {
  position: absolute;
  left: 326px;
  top: 0;
  transform: translateX(-50%);
}

/* 空态 @32,233 326x121 */
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 326px;
  height: 121px;
}
.empty-icon {
  display: block;
  width: 56px;
  height: 65.25px;
  margin-top: 10.5px;
}
.empty-txt {
  width: 84px;
  height: 21px;
  margin-top: 10px;
  font-size: 14px;
  line-height: 21px;
  color: rgb(192, 196, 204);
  text-align: center;
}

/* 规则卡 @16,375 358x66 br20 */
.rule-card {
  display: flex;
  align-items: center;
  width: 358px;
  height: 66px;
  margin-top: 5px;
  padding: 0 15px;
  box-sizing: border-box;
  border-radius: 20px;
  background: rgb(48, 41, 32);
  font-size: 15px;
  line-height: 22px;
  color: rgb(74, 74, 74);
  cursor: pointer;
}
</style>