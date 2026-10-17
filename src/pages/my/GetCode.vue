<script setup>
/**
 * 验证手机号 —— 1:1 复刻原站 pages/my/actionPwd/getCode?action=reset
 * 由 设置操作密码 / 忘记密码 等入口进入，必须保留 query 参数 action。
 *
 * 原站实测（getBoundingClientRect + getComputedStyle）：
 *   .container 390x844 白底 plr-13
 *   返回 back-btn.png @13,10 24x24
 *   导航标题「验证手机号」@157.5,16 75x16  15px/700 rgb(51,51,51) ta:center
 *
 *   内容区.plr-26 @13,241 364x99（padding-left 27）
 *   手机号 uni-input @40,241 310x33  border-bottom 1px solid rgb(224,224,227)
 *     └ 实际 input @40,247 310x20  14px/400 rgb(171,171,171)  line-height 19.6px  type=text
 *     └ 原站已自动填入当前登录用户手机号 17587881293
 *   验证码行 .flex.ptb-12.mt-20 @40,294 310x46
 *     border-bottom 1px solid rgb(224,224,227)
 *     └ 验证码 input @40,306.7 178x19.6  14px/400 rgb(35,38,47)  line-height 19.6px
 *     └ placeholder「请输入验证码」rgb(171,171,171)
 *     └ 「发送验证码」@280,311 70x15  14px/400 rgb(51,51,51)
 *
 *   .action @0,773 390x71（padding 12）
 *   确认按钮 @12,773 366x47  bg rgb(227,227,227)  14px/700 rgb(102,102,102)
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { showToast } from 'vant'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const route = useRoute()
const userStore = useUserStore()

/* 手机号：原站进入即自动填入当前登录用户手机号 */
const info = computed(() => {
  const u = userStore.userInfo || {}
  return u.data && typeof u.data === 'object' ? u.data : u
})

const mobile = ref(info.value.mobile || '')
const code = ref('')
const sending = ref(false)
const left = ref(60)
let timer = null

/* query 参数 action=reset（重置操作密码）/ 其他值，保留原始行为差异 */
const action = computed(() => route.query.action || 'reset')

/* 兼容 store 异步恢复：进入后若为空则补一次 */
onMounted(() => {
  if (!mobile.value) mobile.value = info.value.mobile || ''
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})

function onSend() {
  if (sending.value) return
  if (!mobile.value) {
    return showToast({ icon: 'none', message: '请输入手机号' })
  }
  sending.value = true
  showToast({ icon: 'none', message: '验证码已发送' })
  left.value = 60
  timer = setInterval(() => {
    left.value -= 1
    if (left.value <= 0) {
      clearInterval(timer)
      timer = null
      sending.value = false
    }
  }, 1000)
}

function onSubmit() {
  if (!mobile.value) {
    return showToast({ icon: 'none', message: '请输入手机号' })
  }
  if (!code.value) {
    return showToast({ icon: 'none', message: '请输入验证码' })
  }
  // action=reset 表示重置操作密码，其他值（如绑定）走同一验证链路
  showToast({ icon: 'none', message: '验证成功' })
}
</script>

<template>
  <div class="getcode-page" :data-action="action">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">验证手机号</div>
    </div>

    <!-- 内容区@13,241 364x99 -->
    <div class="body">
      <!-- 手机号输入：外框@40,241 310x33 底部 1px 边框，实际 input@40,247 310x20 -->
      <div class="mobile-row">
        <input class="mobile-input" type="text" v-model="mobile" placeholder="请输入手机号" />
      </div>

      <!-- 验证码行@40,294 310x46，底部 1px 边框 -->
      <div class="code-row">
        <input class="code-input" type="text" v-model="code" placeholder="请输入验证码" maxlength="6" />
        <span class="send-btn" :class="{ disabled: sending }" @click="onSend">
          {{ sending ? `${left}s` : '发送验证码' }}
        </span>
      </div>
    </div>

    <!-- 底部操作区@0,773 390x71 -->
    <div class="action">
      <div class="submit-btn" @click="onSubmit">确认</div>
    </div>
  </div>
</template>

<style scoped>
/* .container 390x844 白底 */
.getcode-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
  overflow: hidden;
}

/* 导航标题「验证手机号」@157.5,16 75x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 内容区@13,241 364x99（padding-left 27 → 实际左边界 40） */
.body {
  position: absolute;
  left: 0;
  top: 241px;
  width: 390px;
  height: 99px;
  padding-left: 40px;
  box-sizing: border-box;
}

/* 手机号行@40,241 310x33，底部 1px 边框 */
.mobile-row {
  position: absolute;
  left: 40px;
  top: 0;
  width: 310px;
  height: 33px;
  box-sizing: border-box;
  border-bottom: 1px solid rgb(224, 224, 227);
}
.mobile-input {
  width: 310px;
  height: 20px;
  margin-top: 6px;   /* input 顶部落在 247（247-241=6） */
  border: none;
  outline: none;
  background: transparent;
  color: rgb(35, 38, 47);
  font-size: 14px;
  font-weight: 400;
  line-height: 19.6px;
  padding: 0;
}
.mobile-input::placeholder {
  color: rgb(171, 171, 171);
  font-size: 14px;
}

/* 验证码行@40,294 310x46（mt-20），底部 1px 边框 */
.code-row {
  position: absolute;
  left: 40px;
  top: 53px;             /* 294-241=53 */
  width: 310px;
  height: 46px;
  box-sizing: border-box;
  border-bottom: 1px solid rgb(224, 224, 227);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.code-input {
  width: 178px;
  height: 19.6px;
  border: none;
  outline: none;
  background: transparent;
  color: rgb(35, 38, 47);
  font-size: 14px;
  font-weight: 400;
  line-height: 19.6px;
  padding: 0;
}
.code-input::placeholder {
  color: rgb(171, 171, 171);
  font-size: 14px;
}
/* 「发送验证码」@280,311 70x15 */
.send-btn {
  width: 70px;
  height: 15px;
  line-height: 15px;
  text-align: center;
  color: #333;
  font-size: 14px;
  font-weight: 400;
  cursor: pointer;
  flex-shrink: 0;
}
.send-btn.disabled { color: rgb(153, 153, 153); cursor: default; }

/* 底部操作区@0,773 390x71 */
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