<script setup>
/**
 * 设置操作密码 —— 1:1 复刻原站 pages/my/actionPwd/setPwd
 * 由 设置页「操作密码」进入。
 *
 * 原站实测（getBoundingClientRect + getComputedStyle）：
 *   .container 390x844 白底 plr-13
 *   返回 back-btn.png @13,10 24x24
 *   导航标题「设置操作密码」@150,16 90x16  15px/700 rgb(51,51,51) ta:center
 *
 *   表单区.plr-16 ptb-80 mt-80 @13,127 364x331（padding-left 16）
 *   u-form @29,210 332x138
 *   输入框1 u-input @29,220 332x37  border-bottom 1px solid rgb(218,2xx)  padding-left 9px
 *     └ 实际 input @38,226 314x24  15px/400 rgb(51,51,51)  line-height 26px  type=password
 *     └ placeholder @38,225 314x26  15px/400 rgb(192,196,204)
 *   输入框2 u-form-item mt-24 @29,291 332x57 → u-input @29,301 332x37
 *     └ 实际 input @38,307 314x24  placeholder「请确认操作新密码」
 *   提示「请输入6位数字密码」@29,360 332x15  10px/400 rgb(122,122,122)
 *
 *   .action @0,773 390x71（padding 12）固定底部
 *   确认按钮 @12,773 366x47  bg rgb(227,227,227)  14px/700 rgb(102,102,102)
 *
 * 注：原站输入框 type=password（密文），占位符为浅灰 rgb(192,196,204)。
 *     两框均为 6 位数字密码，第二个用于确认。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'

const router = useRouter()

const pwd = ref('')
const confirmPwd = ref('')

function onSubmit() {
  if (!/^\d{6}$/.test(pwd.value)) {
    return showToast({ icon: 'none', message: '请输入6位数字密码' })
  }
  if (pwd.value !== confirmPwd.value) {
    return showToast({ icon: 'none', message: '两次输入的密码不一致' })
  }
  showToast({ icon: 'none', message: '操作密码设置成功' })
}
</script>

<template>
  <div class="setpwd-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">设置操作密码</div>
    </div>

    <!-- 表单区@29,210 332x138 -->
    <div class="form">
      <!-- 输入框1：外框@29,220 332x37（含9px 左内边距 + 底部边框），实际 input@38,226 314x24 -->
      <div class="field">
        <input
          class="input"
          type="password"
          v-model="pwd"
          maxlength="6"
          placeholder="请输入操作密码"
          inputmode="numeric"
        />
      </div>
      <!-- 输入框2：mt-24 → @29,301 332x37 -->
      <div class="field field-2">
        <input
          class="input"
          type="password"
          v-model="confirmPwd"
          maxlength="6"
          placeholder="请确认操作新密码"
          inputmode="numeric"
        />
      </div>
      <!-- 提示@29,360 332x15 -->
      <div class="tip">请输入6位数字密码</div>
    </div>

    <!-- 底部操作区@0,773 390x71 -->
    <div class="action">
      <div class="submit-btn" @click="onSubmit">确认</div>
    </div>
  </div>
</template>

<style scoped>
/* .container 390x844 白底 */
.setpwd-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
  overflow: hidden;
}

/* 导航标题「设置操作密码」@150,16 90x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 表单区@29,210 332x138 */
.form {
  position: absolute;
  left: 29px;
  top: 210px;
  width: 332px;
  height: 138px;
}

/* u-input 外框 332x37：左内边距 9px（故实际 input 左移到 38），底部 1px 边框 */
.field {
  position: absolute;
  left: 0;
  top: 10px;
  width: 332px;
  height: 37px;
  box-sizing: border-box;
  padding-left: 9px;
  border-bottom: 1px solid rgb(218, 218, 223);
}
/* 第二框 mt-24：@29,301（相对表单区 top=291） */
.field-2 { top: 91px; }

.input {
  width: 314px;
  height: 24px;
  margin-top: 6px;      /* 使 input 顶部落在 226（226-220=6） */
  border: none;
  outline: none;
  background: transparent;
  color: #333;
  font-size: 15px;
  font-weight: 400;
  line-height: 26px;
  padding: 0;
}
.input::placeholder {
  color: rgb(192, 196, 204);
  font-size: 15px;
  font-weight: 400;
}

/* 提示「请输入6位数字密码」@29,360 332x15 */
.tip {
  position: absolute;
  left: 0;
  top: 150px;
  width: 332px;
  height: 15px;
  color: rgb(122, 122, 122);
  font-size: 10px;
  font-weight: 400;
  line-height: 15px;
}

/* 底部操作区@0,773 390x71（padding 12） */
.action {
  position: absolute;
  left: 0;
  top: 773px;
  width: 390px;
  height: 71px;
  padding: 0 12px;
  box-sizing: border-box;
}
/* 确认按钮 366x47 bg rgb(227,227,227) */
.submit-btn {
  width: 366px;
  height: 47px;
  line-height: 47px;
  text-align: center;
  background: rgb(227, 227, 227);
  border-radius: 24px;
  color: #666;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
</style>