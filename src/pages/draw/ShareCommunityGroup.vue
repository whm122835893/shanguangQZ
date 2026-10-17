<script setup>
/**
 * 分享社群 —— 1:1 复刻原站 pages/draw/shareCommunityGroup/shareCommunityGroup
 *
 * 路由：#/pages/draw/shareCommunityGroup/shareCommunityGroup
 *
 * 原站 390x844 实测（uni-page-head 44px，body 从 y=44 起，bg rgb(245,245,245)）：
 *   uni-page-head @0,0 390x44 bg #fff  返回 i @5,6 27x27；标题「分享」@70,7 250x30 16px/700
 *   .pa-16 @0,44 390x443
 *     深色卡 @16,60 358x57 br20 bg rgb(48,41,32)
 *       内行 @36,72 318x33（左右各 20px 内边距）
 *       「分享社群，已获得 0 积分」@36,77 15px rgb(74,74,74)
 *       「分享图片」按钮 @286,72 68x33 br8 border 14px
 *     「上传图片:」@16,133 358x22  15px rgb(74,74,74)
 *     上传位 @16,171 358x83（容器），灰块 @16,171 83x83 br10 bg rgb(243,244,246)，内 + 16x16
 *     提示 @16,270 358x21  14px rgb(153,153,153)
 *     表头 @16,307 358x18  12px rgb(151,153,151)
 *       「日期」@16 宽125.3 |「上传图片数量」@177.1 宽89.5 |「审核结果」@302.4 宽71.6
 *     深色规则卡 @16,346 358x63 br20 bg rgb(48,41,32)
 *       标题 @31,361 328x21  14px/700 rgb(35,38,47)「分享社群规则」
 *     「提交」@16,425 358x46 bg rgb(20,20,22)  15px #fff
 *   注：本页无空态（数据容器高度 0），规则卡直接跟在表头下方
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="group-share">
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
      <div class="nav-title">分享</div>
    </div>

    <div class="pa16">
      <!-- 分享社群卡 @16,60 358x57 -->
      <div class="dark-card">
        <span class="card-txt">分享社群，已获得&nbsp;<span class="num">0</span>&nbsp;积分</span>
        <span class="share-btn">分享图片</span>
      </div>

      <div class="label">上传图片:</div>

      <div class="upload">
        <div class="upload-plus"></div>
      </div>

      <div class="tip">上传截图，图片大小限制5M内，数量3张</div>

      <!-- 表头 @16,307 -->
      <div class="th">
        <span class="th-1">日期</span>
        <span class="th-2">上传图片数量</span>
        <span class="th-3">审核结果</span>
      </div>

      <!-- 规则卡 @16,346 358x63 -->
      <div class="rule-card">
        <span class="rule-txt">分享社群规则</span>
      </div>

      <div class="submit">提交</div>
    </div>
  </div>
</template>

<style scoped>
.group-share {
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

/* 深色卡 @16,60 358x57 br20 */
.dark-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 358px;
  height: 57px;
  padding: 0 20px;
  box-sizing: border-box;
  border-radius: 20px;
  background: rgb(48, 41, 32);
}
.card-txt {
  font-size: 15px;
  line-height: 22px;
  color: rgb(74, 74, 74);
}
.card-txt .num {
  margin: 0 4px;
}
.share-btn {
  width: 68px;
  height: 33px;
  border: 1px solid rgb(51, 51, 51);
  border-radius: 8px;
  box-sizing: border-box;
  font-size: 14px;
  line-height: 31px;
  color: rgb(51, 51, 51);
  text-align: center;
  cursor: pointer;
}

.label {
  width: 358px;
  height: 22px;
  margin-top: 16px;
  margin-bottom: 16px;
  font-size: 15px;
  line-height: 22px;
  color: rgb(74, 74, 74);
}

/* 上传位：容器 @16,171 358x83，内部灰块 83x83 */
.upload {
  display: flex;
  align-items: flex-start;
  width: 358px;
  height: 83px;
}
.upload-plus {
  position: relative;
  width: 83px;
  height: 83px;
  border-radius: 10px;
  background: rgb(243, 244, 246);
  box-sizing: border-box;
  cursor: pointer;
}
.upload-plus::before,
.upload-plus::after {
  content: '';
  position: absolute;
  left: 50%;
  top: 50%;
  background: rgb(153, 153, 153);
  transform: translate(-50%, -50%);
}
.upload-plus::before {
  width: 16px;
  height: 1.5px;
}
.upload-plus::after {
  width: 1.5px;
  height: 16px;
}

.tip {
  width: 358px;
  height: 21px;
  margin-top: 16px;
  font-size: 14px;
  line-height: 21px;
  color: rgb(153, 153, 153);
}

/* 表头 @16,307 358x18 */
.th {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 358px;
  height: 18px;
  margin-top: 16px;
  font-size: 12px;
  line-height: 18px;
  color: rgb(151, 153, 151);
}
.th-1 {
  width: 125.3px;
  text-align: left;
}
.th-2 {
  width: 89.5px;
  text-align: center;
}
.th-3 {
  width: 71.6px;
  text-align: right;
}

/* 规则卡 @16,346 358x63 br20 */
.rule-card {
  display: flex;
  align-items: center;
  width: 358px;
  height: 63px;
  margin-top: 21px;
  padding: 0 15px;
  box-sizing: border-box;
  border-radius: 20px;
  background: rgb(48, 41, 32);
  cursor: pointer;
}
.rule-txt {
  font-size: 14px;
  font-weight: 700;
  line-height: 21px;
  color: rgb(35, 38, 47);
}

/* 提交 @16,425 358x46 */
.submit {
  width: 358px;
  height: 46px;
  margin-top: 16px;
  background: rgb(20, 20, 22);
  font-size: 15px;
  line-height: 46px;
  color: #fff;
  text-align: center;
  cursor: pointer;
}
</style>