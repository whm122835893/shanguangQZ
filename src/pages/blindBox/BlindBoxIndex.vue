<script setup>
/**
 * 我的盲盒 —— 1:1 复刻原站 pages/blindBox/index
 *
 * 路由：#/pages/blindBox/index
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle，2026-10-15）：
 *   .container.page-container.page-bg.flex.flex-dir-col  @0,0 390x844  bg #fbebeb
 *     .u-navbar--fixed          @0,0    390x44   bg #fafafa
 *       返回 back-btn-white.png  @13,10  24x24（浅粉底上用白色返回键）
 *       标题「我的盲盒」         @165,16 60x16  15px/700 #fff
 *     .content.flex-1           @0,44   390x733
 *       .con.flex.flex-dir-col.flex-ai-center.flex-js-center  @29,236 332x349
 *         .listimg              @29,236 332x234  bg #f1c9c9(占位)
 *                                background: /static/bg/product-bg-tz.png
 *         .num-view             @109.5,470 171x28  bg /static/bg/blind-num-bg.png
 *           「共有0个」          @177,478  12px/500 #fff（数字为 0）
 *         .btnStyle2.mt-16      @65,514 260x45  bg #000  br22  「一键开启」14px/700 #fff
 *         提示「一次最多打开10个盲盒」@135,570 120x14  12px/400 #23262f
 *     .bg-btns.ptb-10           @0,777  390x67（fixed 底栏，pad 10/0）
 *       .flex-center-sb.plr-20  @0,787  390x47  pad 0 20
 *         「立即寄售」           @20,787 166x47  bg #f1f0f0  br26  14px/700 #999
 *         「逐个拆开」           @204,787 166x47 bg #000      br26  14px/700 #fff
 *
 * 空态说明：账号当前无盲盒，故 num-view 文案为「共有0个」，
 *           主按钮与提示仍照常渲染（与原站一致，未做隐藏）。
 *
 * 接口（原站 chunk 实测）：POST /user_collection/user_collection/antMyCollection
 *   body { types: 1, page: 1 } → 盲盒持仓列表
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMyCollection } from '@/api'
import { oss } from '@/api/http'

const router = useRouter()

/* 盲盒持仓：types=1 为盲盒 */
const list = ref([])

const total = computed(() => list.value.length)

/* 展示封面：盲盒图，无则回落空态占位背景 */
const cover = computed(() => {
  const it = list.value[0]
  if (!it) return ''
  const c = it.collection || it.product || it
  const raw = c.listimg || c.main_image || ''
  return raw ? oss(raw) : ''
})

const load = async () => {
  try {
    const res = await getMyCollection({ types: 1, page: 1 })
    const d = res.data
    list.value = (d && (d.data || d.list)) || []
  } catch (e) {
    list.value = []
  }
}

const openAll = () => {
  /* 原站在此拉起批量开启确认弹窗，空态无可开项 */
}

const openOne = () => {
  /* 原站进入逐个拆开流程 */
}

const toResale = () => {
  router.push('/pages/consignments/consignments')
}

onMounted(load)
</script>

<template>
  <div class="bb-page">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="navbar-left">
        <img src="/static/icon/back-btn-white.png" alt="" class="back-btn" @click="router.back()" />
      </div>
      <div class="navbar-title">我的盲盒</div>
    </div>

    <!-- 内容区 @0,44 390x733 -->
    <div class="content">
      <div class="con">
        <!-- 盲盒图 @29,236 332x234 -->
        <div
          class="listimg"
          :style="cover ? { backgroundImage: `url(${cover})`, backgroundSize: '100%' } : null"
        ></div>

        <!-- 数量条 @109.5,470 171x28 -->
        <div class="num-view">共有{{ total }}个</div>

        <!-- 主按钮 @65,514 260x45 -->
        <div class="open-all" @click="openAll">一键开启</div>
        <div class="open-tip">一次最多打开10个盲盒</div>
      </div>
    </div>

    <!-- 底栏 @0,777 390x67 -->
    <div class="btns">
      <div class="btn btn--ghost" @click="toResale">立即寄售</div>
      <div class="btn btn--dark" @click="openOne">逐个拆开</div>
    </div>
  </div>
</template>

<style scoped>
.bb-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fbebeb;
  overflow: hidden;
}

/* ---------- 导航 ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  background: #fafafa;
}
.navbar-left {
  width: 50px;
  height: 44px;
  padding-left: 13px;
  display: flex;
  align-items: center;
  box-sizing: border-box;
}
.back-btn {
  display: block;
  width: 24px;
  height: 24px;
}
.navbar-title {
  position: absolute;
  left: 165px;
  top: 16px;
  width: 60px;
  height: 16px;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
  line-height: 16px;
  text-align: center;
}

/* ---------- 内容 ---------- */
.content {
  position: relative;
  width: 390px;
  height: 733px;
}
.con {
  position: absolute;
  left: 29px;
  top: 192px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: flex-start;
  width: 332px;
  height: 349px;
}

/* 盲盒图 @29,236 332x234 */
.listimg {
  width: 332px;
  height: 234px;
  background: #f1c9c9 url('/static/bg/product-bg-tz.png') no-repeat center center;
  background-size: 100% 100%;
}

/* 数量条 @109.5,470 171x28 —— 用背景图承载胶囊底，文字居中 */
.num-view {
  width: 171px;
  height: 28px;
  margin-top: 0;
  background: url('/static/bg/blind-num-bg.png') no-repeat center center;
  background-size: 100% 100%;
  font-size: 12px;
  font-weight: 500;
  color: #fff;
  line-height: 28px;
  text-align: center;
  box-sizing: border-box;
}

/* 主按钮 @65,514 260x45 br22 */
.open-all {
  width: 260px;
  height: 45px;
  margin-top: 16px;
  background: #000;
  border-radius: 22px;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  line-height: 45px;
  text-align: center;
  box-sizing: border-box;
}
/* 提示 @135,570 120x14 */
.open-tip {
  width: 120px;
  height: 14px;
  margin-top: 11px;
  font-size: 12px;
  font-weight: 400;
  color: #23262f;
  line-height: 14px;
  text-align: center;
}

/* ---------- 底栏 @0,777 390x67 ---------- */
.btns {
  position: absolute;
  left: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 390px;
  height: 67px;
  padding: 10px 20px;
  box-sizing: border-box;
}
.btn {
  width: 166px;
  height: 47px;
  border-radius: 26px;
  font-size: 14px;
  font-weight: 700;
  line-height: 47px;
  text-align: center;
  box-sizing: border-box;
}
.btn--ghost {
  background: #f1f0f0;
  color: #999;
}
.btn--dark {
  background: #000;
  color: #fff;
}
</style>
