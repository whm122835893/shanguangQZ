<script setup>
/**
 * 我的钱包 —— 1:1 复刻原站 pages/wallet/wallet
 *
 * 原站实测：
 *   .container 390x844
 *   返回图标 uni-btn-icon @5,6 27x27（同 invitation 页，uni-page-head 样式）
 *   标题「我的钱包」@70,7 250x30  16px/700 rgb(0,0,0) ta:center
 *
 *   钱包图标 wallet.png @32,102 52x52（原图100x100）
 *   「账户A」        @98,108.5 120x21  14px/400 rgb(255,255,255)
 *   「支持多银行卡，提现快」@98,129.5 120x18  12px/400 rgb(255,255,255)
 *   「立即开通」按钮  @284,116 72x24  12px/400 rgb(51,51,51)
 *     bg rgb(255,255,255)  圆角12
 *
 * 注：账户区为白字，说明该区块底部是深色渐变卡片背景（探测时容器底色透明，
 *     卡片背景由父级渐变提供）。实测账户A/说明/按钮三者在同一水平带内。
 */
import { useRouter } from 'vue-router'
import { showToast } from 'vant'

const router = useRouter()

function onOpen() {
  showToast({ icon: 'none', message: '原站未提供银行卡开通页' })
}
</script>

<template>
  <div class="wallet-page">
    <!-- 标题栏@70,7 250x30 -->
    <div class="page-head">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="head-title">我的钱包</div>
    </div>

    <!-- 账户卡片：图标@32,102 52x52 + 文本@98 + 按钮@284,116 -->
    <div class="acct-card">
      <img class="wallet-icon" src="/static/icon/wallet.png" alt="钱包" />
      <div class="acct-text">
        <div class="acct-name">账户A</div>
        <div class="acct-desc">支持多银行卡，提现快</div>
      </div>
      <div class="open-btn" @click="onOpen">立即开通</div>
    </div>
  </div>
</template>

<style scoped>
/* .container 390x844 */
.wallet-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: rgb(250, 250, 250);
  overflow: hidden;
}

/* 标题栏：返回@5,6 27x27，标题@70,7 250x30 */
.page-head { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 5px; top: 6px; width: 27px; height: 27px; z-index: 3; }
.head-title {
  position: absolute; left: 70px; top: 7px; width: 250px; height: 30px;
  line-height: 30px; text-align: center; color: #000; font-size: 16px; font-weight: 700;
}

/* 账户卡片：整体承载深色渐变底，原站文本为白字 */
/* 黑卡实测@16,75 356x106 bg rgb(0,0,0) */
.acct-card {
  position: absolute;
  left: 16px;
  top: 75px;
  width: 356px;
  height: 106px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  padding-left: 16px;
  background: rgb(0, 0, 0);
}
/* 钱包图标@32,102 52x52 */
.wallet-icon { width: 52px; height: 52px; flex-shrink: 0; }
/* 文本组@98：名称@98,108.5 说明@98,129.5 */
.acct-text { margin-left: 14px; flex: 1; }
.acct-name {
  color: #fff;
  font-size: 14px;
  font-weight: 400;
  line-height: 21px;
}
.acct-desc {
  color: #fff;
  font-size: 12px;
  font-weight: 400;
  line-height: 18px;
}
/* 「立即开通」@284,116 72x24 bg #fff 圆角12 */
.open-btn {
  width: 72px;
  height: 24px;
  line-height: 24px;
  text-align: center;
  background: #fff;
  border-radius: 12px;
  color: #333;
  font-size: 12px;
  font-weight: 400;
  flex-shrink: 0;
  cursor: pointer;
}
</style>