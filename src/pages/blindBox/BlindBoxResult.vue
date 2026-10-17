<script setup>
/**
 * 盲盒结果（多藏品）—— 1:1 复刻原站 pages/blindBox/result
 *
 * 路由：#/pages/blindBox/result
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle）：
 *   .container.page-container.page-bg-colorfull @0,0 390x844  bg #fff
 *     .u-navbar--fixed                   @0,0   390x44   bg #fff
 *       返回 back-btn.png                @13,10 24x24
 *       标题「盲盒结果」                 @165,16 60x16  15px/700 rgb(51,51,51)
 *     .content                           @16,44 358x132 bg #fff  br9
 *       .hint                            @29,57 332x15  10px/400 rgb(122,122,122)
 *         「商品陆续从链上转入个人中心，如有延迟请耐心等待」
 *     .footer.bg-color-item.ptb-10       @0,756 390x88   bg rgb(255,188,205)
 *       「我的藏品」                     @20,776.5 166x47 bg #f1f0f0 br26 14px/700 #999
 *       「继续购买」                     @186,776.5 166x47 bg #000    br26 14px/700 #fff
 *
 * 空态说明：账号当前无可展示的开盒结果，故 content 内仅有提示文案，
 *           不渲染藏品卡（与原站当前状态一致）。
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* 开盒结果列表：原站为页面参数传入，本地按空态处理 */
const list = ref([])

const codeText = computed(() => '')

onMounted(() => {
  /* 原站此处等待开盒结果参数；无参数时保持空态 */
})

const toMyCollection = () => {
  router.push('/pages/myCollection/myCollection')
}

const toBuy = () => {
  router.push('/pages/market/market')
}
</script>

<template>
  <div class="bb-result">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">盲盒结果</div>
    </div>

    <!-- 内容 @16,44 358x132 -->
    <div class="content">
      <div class="hint">商品陆续从链上转入个人中心，如有延迟请耐心等待</div>
    </div>

    <!-- 底栏 @0,756 390x88 -->
    <div class="footer">
      <div class="fbtn fbtn--ghost" @click="toMyCollection">我的藏品</div>
      <div class="fbtn fbtn--dark" @click="toBuy">继续购买</div>
    </div>
  </div>
</template>

<style scoped>
.bb-result {
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

/* ---------- 内容 @16,44 358x132 ---------- */
.content {
  position: absolute;
  left: 16px;
  top: 44px;
  width: 358px;
  height: 132px;
  background: #fff;
  border-radius: 9px;
  box-sizing: border-box;
  padding: 13px;
}
.hint {
  width: 332px;
  height: 15px;
  font-size: 10px;
  font-weight: 400;
  color: rgb(122, 122, 122);
  line-height: 15px;
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
  left: 186px;
  background: #000;
  color: #fff;
}
</style>
