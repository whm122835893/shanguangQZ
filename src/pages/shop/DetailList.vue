<script setup>
/**
 * 水晶明细 —— 1:1 复刻原站 pages/shop/detailList
 *
 * 路由：#/pages/shop/detailList
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg @0,0 390x844  bg #fafafa  padding-bottom 52
 *     .u-sticky__content          @0,0 390x86
 *       background: /static/bg/my-bg.png  size 100%  pos 50% 0  repeat
 *       .u-navbar--fixed          @0,0 390x44
 *         返回 back-btn.png       @13,10 24x24
 *         标题「水晶明细」        @165,16 60x16  15px/700 rgb(51,51,51)
 *       .tab                      @0,44 390x42  padding 10px 62px
 *         .u-tabs                 @62,54 266x22（3 项，各 104 宽）
 *           「全部」              @99,54 30x22  15px/400 rgb(65,65,65)
 *           「水晶获取」          @188,54 60x22
 *           「水晶消耗」          @292,54 60x22
 *         .nav__line               @105,71 18x3  /static/bg/vector.png  br 100px
 *         more-cate.png           @358,57 16x16（右侧分类入口）
 *     空态                        @0,294 390x100
 *       search-empty.png          @135,294 121x100
 *
 * 数据说明：账号当前三个 tab 均无水晶流水，故均为空态。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* 当前 tab：0 全部 / 1 水晶获取 / 2 水晶消耗 */
const active = ref(0)

/* 三 tab 数据：原站接口返回为空 */
const allList = ref([])
const getList = ref([])
const costList = ref([])

const TABS = ['全部', '水晶获取', '水晶消耗']

/* 指示线左移量：tab 宽 104，居中后 line 宽 18 → 偏移 = idx*104 + 43 */
const lineLeft = () => active.value * 104 + 43

const currentList = () => {
  if (active.value === 1) return getList.value
  if (active.value === 2) return costList.value
  return allList.value
}
</script>

<template>
  <div class="detail-list">
    <!-- 粘性头 @0,0 390x86 -->
    <div class="sticky">
      <!-- 导航 @0,0 390x44 -->
      <div class="navbar">
        <div class="nav-back" @click="router.back()">
          <img src="/static/icon/back-btn.png" alt="返回" />
        </div>
        <div class="nav-title">水晶明细</div>
      </div>

      <!-- tab 栏 @0,44 390x42 -->
      <div class="tab">
        <div class="tabs">
          <div
            v-for="(t, i) in TABS"
            :key="t"
            class="tab-item"
            @click="active = i"
          >
            <div class="tab-text">{{ t }}</div>
          </div>
          <div class="tab-line" :style="{ left: lineLeft() + 'px' }"></div>
        </div>
        <div class="more">
          <img src="/static/icon/more-cate.png" alt="分类" />
        </div>
      </div>
    </div>

    <!-- 空态 @0,294 390x100 -->
    <div v-if="currentList().length === 0" class="empty">
      <img src="/static/empty/search-empty.png" alt="暂无记录" />
    </div>
  </div>
</template>

<style scoped>
.detail-list {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fafafa;
  overflow: hidden;
}

/* ---------- 粘性头 @0,0 390x86 ---------- */
.sticky {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 86px;
  background: #fafafa url('/static/bg/my-bg.png') no-repeat 50% 0;
  background-size: 100% auto;
}

/* ---------- 导航 @0,0 390x44 ---------- */
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
  cursor: pointer;
}
.nav-back img {
  display: block;
  width: 24px;
  height: 24px;
}
.nav-title {
  position: absolute;
  left: 165px;
  top: 16px;
  width: 60px;
  height: 16px;
  font-size: 15px;
  font-weight: 700;
  color: rgb(51, 51, 51);
  line-height: 16px;
  text-align: center;
}

/* ---------- tab 栏 @0,44 390x42（padding 10px 62px） ---------- */
.tab {
  position: relative;
  width: 390px;
  height: 42px;
  padding: 10px 62px;
  box-sizing: border-box;
}
.tabs {
  position: relative;
  width: 266px;
  height: 22px;
  display: flex;
}
.tab-item {
  width: 104px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tab-text {
  font-size: 15px;
  font-weight: 400;
  color: rgb(65, 65, 65);
  line-height: 22px;
  white-space: nowrap;
}
/* 指示线 18x3 */
.tab-line {
  position: absolute;
  top: 17px;
  width: 18px;
  height: 3px;
  border-radius: 100px;
  background: url('/static/bg/vector.png') no-repeat center center;
  background-size: cover;
}

/* 右侧分类入口 @358,57 16x16 */
.more {
  position: absolute;
  right: 16px;
  top: 13px;
  width: 16px;
  height: 16px;
  cursor: pointer;
}
.more img {
  display: block;
  width: 16px;
  height: 16px;
}

/* ---------- 空态 @0,294 390x100 ---------- */
.empty {
  position: absolute;
  left: 0;
  top: 294px;
  width: 390px;
  height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.empty img {
  display: block;
  width: 121px;
  height: 100px;
}
</style>