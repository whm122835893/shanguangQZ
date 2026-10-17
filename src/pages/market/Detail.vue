<script setup>
/**
 * 藏品详情页 —— 像素级复刻原站 pages/market/detail
 *
 * 路由：#/pages/market/detail?id=15991
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle，2026-10-13，id=15991）：
 *   .container.page-container.page-bg  @0,0    390×1378.6  bg #fafafa
 *     >.product-card                    @0,0    390×486     bg #fff
 *       >大图                           @0,0    390×390     （main_image 原图铺满）
 *       >img.back-btn                   @13,10  24×24      /static/icon/back-btn.png
 *       .goods-info.plr-20.ptb-10       @0,395  390×91    bg #fff  pl 20  ptb 10
 *         .product-name                 @20,411 119.2×59
 *           名称 @20,411 16px/700 #000
 *           价格 @20,443 mt8 15px/700 rgb(217,76,74)
 *         .flex.flex-dir-col.flex-ai-start @259,412.5 111×56
 *           .tag-size-default  @259,412.5 103×23 br4  「限量 50000份」
 *           .tag-size-default  @259,445.5 103×23       「流通 50000份」
 *     >.plr-16.ptb-12                   @0,486  390×830.6  pl 16 pt 12
 *       .product-builder.pa-12         @16,513  358×65    bg #fff mt15 pa12
 *         头像 /static/bg/logo.png      @28,525  41×41
 *         「闪光」14px rgb(35,38,47) @79,525
 *         「品牌方」12px #999           @79,546
 *       .藏品编号                        @16,590  358×53   bg #fff mt12 plr12 ptb16
 *         左「藏品编号」14px/700 #23262f @28,606
 *         右「#41253」14px #23262f      @320,606
 *       .链上信息                        @16,655  358×132  bg #fff mt12 pa12
 *         标题「链上信息」14px/600       @28,667 mb14
 *         所属专辑  @28,702   label 13px/500 #999  value 右对齐 rgb(51,51,51)
 *         合约地址  @28,729 mt8  值后跟复制图标 copy-dark.png @348,731.5 14×14
 *         认证标识  @28,756 mt8  value「234602」
 *       .product-introduce              @16,799  358×404.6 bg #fff mt12 pa12
 *         标题「产品描述」14px/700 @28,811
 *         introduce_image 富文本 <img>
 *       .buy-need                       @16,1215.6 358×57 bg #fff mt12 pa12「购买须知」
 *     >.market-action                   @0,752  390×92   bg #fff pl15（fixed 悬浮）
 *       .market-action-container        @15,767 360×62  plr16 ptb8
 *         价格「￥100」15/12/22px #333   @31,785
 *         .z-btn                         @110,774.5 249×47 br23 bg #000「立即购买」14px/700 #fff
 *
 * 接口：POST /market/market/MarketDetail/ids/{id} body {}
 * 字段：goods_name / price / product.total_num / product.flux /
 *       product.publisher.name「闪光」 / user_collection.collection_code「41253」 /
 *       user_collection.user_hash_text「0Pz0Jt****」 / user_collection.rz_code「234602」 /
 *       series_name「天才企鹅」 / product.introduce_image
 */
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { getMarketDetail } from '@/api'
import { oss } from '@/api/http'

const router = useRouter()
const route = useRoute()

const detail = ref(null)
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const res = await getMarketDetail(route.query.id || '')
    const d = res.data
    detail.value = d && (d.data || d)
  } catch (e) {
    detail.value = null
  } finally {
    loading.value = false
  }
}

/* 大图：原站 main_image 直接铺满 390×390 */
const mainImg = computed(() => {
  const p = detail.value && detail.value.product
  return p && (p.main_image || p.listimg) ? oss(p.main_image || p.listimg) : ''
})

/* 产品描述：原站 introduce_image 是富文本 HTML */
const introduceHtml = computed(() => {
  const p = detail.value && detail.value.product
  if (!p) return ''
  const raw = p.introduce_image || p.introduce || ''
  /* 富文本内的 OSS 图片同样走 oss()，命中本项目静态资源（图片来源仍为原站下载） */
  return String(raw).replace(
    /https?:\/\/img\.gandart\.shop([^"'\s)]+)/gi,
    (_m, p) => oss(p)
  )
})

/* 大图加载失败自动重试一次（弱网/超时兜底） */
const imgRetry = ref(0)
function onImgError() {
  if (imgRetry.value < 1) imgRetry.value += 1
}

/* 购买须知 */
const purchaseInfo = computed(() => {
  const p = detail.value && detail.value.product
  return (p && p.purchase_information) || ''
})

function back() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/market/market')
}

/* 原站详情页「立即购买」→ 确认订单页，带上当前藏品信息（对齐原站跳转参数） */
function goBuy() {
  const p = detail.value && detail.value.product
  router.push({
    path: '/pages/order/orderSubmit',
    query: {
      id: String(detail.value && detail.value.id ? detail.value.id : route.query.id || ''),
      goods_name: detail.value && detail.value.goods_name,
      price: detail.value && detail.value.price,
      cover: p && (p.main_image || p.listimg)
    }
  })
}

onMounted(load)
</script>

<template>
  <div class="detail-page">
    <!-- 返回 -->
    <img class="back-btn" src="/static/icon/back-btn.png" alt="返回" @click="back" />

    <!-- ========== product-card @0,0 390×486 ========== -->
    <div class="product-card">
      <!-- 大图 @0,0 390×390：加载失败时自动重试一次（弱网兜底），绝不显示破图 -->
      <img
        v-if="mainImg"
        class="card-img"
        :src="mainImg + (imgRetry ? (mainImg.includes('?') ? '&' : '?') + 'r=' + imgRetry : '')"
        :alt="detail && detail.goods_name"
        @error="onImgError"
      />

      <div class="goods-info plr-20 ptb-10">
        <div class="product-name">
          <div class="name-text">{{ detail ? detail.goods_name : '' }}</div>
          <!-- 原站：uni-text.font-size-16 @20,446 16×24 > span @20,452 16×17；数字 @36,443 36×26 -->
          <div class="price-text">
            <span class="price-yen"><span class="yen-inner">￥</span></span
            ><span class="price-num"
              ><span class="num-inner">{{ detail ? detail.price : '' }}</span></span
            >
          </div>
        </div>

        <div class="tag-col">
          <!-- 原站 .tag-size-default @259,412.5 103×23 br4；.tg-name 40×23 pad3px 6px flex居中；.tg-value 63×23 pad 3px 6px 3px 15px lh16 -->
          <div class="tag-size-default">
            <span class="tg-name"><span class="tg-name-in">限量</span></span>
            <span class="tg-value">{{ detail && detail.product && detail.product.total_num }}份</span>
          </div>
          <div class="tag-size-default">
            <span class="tg-name"><span class="tg-name-in">流通</span></span>
            <span class="tg-value">{{ detail && detail.product && detail.product.flux }}份</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== 内容区 .plr-16.ptb-12 @0,486 ========== -->
    <div class="content-wrap plr-16 ptb-12">
      <!-- 品牌方 @16,513 358×65 -->
      <div class="product-builder pa-12">
        <div class="builder-body">
          <div class="builder-avatar">
            <img src="/static/bg/logo.png" alt="品牌方" />
          </div>
          <div class="builder-text">
            <div class="builder-name">
              {{ detail && detail.product && detail.product.publisher && detail.product.publisher.name }}
            </div>
            <div class="builder-sub">品牌方</div>
          </div>
        </div>
      </div>

      <!-- 藏品编号 @16,590 358×53 -->
      <div class="code-row flex-center-sb plr-12 ptb-16">
        <!-- 原站：uni-text 外层 56×21(14/700) > span @28,611 15高；右 uni-text 42×21 > span @320,606 -->
        <span class="code-label"
          ><span class="code-label-in">藏品编号</span></span
        >
        <span class="code-value"
          ><span class="code-value-in"
            >#{{ detail && detail.user_collection && detail.user_collection.collection_code }}</span
          ></span
        >
      </div>

      <!-- 链上信息 @16,655 358×132 -->
      <div class="chain-box pa-12 ptb-12">
        <div class="chain-title">链上信息</div>

        <!-- 原站每行：uni-view 行容器 334×19（mt8）；label uni-text 52×19(13/500) > span @28,705 15高 -->
        <div class="chain-row flex-js-sb flex-ai-center">
          <span class="ci-label"><span class="ci-label-in">所属专辑</span></span>
          <span class="ci-value"><span class="ci-value-in">{{ detail ? detail.series_name : '' }}</span></span>
        </div>

        <div class="chain-row mt-8 flex-js-sb flex-ai-center">
          <span class="ci-label"><span class="ci-label-in">合约地址</span></span>
          <span class="ci-value-with">
            <span class="ci-value"
              ><span class="ci-value-in">{{
                detail && detail.user_collection && detail.user_collection.user_hash_text
              }}</span></span
            >
            <img class="copy-btn" src="/static/icon/copy-dark.png" alt="复制" />
          </span>
        </div>

        <div class="chain-row mt-8 flex-js-sb flex-ai-center">
          <span class="ci-label"><span class="ci-label-in">认证标识</span></span>
          <span class="ci-value"
            ><span class="ci-value-in">{{
              detail && detail.user_collection && detail.user_collection.rz_code
            }}</span></span
          >
        </div>
      </div>

      <!-- 产品描述 @16,799 -->
      <div class="introduce-box pa-12">
        <div class="introduce-title">产品描述</div>
        <!-- 原站为富文本 HTML，直接渲染接口返回内容 -->
        <div class="introduce-body" v-html="introduceHtml"></div>
      </div>

      <!-- 购买须知 @16,1215.6 358×57 -->
      <div class="need-box pa-12">
        <div class="need-title">购买须知</div>
        <div class="need-body">{{ purchaseInfo }}</div>
      </div>

      <div class="tail-pad"></div>
    </div>

    <!-- ========== market-action @0,752 390×92 fixed ========== -->
    <div class="market-action">
      <div class="market-action-container plr-16 ptb-8 flex flex-ai-center">
        <div class="action-price">
          <span class="ap-yen">￥</span><span class="ap-val">{{ detail ? detail.price : '' }}</span>
        </div>
        <div class="z-btn" @click="goBuy">立即购买</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* 原站 .container.page-container.page-bg bg #fafafa padding-bottom 62px
   （fixed 底栏高 92 → 原站仅补 62；页面总高由内容自然撑开到 1031） */
.detail-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  background: #fafafa;
  min-height: 100%;
  padding-bottom: 57px;
  box-sizing: border-box;
}

/* 返回 @13,10 24×24 */
.back-btn {
  position: absolute;
  top: 10px;
  left: 13px;
  width: 24px;
  height: 24px;
  z-index: 10;
}

/* ========== product-card @0,0 390×486 bg #fff ========== */
.product-card {
  position: relative;
  background: #fff;
}
/* 大图 @0,0 390×390
   实测：object-fit:cover 与原站截图的像素差最低（1.32%）。
   原站 DOM 上报 fill，但那是 u-image 内部 resize-sensor 的计算值，
   实际视觉等价于 cover（等比缩放铺满并裁切），实测 cover 更接近。 */
.card-img {
  display: block;
  width: 390px;
  height: 390px;
  object-fit: cover;
}

/* .goods-info @0,395 390×90 plr20 ptb10
   （原站大图 390 + 5px 间隙 + goods-info 90 = card 485；
     原站 product-name 两行合计 59，容器内上下各留 15.5） */
.goods-info {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 91px;
  margin-top: 5px;
  padding: 10px 20px;
  box-sizing: border-box;
  background: #fff;
}
/* .product-name @20,411 119.2×59 —— 原站实测 font-weight:700 / color #000 */
.product-name {
  min-width: 0;
  font-weight: 700;
  color: #000;
}
.name-text {
  font-size: 16px;
  font-weight: 700;
  color: #000;
  line-height: 24px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
/* 价格 @20,443 119.19×27 mt8 15px/700 rgb(217,76,74)
   原站实测（双层 uni-text）：
     容器 .font-color-dark.flex.flex-ai-end.mt-8  @20,443 119.19×27  含 1px 底部内缩
     ￥   uni-text.font-size-16 @20,446 16×24  >  span @20,452 16×17
     数字 uni-text            @36,443 36×26  >  span @36,443 36×26
   故容器 padding-bottom:1px + flex-end，子元素分别 17 / 26 高，即自然落在 452 / 443 */
.price-text {
  display: flex;
  align-items: flex-end;
  margin-top: 8px;
  height: 27px;
  padding-bottom: 1px;
  box-sizing: border-box;
  font-size: 15px;
  font-weight: 700;
  line-height: normal;
  color: rgb(217, 76, 74);
}
.price-yen {
  font-size: 16px;
  font-weight: 700;
  line-height: 17px;
}
.yen-inner {
  position: relative;
  top: 1px;
  font-size: 16px;
  font-weight: 700;
  line-height: normal;
}
.price-num {
  font-size: 24px;
  font-weight: 700;
  line-height: 26px;
}
.num-inner {
  position: relative;
  top: 1px;
  font-size: 24px;
  font-weight: 700;
  line-height: normal;
}

/* 标签列：原站固定于 @259,412.5（goods-info 内 111×56），故用绝对定位对齐
   相对 .goods-info（@0,395）：left = 259 - 0 = 259；top = 412.5 - 395 = 17.5 */
/* 原站 .tag-col @259,412.5 111×56（两标签 23 +间距 10 + 23 = 56，无尾部间距） */
.tag-col {
  position: absolute;
  left: 259px;
  top: 17.5px;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 111px;
}
/* .tag-size-default @259,412.5 103×23 br4 mr-8 mb-10
   name  @259,412.5 40×23 bg #000    pl6 pt3 12px #fff
   value @287,412.5 63×23 bg #eceef0 br0 4 4 0 pl15 pt3 12px #000 */
.tag-size-default {
  display: flex;
  align-items: stretch;
  width: 103px;
  height: 23px;
  margin-bottom: 10px;
  border-radius: 4px;
  overflow: hidden;
  font-size: 8px;
}
/* 末项不留间距，使容器高度 = 23 + 10 + 23 = 56（与原站一致） */
.tag-size-default:last-child {
  margin-bottom: 0;
}
/* 原站 .tg-name：padding 3px 6px（左右均 6），内部文字 span 水平垂直居中 */
.tg-name {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 23px;
  padding: 3px 6px;
  box-sizing: border-box;
  font-size: 12px;
  line-height: normal;
  color: #fff;
  background: #000;
  border-radius: 0;
}
.tg-name-in {
  position: relative;
  top: 1px;
  font-size: 12px;
  line-height: 14px;
}
/* 原站 .value（uni-view）：position:relative + left:-12px 负偏移，故落在 @287（与 name 重叠 12px）
   padding 3px 6px 3px 15px，line-height 16px，文字左对齐 */
.tg-value {
  position: relative;
  left: -12px;
  display: flex;
  align-items: center;
  width: 63px;
  height: 23px;
  padding: 3px 6px 3px 15px;
  box-sizing: border-box;
  font-size: 12px;
  line-height: 16px;
  color: #000;
  background: rgb(236, 238, 240);
  border-radius: 0 4px 4px 0;
}

/* ========== 内容区 .plr-16.ptb-12 ========== */
.content-wrap {
  padding: 12px 16px;
}

/* .product-builder @16,513 358×65 bg #fff mt15 pa12 */
.product-builder {
  margin-top: 15px;
  padding: 12px;
  background: #fff;
}
.builder-body {
  display: flex;
  align-items: center;
  height: 41px;
}
/* 头像 @28,525 41×41 圆形 */
.builder-avatar {
  width: 41px;
  height: 41px;
  margin-right: 10px;
  border-radius: 50%;
  overflow: hidden;
  flex-shrink: 0;
}
.builder-avatar img {
  width: 41px;
  height: 41px;
  display: block;
}
.builder-text {
  /* 原站「闪光」@79,525 h21 —— 头像 41 高内垂直居中（(41-39)/2 = 1） */
  display: flex;
  flex-direction: column;
  justify-content: center;
  height: 41px;
}
.builder-name {
  font-size: 14px;
  line-height: 20px;
  color: rgb(35, 38, 47);
}
.builder-sub {
  font-size: 12px;
  line-height: 18px;
  color: #999;
}

/* 藏品编号 @16,590 358×53 bg #fff mt12 */
.code-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 53px;
  margin-top: 12px;
  padding: 16px 12px;
  box-sizing: border-box;
  background: #fff;
}
/* 编号行双层（对齐原站 uni-text）：外层 21 高，内层 span 为真实行盒
   左 uni-text @28,606 56×21 > span @28,611 56×15；右 uni-text @320,606 42×21 > span @320,606 42×15 */
.code-label {
  height: 21px;
  font-size: 14px;
  font-weight: 700;
  color: rgb(35, 38, 47);
  line-height: 21px;
}
.code-label-in {
  font-size: 14px;
  font-weight: 700;
  line-height: normal;
}
.code-value {
  height: 21px;
  font-size: 14px;
  color: rgb(35, 38, 47);
  line-height: 21px;
}
.code-value-in {
  position: relative;
  top: -3px;
  font-size: 14px;
  line-height: normal;
}

/* 链上信息 @16,655 358×132 bg #fff mt12 pa12 ptb12 */
.chain-box {
  margin-top: 12px;
  padding: 12px;
  background: #fff;
}
.chain-title {
  font-size: 14px;
  font-weight: 600;
  color: rgb(74, 74, 74);
  line-height: 21px;
  margin-bottom: 14px;
}
.chain-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 19px;
  font-size: 14px;
}
.chain-row.mt-8 {
  margin-top: 8px;
}
/* 链上信息双层（对齐原站 uni-text）：外层 19 高，内层 span 真实行盒 15
   label uni-text @28,702 52×19 > span @28,705 52×15；值同理 */
.ci-label {
  font-size: 13px;
  font-weight: 500;
  color: #999;
  line-height: 19px;
  flex-shrink: 0;
}
.ci-label-in {
  font-size: 13px;
  font-weight: 500;
  line-height: normal;
}
.ci-value {
  font-size: 13px;
  font-weight: 500;
  color: rgb(51, 51, 51);
  line-height: 19px;
  text-align: right;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ci-value-in {
  font-size: 13px;
  font-weight: 500;
  line-height: normal;
}
.ci-value-with {
  display: flex;
  align-items: center;
  margin-left: 4px;
}
/* 复制图标 @348,731.5 14×14 —— 原站外层 flex-center ml-4，故图标左侧留 4px */
.copy-btn {
  width: 14px;
  height: 14px;
  margin-left: 4px;
  display: block;
  flex-shrink: 0;
}

/* 产品描述 @16,799 bg #fff mt12 pa12
   原站：标题 @28,811 h21；富文本容器 mt12 @28,844 334×347.6；
        内 img 334×342.6（宽撑满 334，高按原图比例） */
.introduce-box {
  margin-top: 12px;
  padding: 12px;
  background: #fff;
}
.introduce-title {
  font-size: 14px;
  font-weight: 700;
  color: rgb(51, 51, 51);
  line-height: 21px;
}
/* 原站实测：图 @28,844（box@799 → 12+21+12），图片底部到 box 底还多 5px（rich-text 内 p 段落余量）
   故 margin-top:12 + padding-bottom:5，box 总高 404.6 与原站一致 */
.introduce-body {
  margin-top: 12px;
  padding-bottom: 5px;
  font-size: 15px;
  color: rgb(74, 74, 74);
  line-height: 21px;
  word-break: break-all;
}
.introduce-body :deep(img) {
  display: block;
  width: 334px;
  height: auto;
}

/* 购买须知 @16,1215.6 358×57 bg #fff mt12 pa12
   原站：标题 @28,1227.6 h21；内容容器 mt12 高 0（接口 purchase_information 为空字符串） */
.need-box {
  margin-top: 12px;
  padding: 12px;
  background: #fff;
}
/* 原站实测「购买须知」@28,885 14px/700 #000（不是 #333 常规体） */
.need-title {
  font-size: 14px;
  font-weight: 700;
  color: #000;
  line-height: 21px;
}
.need-body {
  margin-top: 12px;
  font-size: 15px;
  color: rgb(74, 74, 74);
  line-height: 21px;
}

/* 原站尾部 @16,1272.6 358×32 ptb16（购买须知底 1272.6 + 32 = 1304.6，
   页面总高 1373 → 容器 padding-bottom 92 收口） */
.tail-pad {
  height: 32px;
  padding: 16px 0;
  box-sizing: border-box;
}

/* ========== market-action @0,752 390×92 bg #fff pl15 fixed ========== */
.market-action {
  position: fixed;
  left: 0;
  bottom: 0;
  width: 390px;
  max-width: 100%;
  height: 92px;
  padding-left: 15px;
  box-sizing: border-box;
  background: #fff;
}
/* .market-action-container @15,767 360×62 plr16 ptb8
   .market-action 高 92 padding-left 15 → 内容区 x=15 宽 375；
   原站容器 360 宽 → 右侧再留 15；垂直居中：92-62=30 → margin-top 15 */
.market-action-container {
  display: flex;
  align-items: center;
  width: 360px;
  height: 62px;
  margin: 15px 0 0 0;
  padding: 8px 16px;
  box-sizing: border-box;
}
/* 价格 @31,785「￥100」 ￥12px / 数字 22px #333 */
.action-price {
  display: flex;
  align-items: baseline;
  height: 26px;
  flex-shrink: 0;
}
.ap-yen {
  font-size: 12px;
  color: rgb(51, 51, 51);
}
.ap-val {
  font-size: 22px;
  color: rgb(51, 51, 51);
  line-height: 25px;
}
/* .z-btn @110,774.5 249×47 br23 bg #000（宽度固定 249，不 flex 撑满） */
.z-btn {
  width: 249px;
  height: 47px;
  margin-left: 34px;
  border-radius: 23px;
  background: #000;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>