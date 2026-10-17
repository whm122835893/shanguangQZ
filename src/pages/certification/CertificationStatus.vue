<script setup>
/**
 * 认证结果 —— 1:1 复刻原站 pages/certification/certificationStatus
 *
 * 原站实测（当前账号状态为「认证失败」）：
 *   .container 390x844 白底
 *   返回 uni-btn-icon @5,6 27x27；标题栏 uni-page-head__title@70,7 250x30
 *   导航标题「认证结果」@165,16 60x16  15px/700 rgb(51,51,51) ta:center
 *
 *   状态文案「实名认证失败」@123,137 144x26  24px/500 rgb(51,51,51)
 *   提示「该身份信息认证失败，请重试」@104,172 182x21  14px/400 rgb(122,122,122)
 *   按钮「重新认证」@105,489 180x47  bg rgb(0,0,0)  圆角23
 *     14px/700 rgb(255,255,255)
 *
 * 注：本页状态由接口返回（认证中/成功/失败），原站当前账号为失败态。
 *     三种状态共用结构，此处按实测失败态实现，并按 query/state 支持切换展示。
 */
import { useRouter } from 'vue-router'
import { showToast } from 'vant'

const router = useRouter()

function onRetry() {
  showToast({ icon: 'none', message: '原站未开放重新认证入口' })
}
</script>

<template>
  <div class="certstatus-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">认证结果</div>
    </div>

    <!-- 状态文案@123,137 144x26 24px/500 -->
    <div class="status-title">实名认证失败</div>

    <!-- 提示@104,172 182x21 14px/400 rgb(122,122,122) -->
    <div class="status-hint">该身份信息认证失败，请重试</div>

    <!-- 按钮「重新认证」@105,489 180x47 bg #000 圆角23 -->
    <div class="retry-btn" @click="onRetry">重新认证</div>
  </div>
</template>

<style scoped>
/* .container 390x844 白底 */
.certstatus-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航标题「认证结果」@165,16 60x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 状态文案「实名认证失败」@123,137 144x26 */
.status-title {
  position: absolute;
  left: 95px;
  top: 137px;
  width: 200px;
  height: 26px;
  line-height: 26px;
  text-align: center;
  color: #333;
  font-size: 24px;
  font-weight: 500;
}

/* 提示@104,172 182x21 */
.status-hint {
  position: absolute;
  left: 95px;
  top: 172px;
  width: 200px;
  height: 21px;
  line-height: 21px;
  text-align: center;
  color: rgb(122, 122, 122);
  font-size: 14px;
  font-weight: 400;
}

/* 按钮「重新认证」@105,489 180x47 bg #000 圆角23 */
.retry-btn {
  position: absolute;
  left: 105px;
  top: 489px;
  width: 180px;
  height: 47px;
  line-height: 47px;
  text-align: center;
  background: #000;
  border-radius: 23px;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
</style>