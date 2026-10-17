<script setup>
/**
 * 盲盒结果（单藏品）—— 1:1 复刻原站 pages/blindBox/result2
 *
 * 路由：#/pages/blindBox/result2
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg-colorfull @0,0 390x844  bg #fff
 *     .u-navbar--fixed        @0,0 390x44  bg #fff
 *       返回 back-btn.png     @13,10 24x24
 *       标题「盲盒结果」      @165,16 60x16  15px/700 rgb(51,51,51)
 *     .content                @0,44 390x194（无背景）
 *       .font-size-12.mt-4    @162,102 66x18  12px/500 rgb(153,153,153)
 *         「藏品编号：#」
 *     .footer.bg-color-item   @0,756 390x88  bg rgb(255,188,205)
 *       「我的藏品」(mr-10)    @20,776.5 166x47 bg #f1f0f0 br26 14px/700 #999
 *       「继续购买」           @196,776.5 166x47 bg #000     br26 14px/700 #fff
 *
 * 空态说明：无参数进入时藏品编号为空，仅渲染「#」占位（原站同样表现）。
 */
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* 藏品编号：原站由页面参数传入 */
const code = computed(() => '')

onMounted(() => {
  /* 无参数时保持空态 */
})

const toMyCollection = () => {
  router.push('/pages/myCollection/myCollection')
}

const toBuy = () => {
  router.push('/pages/market/market')
}
</script>

<template>
  <div class="bb-result2">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">盲盒结果</div>
    </div>

    <!-- 内容 @0,44 390x194 -->
    <div class="content">
      <div class="code">藏品编号：<span class="code-hash">#{{ code }}</span></div>
    </div>

    <!-- 底栏 @0,756 390x88 -->
    <div class="footer">
      <div class="fbtn fbtn--ghost" @click="toMyCollection">我的藏品</div>
      <div class="fbtn fbtn--dark" @click="toBuy">继续购买</div>
    </div>
  </div>
</template>

<style scoped>
.bb-result2 {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fff;
  overflow: hidden;
}

/* ---------- 导航 @0,0 390x44 ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  background: #fff;
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

/* ---------- 内容 @0,44 390x194 ---------- */
.content {
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  height: 194px;
}
/* 「藏品编号：#」@162,102 66x18 —— 整块水平居中 */
.code {
  position: absolute;
  left: 0;
  top: 58px;
  width: 390px;
  height: 18px;
  font-size: 12px;
  font-weight: 500;
  color: rgb(153, 153, 153);
  line-height: 18px;
  text-align: center;
}
.code-hash {
  font-weight: 500;
}

/* ---------- 底栏 @0,756 390x88 ---------- */
.footer {
  position: absolute;
  left: 0;
  top: 756px;
  width: 390px;
  height: 88px;
  background: rgb(255, 188, 205);
}
.fbtn {
  position: absolute;
  top: 20.5px;
  width: 166px;
  height: 47px;
  border-radius: 26px;
  font-size: 14px;
  font-weight: 700;
  line-height: 47px;
  text-align: center;
  box-sizing: border-box;
}
.fbtn--ghost {
  left: 20px;
  background: #f1f0f0;
  color: rgb(153, 153, 153);
}
.fbtn--dark {
  left: 196px;
  background: #000;
  color: #fff;
}
</style>
