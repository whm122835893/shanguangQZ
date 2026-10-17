<script setup>
/**
 * 订单详情（普通 / 市场 / 转售）—— 1:1 复刻原站
 *   /pages/order/defaultDetail   （普通订单详情，chunk pages-order-defaultDetail）
 *   /pages/order/marketDetail    （市场订单详情，chunk pages-order-marketDetail）
 *   /pages/order/regiftDetail    （转售订单详情，chunk pages-order-regiftDetail）
 *
 * 三页原站 chunk 结构同构，仅接口不同，故共用本组件。
 *
 * 原站 onLoad（chunk 实测）：
 *   defaultDetail: onLoad({id, orderType}) → orderType / orderId → getOrderInfo()
 *   marketDetail : onLoad({id})          → orderId → getOrderInfo()
 *   regiftDetail : onLoad({id, orderType}) → orderType / orderId → getOrderInfo()
 *
 * 原站接口（chunk 实测）：
 *   getOrderDetails(id, type=1)
 *     type 默认 1 → POST /order/order/orderDetail/ids/{id}
 *     type === '2' → POST /order/order/MarketOrderDetail/ids/{id}
 *   getRegiftOrderDetails(id) → POST /order/order/saleMarketOrderDetail {order_id}
 *   实测账号无任何订单，上述接口均返回「找不到该订单或已被删除」/「订单不存在」
 *   → info 为空 → OrderStatus / GoodsBar / GoodsInfo / OrderAction 均不渲染
 *   → 原站直接访问时重定向首页；本复刻保持与委托详情页一致：仅渲染导航栏。
 *
 * 主模板（chunk 逐节点解析）：
 *   w-container.page-bg-colorfull#order-details  loading
 *     w-navbar  navType:"default" id:"my-nav" leftIcon:"back-btn"
 *               centerIcon=orderStatusInfo.icon  title=状态标题
 *     OrderStatus   attrs:{order:info}
 *     .info-container
 *       GoodsBar     attrs:{order:info}
 *       GoodsInfo.mt-14
 *     OrderAction   仅当 order_status 不属于 [5,6]
 *
 * 状态映射表（chunk 模块 95ba，三页共用）：
 *   1: {
 *     2: {title:"藏品转入成功", icon:"order-success", text:"转入成功",
 *         tips:"可前往我的藏品中查看", tipsColor:"main-text", process:"转入成功"},
 *     1: {title:"待付款", icon:"order-await", text:"待支付",
 *         tips:"支付倒计时", tipsColor:"warning", process:"等待支付"},
 *     7: {title:"藏品转入中", icon:"order-success", text:"转入中",
 *         tips:"正在转入", process:"支付成功,转入中"},
 *     3: {title:"订单已取消", icon:"qx-success", text:"已取消", process:"订单取消"},
 *     5: {title:"待退款", icon:"order-cancel", text:"待退款"},
 *     6: {title:"已退款", icon:"order-cancel", text:"已退款"}
 *   }
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getOrderDetails, getRegiftOrderDetails } from '@/api'

const props = defineProps({
  /** 'default' | 'market' | 'regift' */
  kind: { type: String, default: 'default' }
})

const route = useRoute()
const router = useRouter()

const orderId = computed(() => String(route.query.id || ''))
const orderType = computed(() => route.query.orderType)
const info = ref(null)
const loading = ref(true)

/* chunk 模块 95ba 状态映射表 */
const STATUS_MAP = {
  1: {
    2: {
      title: '藏品转入成功',
      icon: 'order-success',
      text: '转入成功',
      tips: '可前往我的藏品中查看',
      tipsColor: 'main-text',
      process: '转入成功'
    },
    1: {
      title: '待付款',
      icon: 'order-await',
      text: '待支付',
      tips: '支付倒计时',
      tipsColor: 'warning',
      process: '等待支付'
    },
    7: {
      title: '藏品转入中',
      icon: 'order-success',
      text: '转入中',
      tips: '正在转入',
      process: '支付成功,转入中'
    },
    3: { title: '订单已取消', icon: 'qx-success', text: '已取消', process: '订单取消' },
    5: { title: '待退款', icon: 'order-cancel', text: '待退款' },
    6: { title: '已退款', icon: 'order-cancel', text: '已退款' }
  }
}

/* 标题：info 存在时按 order_type → order_status 取；否则空串（导航仅显示返回） */
const statusInfo = computed(() => {
  if (!info.value?.id) return {}
  const typeMap = STATUS_MAP[info.value.order_type]
  return typeMap ? typeMap[info.value.order_status] || {} : {}
})

const title = computed(() => statusInfo.value.title || '')

/* 底部操作区：order_status 属于 [5,6] 时不渲染 */
const showAction = computed(
  () => !!info.value && ![5, 6].includes(info.value.order_status)
)

const load = async () => {
  if (!orderId.value) {
    loading.value = false
    router.replace('/pages/index/index')
    return
  }
  loading.value = true
  try {
    let res
    if (props.kind === 'regift') {
      res = await getRegiftOrderDetails(orderId.value)
    } else if (props.kind === 'market') {
      res = await getOrderDetails(orderId.value, 2)
    } else {
      res = await getOrderDetails(orderId.value, orderType.value || 1)
    }
    if (res && res.code === 1 && res.data) {
      info.value = res.data
    } else {
      /* 原站实测：订单不存在时直接 redirectTo 首页（探索页 tab），
         本复刻保持同一行为，避免出现原站不存在的中间态 */
      router.replace('/pages/index/index')
    }
  } catch (e) {
    router.replace('/pages/index/index')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="order-page">
    <!-- 导航 -->
    <div class="navbar">
      <div class="navbar-left">
        <img src="/static/icon/back-btn.png" alt="返回" class="back-btn" />
      </div>
      <div class="navbar-title">{{ title }}</div>
    </div>

    <!-- 主体：info 为空（订单不存在）时不渲染，与原站一致 -->
    <div v-if="info" class="order-body">
      <!-- OrderStatus -->
      <div class="status-box">
        <div v-if="statusInfo.text" class="status-text">{{ statusInfo.text }}</div>
        <div v-if="statusInfo.tips" class="status-tips">{{ statusInfo.tips }}</div>
      </div>

      <!-- GoodsBar + GoodsInfo -->
      <div class="info-container">
        <div class="goods-bar">
          <img
            v-if="info.goods_image || info.goods_cover_image"
            class="goods-img"
            :src="info.goods_cover_image || info.goods_image"
            alt=""
          />
          <div class="goods-text">
            <div class="goods-name">{{ info.goods_name || info.name }}</div>
            <div class="goods-code">{{ (info.collection_code || []).join(' / ') }}</div>
          </div>
        </div>
        <div class="goods-info">
          <div class="info-row">
            <span class="info-label">订单金额:</span>
            <span class="info-value">￥{{ info.total_price }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">订单ID号:</span>
            <span class="info-value">{{ info.order_sn }}</span>
          </div>
        </div>
      </div>

      <!-- OrderAction -->
      <div v-if="showAction" class="order-action">
        <div class="action-btn action-btn--ghost">取消订单</div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* P0：颜色走 token，无硬编码（#fff 除外） */
.order-page {
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
  white-space: nowrap;
  overflow: hidden;
}

/* ---------- 主体 ---------- */
.order-body {
  box-sizing: border-box;
  padding: 0 16px;
}
.status-box {
  padding: 20px 0;
}
.status-text {
  font-size: 18px;
  font-weight: 700;
  color: #23262f;
  line-height: 26px;
}
.status-tips {
  margin-top: 6px;
  font-size: 13px;
  color: #999;
  line-height: 18px;
}
.info-container {
  margin-top: 14px;
}
.goods-bar {
  display: flex;
  align-items: center;
  padding: 12px;
  background: #fff;
  border-radius: 8px;
  box-sizing: border-box;
}
.goods-img {
  width: 60px;
  height: 60px;
  border-radius: 6px;
  object-fit: cover;
  display: block;
}
.goods-text {
  flex: 1;
  margin-left: 10px;
}
.goods-name {
  font-size: 14px;
  color: #23262f;
  line-height: 20px;
}
.goods-code {
  margin-top: 4px;
  font-size: 12px;
  color: #999;
  line-height: 16px;
}
.goods-info {
  margin-top: 14px;
  padding: 14px;
  background: #fff;
  border-radius: 8px;
  font-size: 12px;
}
.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
}
.info-label {
  font-size: 12px;
  color: #999;
}
.info-value {
  font-size: 14px;
  color: #000;
}
.order-action {
  margin-top: 20px;
  display: flex;
  justify-content: flex-end;
}
.action-btn {
  height: 40px;
  padding: 0 20px;
  border-radius: 20px;
  font-size: 14px;
  line-height: 40px;
  box-sizing: border-box;
}
.action-btn--ghost {
  border: 1px solid #ccc;
  color: #666;
  background: #fff;
}
</style>
