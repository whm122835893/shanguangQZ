<script setup>
/**
 * 登录首页 —— 对齐原站 pages/login/login
 * 基线（390x844）：
 *   logo@61,92   269x269
 *   账号登录@42,435 306x48 bg #f23d4d r10 白字 15px/700
 *   手机一键@42,495 306x48 bg #1a1a1a r10
 *   去注册@147,562 12px #000 700
 *   协议@69,736 11px #222 500
 */
const go = (path) => {
  if (location.hash.slice(1) === path) return
  location.hash = '#' + path
}
</script>

<template>
  <div class="container page-container login-page">
    <div class="header">
      <img class="logo-title" src="/static/icon/login-logo.png" alt="闪光" />
    </div>

    <div class="login-options">
      <div class="login-options-pass" @click="go('/pages/login/login-pass')">
        <span class="login-options-pass-text">账号登录</span>
      </div>
      <div class="login-options-code" @click="go('/pages/login/login-code')">
        <span class="login-options-code-text">手机一键登陆</span>
      </div>
    </div>

    <div class="register-link" @click="go('/pages/login/register')">
      没有账号？<span class="link">去注册</span>
    </div>

    <div class="agreement">
      <img class="agreement-image" src="/static/image/login/yes-checked-image.png" alt="同意" />
      <span class="agreement-1">登录即代表您同意</span>
      <span class="agreement-color">《用户协议》</span>
      <span>与</span>
      <span class="agreement-color">《隐私政策》</span>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  background: #fff;
  min-height: 100vh;
  padding-bottom: calc(100vh - 844px + 92px);
}
.header {
  padding-top: 92px;
  display: flex;
  justify-content: center;
}
.logo-title {
  width: 269px;
  height: 269px;
}
.login-options {
  margin-top: 50px;
  padding: 0 42px;
}
.login-options-pass,
.login-options-code {
  width: 100%;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.login-options-pass {
  background: var(--gd-primary);
  margin-bottom: 12px;
}
.login-options-code {
  background: #1a1a1a;
}
.login-options-pass-text,
.login-options-code-text {
  font-size: var(--gd-fs-15);
  font-weight: 700;
  color: #fff;
}
.register-link {
  margin-top: 49px;
  text-align: center;
  font-size: var(--gd-fs-12);
  color: #000;
  font-weight: 700;
}
.register-link .link { text-decoration: underline; }
.agreement {
  position: fixed;
  left: 50%;
  transform: translateX(-50%);
  bottom: 92px;
  display: flex;
  align-items: center;
  font-size: 11px;
  color: #222;
  font-weight: 500;
  white-space: nowrap;
}
.agreement-image {
  width: 14px;
  height: 14px;
  margin-right: 7px;
}
.agreement-color { color: #000; }
</style>