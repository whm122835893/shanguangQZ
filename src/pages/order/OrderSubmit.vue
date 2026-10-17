<script setup>
/**
 * 确认订单 —— 100% 复刻原站 pages/order/orderSubmit
 *
 * 原站 390x844 实测（唯一权威数据，来自 getBoundingClientRect + getComputedStyle）：
 *   uni-page-head                    @0,0    390x44   原生导航栏（标题居中）
 *     uni-btn-icon(返回)             @5,6    27x27
 *     .uni-page-head__title          @70,7   250x30   16px /700  「确认订单」
 *   .container.page-container.page-bg.pt-16  @0,44  390x800  bg #fafafa  pt16
 *     .bg-color-item.flex.flex-dir-col.pa-8.plr-16.mb-16  @0,60   390x119 bg #fff r8
 *       「商品详情」                 @16,68  358x21   14px/700 rgb(35,38,47) mb10
 *       .flex                        @16,99  358x72
 *         img 缩略图                 @16,99  72x72    （u-image 原图，无圆角）
 *         商品名                     @104,129.5 104.3x15  14px/700 rgb(35,38,47)
 *     .bg-color-item.pa-8.plr-16.font-size-14.mb-8  @0,195 390x37 bg #fff r8
 *       「订单金额:」                @16,208 59.9x15  14px/400 rgb(35,38,47)
 *       「￥100.00」                 @321.5,203 52.5x21 14px/500 rgb(35,38,47)
 *     .bg-color-item.ptb-4.mt-16     @0,248  390x48   bg #fff  ptb4 mt16
 *       .plr-16.ptb-8.flex.flex-ai-center  @0,252 390x40
 *         img 汇付图标               @16,260 24x24    /static/icon/huifupay.png
 *         「汇付」                   @52,266 24x14    12px/700 rgb(35,38,47)
 *         「去开通」                 @324,261 50x22    10px/700 #666 bg #e3e3e3 r11（未开通→禁用态）
 *     .action.bg-color-pageBg        @0,782  390x62   bg #fff（底部固定操作栏）
 *       「￥」                       @16,807.5 12x14   12px/500 rgb(35,38,47)
 *       「100.00」                   @28,801.5 55x22   20px/500 rgb(35,38,47)
 *       「立即支付」                 @125,789.5 249x47 14px/700 bg #e3e3e3 r23（禁用态）
 *
 * 未实名弹窗（u-popup + w-toast，进入页面即弹出）：
 *   遮罩  @0,0    390x844  rgba(0,0,0,0.7)
 *   .u-popup__content / .w-toast  @49.5,323 291x198  bg #fff  r15(16)  pad 14px 17px 24px
 *     「提示」                     @66.5,337 257x24   16px/700 rgb(51,51,51)
 *     「未实名,是否立即实名？」     @66.5,361 257x37   14px/400 rgb(51,51,51)
 *     「取消」                     @66.5,456 114x41   14px/700 #999 bg rgb(241,240,240) r6
 *     「确认」                     @209.5,456 114x41  14px/700 #fff  bg #000 r6
 *
 * 接口：POST /user/getUserInfo  —— 判断 real_name_authentication（0=未实名）
 * 数据来源：路由 query 携带 detail 页的藏品信息（id/goods_name/price/cover）
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getUserInfo, getMarketDetail } from '@/api'
import { oss } from '@/api/http'

const route = useRoute()
const router = useRouter()

const detail = ref(null)
const userInfo = ref(null)
const showVerifyPopup = ref(false)

/* 缩略图：优先路由传入的封面，其次用 id 重新拉详情 */
const cover = computed(() => {
  if (route.query.cover) return oss(String(route.query.cover))
  const p = detail.value && detail.value.product
  if (p) return oss(p.main_image || p.listimg)
  return ''
})

const goodsName = computed(
  () => (route.query.goods_name ? String(route.query.goods_name) : (detail.value && detail.value.goods_name) || '')
)

/* 订单金额：原站未传 price 时会原样渲染 NaN（不做兜底），此处与原站缺陷保持一致 */
const amount = computed(() => {
  const src = route.query.price != null
    ? route.query.price
    : (detail.value && detail.value.price != null ? detail.value.price : NaN)
  const raw = Number(src)
  /* 原站缺陷：无 price 时原样渲染 NaN，不做 0 兜底 */
  return (Number.isFinite(raw) ? raw.toFixed(2) : 'NaN')
})

/* 原站：本页仅支持汇付通道，未开通时右侧展示「未支持该支付通道收款」而非按钮 */
const payTip = '未支持该支付通道收款'

/* 原站：汇付未开通时「去开通」与「立即支付」均为禁用灰态 */
const huifuOpened = computed(() => Number(userInfo.value && userInfo.value.huifu_status) === 1)

const back = () => {
  if (history.length > 1) router.back()
  else router.replace('/pages/market/detail')
}

onMounted(async () => {
  const id = route.query.id
  if (id) {
    try {
      const res = await getMarketDetail(id, {})
      detail.value = res.data
    } catch (e) { detail.value = null }
  }
  try {
    const res = await getUserInfo()
    const info = (res.data && (res.data.userinfo || res.data.userInfo)) || res.data
    userInfo.value = info || null
    /* 原站：未实名（real_name_authentication = 0）进入本页立即弹实名提示 */
    if (info && Number(info.real_name_authentication) === 0) {
      showVerifyPopup.value = true
    }
  } catch (e) { userInfo.value = null }
})

/* 弹窗按钮（对齐原站：取消关闭、确认去实名页） */
const onCancel = () => { showVerifyPopup.value = false }
const onConfirm = () => {
  showVerifyPopup.value = false
  router.push('/pages/certification/certification')
}
</script>

<template>
  <div class="order-submit">
    <!-- 原生导航栏 @0,0 390x44（标题 16px/700 居中） -->
    <div class="page-head">
      <img class="head-back" src="/static/icon/back-btn.png" alt="返回" @click="back" />
      <div class="head-title">确认订单</div>
    </div>

    <div class="order-body">
      <!-- 商品详情卡片 @0,60 390x55（无数据时仅标题，原站实测） -->
      <div class="card goods-card">
        <div class="card-title">商品详情</div>
        <div v-if="cover" class="goods-row">
          <img class="goods-thumb" :src="cover" :alt="goodsName" />
          <div class="goods-name">{{ goodsName }}</div>
        </div>
      </div>

      <!-- 订单金额卡片 @0,136 390x37 -->
      <div class="card amount-card">
        <div class="flex-js-sb flex-ai-center">
          <span class="amount-label">订单金额:</span>
          <span class="amount-value">￥{{ amount }}</span>
        </div>
      </div>

      <!-- 支付方式卡片 @0,190 390x48 -->
      <div class="card pay-card">
        <div class="pay-row">
          <img class="pay-icon" src="/static/icon/huifupay.png" alt="汇付" />
          <span class="pay-name">汇付</span>
          <div class="pay-tip">{{ payTip }}</div>
        </div>
      </div>
    </div>

    <!-- 底部操作栏 @0,782 390x62 -->
    <div class="action-bar">
      <div class="bar-inner">
        <div class="bar-price">
          <span class="bar-yen">￥</span>
          <span class="bar-num">{{ amount }}</span>
        </div>
        <div class="pay-btn" :class="{ disabled: !huifuOpened }">立即支付</div>
      </div>
    </div>

    <!-- 未实名弹窗（u-popup + w-toast） -->
    <div v-if="showVerifyPopup" class="popup-mask" @click="onCancel"></div>
    <div v-if="showVerifyPopup" class="w-toast">
      <div class="toast-title">提示</div>
      <div class="toast-content">未实名,是否立即实名？</div>
      <div class="toast-btns">
        <div class="toast-btn cancel" @click="onCancel">取消</div>
        <div class="toast-btn ok" @click="onConfirm">确认</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.order-submit {
  position: relative;
  min-height: 100vh;
  background: #fafafa;
  padding-bottom: 62px;
}

/* ---- 原生导航栏 390x44 ---- */
.page-head {
  position: relative;
  height: 44px;
  background: #fff;
}
.head-back {
  position: absolute;
  top: 6px;
  left: 5px;
  width: 27px;
  height: 27px;
}
.head-title {
  position: absolute;
  top: 7px;
  left: 70px;
  width: 250px;
  height: 30px;
  line-height: 30px;
  text-align: center;
  font-size: 16px;
  font-weight: 700;
  color: #000;
}

/* ---- 主体：原站 container.page-container bg #fafafa，按实测绝对坐标 ---- */
.order-body {
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  min-height: 800px;
}

.card {
  position: absolute;
  left: 0;
  width: 390px;
  background: #fff;
  box-sizing: border-box;
}

/* 商品详情 @0,60 390x55：pa8 plr16 */
.goods-card {
  top: 16px;
  min-height: 55px;
  padding: 8px 16px;
}
.card-title {
  height: 21px;
  line-height: 21px;
  font-size: 14px;
  font-weight: 700;
  color: rgb(35, 38, 47);
}
.goods-row {
  display: flex;
  align-items: center;
  height: 72px;
}
.goods-thumb {
  width: 72px;
  height: 72px;
  flex-shrink: 0;
  object-fit: cover;
}
.goods-name {
  margin-left: 16px;
  font-size: 14px;
  font-weight: 700;
  line-height: 15px;
  color: rgb(35, 38, 47);
}

/* 订单金额 @0,136 390x37：pa8 plr16 */
.amount-card {
  top: 87px;
  height: 37px;
  padding: 8px 16px;
}
.flex-js-sb {
  display: flex;
  justify-content: space-between;
}
.flex-ai-center {
  display: flex;
  align-items: center;
}
.amount-label {
  position: relative;
  top: 2px;
  font-size: 14px;
  line-height: 15px;
  color: rgb(35, 38, 47);
}
.amount-value {
  font-size: 14px;
  font-weight: 500;
  line-height: 21px;
  color: rgb(35, 38, 47);
}

/* 支付方式 @0,190 390x48：ptb4 */
.pay-card {
  top: 140px;
  height: 48px;
  padding: 4px 0;
}
.pay-row {
  display: flex;
  align-items: center;
  height: 40px;
  padding: 0 16px;
  box-sizing: border-box;
}
.pay-icon {
  width: 24px;
  height: 24px;
  flex-shrink: 0;
}
.pay-name {
  position: relative;
  top: 1px;
  margin-left: 12px;
  font-size: 12px;
  font-weight: 700;
  line-height: 14px;
  color: rgb(35, 38, 47);
}
.pay-tip {
  margin-left: auto;
  font-size: 12px;
  font-weight: 400;
  line-height: 18px;
  color: rgb(74, 74, 74);
}

/* ---- 底部操作栏 @0,782 390x62 ---- */
.action-bar {
  position: fixed;
  left: 0;
  bottom: 0;
  width: 390px;
  max-width: 100%;
  height: 62px;
  background: #fff;
}
.bar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 62px;
  padding: 0 16px;
  box-sizing: border-box;
}
.bar-price {
  display: flex;
  align-items: baseline;
  flex-shrink: 0;
}
.bar-yen {
  font-size: 12px;
  font-weight: 500;
  line-height: 14px;
  color: rgb(35, 38, 47);
}
.bar-num {
  font-size: 20px;
  font-weight: 500;
  line-height: 22px;
  color: rgb(35, 38, 47);
}
.pay-btn {
  width: 249px;
  height: 47px;
  border-radius: 23px;
  background: #000;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
/* 原站未开通汇付时两按钮均为禁用灰态 */
.pay-btn.disabled,
.open-btn.disabled {
  background: #e3e3e3;
  color: #666;
}

/* ---- 未实名弹窗 ---- */
.popup-mask {
  position: fixed;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  z-index: 100;
}
.w-toast {
  position: fixed;
  left: 50%;
  top: 323px;
  transform: translateX(-50%);
  width: 291px;
  height: 198px;
  padding: 14px 17px 24px;
  box-sizing: border-box;
  background: #fff;
  border-radius: 16px;
  z-index: 101;
}
.toast-title {
  width: 257px;
  height: 24px;
  line-height: 24px;
  text-align: center;
  font-size: 16px;
  font-weight: 700;
  color: rgb(51, 51, 51);
}
.toast-content {
  width: 257px;
  height: 37px;
  margin-top: 0;
  line-height: 21px;
  text-align: center;
  font-size: 14px;
  font-weight: 400;
  color: rgb(51, 51, 51);
}
.toast-btns {
  display: flex;
  justify-content: space-between;
  margin-top: 58px;
}
.toast-btn {
  width: 114px;
  height: 41px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
.toast-btn.cancel {
  background: rgb(241, 240, 240);
  color: #999;
}
.toast-btn.ok {
  background: #000;
  color: #fff;
}
</style>
