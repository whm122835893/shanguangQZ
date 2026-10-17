<script setup>
/**
 * 发起求购 —— 1:1 复刻原站 pages/market/submitBegBuy
 *
 * 原站 390x844 实测（collection_id=124 天才企鹅）：
 *   .container.page-container   390x844  bg #fafafa  padding 0 16px
 *   .u-navbar--fixed           @0,0    390x44 bg #fafafa
 *     返回 back-btn.png        @13,10  24x24
 *     标题「发起求购」          @165,16 60x16  15px/700 #333
 *
 *   藏品卡 .pa-10.br-12.bg-color-item @16,44 358x163 白底 br12 padding10
 *     图                       @26,54  72x72 br12
 *     名称「天才企鹅」          @114,54 14px #23262f
 *     u-number-box             @297,96 67x30（minus @297,104 / plus @350,104）
 *     .pt-16                   @26,126 338x71 padding-top16
 *       地板价提示              @26,142 338x18  12px #999
 *         「求购价格不得低于地板价的100%，当前地板价为5元」
 *       价格行 .inp             @26,160 338x37 padding-top16
 *         「￥」+「不得低于5.00元」 @26,176 182x21
 *
 *   支付卡 .pay-list.pa-12.mt-16 @16,223 358x64 白底 br12 padding12
 *     huifupay.png @28,243 24x24 / 「汇付」/「去开通」@316,243.5 46x23
 *
 *   求购说明卡 .trade.pa-16.mt-16 @16,303 358x61 白底 br12 padding16
 *     标题「求购说明」          @32,319 326x21  14px/700 #23262f mb8
 *
 *   底部 .action @0,783 390x61 白底（fixed）
 *     「合计：￥--」           @16,803  14px #23262f
 *     .z-btn                   @203,790 171x47 br23 disabled bg #e3e3e3 #666「求购」
 *
 * 接口（原站实测）：
 *   POST /market/trade/collectionDetail      {collection_id:"124"}
 *   POST /market/Prepayment/collectionDetail {collection_id:"124"}
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getTradeCollectionDetail, getPrepaymentCollectionDetail } from '@/api'
import { oss } from '@/api/http'

const route = useRoute()
const router = useRouter()

const collectionId = computed(() => route.query.id || route.query.collection_id || '')

const detail = ref(null)
const fee = ref(null)
const count = ref(1)
const price = ref('')

const PAYWAY = [
  { key: 'huifu', name: '汇付', icon: '/static/icon/huifupay.png', opened: false }
]
const payIndex = ref(0)
const pay = computed(() => PAYWAY[payIndex.value])

const priceNum = computed(() => (price.value === '' ? null : Number(price.value)))

/* 地板价限制文案：「求购价格不得低于地板价的100%，当前地板价为5元」 */
const pricePro = computed(() => Number(fee.value?.price_price_ratio || 100))
const lowPrice = computed(() => fee.value?.low_price ?? '')
const lowPriceText = computed(() => fee.value?.low_price_text || '')

/* 输入框下方限价提示：「不得低于5.00元」（已输入价格则提示不得高于） */
const floorTip = computed(() =>
  lowPriceText.value ? `不得低于${Number(lowPrice.value).toFixed(2)}元` : '请输入求购价格'
)
const hasPrice = computed(() => priceNum.value !== null && priceNum.value > 0)

function minus() {
  if (count.value > 1) count.value -= 1
}

function plus() {
  count.value += 1
}

function onInput(e) {
  price.value = e.target.value.replace(/[^\d.]/g, '')
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
        <span class="nav-title">发起求购</span>
      </div>
    </div>

    <!-- 藏品 + 地板价 + 价格卡 @16,44 358x163 -->
    <div class="pa-10 br-12 bg-color-item goods-card">
      <div class="flex">
        <div class="goods-img">
          <img v-if="detail && detail.listimg" :src="oss(detail.listimg)" alt="" />
        </div>
        <div class="flex-dir-col flex flex-js-sb pl-16 flex-1">
          <div class="font-color-black goods-name">{{ detail ? detail.name : '' }}</div>
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

      <div class="pt-16">
        <!-- 地板价提示 @26,142 -->
        <div class="font-color-999 font-size-12 floor-tip">
          求购价格不得低于地板价的{{ pricePro }}%，当前地板价为{{ lowPrice }}元
        </div>
        <!-- 价格行 @26,160 -->
        <div class="flex inp flex-js-sb font-color-222">
          <div class="flex flex-js-sb font-size-20 flex-ai-center price-line">
            <span class="yen">￥</span>
            <span class="floor-text">{{ floorTip }}</span>
          </div>
          <input
            class="price-input"
            type="text"
            :placeholder="floorTip"
            :value="price"
            @input="onInput"
          />
        </div>
      </div>
    </div>

    <!-- 支付方式卡 @16,223 358x64 -->
    <div class="pay-list pa-12 bg-color-item mt-16 br-12">
      <div class="ptb-8 flex flex-js-sb flex-ai-center">
        <div class="flex">
          <div class="flex-center pay-icon">
            <img :src="pay.icon" :alt="pay.name" />
          </div>
          <span class="pay-name">{{ pay.name }}</span>
        </div>
        <div class="font-color-white font-size-10 flex flex-center go-open">去开通</div>
      </div>
    </div>

    <!-- 求购说明 @16,303 358x61 -->
    <div class="trade pa-16 bg-color-item mt-16 br-12">
      <div class="font-color-black font-w-bold font-size-14 mb-8">求购说明</div>
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
  overflow: hidden;
  font-size: 14px;
  color: #4a4a4a;
}

.font-size-12 { font-size: 12px; }
.font-size-14 { font-size: 14px; }
.font-size-20 { font-size: 20px; }
.font-size-10 { font-size: 10px; }
.font-w-bold { font-weight: 700; }
.font-color-black { color: #23262f; }
.font-color-white { color: #fff; }
.font-color-999 { color: #999; }
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
.pt-16 { padding-top: 16px; }
.ptb-7 { padding: 7px 0; }
.ptb-8 { padding: 8px 0; }
.pl-16 { padding-left: 16px; }
.plr-16 { padding-left: 16px; padding-right: 16px; }
.plr-10 { padding-left: 10px; padding-right: 10px; }
.mt-10 { margin-top: 10px; }
.mt-16 { margin-top: 16px; }
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

/* 藏品卡 @16,44 358x163 */
.goods-card {
  margin-top: 44px;
  height: 163px;
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

.number-box { height: 30px; }

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
.plus { width: 14px; height: 14px; }

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

/* 地板价提示 @26,142 338x18 */
.floor-tip {
  width: 338px;
  height: 18px;
  font-size: 12px;
  line-height: 18px;
  color: #999;
  white-space: nowrap;
  overflow: hidden;
}

/* 价格行 @26,160 338x37 */
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

.floor-text {
  margin-left: 4px;
  font-size: 14px;
  line-height: 21px;
  color: #222;
}

.price-input {
  width: 152px;
  height: 20px;
  font-size: 14px;
  line-height: 20px;
  color: #222;
  text-align: right;
  border: 0;
  background: transparent;
  outline: none;
}

.price-input::placeholder { color: #808080; }

/* 支付方式卡 @16,223 358x64 */
.pay-list {
  height: 64px;
  box-sizing: border-box;
}

.pay-icon { width: 24px; height: 24px; }

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

/* 求购说明 @16,303 358x61 */
.trade {
  height: 61px;
  box-sizing: border-box;
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

.action > div { height: 61px; }

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
