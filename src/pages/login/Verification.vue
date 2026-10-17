<script setup>
/**
 * 验证码验证 —— 1:1 复刻原站 pages/login/verification
 *
 * 路由：#/pages/login/verification?mobile={手机号}&type={bind|resetPwd|1}
 *
 * 原站 390x844 实测（?mobile=175****1293&type=bind）：
 *   .container.page-container @0,0 390x844 bg #fff
 *   .login_center @0,44 390x642
 *     .title @41,71 308x57
 *       「请输入验证码」 @41,71 308x26  18px/600 rgb(35,38,47)
 *       「已发送至+86 175****1293」 @41,97 308x31  12px rgb(122,122,122)
 *     .iptStyle @41,275 308x50
 *       .u-input--square.u-border-bottom @41,275 308x50 br10  （实测为全边框）
 *         .u-input__content @57,288 276x24 15px「请输入验证码」
 *         subfix「(重新发送 56s)」
 *     「下一步」 .plr-10.login_btn.z-btn @41,374 308x47 br8 bg #000 14px/700 #fff
 *   .agreement @0,686 390x20
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route = useRoute()

const mobile = ref(String(route.query.mobile || route.query.phone || ''))
const type = ref(String(route.query.type || 'bind'))
const code = ref('')
const left = ref(56)
let timer = null

onMounted(() => {
  timer = setInterval(() => {
    if (left.value > 0) left.value -= 1
    else clearInterval(timer)
  }, 1000)
})
onUnmounted(() => timer && clearInterval(timer))

const subfixText = computed(() => `(重新发送 ${left.value}s)`)

function onNext() {
  router.back()
}
</script>

<template>
  <div class="verification">
    <div class="navbar">
      <img class="nav-back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
    </div>

    <div class="login-center">
      <div class="title">
        <div class="title-main font-size-18 font-color-black fw-600">请输入验证码</div>
        <div class="hint">已发送至+86 {{ mobile }}</div>
      </div>

      <div class="ipt-style">
        <div class="input-box">
          <input v-model="code" class="ipt" type="tel" maxlength="6" placeholder="请输入验证码" />
          <span class="subfix">{{ subfixText }}</span>
        </div>
      </div>

      <div class="login-btn z-btn" @click="onNext">下一步</div>
    </div>

    <div class="agreement"></div>
  </div>
</template>

<style scoped>
.verification {
  position: relative;
  width: 390px;
  min-height: 844px;
  box-sizing: border-box;
  background: #fff;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.navbar {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 44px;
  background: #fff;
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  cursor: pointer;
}

.login-center {
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  height: 642px;
}

/* 标题块 @41,71 308x57 */
.title {
  position: absolute;
  left: 41px;
  top: 29px;
  width: 308px;
  height: 57px;
}
.title-main {
  width: 308px;
  height: 26px;
  font-size: 18px;
  font-weight: 600;
  line-height: 26px;
  color: rgb(35, 38, 47);
  text-align: center;
}
.hint {
  width: 308px;
  height: 31px;
  margin-top: 4px;
  font-size: 12px;
  line-height: 18px;
  color: rgb(122, 122, 122);
  text-align: center;
}

/* 输入框 @41,275（相对 login_center top:231）308x50 */
.ipt-style {
  position: absolute;
  left: 41px;
  top: 231px;
  width: 308px;
  height: 50px;
}
.input-box {
  position: relative;
  width: 308px;
  height: 50px;
  box-sizing: border-box;
  border: 1px solid rgb(207, 207, 207);
  border-radius: 10px;
  background: #fff;
  display: flex;
  align-items: center;
}
.ipt {
  width: 200px;
  height: 24px;
  margin-left: 15px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: rgb(192, 196, 204);
}
.subfix {
  margin-left: auto;
  margin-right: 16px;
  font-size: 12px;
  color: rgb(192, 196, 204);
  white-space: nowrap;
}

/* 「下一步」 @41,374（相对 top:330）308x47 */
.login-btn {
  position: absolute;
  left: 41px;
  top: 330px;
  width: 308px;
  height: 47px;
  border-radius: 8px;
  background: rgb(0, 0, 0);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  line-height: 47px;
  text-align: center;
  cursor: pointer;
}

.agreement {
  position: absolute;
  left: 0;
  top: 686px;
  width: 390px;
  height: 20px;
}
</style>
