<script setup>
/**
 * 分类管理 —— 1:1 复刻原站 pages/market/categoryManager
 *
 * 原站 390x844 实测：
 *   .container.page-bg-colorfull  390x844 bg #fff
 *   .u-navbar--fixed              @0,0  390x44（透明）
 *     返回 back-btn.png          @13,10 24x24
 *     标题「分类」                @180,16 30x16 15px/700 #333
 *     右侧「确认」                @353,15.5 24x14 12px #333
 *
 *   第一组 .pa-16 @0,44 390x102
 *     「我的分类」                @16,60 358x21 14px #333
 *     胶囊行 .flex.gap-12        @16,81 358x49
 *       胶囊 .ptb-8.plr-12.br-44  110x37  bg #fff  br44 padding 8px 12px
 *         @16,93「我的关注」/ @138,93「推荐」/ @260,93「全部」
 *         文字 14px #333，水平居中
 *
 *   第二组 .pa-16 @0,146 390x53
 *     「其他分类」                @16,162 358x21 14px #333
 *     （当前账号无其他分类，列表为空）
 *
 * 接口（原站实测）：
 *   GET  /index/getSeries?type=10
 *   GET  /index/getSeries?type=20
 *   GET  /index/customSeriesList
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* 原站实测固定三项；「其他分类」分组当前为空 */
const myCats = ['我的关注', '推荐', '全部']
const otherCats = ref([])
</script>

<template>
  <div class="container page-container page-bg-colorfull">
    <!-- 导航 @0,0 390x44 -->
    <div class="u-navbar--fixed">
      <div class="u-navbar__content">
        <div class="u-navbar__content__left">
          <div class="flex-center" @click="router.back()">
            <img class="back-img" src="/static/icon/back-btn.png" alt="返回" />
          </div>
        </div>
        <span class="nav-title">分类</span>
        <div class="u-navbar__content__right">
          <div class="right-inner" @click="router.back()">确认</div>
        </div>
      </div>
    </div>

    <!-- 第一组 @0,44 390x102 -->
    <div class="pa-16 group-1">
      <div class="font-size-14 font-color-textPrimary group-title">我的分类</div>
      <div class="flex flex-w gap-12 cat-row">
        <div
          v-for="c in myCats"
          :key="c"
          class="ptb-8 plr-12 mt-12 flex flex-ai-center bg-color-item font-size-14 font-color-textPrimary cat-pill"
        >
          {{ c }}
        </div>
      </div>
    </div>

    <!-- 第二组 @0,146 390x53 -->
    <div class="pa-16 group-2">
      <div class="font-size-14 font-color-textPrimary group-title">其他分类</div>
      <div v-if="otherCats.length" class="flex flex-w gap-12 cat-row">
        <div
          v-for="c in otherCats"
          :key="c"
          class="ptb-8 plr-12 mt-12 flex flex-ai-center bg-color-item font-size-14 font-color-textPrimary cat-pill"
        >
          {{ c }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.container {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fff;
  box-sizing: border-box;
  overflow: hidden;
}

.flex { display: flex; }
.flex-w { flex-wrap: wrap; }
.flex-center { display: flex; align-items: center; justify-content: center; }
.flex-ai-center { align-items: center; }
.pa-16 { padding: 16px; }
.ptb-8 { padding: 8px 0; }
.plr-12 { padding-left: 12px; padding-right: 12px; }
.mt-12 { margin-top: 12px; }
.gap-12 { gap: 12px; }
.font-size-14 { font-size: 14px; }
.font-color-textPrimary { color: #333; }
.bg-color-item { background: #fff; }

/* 导航 @0,0 390x44 */
.u-navbar--fixed {
  position: fixed;
  left: 0;
  top: 0;
  width: 100%;
  height: 44px;
  z-index: 20;
}

.u-navbar__content {
  position: relative;
  height: 44px;
  display: flex;
  align-items: center;
}

.u-navbar__content__left {
  position: absolute;
  left: 0;
  top: 0;
  width: 50px;
  height: 44px;
  padding: 0 13px;
  box-sizing: border-box;
}

.back-img {
  width: 24px;
  height: 24px;
  object-fit: fill;
  display: block;
}

.nav-title {
  position: absolute;
  left: 50%;
  top: 16px;
  transform: translateX(-50%);
  font-size: 15px;
  line-height: 16px;
  font-weight: 700;
  color: #333;
  white-space: nowrap;
}

.u-navbar__content__right {
  position: absolute;
  right: 0;
  top: 0;
  width: 50px;
  height: 44px;
  padding: 0 13px;
  box-sizing: border-box;
}

.right-inner {
  width: 24px;
  height: 19px;
  font-size: 12px;
  line-height: 14px;
  color: #333;
  text-align: center;
  white-space: nowrap;
}

/* 第一组 @0,44 390x102 */
.group-1 {
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  height: 102px;
  box-sizing: border-box;
}

.group-title {
  width: 358px;
  height: 21px;
  font-size: 14px;
  line-height: 21px;
  color: #333;
}

/* 胶囊行 @16,81 358x49 */
.cat-row {
  width: 358px;
  height: 49px;
  margin-top: 0;
}

/* 胶囊 110x37 br44 */
.cat-pill {
  width: 110px;
  height: 37px;
  border-radius: 44px;
  background: #fff;
  font-size: 14px;
  color: #333;
  justify-content: center;
  box-sizing: border-box;
}

/* 第二组 @0,146 390x53 */
.group-2 {
  position: absolute;
  left: 0;
  top: 146px;
  width: 390px;
  min-height: 53px;
  box-sizing: border-box;
}
</style>
