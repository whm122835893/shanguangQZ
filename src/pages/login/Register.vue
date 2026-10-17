<script setup>
/**
 * 注册 —— 100% 复刻原站 pages/login/register
 *
 * 原站 390x844 实测（唯一权威数据，来自 getBoundingClientRect + getComputedStyle）：
 *   .container.page-container            @0,0     390x844  bg #fff
 *   img 返回                             @15,31   31x31    /static/icon/back-btn.png
 *   img Logo                             @146.5,185.5 97x62  /static/image/login/login-logo-1.png
 *   .register-form                       @45,304.5 300x354
 *     .input-group                       @45,320.5 291x44  bg #fff br10 pad 0 19px border 2px solid rgb(207,207,207)
 *       u-input                          @66,324.5 249x36  pad 6px 9px
 *       占位文字                          @75,329.5 231x26  14px rgb(129,129,129)
 *     .input-group（验证码）              @45,384.5（间隔 64）
 *       「获取验证码」                    @236,396  70x21   14px /700 rgb(51,51,51)
 *     .input-group（密码）                @45,448.5
 *     .input-group.optional（邀请码）     @45,512.5
 *     .flex.flex-center.mb-16            @45,576.5 300x18
 *       .login-link                      @153,576.5 84x18
 *         「有账号？」                    @153,579.5 48x14  12px /700 #000
 *         「去登录」                      @201,579.5 36x14  12px /700
 *   .register-button                     @45,610.5 300x48  bg #000 br26 白字 15px
 *
 * 接口（原站实测）：
 *   POST /sms/send  body {mobile, event}   event: register / resetpwd
 *   POST /user/register                    注册
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { sendSms, register } from '@/api'
import { showToast } from 'vant'

const router = useRouter()
const mobile = ref('')
const code = ref('')
const password = ref('')
const inviterCode = ref('')
const counting = ref(0)
const submitting = ref(false)

let timer = null

function back() {
  if (history.length > 1) router.back()
  else router.replace('/pages/login/login')
}

/** 获取验证码：60s 倒计时（原站实测倒计时文案「(重新发送 60s)」） */
async function getCode() {
  if (counting.value > 0) return
  if (!/^1\d{10}$/.test(mobile.value)) {
    showToast({ icon: 'none', message: '请输入正确的手机号' })
    return
  }
  try {
    await sendSms({ mobile: mobile.value, event: 'register' })
    showToast({ icon: 'success', message: '验证码已发送' })
    counting.value = 60
    timer = setInterval(() => {
      counting.value -= 1
      if (counting.value <= 0) clearInterval(timer)
    }, 1000)
  } catch (e) {
    showToast({ icon: 'none', message: (e && e.msg) || '发送失败' })
  }
}

async function onRegister() {
  if (submitting.value) return
  if (!/^1\d{10}$/.test(mobile.value)) {
    showToast({ icon: 'none', message: '请输入正确的手机号' })
    return
  }
  if (!code.value) {
    showToast({ icon: 'none', message: '请输入验证码' })
    return
  }
  if (!password.value) {
    showToast({ icon: 'none', message: '请设置密码' })
    return
  }
  submitting.value = true
  try {
    await register({
      mobile: mobile.value,
      code: code.value,
      password: password.value,
      inviter_code: inviterCode.value || ''
    })
    showToast({ icon: 'success', message: '注册成功' })
    setTimeout(() => router.replace('/pages/login/login-pass'), 800)
  } catch (e) {
    showToast({ icon: 'none', message: (e && e.msg) || '注册失败' })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="register-page">
    <img class="back-btn" src="/static/icon/back-btn.png" alt="返回" @click="back" />
    <img class="login-logo" src="/static/image/login/login-logo-1.png" alt="闪光" />

    <div class="register-form">
      <!-- 手机号 @45,320.5 291x44 -->
      <div class="input-group">
        <input v-model="mobile" class="field-input" type="number" maxlength="11" placeholder="请输入手机号" />
      </div>

      <!-- 验证码 @45,384.5 -->
      <div class="input-group">
        <input v-model="code" class="field-input code-input" type="number" maxlength="6" placeholder="请输入验证码" />
        <span class="get-code" @click="getCode">
          {{ counting > 0 ? `(重新发送 ${counting}s)` : '获取验证码' }}
        </span>
      </div>

      <!-- 密码 @45,448.5 -->
      <div class="input-group">
        <input v-model="password" class="field-input" type="password" placeholder="请设置密码" />
      </div>

      <!-- 邀请码（选填）@45,512.5 -->
      <div class="input-group optional">
        <input v-model="inviterCode" class="field-input" placeholder="请输入邀请码（选填）" />
      </div>

      <!-- 有账号？去登录 @45,576.5 -->
      <div class="login-link-row">
        <div class="login-link" @click="router.replace('/pages/login/login-pass')">
          有账号？<span class="link">去登录</span>
        </div>
      </div>

      <!-- 注册按钮 @45,610.5 300x48 -->
      <div class="register-button" @click="onRegister">注册</div>
    </div>
  </div>
</template>

<style scoped>
.register-page {
  position: relative;
  min-height: 100vh;
  background: #fff;
}

/* 返回 @15,31 31x31 */
.back-btn {
  position: absolute;
  top: 31px;
  left: 15px;
  width: 31px;
  height: 31px;
}
/* Logo @146.5,185.5 97x62 */
.login-logo {
  position: absolute;
  top: 185.5px;
  left: 146.5px;
  width: 97px;
  height: 62px;
}

/* .register-form @45,304.5 300x354（首个输入框起始 @320.5，故 padding-top 16） */
.register-form {
  position: absolute;
  top: 304.5px;
  left: 45px;
  width: 300px;
  padding-top: 16px;
  box-sizing: border-box;
}

/* .input-group 291x44：bg #fff br10 pad 0 19px border 2px solid #cfcfcf */
.input-group {
  position: relative;
  width: 291px;
  height: 44px;
  margin-bottom: 20px;
  padding: 0 19px;
  box-sizing: border-box;
  background: #fff;
  border: 2px solid rgb(207, 207, 207);
  border-radius: 10px;
}
/* 4 个输入框间距 64（44 + 20 = 64） */
.input-group:last-of-type {
  margin-bottom: 0;
}

/* 内输入框 @66,324.5 249x36（pad 6px 9px 撑出） */
.field-input {
  display: block;
  width: 100%;
  height: 42px;
  line-height: 42px;
  padding: 0;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: rgb(51, 51, 51);
  font-family: inherit;
}
.field-input::-webkit-input-placeholder {
  color: rgb(129, 129, 129);
  font-size: 14px;
}
.code-input {
  width: calc(100% - 70px);
}

/* 「获取验证码」@236,396 70x21 14px/700 #333 */
.get-code {
  position: absolute;
  top: 9.5px;
  right: 28px;
  width: 70px;
  height: 21px;
  line-height: 21px;
  text-align: right;
  font-size: 14px;
  font-weight: 700;
  color: rgb(51, 51, 51);
}

/* 有账号？去登录：容器 @45,576.5 300x18 */
.login-link-row {
  display: flex;
  justify-content: center;
  height: 18px;
  margin-top: 16px;
  margin-bottom: 16px;
}
.login-link {
  font-size: 12px;
  font-weight: 700;
  line-height: 14px;
  color: #000;
}
.login-link .link {
  color: #000;
}

/* 注册按钮 @45,610.5 300x48 bg #000 br26 白字15px */
.register-button {
  width: 300px;
  height: 48px;
  line-height: 48px;
  text-align: center;
  background: rgb(0, 0, 0);
  border-radius: 26px;
  color: #fff;
  font-size: 15px;
}
</style>
