<script setup>
/**
 * 搜索 —— 1:1 复刻原站 pages/market/search
 *
 * 原站实测：
 *   .container 390x844 白底
 *   返回 back-btn.png @16,10 24x24   ← 注意：本页返回按钮左边距 16（其他页 13）
 *   搜索框 u-input.u-border.u-input--radius @62,3.5 312x37
 *     bg rgb(255,255,255)  圆角100  border 1px solid rgb(255,...)
 *     放大镜 search3.png @72,10 24x24（原图32x31）
 *     placeholder「请输入内容」@100,9 264x26  15px/400 rgb(192,196,204) ta:left
 *
 *   类型 Tab：
 *     「数字藏品」.goods-type-item.active @136,62 64x24  16px/400 rgb(51,51,51)（选中）
 *     「盲盒」    .goods-type-item       @226,63.5 28x21  14px/400 rgb(122,122,122)
 *
 * 注：搜索框上边y=3.5，紧贴顶部（原站搜索框与返回按钮同行）。
 *     两 Tab 字号不同（16/14），按实测照抄，不做统一。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const keyword = ref('')
/* 类型：collection=数字藏品 box=盲盒 */
const activeType = ref('collection')
</script>

<template>
  <div class="search-page">
    <!-- 搜索框@62,3.5 312x37 + 返回@16,10 -->
    <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
    <div class="search-box">
      <img class="search-icon" src="/static/icon/search3.png" alt="搜索" />
      <input class="search-input" type="text" v-model="keyword" placeholder="请输入内容" />
    </div>

    <!-- 类型 Tab：数字藏品@136,62 /盲盒@226,63.5 -->
    <div class="type-tabs">
      <div class="type-tab" :class="{ active: activeType === 'collection' }" @click="activeType = 'collection'">
        数字藏品
      </div>
      <div class="type-tab type-tab-box" :class="{ active: activeType === 'box' }" @click="activeType = 'box'">
        盲盒
      </div>
    </div>
  </div>
</template>

<style scoped>
/* .container 390x844 白底 */
.search-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
  overflow: hidden;
}

/* 返回@16,10 24x24（本页左边距 16） */
.back { position: absolute; left: 16px; top: 10px; width: 24px; height: 24px; z-index: 3; }

/* 搜索框@62,3.5 312x37 圆角100 bg #fff */
.search-box {
  position: absolute;
  left: 62px;
  top: 3.5px;
  width: 312px;
  height: 37px;
  box-sizing: border-box;
  padding-left: 22px;
  border: 1px solid rgba(255, 255, 255, 1);
  border-radius: 100px;
  background: #fff;
  display: flex;
  align-items: center;
}
/* 放大镜@72,10 24x24 */
.search-icon { width: 24px; height: 24px; flex-shrink: 0; }
/* 输入区：placeholder 15px rgb(192,196,204) ta:left */
.search-input {
  flex: 1;
  margin-left: 4px;
  height: 26px;
  border: none;
  outline: none;
  background: transparent;
  text-align: left;
  color: #333;
  font-size: 15px;
  font-weight: 400;
  line-height: 26px;
  padding: 0;
}
.search-input::placeholder {
  color: rgb(192, 196, 204);
  font-size: 15px;
  text-align: left;
}

/* 类型 Tab 容器 */
.type-tabs { position: absolute; left: 0; top: 58px; width: 390px; height: 30px; }
/* 「数字藏品」@136,62 64x24  16px rgb(51,51,51) */
.type-tab {
  position: absolute;
  left: 136px;
  top: 4px;
  width: 64px;
  height: 24px;
  line-height: 24px;
  text-align: center;
  color: #999;
  font-size: 16px;
  font-weight: 400;
  cursor: pointer;
}
/* 「盲盒」@226,63.5 28x21  14px rgb(122,122,122)（字号与选中态不同，按实测） */
.type-tab-box {
  left: 226px;
  top: 5.5px;
  width: 40px;
  height: 21px;
  line-height: 21px;
  font-size: 14px;
}
/* 选中态 rgb(51,51,51) */
.type-tab.active { color: #333; }
</style>