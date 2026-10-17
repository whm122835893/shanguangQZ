<script setup>
/**
 * 消息 —— 100% 复刻原站 pages/message/message
 *
 * 原站 390x844 实测：
 *   uni-page-head          @0,0   390x44  白底  标题「消息」16px #000 居中
 *   返回 icon             @15,12  20x20
 *   页面主体               空白（body scrollHeight=844，无任何列表/空态）
 *
 * 说明：原站该页仅实现导航栏，未请求任何消息接口（实测登录后仅触发 /user/getUserInfo），
 *       属于功能未上线。此处严格按原站 1:1 复刻，不擅自添加内容。
 */
import { useRouter } from 'vue-router'
const router = useRouter()
</script>

<template>
  <div class="msg-page">
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">消息</div>
    </div>
  </div>
</template>

<style scoped>
.msg-page { min-height: 100vh; background: #fff; }

/* 导航 @0,0 390x44 白底 */
.navbar { position: relative; width: 390px; height: 44px; background: #fff; }
.back { position: absolute; left: 15px; top: 12px; width: 20px; height: 20px; }
.nav-title {
  width: 390px;
  height: 44px;
  line-height: 44px;
  text-align: center;
  font-size: 16px;
  color: #000;
}
</style>
