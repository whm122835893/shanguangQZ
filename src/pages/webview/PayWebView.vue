<script setup>
/**
 * 支付 webview —— 1:1 复刻原站 pages/webview/pay-webview
 *
 * 路由：#/pages/webview/pay-webview?url=xxx
 *
 * 原站 390x844 实测（整页 bg #fff）：
 *   uni-page-head @0,0 390x44 bg #fff；返回 i @5,6 27x27；标题为空
 *   uni-web-view（fullscreen）@0,44 390x800
 *     与 webview 结构一致，承载支付收银台页面
 */
import WebView from './WebView.vue'
</script>

<template>
  <WebView />
</template>
