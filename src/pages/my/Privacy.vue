<script setup>
/**
 * 隐私设置 —— 1:1 复刻原站 pages/my/privacy
 * 由 设置页「隐私设置」/ 其他功能页「隐私设置」进入。
 *
 * 注意：本页名为 privacy，但**不是隐私政策页**，而是一个隐私开关页。
 * 真正的隐私政策在 my/about 里以链接形式存在。
 *
 * 原站登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container 高 844 白底
 *   返回 back-btn.png@13,10 24x24
 *   导航标题「隐私设置」@165,16 60x16  15px/700 rgb(51,51,51)
 *   文字「对外隐藏所有藏品」@29,77 120x16  15px/400 rgb(35,38,47)
 *   开关 u-switch @319,72 42x22  bg rgb(27,100,222)  开启态
 *     border 1px solid rgba(0,0,0,...)
 *
 * 接口：原站切换开关时调用隐私设置接口（本页未实测到具体接口名，
 *       故此处仅维护本地状态 + 抛出事件，不编造接口调用）
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()

/* 原站实测默认为开启态 */
const hidden = ref(true)

function toggle() {
  hidden.value = !hidden.value
  userStore.setPrivacyHidden(hidden.value)
}
</script>

<template>
  <div class="privacy-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">隐私设置</div>
    </div>

    <!-- 开关行：文字 @29,77 120x16；开关 @319,72 42x22 -->
    <div class="row" @click="toggle">
      <span class="label">对外隐藏所有藏品</span>
      <span class="switch" :class="{ on: hidden }">
        <span class="knob"></span>
      </span>
    </div>
  </div>
</template>

<style scoped>
/* .container 高 844 白底 */
.privacy-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航：标题「隐私设置」@165,16 60x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 开关行 @29,77 —— 容器从 y=72 开始（避开导航区） */
.row {
  position: absolute;
  left: 29px;
  top: 72px;
  width: 332px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
/* 文字 15px/400 rgb(35,38,47) */
.label {
  color: rgb(35, 38, 47);
  font-size: 15px;
  font-weight: 400;
  line-height: 16px;
}
/* 开关 u-switch 42x22 bg rgb(27,100,222) */
.switch {
  position: relative;
  width: 42px;
  height: 22px;
  flex-shrink: 0;
  border-radius: 11px;
  background: #e5e5e5;
  box-sizing: border-box;
  transition: background 0.2s;
  cursor: pointer;
}
.switch.on {
  background: rgb(27, 100, 222);
  border: 1px solid rgba(0, 0, 0, 0.08);
}
/* 滑块：直径 18，开启态靠右 */
.knob {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s;
}
.switch.on .knob { transform: translateX(20px); }
</style>