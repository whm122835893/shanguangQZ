<script setup>
/**
 * 兑换记录 —— 100% 复刻原站 pages/shop/record
 *
 * 原站 390x844 实测：
 *   body bg #fff，页面高 844
 *   u-navbar @0,0 390x44
 *     返回图标 @13,10 24x24（/static/icon/back-btn-white.png）
 *     标题「兑换记录」@91,11 208x22 → 文字 @165,16 60x16  15px/700  rgb(51,51,51)
 *   空态 @16,268 358x100（mt-12，父级再上移 208px）
 *     search-empty.png @134.5,268 121x100
 *   原站当前无兑换记录数据，故页面除导航栏与空态图外无其他内容
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="record-page">
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn-white.png" alt="返回" />
      </div>
      <div class="nav-title">兑换记录</div>
    </div>
    <div class="empty">
      <img src="/static/empty/search-empty.png" alt="暂无记录" />
    </div>
  </div>
</template>

<style scoped>
.record-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  min-height: 844px;
  background: #fff;
  color: rgb(74, 74, 74);
  font-family: 'Times New Roman', serif;
  font-size: 15px;
  overflow: hidden;
}
/* 导航栏 @0,0 390x44 */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.nav-back img {
  width: 24px;
  height: 24px;
}
/* 标题 @91,11 208x22 */
.nav-title {
  position: absolute;
  left: 91px;
  top: 11px;
  width: 208px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: rgb(51, 51, 51);
}
/* 空态 @16,268 358x100 */
.empty {
  position: absolute;
  left: 16px;
  top: 268px;
  width: 358px;
  height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.empty img {
  width: 121px;
  height: 100px;
}
</style>
