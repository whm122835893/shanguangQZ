<script setup>
/**
 * 忘记密码 —— 100% 复刻原站 pages/login/forgetPwd
 *
 * 原站 390x844 实测（唯一权威数据，来自 getBoundingClientRect + getComputedStyle）：
 *   .container.page-container            @0,0     390x844
 *   img 返回                             @13,10   24x24    /static/icon/back-btn.png
 *   .login_center                        @0,44    390x762  pad 27px 41px 0
 *     .title「忘记密码」                  @41,71   308x26   18px /600 rgb(35,38,47)
 *     .iptStyle(u-input)                 @41,244  308x50   br10 pad 0 15px border 1px solid rgb(207,207,207)
 *       占位「请输入手机号」              @57,256  276x26   14px rgb(192,196,204)
 *     按钮「获取验证码」                  @41,343  308x47   14px/700 白字 bg #000 br8
 *   .agreement                           @0,806   390x38   pad 0 41px 20px
 *     checkbox 图标                      @41,806  16x16    bg rgb(51,51,51) 圆
 *     协议文字                            @63,806  267x18   12px rgb(122,122,122)
 *     《使用条款》                        @168,809 72x14    12px rgb(51,51,51)
 *     《隐私协议》                        @258,809 72x14    12px rgb(51,51,51)
 *
 * 第二步（点获取验证码后，原站实测）：
 *     .title「请输入验证码」              @41,71   308x26   18px /600
 *     验证码输入框占位                    @57,287  196.33x26 14px rgb(192,196,204)
 *     「(重新发送 56s)」                  @257.33,291 75.67x18  12px rgb(192,196,204)
 *     按钮「下一步」                      @41,374  308x47   bg #000 br8 白字 14px/700
 *
 * 接口（原站实测）：
 *   POST /sms/send      body {mobile, event:"resetpwd"}
 *   POST /user/resetpwd 重置密码
 */
import { ref, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { sendSms, resetPwd } from '@/api'
import { showToast } from 'vant'

const router = useRouter()

/* step: 1=输入手机号  2=输入验证码  3=设置新密码 */
const step = ref(1)
const mobile = ref('')
const code = ref('')
const password = ref('')
const password2 = ref('')
const counting = ref(0)
const submitting = ref(false)
const agreed = ref(true)

let timer = null
onUnmounted(() => timer && clearInterval(timer))

function back() {
  if (step.value > 1) {
    step.value -= 1
    return
  }
  if (history.length > 1) router.back()
  else router.replace('/pages/login/login-pass')
}

function startCount() {
  counting.value = 60
  timer && clearInterval(timer)
  timer = setInterval(() => {
    counting.value -= 1
    if (counting.value <= 0) clearInterval(timer)
  }, 1000)
}

/** 第一步：获取验证码 */
async function getCode() {
  if (counting.value > 0) return
  if (!/^1\d{10}$/.test(mobile.value)) {
    showToast({ icon: 'none', message: '请输入正确的手机号' })
    return
  }
  try {
    await sendSms({ mobile: mobile.value, event: 'resetpwd' })
    showToast({ icon: 'success', message: '验证码已发送' })
    step.value = 2
    startCount()
  } catch (e) {
    showToast({ icon: 'none', message: (e && e.msg) || '发送失败' })
  }
}

/** 第二步：下一步 -> 进入设置新密码 */
function nextStep() {
  if (!code.value) {
    showToast({ icon: 'none', message: '请输入验证码' })
    return
  }
  step.value = 3
}

/** 第三步：提交重置 */
async function onSubmit() {
  if (submitting.value) return
  if (password.value.length < 6) {
    showToast({ icon: 'none', message: '密码不少于 6 位' })
    return
  }
  if (password.value !== password2.value) {
    showToast({ icon: 'none', message: '两次密码不一致' })
    return
  }
  submitting.value = true
  try {
    await resetPwd({
      mobile: mobile.value,
      code: code.value,
      password: password.value,
      repassword: password2.value
    })
    showToast({ icon: 'success', message: '重置成功' })
    setTimeout(() => router.replace('/pages/login/login-pass'), 800)
  } catch (e) {
    showToast({ icon: 'none', message: (e && e.msg) || '重置失败' })
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="forget-page">
    <img class="back-btn" src="/static/icon/back-btn.png" alt="返回" @click="back" />

    <div class="login_center">
      <div class="title">{{ step === 1 ? '忘记密码' : step === 2 ? '请输入验证码' : '设置新密码' }}</div>

      <!-- 第一步：手机号 -->
      <div v-if="step === 1" class="iptStyle">
        <input v-model="mobile" class="field-input" type="number" maxlength="11" placeholder="请输入手机号" />
      </div>

      <!-- 第二步：验证码 -->
      <div v-if="step === 2" class="iptStyle">
        <input v-model="code" class="field-input code-input" type="number" maxlength="6" placeholder="请输入验证码" />
        <span v-if="counting > 0" class="resend">(重新发送 {{ counting }}s)</span>
      </div>

      <!-- 第三步：新密码 -->
      <template v-if="step === 3">
        <div class="iptStyle">
          <input v-model="password" class="field-input" type="password" placeholder="请设置新密码" />
        </div>
        <div class="iptStyle second">
          <input v-model="password2" class="field-input" type="password" placeholder="请确认新密码" />
        </div>
      </template>

      <!-- 按钮 -->
      <div v-if="step === 1" class="login_btn" @click="getCode">获取验证码</div>
      <div v-else-if="step === 2" class="login_btn" @click="nextStep">下一步</div>
      <div v-else class="login_btn" @click="onSubmit">确认重置</div>
    </div>

    <!-- 协议 @0,806 -->
    <div class="agreement">
      <div class="checkbox" :class="{ checked: agreed }" @click="agreed = !agreed">
        <span v-if="agreed" class="check-mark"></span>
      </div>
      <div class="agreement-text">
        我已同意 闪光 平台
        <span class="link">《使用条款》</span> 和
        <span class="link">《隐私协议》</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* 原站含 44px 原生导航栏（.login_center 从 y=44 起） */
.forget-page {
  position: relative;
  min-height: 100vh;
  padding-top: 44px;
  box-sizing: border-box;
  background: #fff;
}

/* 返回 @13,10 24x24 */
.back-btn {
  position: absolute;
  top: 10px;
  left: 13px;
  width: 24px;
  height: 24px;
}

/* .login_center @0,44 pad 27px 41px 0 */
.login_center {
  padding: 27px 41px 0;
  box-sizing: border-box;
  min-height: 762px;
}

/* 标题 @41,71 308x26 18px/600（文字居中） */
.title {
  position: relative;
  top: 2px;
  width: 308px;
  height: 26px;
  line-height: 26px;
  font-size: 18px;
  font-weight: 600;
  text-align: center;
  color: rgb(35, 38, 47);
}

/* 输入框 @41,244 308x50 br10 pad 0 15px border 1px solid #cfcfcf */
.iptStyle {
  position: relative;
  width: 308px;
  height: 50px;
  margin-top: 147px;
  box-sizing: border-box;
  border: 1px solid rgb(207, 207, 207);
  border-radius: 10px;
}
.iptStyle.second {
  margin-top: 20px;
}
.field-input {
  display: block;
  width: 100%;
  height: 48px;
  padding: 0 15px;
  box-sizing: border-box;
  line-height: 48px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: rgb(51, 51, 51);
  font-family: inherit;
}
.field-input::-webkit-input-placeholder {
  color: rgb(192, 196, 204);
  font-size: 14px;
}
.code-input {
  width: calc(100% - 80px);
}
/* 「(重新发送 56s)」@257.33,291 */
.resend {
  position: absolute;
  top: 16px;
  right: 15px;
  font-size: 12px;
  color: rgb(192, 196, 204);
  line-height: 18px;
}

/* 按钮 @41,343 308x47 bg #000 br8 白字14px/700 */
.login_btn {
  width: 308px;
  height: 47px;
  line-height: 47px;
  text-align: center;
  margin-top: 49px;
  background: rgb(0, 0, 0);
  border-radius: 8px;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
}

/* 协议 @0,806 390x38 pad 0 41px 20px */
.agreement {
  position: absolute;
  left: 0;
  bottom: 0;
  display: flex;
  align-items: flex-start;
  width: 390px;
  height: 38px;
  padding: 0 41px 20px;
  box-sizing: border-box;
}
.checkbox {
  position: relative;
  width: 16px;
  height: 16px;
  border: 1px solid rgb(51, 51, 51);
  border-radius: 100%;
  background: rgb(51, 51, 51);
  box-sizing: border-box;
  flex-shrink: 0;
}
/* 对勾：原站实测像素范围 x7~17 / y7~10（16x16 圆内偏左上） */
.check-mark {
  position: absolute;
  top: 3px;
  left: 1px;
  width: 12px;
  height: 12px;
  display: block;
}
.check-mark::after {
  content: '';
  position: absolute;
  top: 1px;
  left: 3px;
  width: 2.5px;
  height: 4px;
  border: solid #fff;
  border-width: 0 1.5px 1.5px 0;
  transform: rotate(45deg);
}
.agreement-text {
  margin-left: 6px;
  padding-top: 1px;
  font-size: 12px;
  line-height: 18px;
  color: rgb(122, 122, 122);
}
.agreement-text .link {
  color: rgb(51, 51, 51);
}
</style>
