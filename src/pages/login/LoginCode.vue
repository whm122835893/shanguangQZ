<script setup>
/**
 * 验证码登录 —— 1:1 复刻原站 pages/login/login-code
 *
 * 路由：#/pages/login/login-code
 *
 * 原站 390x844 实测（container bg #fff，无 navbar）：
 *   返回图标 @15,119.5 31x31（back-btn.png，容器高度 0，用 padding 撑开）
 *   logo @147,231.5 97x62（login-logo-1.png，原图 292x185）
 *   .login-form @45,368.5 300x192
 *     输入组1 @45,384.5 291x44 br10 border-bottom 2px solid rgb(207,207,207) bg #fff
 *       输入区 @75,394.5 231x24「请输入手机号」15px
 *     输入组2 @45,448.5 291x44（同上，mt 20）
 *       输入区 @75,458.5 157x24「请输入验证码」
 *       「获取验证码」@236,460 70x21
 *     「立即登录」@45,512.5 300x48 br26 bg #000 15px/700 #fff lh 48px
 *   「没有账号？去注册」@147,579.5 96x18 12px/700
 *     前半 rgb(153,153,153)；「去注册」@207,582.5 36x14 #000
 *   协议行 @69,664.5 252x16 11px/500 ta-center
 *     勾选图 @69,665.5 14x14（yes-checked-image.png，原图 26x26）
 *     「登录即代表您同意」@90,664.5 88x16 rgb(127,127,127)
 *     「《用户协议》」@178,664.5 66x16 #000
 *     「与」@166 附近 rgb(127,127,127)
 *     「《隐私政策》」@255,664.5 66x16 #000
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const phone = ref('')
const code = ref('')
</script>

<template>
  <div class="login-code">
    <img class="back-btn" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />

    <img class="logo" src="/static/image/login/login-logo-1.png" alt="闪光" />

    <div class="login-form">
      <div class="input-group">
        <input v-model="phone" class="ipt" type="tel" placeholder="请输入手机号" />
      </div>

      <div class="input-group input-group--mt20">
        <input v-model="code" class="ipt ipt--code" type="tel" placeholder="请输入验证码" />
        <span class="get-code">获取验证码</span>
      </div>

      <div class="login-button">立即登录</div>
    </div>

    <div class="login-link">没有账号？<span class="link">去注册</span></div>

    <div class="agreement">
      <img class="agreement-img" src="/static/image/login/yes-checked-image.png" alt="" />
      <span class="agreement-txt">登录即代表您同意</span>
      <span class="agreement-color">《用户协议》</span>
      <span class="agreement-txt">与</span>
      <span class="agreement-color">《隐私政策》</span>
    </div>
  </div>
</template>

<style scoped>
.login-code {
  position: relative;
  width: 390px;
  min-height: 844px;
  box-sizing: border-box;
  background: #fff;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.back-btn {
  position: absolute;
  left: 15px;
  top: 114.5px;
  width: 31px;
  height: 31px;
  cursor: pointer;
}

.logo {
  position: absolute;
  left: 147px;
  top: 219px;
  width: 97px;
  height: 62px;
}

/* 表单 @45,368.5 300x192 */
.login-form {
  position: absolute;
  left: 45px;
  top: 368.5px;
  width: 300px;
  height: 192px;
}

.input-group {
  position: absolute;
  left: 0;
  top: 16px;
  width: 291px;
  height: 44px;
  box-sizing: border-box;
  border: 2px solid rgb(207, 207, 207);
  border-radius: 10px;
  background: #fff;
}
.input-group--mt20 {
  top: 80px;
}

.ipt {
  position: absolute;
  left: 29px;
  top: 4px;
  width: 231px;
  height: 36px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 15px;
  font-family: inherit;
  color: rgb(35, 38, 47);
}
.ipt--code {
  width: 157px;
}
.ipt::placeholder {
  color: rgb(153, 153, 153);
}

.get-code {
  position: absolute;
  right: 14px;
  top: 11.5px;
  width: 70px;
  height: 21px;
  font-size: 14px;
  line-height: 21px;
  color: #000;
  text-align: center;
  cursor: pointer;
}

/* 立即登录 @45,512.5 300x48 br26 */
.login-button {
  position: absolute;
  left: 0;
  top: 144px;
  width: 300px;
  height: 48px;
  border-radius: 26px;
  background: #000;
  font-size: 15px;
  font-weight: 700;
  line-height: 48px;
  color: #fff;
  text-align: center;
  cursor: pointer;
}

/* 没有账号？去注册 @147,579.5 96x18 */
.login-link {
  position: absolute;
  left: 147px;
  top: 579.5px;
  width: 96px;
  height: 18px;
  font-size: 12px;
  font-weight: 700;
  line-height: 18px;
  color: rgb(153, 153, 153);
  text-align: center;
}
.login-link .link {
  color: #000;
}

/* 协议 @69,664.5 252x16 */
.agreement {
  position: absolute;
  left: 69px;
  top: 664.5px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 252px;
  height: 16px;
  font-size: 11px;
  font-weight: 500;
  line-height: 16px;
  color: rgb(127, 127, 127);
}
.agreement-img {
  width: 14px;
  height: 14px;
  flex: none;
}
.agreement-txt:first-of-type {
  margin-left: 7px;
}
.agreement-txt,
.agreement-color {
  white-space: nowrap;
}
.agreement-color {
  color: #000;
}
</style>