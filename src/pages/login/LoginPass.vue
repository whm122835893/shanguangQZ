<script setup>
/**
 * 账号登录 —— 对齐原站 pages/login/login-pass
 * 基线（390x844）：
 *   返回@15,31 31x31
 *   logo @147,104 97x62
 *   手机号输入 @45,269 300x46 r26 边框1px #000，文字14px #333
 *   密码输入   @45,328 同上
 *   忘记密码？12px #23262f700 / 没有账号？去注册 12px #999
 *   立即登录 @45,443 300x48 bg #000 r26 白字15/700
 *   协议 @69,558 11px #777
 */
import { ref } from 'vue'
import { showToast } from 'vant'
import { useRouter } from 'vue-router'
import { login, getUserInfo } from '@/api'
import { saveCred } from '@/api/http'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()

const mobile = ref('')
const password = ref('')
const loading = ref(false)

async function onSubmit() {
  if (!mobile.value) return showToast({ icon: 'none', message: '请输入手机号' })
  if (!password.value) return showToast({ icon: 'none', message: '请输入密码' })
  loading.value = true
  try {
    // 原站实测：POST /api/user/login  body { account, password, inviter_code }
    // token 在 data.userinfo.token
    const res = await login({ account: mobile.value, password: password.value })
    const token = (res.data && res.data.userinfo && res.data.userinfo.token) || ''
    userStore.setLogin(token, (res.data && res.data.userinfo) || null)
    /* 预览环境会话保活：保存凭据供 token 过期时静默续登（见 api/http.js relogin） */
    saveCred(mobile.value, password.value)
    try {
      const u = await getUserInfo()
      userStore.setLogin(token, u.data)
    } catch (e) { /* 原站同样容错 */ }
    showToast({ icon: 'success', message: '登录成功' })
    setTimeout(() => {
      if (history.length > 1) router.back()
      else router.replace('/pages/index/index')
    }, 800)
  } catch (e) {
    showToast({ icon: 'none', message: (e && e.msg) || '登录失败' })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="container page-container login-pass">
    <img class="back-title" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />

    <div class="pass-logo">
      <img src="/static/image/login/login-logo-1.png" alt="闪光" />
    </div>

    <div class="register-form">
      <div class="input-group">
        <input v-model="mobile" class="field" type="number" maxlength="11" placeholder="请输入手机号" />
      </div>

      <div class="input-group">
        <input
          v-model="password"
          class="field"
          type="password"
          placeholder="请输入密码"
          @keyup.enter="onSubmit"
        />
      </div>

      <div class="flex flex-center-sb form-links">
        <div class="font-size-12 font-color-black font-w-bold" @click="router.push('/pages/login/forgetPwd')">
          忘记密码？
        </div>
        <div class="register-link" @click="router.push('/pages/login/register')">
          没有账号？<span class="link">去注册</span>
        </div>
      </div>

      <div class="register-button" :class="{ 'is-loading': loading }" @click="onSubmit">
        {{ loading ? '登录中...' : '立即登录' }}
      </div>
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
.login-pass {
  background: #fff;
  min-height: 100vh;
  padding-bottom: 60px;
}
.back-title {
  position: absolute;
  left: 15px;
  top: 31px;
  width: 31px;
  height: 31px;
}
.pass-logo {
  padding-top: 104px;
  display: flex;
  justify-content: center;
}
.pass-logo img { width: 97px; height: 62px; }
.register-form {
  margin: 83px 45px 0;
}
.input-group {
  width: 100%;
  height: 46px;
  border: 1px solid #000;
  border-radius: 26px;
  margin-bottom: 13px;
  display: flex;
  align-items: center;
  padding: 0 20px;
  box-sizing: border-box;
}
.field {
  width: 100%;
  font-size: var(--gd-fs-14);
  color: #333;
}
.field::placeholder { color: #818181; }
.form-links {
  margin-top: 4px;
  font-size: var(--gd-fs-12);
}
.register-link { color: #999; font-weight: 700; }
.register-link .link { color: #000; text-decoration: underline; }
.register-button {
  margin-top: 39px;
  width: 100%;
  height: 48px;
  border-radius: 26px;
  background: #000;
  color: #fff;
  font-size: var(--gd-fs-15);
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.register-button.is-loading { opacity: 0.7; }
.agreement {
  margin: 47px auto 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  color: #7f7f7f;
  font-weight: 500;
  white-space: nowrap;
}
.agreement-image { width: 14px; height: 14px; margin-right: 7px; }
.agreement-color { color: #000; }
</style>