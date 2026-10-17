<script setup>
/**
 * 社群分享 —— 1:1 复刻原站 pages/draw/communityShare/communityShare
 *
 * 路由：#/pages/draw/communityShare/communityShare
 *
 * 原站 390x844 实测（uni-page-head 44px，body 从 y=44 起，bg rgb(245,245,245)）：
 *   uni-page-head @0,0 390x44 bg #fff  返回 i @5,6 27x27；标题「分享」@70,7 250x30
 *   .pa-16 @0,44 390x546
 *     深色卡 @16,60 358x57 br20 bg rgb(48,41,32)
 *       「您已成功获得助力 0 次」@36,78 152x21  14px rgb(102,102,102)
 *       「分享图片」按钮 @286,72 68x33 br8 border 14px rgb(51,51,51)
 *     「上传图片:」@16,133 358x22  15px rgb(74,74,74)
 *     上传位 @16,171 83x83  br10 bg rgb(243,244,246)，内 + 图标 16x16
 *     提示 @16,270 358x21  14px rgb(153,153,153)
 *     表头 @16,307 358x18  12px rgb(151,153,151)
 *       「日期」@16 宽125.3 |「上传图片数量」@177.1 宽89.5 |「审核结果」@302.4 宽71.6
 *     空态 @16,361 358x121：图 @150,361 90x90；文案 @153,461 84x21 14px rgb(192,196,204)
 *     深色卡2 @16,508 358x66 br20 bg rgb(48,41,32)
 *       前图标 @31,527.5 15x15；「分享社群规则」文字 rgb(74,74,74)
 *   底部固定「提交」@16,~1004 358x47 br23
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="community-share">
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
      <!-- 助力卡 -->
      <div class="dark-card">
        <div class="help-txt">您已成功获得助力&nbsp;<span class="num">0</span>&nbsp;次</div>
        <div class="share-btn">分享图片</div>
      </div>

      <div class="label">上传图片:</div>

      <div class="upload">
        <div class="upload-plus"></div>
      </div>

      <div class="tip">上传截图，图片大小限制5M内，数量3张</div>

      <div class="table">
        <div class="th">
          <span class="th-1">日期</span>
          <span class="th-2">上传图片数量</span>
          <span class="th-3">审核结果</span>
        </div>
        <div class="empty">
          <svg class="empty-icon" viewBox="0 0 90 90" width="90" height="90" aria-hidden="true">
            <path
              d="M22 12 h30 l16 16 v50 a4 4 0 0 1 -4 4 H22 a4 4 0 0 1 -4 -4 V16 a4 4 0 0 1 4 -4 z"
              fill="none"
              stroke="rgb(192, 196, 204)"
              stroke-width="4"
            />
            <path
              d="M52 12 v16 h16"
              fill="none"
              stroke="rgb(192, 196, 204)"
              stroke-width="4"
            />
            <line x1="28" y1="42" x2="50" y2="42" stroke="rgb(192, 196, 204)" stroke-width="4" />
            <line x1="28" y1="56" x2="44" y2="56" stroke="rgb(192, 196, 204)" stroke-width="4" />
            <circle cx="62" cy="66" r="11" fill="rgb(192, 196, 204)" />
            <line x1="62" y1="61" x2="62" y2="66" stroke="#fff" stroke-width="3.4" stroke-linecap="round" />
            <circle cx="62" cy="71" r="1.8" fill="#fff" />
          </svg>
          <div class="empty-txt">暂无分享记录</div>
        </div>
      </div>

      <!-- 社群规则卡 -->
      <div class="rule-card">
        <svg class="rule-ico" viewBox="0 0 24 24" width="15" height="15" aria-hidden="true">
          <path
            d="M12 3 L21 7.5 L12 12 L3 7.5 Z"
            fill="none"
            stroke="rgb(74, 74, 74)"
            stroke-width="1.8"
            stroke-linejoin="round"
          />
          <path
            d="M3 12 L12 16.5 L21 12 M3 16.5 L12 21 L21 16.5"
            fill="none"
            stroke="rgb(74, 74, 74)"
            stroke-width="1.8"
            stroke-linejoin="round"
          />
        </svg>
        <span class="rule-txt">分享社群规则</span>
      </div>
    </div>

    <div class="submit">提交</div>
  </div>
</template>

<style scoped>
.community-share {
  position: relative;
  width: 390px;
  min-height: 844px;
  padding-bottom: 90px;
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
  padding: 16px;
  padding-top: 60px;
  box-sizing: border-box;
}

/* 助力卡 @16,60 358x57 br20 */
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
.help-txt {
  font-size: 14px;
  line-height: 21px;
  color: rgb(102, 102, 102);
}
.help-txt .num {
  margin: 0 6px;
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

/* 上传位 @16,171 83x83 */
.upload {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 83px;
  height: 83px;
  border-radius: 10px;
  background: rgb(243, 244, 246);
  cursor: pointer;
}
.upload-plus {
  position: relative;
  width: 16px;
  height: 16px;
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

/* 表格 */
.table {
  display: flex;
  flex-direction: column;
  width: 358px;
  margin-top: 16px;
}
.th {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 358px;
  height: 18px;
  margin-bottom: 16px;
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

/* 空态 @16,361 358x121 */
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 358px;
  height: 121px;
  margin-top: 20px;
}
.empty-icon {
  display: block;
  width: 90px;
  height: 90px;
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

/* 规则卡 @16,508 358x66 br20 */
.rule-card {
  display: flex;
  align-items: center;
  width: 358px;
  height: 66px;
  margin-top: 26px;
  padding: 0 15px;
  box-sizing: border-box;
  border-radius: 20px;
  background: rgb(48, 41, 32);
  cursor: pointer;
}
.rule-ico {
  display: block;
  width: 15px;
  height: 15px;
  margin-right: 12px;
}
.rule-txt {
  font-size: 15px;
  line-height: 24px;
  color: rgb(74, 74, 74);
}

/* 底部提交 */
.submit {
  position: fixed;
  left: 16px;
  bottom: 24px;
  width: 358px;
  height: 47px;
  border-radius: 23px;
  background: rgb(229, 229, 229);
  font-size: 14px;
  font-weight: 700;
  line-height: 47px;
  color: rgb(153, 153, 153);
  text-align: center;
  cursor: pointer;
}
</style>
