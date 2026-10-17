<script setup>
/**
 * 中签结果 —— 1:1 复刻原站 pages/draw/drawResult/drawResult
 *
 * 路由：#/pages/draw/drawResult/drawResult
 *
 * 原站 390x844 实测：
 *   uni-page-head @0,0 390x44 bg #fff
 *     返回 i @5,6 27x27；标题「中签结果」@70,7 250x30 16px/700 #000
 *   .container @0,44 390x800  bg rgb(245,245,245)
 *     .u-loading-page @0,0 390x844
 *       warpper @172.5,323.5 45x47
 *         loading-icon @185.5,323.5 19x19（转圈圆弧 rgb(200,200,200)）
 *         文字「加载中...」@172.5,352.5 45x18  12px rgb(200,200,200)
 *
 * 说明：该页依赖 drawId 参数，当前无有效数据，原站自身停留在 loading 态。
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="draw-result">
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <svg viewBox="0 0 24 24" width="27" height="27" aria-hidden="true">
          <path
            d="M15 4 L7 12 L15 20"
            fill="none"
            stroke="rgb(0, 0, 0)"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
      </div>
      <div class="nav-title">中签结果</div>
    </div>

    <div class="loading-page">
      <div class="loading-icon"></div>
      <div class="loading-txt">加载中...</div>
    </div>
  </div>
</template>

<style scoped>
.draw-result {
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
  z-index: 10;
  width: 390px;
  height: 44px;
  background: #fff;
}
.nav-back {
  position: absolute;
  left: 5px;
  top: 6px;
  width: 27px;
  height: 27px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.nav-title {
  position: absolute;
  left: 70px;
  top: 7px;
  width: 250px;
  height: 30px;
  font-size: 16px;
  font-weight: 700;
  line-height: 30px;
  color: #000;
  text-align: center;
}

/* loading 页 @0,44 390x800 —— 图标视觉中心 @195,333 */
.loading-page {
  display: flex;
  flex-direction: column;
  align-items: center;
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  height: 800px;
  padding-top: 279.5px;
  box-sizing: border-box;
  background: rgb(245, 245, 245);
}

.loading-icon {
  width: 19px;
  height: 19px;
  border-radius: 50%;
  border: 2px solid rgb(230, 230, 230);
  border-top-color: rgb(200, 200, 200);
  box-sizing: border-box;
}

.loading-txt {
  width: 45px;
  height: 18px;
  margin-top: 10px;
  font-size: 12px;
  line-height: 18px;
  color: rgb(200, 200, 200);
  text-align: center;
}
</style>