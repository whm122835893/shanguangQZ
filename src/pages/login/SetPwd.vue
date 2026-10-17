<script setup>
/**
 * 设置新密码 —— 1:1 复刻原站 pages/login/setPwd
 *
 * 路由：#/pages/login/setPwd
 *
 * 原站 390x844 实测（body bg #fff）：
 *   u-navbar @0,0 390x44（无返回按钮、无标题）
 *   .login_center @0,44 390x780
 *     标题「设置新密码」@41,71 308x26  18px/600 rgb(35,38,47)
 *     输入框1 @41,244 308x50 br10（仅下边框）「请输入新密码」
 *     输入框2 @41,314 308x50 br10（mt 20）「请再次输入新密码」
 *     提示 @41,364 308x41  10px rgb(122,122,122)
 *       「密码内需同时包含数字、字母，长度在6-12之间」（可换行）
 *     「确认」按钮 @41,454 308x47 br8 bg #000  14px/700 #fff
 *   .agreement @0,824 390x20（页面底部预留位）
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const pwd = ref('')
const pwd2 = ref('')
</script>

<template>
  <div class="set-pwd">
    <div class="navbar">
      <img class="nav-back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
    </div>

    <div class="login-center">
      <div class="title">设置新密码</div>

      <div class="ipt">
        <input v-model="pwd" class="ipt-field" type="password" placeholder="请输入新密码" />
      </div>

      <div class="ipt ipt--mt20">
        <input v-model="pwd2" class="ipt-field" type="password" placeholder="请再次输入新密码" />
      </div>

      <div class="hint">密码内需同时包含数字、字母，长度在6-12之间</div>

      <div class="submit">确认</div>
    </div>

    <div class="agreement"></div>
  </div>
</template>

<style scoped>
.set-pwd {
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
  height: 780px;
  box-sizing: border-box;
}

.title {
  position: absolute;
  left: 41px;
  top: 27px;
  width: 308px;
  height: 26px;
  font-size: 18px;
  font-weight: 600;
  line-height: 26px;
  color: rgb(35, 38, 47);
  text-align: center;
}

/* 输入框 @41,244 / @41,314 308x50 仅下边框 */
.ipt {
  position: absolute;
  left: 41px;
  top: 200px;
  width: 308px;
  height: 50px;
  box-sizing: border-box;
  border: 1px solid rgb(207, 207, 207);
  border-radius: 10px;
}
.ipt--mt20 {
  top: 270px;
}
.ipt-field {
  width: 308px;
  height: 50px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 15px;
  line-height: 50px;
  color: rgb(35, 38, 47);
  font-family: inherit;
}
.ipt-field::placeholder {
  color: rgb(153, 153, 153);
}

.hint {
  position: absolute;
  left: 41px;
  top: 334px;
  width: 308px;
  height: 41px;
  font-size: 10px;
  line-height: 15px;
  color: rgb(122, 122, 122);
}

/* 确认 @41,454 308x47 */
.submit {
  position: absolute;
  left: 41px;
  top: 410px;
  width: 308px;
  height: 47px;
  border-radius: 8px;
  background: #000;
  font-size: 14px;
  font-weight: 700;
  line-height: 47px;
  color: #fff;
  text-align: center;
  cursor: pointer;
}

.agreement {
  position: absolute;
  left: 0;
  top: 824px;
  width: 390px;
  height: 20px;
}
</style>