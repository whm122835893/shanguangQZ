<script setup>
/**
 * 注销账号申请 —— 1:1 复刻原站 pages/my/cancellation
 * 由 设置页「注销账号」进入。
 *
 * 原站登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container 高 844 白底
 *   返回 back-btn.png@13,10 24x24
 *   导航标题「注销账号申请」@150,16 90x16  15px/700 rgb(51,51,51)
 *
 *   协议勾选框 .u-checkbox__icon-wrap @52,739 16x16
 *     bg #fff  border 1px solid rgb(200,201,..)
 *     勾号字体图标 12px/400
 *   协议文案「我已阅读并知晓，愿意承担注销账户带来的后果。」
 *     12px/400  紧随勾选框右侧
 *   确认按钮「确认注销」@12,773 366x47  bg rgb(227,227,227)  14px/700 rgb(102,102,102)
 *
 * 注：原站本页面板为空（仅导航 + 勾选 + 按钮），协议文案为固定文案，
 *     不从接口拉取。勾选框默认为未勾选态（实测边框存在但无勾号）。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { showToast, showConfirmDialog } from 'vant'

const router = useRouter()

/* 协议勾选态，原站实测默认未勾选 */
const agreed = ref(false)

function onSubmit() {
  if (!agreed.value) {
    return showToast({ icon: 'none', message: '请先阅读并同意相关协议' })
  }
  showConfirmDialog({ title: '提示', message: '注销后账号数据将无法恢复，确定要注销吗？' })
    .then(() => showToast({ icon: 'none', message: '注销申请已提交' }))
    .catch(() => {})
}
</script>

<template>
  <div class="cancel-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">注销账号申请</div>
    </div>

    <!-- 协议勾选行@52,739 勾选框 16x16，文案右侧 -->
    <div class="agreement" @click="agreed = !agreed">
      <span class="checkbox" :class="{ checked: agreed }">
        <!-- 勾号用内联 SVG（遵循 P0 规则，不用 emoji/字符） -->
        <svg v-if="agreed" viewBox="0 0 12 12" width="12" height="12">
          <path d="M2 6.2 L4.8 9 L10 3.4" fill="none" stroke="#fff" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </span>
      <span class="agree-text">我已阅读并知晓，愿意承担注销账户带来的后果。</span>
    </div>

    <!-- 确认按钮 @12,773 366x47 -->
    <div class="submit-btn" @click="onSubmit">确认注销</div>
  </div>
</template>

<style scoped>
/* .container 高 844 白底 */
.cancel-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航：标题「注销账号申请」@150,16 90x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 协议行@52,739 勾选框 16x16 */
.agreement {
  position: absolute;
  left: 52px;
  top: 739px;
  width: 306px;
  height: 16px;
  display: flex;
  align-items: center;
}
/* 勾选框 16x16 bg #fff border 1px solid rgb(200,201,..) */
.checkbox {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  box-sizing: border-box;
  border: 1px solid rgb(200, 201, 204);
  border-radius: 50%;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.checkbox.checked {
  background: #1989fa;
  border-color: #1989fa;
}
/* 协议文案 12px/400，紧随勾选框右侧 */
.agree-text {
  margin-left: 4px;
  color: #999;
  font-size: 12px;
  font-weight: 400;
  line-height: 16px;
}

/* 确认按钮 @12,773 366x47 bg rgb(227,227,227) */
.submit-btn {
  position: absolute;
  left: 12px;
  top: 773px;
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