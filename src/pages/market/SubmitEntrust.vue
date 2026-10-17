<script setup>
/**
 * 发起委托 —— 1:1 复刻原站 pages/market/submitEntrust
 *
 * 原站 390x844 实测（collection_id=124 天才企鹅）：
 *   .container.page-container  390x844  bg #fafafa  padding 0 16px
 *   .u-navbar--fixed          @0,0    390x44   bg #fafafa
 *     返回 back-btn.png       @13,10  24x24
 *     标题「发起委托」         @165,16 60x16  15px/700 #333
 *
 *   藏品+价格卡 .pa-10.br-12.bg-color-item @16,44 358x129  白底 br12 padding10
 *     图                       @26,54  72x72   br12（天才企鹅 listimg）
 *     名称「天才企鹅」          @114,54 14px #23262f
 *     u-number-box             @297,96 67x30
 *       minus-active.png       @297,104 14x14
 *       input wrapper          @313,96  35x30
 *       plus-active.png        @350,104 14x14
 *     价格行 .inp              @26,126 338x37  padding-top16
 *       「￥」                  @26,142 14px/20px
 *       input placeholder      @40,142.7 168x19.6 14px #808080「请输入价格」
 *
 *   支付卡 .pay-list.pa-12.mt-16.br-12 @16,189 358x64 白底 br12 padding12
 *     huifupay.png             @28,209  24x24
 *     「汇付」                  @64,214  14px #23262f
 *     「去开通」                @316,209.5 46x23  10px 白字 br32
 *       渐变 linear-gradient(90deg,#FF4B2B,#FF416C)
 *
 *   预计收入卡 .card.mt-10     @16,263 358x172 白底 br12 padding12
 *     标题「预计收入」          @28,275  14px/700 #23262f mb4
 *     行1 总售价:              @28,300  334x41 ptb-10  右 ¥--   @345.7,312.5
 *     行2 综合服务费 (5%)      @28,341  334x41           右 - ¥0   @339.8,353.5
 *     行3 藏品服务费 (0.00%)   @28,382  334x41           右 - ¥0.00 @322.3,394.5
 *
 *   委托说明卡 .trade.pa-16.mt-16.br-12 @16,451 358x124 白底 br12 padding16
 *     标题「委托说明」          @32,467  14px/700 #23262f mb8
 *     正文                     @32,501  322x57  14px #4a4a4a
 *
 *   底部 .action @0,783 390x61 白底（fixed）
 *     内层 ptb-7 plr-16
 *     「合计：￥--」           @16,803  14px #23262f
 *     .z-btn 按钮             @203,790 171x47 br23 14px/700
 *       disabled 态：bg #e3e3e3 文字 #666「求购」
 *
 * 接口（原站实测）：
 *   POST /market/trade/collectionDetail      {collection_id:"124"}
 *   POST /market/Prepayment/collectionDetail {collection_id:"124"}
 */
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getTradeCollectionDetail, getPrepaymentCollectionDetail } from '@/api'
import { oss } from '@/api/http'

const route = useRoute()
const router = useRouter()

/* 藏品 id：原站由 marketGoodsList / detail 页带入 */
const collectionId = computed(() => route.query.id || route.query.collection_id || '')

const detail = ref(null)
const fee = ref(null)
/* 数量步进器初值：原站 u-number-box 未操作时为 1 */
const count = ref(1)
const price = ref('')

/* 支付方式（原站当前账号未开通汇付，展示「去开通」） */
const PAYWAY = [
  { key: 'huifu', name: '汇付', icon: '/static/icon/huifupay.png', opened: false }
]
const payIndex = ref(0)
const pay = computed(() => PAYWAY[payIndex.value])

/* 服务费率：平台服务费 5%、藏品服务费 0.00%（原站接口返回 0.05 / 0.00） */
const platRate = computed(() => Number(fee.value?.pay_platform_service_rate || 0))
const goodsRate = computed(() => Number(fee.value?.share_proportion_rate || 0))

const priceNum = computed(() => (price.value === '' ? null : Number(price.value)))

/* 预计收入区（原站未输入价格时全部显示 ¥-- / - ¥0 / - ¥0.00） */
const totalText = computed(() => (priceNum.value === null ? '¥--' : `¥${priceNum.value}`))
const platFeeText = computed(() =>
  priceNum.value === null ? '- ¥0' : `- ¥${(priceNum.value * platRate.value).toFixed(2)}`
)
const goodsFeeText = computed(() =>
  priceNum.value === null ? '- ¥0.00' : `- ¥${(priceNum.value * goodsRate.value).toFixed(2)}`
)

/* 说明文案：原站显示的是 Prepayment/collectionDetail 的 tips
   （「出价下限为昨日平均成交价100%」），不是 trade 接口的「寄售出价下限」 */
const tips = computed(() => fee.value?.tips || '')
const lowPriceText = computed(() => fee.value?.low_price_text || '')
const hasPrice = computed(() => priceNum.value !== null && priceNum.value > 0)

function minus() {
  if (count.value > 1) count.value -= 1
}

function plus() {
  count.value += 1
}

function onInput(e) {
  const v = e.target.value.replace(/[^\d.]/g, '')
  price.value = v
}

async function load() {
  if (!collectionId.value) return
  try {
    const r = await getTradeCollectionDetail({ collection_id: String(collectionId.value) })
    detail.value = r.data || null
  } catch (e) {
    detail.value = null
  }
  try {
    const r2 = await getPrepaymentCollectionDetail({ collection_id: String(collectionId.value) })
    fee.value = r2.data || null
  } catch (e) {
    fee.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="container page-container page-bg plr-16 font-size-14">
    <!-- 导航 @0,0 390x44 -->
    <div class="u-navbar--fixed">
      <div class="u-navbar__content">
        <div class="u-navbar__content__left">
          <div class="flex-center" @click="router.back()">
            <img class="back-img" src="/static/icon/back-btn.png" alt="返回" />
          </div>
        </div>
        <span class="nav-title">发起委托</span>
      </div>
    </div>

    <!-- 藏品 + 价格卡 @16,44 358x129 -->
    <div class="pa-10 br-12 bg-color-item goods-card">
      <div class="flex">
        <div class="goods-img">
          <img v-if="detail && detail.listimg" :src="oss(detail.listimg)" alt="" />
        </div>
        <div class="flex-dir-col flex flex-js-sb pl-16 flex-1">
          <div class="font-color-black goods-name">{{ detail ? detail.name : '' }}</div>
          <!-- 数量步进器 @114,96 -->
          <div class="flex flex-js-end number-box">
            <div class="u-number-box">
              <div class="u-number-box__slot">
                <div class="flex-center minus" @click="minus">
                  <img src="/static/icon/minus-active.png" alt="减少" />
                </div>
              </div>
              <div class="num-input">
                <input class="uni-input-input" type="text" :value="count" readonly />
              </div>
              <div class="u-number-box__slot">
                <div class="flex-center plus" @click="plus">
                  <img src="/static/icon/add-active.png" alt="增加" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- 价格行 @26,126 338x37 -->
      <div class="flex inp flex-js-sb font-color-222">
        <div class="flex flex-js-sb font-size-20 flex-ai-center price-line">
          <span class="yen">￥</span>
          <input
            class="price-input"
            type="text"
            placeholder="请输入价格"
            :value="price"
            @input="onInput"
          />
        </div>
      </div>
    </div>

    <!-- 支付方式卡 @16,189 358x64 -->
    <div class="pay-list pa-12 bg-color-item mt-16 br-12">
      <div class="ptb-8 flex flex-js-sb flex-ai-center">
        <div class="flex">
          <div class="flex-center pay-icon">
            <img :src="pay.icon" :alt="pay.name" />
          </div>
          <span class="pay-name">{{ pay.name }}</span>
        </div>
        <div class="font-color-white  font-size-10 flex flex-center go-open">去开通</div>
      </div>
    </div>

    <!-- 预计收入 @16,263 358x172 -->
    <div class="income-card mt-10 bg-color-item br-12 pa-12">
      <div class="font-size-14 font-color-black font-w-bold mb-4">预计收入</div>
      <div class="flex-center-sb ptb-10">
        <div class="fee-label">总售价:</div>
        <div class="font-size-14 font-color-black fee-val">{{ totalText }}</div>
      </div>
      <div class="flex-center-sb ptb-10">
        <div class="fee-label">综合服务费 ({{ (platRate * 100).toFixed(0) }}%)</div>
        <div class="fee-val dark">{{ platFeeText }}</div>
      </div>
      <div class="flex-center-sb ptb-10">
        <div class="fee-label">藏品服务费 ({{ (goodsRate * 100).toFixed(2) }}%)</div>
        <div class="fee-val dark">{{ goodsFeeText }}</div>
      </div>
    </div>

    <!-- 委托说明 @16,451 358x124 -->
    <div class="trade pa-16 bg-color-item mt-16 br-12">
      <div class="font-color-black font-w-bold font-size-14 mb-8">委托说明</div>
      <div class="trade-tips">{{ tips }}</div>
    </div>

    <!-- 底部操作栏 @0,783 390x61 -->
    <div class="action">
      <div class="flex flex-js-sb flex-ai-center ptb-7 plr-16">
        <div class="font-color-black total">合计：￥{{ priceNum === null ? '--' : priceNum }}</div>
        <div class="plr-10 z-btn btn-size-lg circle" :class="{ disabled: !hasPrice }">求购</div>
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
  background: #fafafa;
  box-sizing: border-box;
  padding: 0 16px;
  font-size: 14px;
  color: #4a4a4a;
  overflow: hidden;
}

.font-size-14 { font-size: 14px; }
.font-size-20 { font-size: 20px; }
.font-size-10 { font-size: 10px; }
.font-w-bold { font-weight: 700; }
.font-color-black { color: #23262f; }
.font-color-white { color: #fff; }
.flex { display: flex; }
.flex-1 { flex: 1; }
.flex-center { display: flex; align-items: center; justify-content: center; }
.flex-center-sb { display: flex; align-items: center; justify-content: space-between; }
.flex-dir-col { flex-direction: column; }
.flex-js-sb { justify-content: space-between; }
.flex-js-end { justify-content: flex-end; }
.flex-ai-center { align-items: center; }
.pa-10 { padding: 10px; }
.pa-12 { padding: 12px; }
.pa-16 { padding: 16px; }
.ptb-7 { padding: 7px 0; }
.ptb-8 { padding: 8px 0; }
.ptb-10 { padding: 10px 0; }
.pl-16 { padding-left: 16px; }
.plr-16 { padding-left: 16px; padding-right: 16px; }
.plr-10 { padding-left: 10px; padding-right: 10px; }
.mt-8 { margin-top: 8px; }
.mt-10 { margin-top: 10px; }
.mt-16 { margin-top: 16px; }
.mb-4 { margin-bottom: 4px; }
.mb-8 { margin-bottom: 8px; }
.bg-color-item { background: #fff; }
.br-12 { border-radius: 12px; }
.font-color-222 { color: #222; }

/* 导航 @0,0 390x44 */
.u-navbar--fixed {
  position: fixed;
  left: 0;
  top: 0;
  width: 100%;
  height: 44px;
  background: #fafafa;
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

/* 藏品 + 价格卡 @16,44 358x129 */
.goods-card {
  margin-top: 44px;
  height: 129px;
  box-sizing: border-box;
}

.goods-img {
  width: 72px;
  height: 72px;
  border-radius: 12px;
  overflow: hidden;
  flex-shrink: 0;
}

.goods-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.goods-name {
  height: 21px;
  line-height: 21px;
  font-size: 14px;
  color: #23262f;
}

/* 数量步进器：原站 u-number-box @297,96 67x30 */
.number-box {
  height: 30px;
}

.u-number-box {
  display: flex;
  align-items: center;
  width: 67px;
  height: 30px;
}

.u-number-box__slot {
  width: 14px;
  height: 14px;
  flex-shrink: 0;
}

.minus,
.plus {
  width: 14px;
  height: 14px;
}

.minus img,
.plus img {
  width: 14px;
  height: 14px;
  object-fit: fill;
  display: block;
}

.num-input {
  width: 35px;
  height: 30px;
  display: flex;
  align-items: center;
}

.num-input input {
  width: 100%;
  height: 30px;
  text-align: center;
  font-size: 14px;
  color: #23262f;
  border: 0;
  background: transparent;
  outline: none;
}

/* 价格行 @26,126 338x37 */
.inp {
  height: 37px;
  padding-top: 16px;
  box-sizing: border-box;
}

.price-line {
  width: 182px;
  height: 21px;
}

.yen {
  font-size: 14px;
  line-height: 21px;
  color: #222;
}

.price-input {
  width: 168px;
  height: 20px;
  margin-left: 4px;
  font-size: 14px;
  line-height: 20px;
  color: #222;
  border: 0;
  background: transparent;
  outline: none;
}

.price-input::placeholder {
  color: #808080;
}

/* 支付方式卡 @16,189 358x64 */
.pay-list {
  height: 64px;
  box-sizing: border-box;
}

.pay-icon {
  width: 24px;
  height: 24px;
}

.pay-icon img {
  width: 24px;
  height: 24px;
  object-fit: fill;
  display: block;
}

.pay-name {
  margin-left: 12px;
  font-size: 14px;
  line-height: 15px;
  color: #23262f;
}

.go-open {
  width: 46px;
  height: 23px;
  border-radius: 32px;
  background-image: linear-gradient(90deg, #ff4b2b 0%, #ff416c 100%);
}

/* 预计收入 @16,263 358x172 */
.income-card {
  height: 172px;
  box-sizing: border-box;
}

.fee-label {
  font-size: 14px;
  line-height: normal;
  color: #999;
}

.fee-val {
  font-size: 14px;
  line-height: normal;
  color: #999;
}

.fee-val.dark {
  color: #23262f;
}

/* 委托说明 @16,451 358x124 */
.trade {
  height: 124px;
  box-sizing: border-box;
}

/* 原站正文 span 实测 @32,501 322x57（3 行 × 19px）
   line-height 为 normal（≈19px），y 由标题 467 + 21 + mb8 + inline leading 5 得到 */
.trade-tips {
  width: 326px;
  font-size: 14px;
  line-height: normal;
  color: #4a4a4a;
}

/* 底部操作栏 @0,783 390x61 */
.action {
  position: fixed;
  left: 0;
  bottom: 0;
  width: 100%;
  height: 61px;
  background: #fff;
  z-index: 20;
  box-sizing: border-box;
}

.action > div {
  height: 61px;
}

.total {
  font-size: 14px;
  line-height: 21px;
  color: #23262f;
}

.z-btn {
  width: 171px;
  height: 47px;
  border-radius: 23px;
  background: #000;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}

.z-btn.disabled {
  background: #e3e3e3;
  color: #666;
}
</style>
