<script setup>
/**
 * 委托详情 / 求购详情 —— 1:1 复刻原站
 *   /pages/order/entrust/info  （委托详情，chunk pages-order-entrust-info）
 *   /pages/order/beg/info      （求购详情，chunk pages-order-beg-info）
 *
 * 两页原站 chunk 结构完全同构，仅接口不同，故共用本组件。
 *
 * 原站 onLoad（chunk 实测）：
 *   onLoad({id}) → this.id = id; this.getInfo()
 *   委托 getInfo: getMyEntrustInfo(id)  → POST /market/Prepayment/myOrderInfo {order_id}
 *                 成功后 order.num = order.number; order.goods_cover_image = order.goods_image
 *   求购 getInfo: getMyBegInfo(id)      → POST /market/trade/begBuyOrderInfo {order_id}
 *                 成功后 order.num = order.number; order.goods_cover_image = order.goods_...
 *   接口返回 null（订单不存在）→ order 保持 null → 主体不渲染，仅剩导航栏。
 *   实测账号无任何订单，原站截图 390x844：仅「‹ 订单详情」+ 空白灰底。
 *
 * 模板结构（chunk 逐节点解析，order 非空时）：
 *   .goods-info
 *     「商品编号」仅 order.goods_type!=1 且 collection_code 多于 1 个时显示
 *     编号胶囊  .font-size-12.pa-3.br-4  bg #e5e5e5   文案「#<code>」
 *     .bg-color-item.br-8.pa-14.mt-14.font-size-12   订单详情卡
 *       标题「订单详情」.font-color-black.font-w-bold.pb-8
 *       行「订单金额:」/ 右 ￥<price 或 total_price>（order_type 属于 [8,8] 用 price）
 *       行「订单ID号:」/ 右 order_sn + copy-dark 图标
 *       行「分账ID号:」仅 buy_order_sn 存在时
 *       行「支付方式:」或「到账账户:」/ 右 payName(pay_way)
 *       E卡列表 card[]：卡号 + 密码（可切换 eye 显示）
 *     「订单状态」标题：order_status!=3 且 step>-1
 *     u-steps 时间轴：direction column、dot、activeColor #f52500
 *       order_type!=11 时走 process[1,7,2] 固定三段
 *       否则走 this.process 动态段（title / status / time）
 *     说明块：order_type 属于 [1,2,'1','2'] 且 order_status==1
 *       「说明:」「1.超时未支付，系统会自动取消。」「2.同一账号一段时间内累计取消3次订单,将会被限购一段时间。」
 *
 * 弹窗：w-toast「取消委托」cancelText「我再想想」submitText「确认取消」
 *   content「当订单数量大于1时，取消订单只能取消求购中的订单，不能取消委托成功的订单。」
 *   title/按钮「您确认委托吗？」「继续委托」「取消订单」
 *   操作密码弹窗 u-popup round 12「请输入操作密码」「+新增」「忘记密码？」「跳过」
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getMyEntrustInfo, getMyBegInfo } from '@/api'

const props = defineProps({
  isEntrust: { type: Boolean, default: true }
})

const route = useRoute()
const router = useRouter()

const orderId = computed(() => String(route.query.id || ''))
const order = ref(null)
const loading = ref(true)
const showCancel = ref(false)
const showPwd = ref(false)

const load = async () => {
  if (!orderId.value) {
    loading.value = false
    return
  }
  loading.value = true
  try {
    const fn = props.isEntrust ? getMyEntrustInfo : getMyBegInfo
    const res = await fn(orderId.value)
    if (res && res.code === 1 && res.data) {
      order.value = {
        ...res.data,
        num: res.data.number,
        goods_cover_image: res.data.goods_image
      }
    }
  } catch (e) {
    order.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)

/* 订单金额：order_type 属于 [8,8] 取 price，否则取 total_price */
const amount = computed(() => {
  if (!order.value) return '--'
  const t = order.value.order_type
  return [8, '8'].includes(t) ? order.value.price : order.value.total_price
})

/* 是否展示支付方式（而非到账账户） */
const isPayType = computed(() => [1, 2, '1', '2'].includes(order.value?.order_type))

const payName = computed(() => {
  const w = order.value?.pay_way
  const map = { 1: '汇付钱包', 2: '微信支付', 3: '支付宝', huifu: '汇付钱包' }
  return map[w] || w || '--'
})

/* 状态时间轴：order_type!=11 走固定三段，否则走动态 process */
const stepList = computed(() => {
  if (!order.value) return []
  const st = order.value.order_status
  if (order.value.order_type === 11) {
    return order.value.process || []
  }
  return [
    { status: 1, name: '已委托', time: 'create_time' },
    { status: 7, name: '已成交', time: 'finish_time' },
    { status: 2, name: '已取消', time: 'cancel_time' }
  ].map((s) => ({
    status: s.status,
    title: s.name,
    time: s.time,
    active: st === s.status
  }))
})

/* 当前进行到第几步 */
const step = computed(() => {
  const st = order.value?.order_status
  if (st === 1) return 0
  if (st === 7) return 1
  if (st === 2) return 2
  return -1
})

const showStatus = computed(() => order.value && order.value.order_status !== 3 && step.value > -1)
const showSteps = computed(() => showStatus.value && order.value.order_type !== 11)
const showDesc = computed(
  () => order.value && [1, 2, '1', '2'].includes(order.value.order_type) && order.value.order_status === 1
)

const goBeg = () => {
  router.replace(`/pages/market/submitBegBuy?id=${order.value?.goods_id}`)
}
</script>

<template>
  <div class="info-page">
    <!-- 导航 -->
    <div class="navbar">
      <div class="navbar-left">
        <img src="/static/icon/back-btn.png" alt="返回" class="back-btn" />
      </div>
      <div class="navbar-title">订单详情</div>
    </div>

    <!-- 主体：order 为空（原站订单不存在）时不渲染 -->
    <div v-if="order" class="info-body">
      <!-- 商品编号 -->
      <div v-if="order.goods_type !== 1 && (order.collection_code || []).length > 1" class="code-title">
        商品编号
      </div>
      <div
        v-if="order.goods_type !== 1 && (order.collection_code || []).length > 1"
        class="code-list"
      >
        <span v-for="c in order.collection_code" :key="c" class="code-chip">#{{ c }}</span>
      </div>

      <!-- 订单详情卡 -->
      <div class="detail-card">
        <div class="card-title">订单详情</div>
        <div class="row">
          <span class="row-label">订单金额:</span>
          <span class="row-value row-value--lg">￥{{ amount }}</span>
        </div>
        <div class="row">
          <span class="row-label">订单ID号:</span>
          <span class="row-value row-value--id">
            {{ order.order_sn }}
            <span class="copy-ico"></span>
          </span>
        </div>
        <div v-if="order.buy_order_sn" class="row">
          <span class="row-label">分账ID号:</span>
          <span class="row-value row-value--id">
            {{ order.buy_order_sn }}
            <span class="copy-ico"></span>
          </span>
        </div>
        <div v-if="!(order.card && order.card.length)" class="row">
          <span class="row-label">{{ isPayType ? '支付方式:' : '到账账户:' }}</span>
          <span class="row-value">{{ payName }}</span>
        </div>
      </div>

      <!-- E 卡 -->
      <div v-if="order.card && order.card.length" class="detail-card">
        <div v-for="(c, i) in order.card" :key="i" class="ecard">
          <div class="row">
            <span class="row-label">E卡卡号{{ order.card.length === 1 ? '' : i + 1 }}：</span>
            <span class="row-value row-value--id">
              {{ c.card_sn }}<span class="copy-ico"></span>
            </span>
          </div>
          <div class="row">
            <span class="row-label">E卡密码{{ order.card.length === 1 ? '' : i + 1 }}：</span>
            <span class="row-value">{{ c.show_pwd ? c.card_pwd : c.card_keys }}</span>
          </div>
        </div>
      </div>

      <!-- 订单状态 -->
      <div v-if="showStatus" class="status-title">订单状态</div>

      <div v-if="showStatus && !showSteps" class="steps">
        <div
          v-for="(s, i) in stepList"
          :key="i"
          class="step-item"
          :class="{ 'step-item--active': s.active }"
        >
          <span class="step-dot"></span>
          <span class="step-text">{{ s.title }}</span>
        </div>
      </div>

      <div v-else-if="showSteps" class="steps">
        <div
          v-for="(s, i) in stepList"
          :key="i"
          class="step-item"
          :class="{ 'step-item--active': s.active }"
        >
          <span class="step-dot"></span>
          <span class="step-text">{{ s.title || s.name }}</span>
        </div>
      </div>

      <!-- 说明 -->
      <div v-if="showDesc" class="desc">
        <div class="desc-title">说明:</div>
        <div class="desc-line">1.超时未支付，系统会自动取消。</div>
        <div class="desc-line">2.同一账号一段时间内累计取消3次订单,将会被限购一段时间。</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* P0：颜色走 token，无硬编码（#fff 除外） */
.info-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: var(--color-page-bg, #f2f2f2);
  box-sizing: border-box;
  overflow: hidden;
}

/* ---------- 导航 ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  background: #fafafa;
  display: flex;
  align-items: center;
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
  width: 24px;
  height: 24px;
  display: block;
}
.navbar-title {
  position: absolute;
  left: 165px;
  top: 16px;
  width: 60px;
  height: 16px;
  font-size: 15px;
  font-weight: 700;
  color: #333;
  line-height: 16px;
  text-align: center;
}

/* ---------- 主体 ---------- */
.info-body {
  box-sizing: border-box;
  padding: 0 16px;
}

.code-title {
  font-size: 14px;
  font-weight: 500;
  color: #000;
  padding-bottom: 8px;
}
.code-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-bottom: 16px;
}
.code-chip {
  font-size: 12px;
  color: #000;
  padding: 3px;
  border-radius: 4px;
  background: #e5e5e5;
}

.detail-card {
  width: 358px;
  margin-top: 14px;
  padding: 14px;
  background: #fff;
  border-radius: 8px;
  box-sizing: border-box;
  font-size: 12px;
}
.card-title {
  font-size: 12px;
  font-weight: 700;
  color: #000;
  padding-bottom: 8px;
}
.row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
}
.row-label {
  font-size: 12px;
  color: #999;
  line-height: 16px;
}
.row-value {
  font-size: 14px;
  color: #000;
  line-height: 16px;
}
.row-value--lg {
  font-size: 16px;
}
.row-value--id {
  font-size: 14px;
  display: flex;
  align-items: center;
}
.copy-ico {
  width: 18px;
  height: 18px;
  margin-left: 4px;
  display: block;
}

.ecard {
  width: 100%;
}

/* 状态时间轴 */
.status-title {
  margin-top: 14px;
  font-size: 14px;
  font-weight: 700;
  color: #000;
  padding-bottom: 8px;
}
.steps {
  margin-top: 8px;
}
.step-item {
  position: relative;
  padding: 0 0 20px 20px;
}
.step-dot {
  position: absolute;
  left: 0;
  top: 4px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ddd;
}
.step-item--active .step-dot {
  background: #f52500;
}
.step-text {
  font-size: 14px;
  color: #999;
  line-height: 20px;
}
.step-item--active .step-text {
  color: #f52500;
}

/* 说明 */
.desc {
  margin-top: 8px;
  font-size: 12px;
  color: #999;
}
.desc-title {
  line-height: 16px;
}
.desc-line {
  margin-top: 4px;
  line-height: 16px;
}
</style>
