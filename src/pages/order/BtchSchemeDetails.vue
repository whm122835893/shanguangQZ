<script setup>
/**
 * 批量合成方案详情 —— 1:1 复刻原站 pages/compound/btchschemeDetails
 *
 * 路由：#/pages/compound/btchschemeDetails
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg @0,0 390x844  bg #fff  padding 0 14px
 *     .u-navbar--fixed            @0,0 390x44（透明）
 *       返回 back-btn.png         @13,10 24x24
 *       标题「活动」              @169,13 52x19  15px/700 rgb(51,51,51)（居中）
 *       右侧「合成记录」          @325,13 52x19  13px/500 #000
 *     .mian-img                   @14,44 362x301  br10
 *       uni-image                 @14,44 362x296（无图，占位）
 *       .status                   @14,44 75x22  bg rgba(0,0,0,.8)  br 0 0 9px 9px
 *                                 12px/500 rgb(255,116,98)  lh 22px（文案为空）
 *     .fxsy                      @14,355 362x20
 *       .fx 发行 @126,355 64x20 → .txt @127,356 34x18  12px/400 #fff  bg #000
 *                                .sl 0份 @161,356 28x18  12px/400 #4a4a4a
 *       .fx 剩余 @200,355 64x20 → 同上
 *     .hccl                      @14,375 362x39
 *       .title「兑换材料」        @14,375 362x21  14px/600 #000
 *       .djs「活动结束时间」      @14,396 362x18  12px/400 rgb(255,116,98)
 *       u-input（数量）           @14,414 50x38  bg rgb(245,247,250)  br4  pad 6px 9px
 *       u-slider（滑块）          @64,414 312x38  值 10，handle 100%，inactive #c0c4cc
 *       u-count-down              @14,452 362x22  「00 00:00:00」15px/400 rgb(96,98,102)
 *     .hc_button                  @14,474 362x87  padding 20px 16px
 *       「立即合成」              @30,494 330x47  bg #000  br23  14px/700 #fff
 *
 * 空态说明：无 scheme 参数进入，方案图/status 文案为空，
 *   数量默认 10、倒计时归零（与原站一致）。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* 合成数量：原站默认 10，滑块 100% 即最大值 */
const count = ref(10)
const MAX = 10

/* 合成记录入口 */
const toRecord = () => {
  router.push('/pages/compound/btchrecord')
}

const doCompound = () => {
  /* 原站在此校验持仓并发起批量合成 */
}
</script>

<template>
  <div class="btch-scheme">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">活动</div>
      <div class="nav-right" @click="toRecord">合成记录</div>
    </div>

    <!-- 方案图 @14,44 362x301 -->
    <div class="main-img"></div>
    <!-- status 标签 @14,44 75x22（文案为空，仅黑底；独立定位避免被主图圆角裁切） -->
    <div class="status"></div>

    <!-- 发行/剩余 @14,355 362x20 -->
    <div class="fxsy">
      <div class="fx">
        <span class="fx-tag">发行</span>
        <span class="fx-num">0份</span>
      </div>
      <div class="fx">
        <span class="fx-tag">剩余</span>
        <span class="fx-num">0份</span>
      </div>
    </div>

    <!-- 兑换材料 @14,375 362x39 -->
    <div class="hccl">
      <div class="hccl-title">兑换材料</div>
      <div class="hccl-end">活动结束时间</div>
      <div class="count-row">
        <!-- 数量输入 @14,414 50x38 -->
        <div class="count-input">
          <input v-model="count" class="count-field" type="text" />
        </div>
        <!-- 滑块 @64,414 312x38 -->
        <div class="slider" @click="count = MAX">
          <div class="slider-track"></div>
          <div class="slider-thumb"></div>
        </div>
      </div>
      <!-- 倒计时占位 @14,452 362x22 —— 原站实测该区域截图为纯白，
           u-count-down 虽有 362x22 占位但文字不可见（组件入场动画未完成），
           故此处仅保留占位尺寸、隐藏文字，与原站可见结果一致。 -->
      <div class="countdown">00 00:00:00</div>
    </div>

    <!-- 底部按钮 @14,474 -->
    <div class="hc-button">
      <div class="hc-btn" @click="doCompound">立即合成</div>
    </div>
  </div>
</template>

<style scoped>
.btch-scheme {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  padding: 0 14px;
  background: #fff;
  box-sizing: border-box;
  overflow: hidden;
}

/* ---------- 导航 @0,0 390x44（透明） ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  margin: 0 -14px;
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
  left: 169px;
  top: 15px;
  width: 52px;
  height: 19px;
  font-size: 15px;
  font-weight: 700;
  color: rgb(51, 51, 51);
  line-height: 19px;
  text-align: center;
  white-space: nowrap;
}
.nav-right {
  position: absolute;
  left: 325px;
  top: 15px;
  width: 52px;
  height: 19px;
  font-size: 13px;
  font-weight: 500;
  color: #000;
  line-height: 19px;
  text-align: center;
  white-space: nowrap;
  cursor: pointer;
}

/* ---------- 方案图 @14,44 362x301 ---------- */
.main-img {
  position: absolute;
  left: 14px;
  top: 44px;
  width: 362px;
  height: 301px;
  border-radius: 10px;
  background: #fff;
}
/* status 标签 @14,44 75x22 —— 文案为空，仅保留黑底 */
.status {
  position: absolute;
  left: 14px;
  top: 44px;
  width: 75px;
  height: 22px;
  background: rgba(0, 0, 0, 0.8);
  border-radius: 0 0 9px 9px;
  font-size: 12px;
  font-weight: 500;
  color: rgb(255, 116, 98);
  line-height: 22px;
  box-sizing: border-box;
  z-index: 1;
}

/* ---------- 发行/剩余 @14,355 362x20（整体水平居中，间距 10） ---------- */
.fxsy {
  position: absolute;
  left: 14px;
  top: 355px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 362px;
  height: 20px;
}
.fx {
  display: flex;
  align-items: center;
  width: 64px;
  height: 20px;
  border: 1px solid #000;
  border-radius: 2px;
  box-sizing: border-box;
  overflow: hidden;
}
.fx + .fx {
  margin-left: 10px;
}
.fx-tag {
  width: 34px;
  height: 18px;
  background: #000;
  font-size: 12px;
  font-weight: 400;
  color: #fff;
  line-height: 18px;
  text-align: center;
  box-sizing: border-box;
  flex: 0 0 34px;
}
.fx-num {
  width: 28px;
  height: 18px;
  padding: 0 5px;
  font-size: 12px;
  font-weight: 400;
  color: rgb(74, 74, 74);
  line-height: 18px;
  white-space: nowrap;
  box-sizing: border-box;
  text-align: center;
}

/* ---------- 兑换材料 @14,375 362x39 ---------- */
.hccl {
  position: absolute;
  left: 14px;
  top: 375px;
  width: 362px;
  height: 39px;
}
.hccl-title {
  width: 362px;
  height: 21px;
  font-size: 14px;
  font-weight: 600;
  color: #000;
  line-height: 21px;
}
.hccl-end {
  width: 362px;
  height: 18px;
  font-size: 12px;
  font-weight: 400;
  color: rgb(255, 116, 98);
  line-height: 18px;
}

/* 数量行 @14,414 362x38 */
.count-row {
  position: absolute;
  left: 0;
  top: 39px;
  display: flex;
  align-items: center;
  width: 362px;
  height: 38px;
}
.count-input {
  width: 50px;
  height: 38px;
  padding: 6px 9px;
  background: rgb(245, 247, 250);
  border-radius: 4px;
  box-sizing: border-box;
}
.count-field {
  width: 30px;
  height: 24px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 15px;
  font-weight: 400;
  color: rgb(48, 49, 51);
  line-height: 26px;
  text-align: center;
  font-family: inherit;
}
/* 滑块 @64,414 312x38
   原站内部实测：tap-area @82,424 276x18（左右各留 18）
                track    @82,432 276x2  rgb(126,127,128)
                thumb    @349,420 26x26  同色圆点 */
.slider {
  position: relative;
  width: 312px;
  height: 38px;
  cursor: pointer;
}
.slider-track {
  position: absolute;
  left: 18px;
  top: 19px;
  width: 276px;
  height: 2px;
  border-radius: 6px;
  background: rgb(126, 127, 128);
}
.slider-thumb {
  position: absolute;
  left: 285px;
  top: 6px;
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: rgb(126, 127, 128);
}

/* 倒计时占位 @14,452 362x22 —— 原站可见结果为空白，仅保留占位 */
.countdown {
  position: absolute;
  left: 0;
  top: 77px;
  width: 362px;
  height: 22px;
  font-size: 15px;
  font-weight: 400;
  color: rgb(96, 98, 102);
  line-height: 22px;
  visibility: hidden;
}

/* ---------- 底部按钮 @14,474 ---------- */
.hc-button {
  position: absolute;
  left: 14px;
  top: 474px;
  width: 362px;
  height: 87px;
  padding: 20px 16px;
  box-sizing: border-box;
}
.hc-btn {
  width: 330px;
  height: 47px;
  background: #000;
  border-radius: 23px;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  line-height: 47px;
  text-align: center;
  box-sizing: border-box;
}
</style>