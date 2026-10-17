<script setup>
/**
 * 我的订单 —— 100% 复刻原站 pages/order/list
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle）：
 *   .u-navbar                       @0,0    390x44   白底  标题「我的订单」居中 16px
 *   返回 img                         @15,11  20x20
 *   .u-tabs（胶囊）                 @15,59  360x41  bg #fff br22
 *     item「正常交易」                @45,69   60x21  15px/800 #000
 *     item「求购订单」                @165,69  60x21  15px/800 #999
 *     item「委托订单」                @285,69  60x21  15px/800 #999
 *   状态 tabs（横向滚动）             y=115  高21
 *     「全部」                       @15,116  26x19  13px/800 #000（下划线在 y137）
 *     「已完成」                     @71,115  45x21  15px/700 #434343
 *     「批量付」                     @146,115 45x21  15px/700 #434343
 *     「待支付」                     @221,115 45x21  15px/700 #434343
 *     「进行中」                     @296,115 45x21  15px/700 #434343
 *     「已取消」                     @371,115 45x21  15px/700 #434343（超出屏宽，横向滚动）
 *   空态图 order-empty.png          @134.5,277 121x100
 *   空态文案「暂无相关订单」
 *   按钮「去看看」                   @151.5,433 87x34  12px/700 白字 bg #000 br17
 *
 * 接口（原站实测）：POST /order/order/orderList  body {page:1,status:0,type:1}
 *   type 1=正常交易 2=求购订单 3=委托订单
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getOrderList } from '@/api'
import { oss } from '@/api/http'

const router = useRouter()

/* 顶部胶囊 tab：正常交易 / 求购订单 / 委托订单 */
const mainTabs = ref([
  { key: 1, text: '正常交易' },
  { key: 2, text: '求购订单' },
  { key: 3, text: '委托订单' }
])
const mainIdx = ref(0)

/* 状态 tab：全部 / 已完成 / 批量付 / 待支付 / 进行中 / 已取消
   原站「全部」为 13px/800，其余 15px/700 —— 选中态样式不同，按实测还原 */
const statusTabs = ref([
  { key: 0, text: '全部', fs: 13 },
  { key: 3, text: '已完成', fs: 15 },
  { key: 6, text: '批量付', fs: 15 },
  { key: 1, text: '待支付', fs: 15 },
  { key: 2, text: '进行中', fs: 15 },
  { key: 4, text: '已取消', fs: 15 }
])
const statusIdx = ref(0)

const list = ref([])
const loading = ref(false)

async function load() {
  loading.value = true
  try {
    const r = await getOrderList({
      page: 1,
      status: statusTabs.value[statusIdx.value].key,
      type: mainTabs.value[mainIdx.value].key
    })
    const d = r.data
    list.value = (d && (d.data || d.list)) || []
  } catch (e) {
    list.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)

/* ---------- 列表项字段映射（原站 chunk 实测） ---------- */

/* 汇付钱包标识：artel_order 为汇付二手市场订单（mh_secondary_market*）时展示「汇」标 */
const isHuifu = (o) => String(o.artel_order || '').indexOf('mh_secondary_market') === 0

/* 订单状态文案（原站 chunk 实测）：
   order_status=2 且为转入类订单时展示「藏品转入成功」，其余按状态映射 */
const STATUS_TEXT = {
  1: '待支付',
  2: '已完成',
  3: '待收货',
  4: '已取消',
  5: '已完成',
  6: '待支付'
}

/* 汇付转入订单（藏品转入成功）：artel_order 前缀 mh_secondary_market + status 2 */
const statusTextOf = (o) => {
  if (isHuifu(o) && Number(o.order_status) === 2) return '藏品转入成功'
  return STATUS_TEXT[o.order_status] || ''
}


/* 藏品编号：原站展示「编号：#<collection_code>」 */
const codeText = (o) => {
  let c = o.collection_code
  if (Array.isArray(c) && c.length) c = c[0]
  if (c === undefined || c === null) return ''
  /* 原站展示「编号：#<序号>」，collection_code 形如 "1926/100000" 时只取前半 */
  return String(c).split('/')[0]
}

/* 主图 */
const coverOf = (o) => {
  const p = o.product || o.collection || {}
  const raw = p.listimg || p.main_image || p.goods_cover_image || o.goods_cover_image || ''
  return raw ? oss(raw) : ''
}

/* 时间：原站格式 YYYY-MM-DD HH:mm:ss */
const timeText = (o) => {
  const t = Number(o.createtime || o.updatetime || 0)
  if (!t) return ''
  const d = new Date(t * 1000)
  const p = (n) => String(n).padStart(2, '0')
  return (
    d.getFullYear() +
    '-' + p(d.getMonth() + 1) +
    '-' + p(d.getDate()) +
    ' ' + p(d.getHours()) +
    ':' + p(d.getMinutes()) +
    ':' + p(d.getSeconds())
  )
}

const nameOf = (o) => o.goods_name || (o.product && o.product.name) || ''

/* 点击卡片进入订单详情：按 order_type 分流 */
const goDetail = (o) => {
  if (!o || !o.id) return
  if (o.order_type === 2) router.push('/pages/order/marketDetail?id=' + o.id)
  else if (o.order_type === 3) router.push('/pages/order/regiftDetail?id=' + o.id)
  else router.push('/pages/order/defaultDetail?id=' + o.id)
}
</script>

<template>
  <div class="order-page">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">我的订单</div>
    </div>

    <!-- 胶囊 tabs @15,59 360x41 br22 -->
    <div class="main-tabs">
      <div
        v-for="(t, i) in mainTabs"
        :key="t.key"
        class="main-tab"
        :class="{ 'is-active': mainIdx === i }"
        @click="mainIdx = i; load()"
      >{{ t.text }}</div>
    </div>

    <!-- 状态 tabs y=115 -->
    <div class="status-tabs">
      <div
        v-for="(t, i) in statusTabs"
        :key="t.key"
        class="status-tab"
        :class="{ 'is-active': statusIdx === i }"
        :style="{ fontSize: t.fs + 'px' }"
        @click="statusIdx = i; load()"
      >{{ t.text }}</div>
    </div>

    <!-- 列表 -->
    <div v-if="list.length" class="order-list">
      <div v-for="(o, i) in list" :key="i" class="order-item" @click="goDetail(o)">
        <!-- 卡头：编号 + 状态徽标 -->
        <div class="oi-head">
          <span class="oi-code">编号：#{{ codeText(o) }}</span>
          <span v-if="statusTextOf(o)" class="oi-badge">{{ statusTextOf(o) }}</span>
        </div>
        <!-- 卡身：图 + 名称 / 标识 + 时间 + 金额 -->
        <div class="oi-body">
          <img v-if="coverOf(o)" :src="coverOf(o)" alt="" class="oi-cover" />
          <div class="oi-info">
            <div class="oi-name">{{ nameOf(o) }}</div>
            <div class="oi-sub">
              <span v-if="isHuifu(o)" class="oi-hui">汇</span>
              <span class="oi-time">{{ timeText(o) }}</span>
              <span class="oi-amount">￥{{ o.total_price }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 空态 @134.5,277 -->
    <div v-else class="empty">
      <img class="empty-img" src="/static/empty/order-empty.png" alt="暂无相关订单" />
      <div class="empty-btn" @click="router.push('/pages/market/market')">去看看</div>
    </div>
  </div>
</template>

<style scoped>
.order-page { min-height: 100vh; background: #f5f5f5; }

/* 导航 @0,0 390x44 白底 */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  background: #fff;
}
.back { position: absolute; left: 15px; top: 11px; width: 20px; height: 20px; }
.nav-title {
  width: 390px;
  height: 44px;
  line-height: 44px;
  text-align: center;
  font-size: 16px;
  color: #000;
}

/* 胶囊 tabs @15,59 360x41 br22 */
.main-tabs {
  display: flex;
  align-items: center;
  width: 360px;
  height: 42px;
  margin: 15px 0 0 15px;
  padding: 0 30px;
  box-sizing: border-box;
  background: #fff;
  border-radius: 22px;
  justify-content: space-between;
}
.main-tab {
  font-size: 15px;
  font-weight: 800;
  color: #999;
}
.main-tab.is-active { color: #000; }

/* 状态 tabs @0,101 390x42 —— 原站实测此栏无白底，与页面同为 #f5f5f5 */
.status-tabs {
  display: flex;
  align-items: flex-start;
  width: 390px;
  height: 42px;
  margin-top: 0;
  padding: 14px 15px 0;
  box-sizing: border-box;
  background: transparent;
  overflow-x: auto;
  white-space: nowrap;
}
.status-tabs::-webkit-scrollbar { display: none; }
/* 原站实测文字左边缘 x: 15/71/146/221/296/371，宽 26/45/45/45/45/45，间距恒为 30px */
.status-tab {
  flex-shrink: 0;
  width: 45px;
  margin-right: 30px;
  font-weight: 700;
  color: #434343;
  position: relative;
  text-align: left;
}
.status-tab:first-child { width: 26px; }
.status-tab.is-active { color: #000; }
/* 原站选中下划线 y=137 高2 宽26 */
.status-tab.is-active::after {
  content: '';
  position: absolute;
  left: 0;
  bottom: -6px;
  width: 26px;
  height: 2px;
  background: #000;
}

/* 空态：原站图 @134.5,277 121x100，按钮 @151.5,433 87x34 */
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 136px;
}
.empty-img { width: 121px; height: 100px; }
.empty-btn {
  width: 87px;
  height: 34px;
  line-height: 34px;
  margin-top: 57px;
  text-align: center;
  border-radius: 17px;
  background: #000;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}

.order-list { padding: 0 16px; }
/* 订单卡 @16,158 358x133 白底 br12 pad12 */
.order-item {
  width: 358px;
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
  box-sizing: border-box;
}
/* 卡头 @28,170 334x30，pb6 */
.oi-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 334px;
  height: 30px;
  padding-bottom: 6px;
  box-sizing: border-box;
}
.oi-code {
  font-size: 12px;
  font-weight: 700;
  color: #333;
  line-height: 14px;
}
/* 状态徽标 .plr-8.ptb-2.br-4 94x24（宽随文案） */
.oi-badge {
  display: block;
  height: 24px;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 13px;
  font-weight: 700;
  color: #000;
  line-height: 20px;
  box-sizing: border-box;
  white-space: nowrap;
}
/* 卡身 @28,200 334x79，pt12 */
.oi-body {
  display: flex;
  width: 334px;
  height: 79px;
  padding-top: 12px;
  box-sizing: border-box;
}
.oi-cover {
  display: block;
  width: 64px;
  height: 64px;
  border-radius: 10px;
  object-fit: cover;
  flex: none;
}
.oi-info {
  display: flex;
  flex-direction: column;
  flex: 1;
  height: 67px;
  padding-left: 12px;
  box-sizing: border-box;
}
/* 名称行 margin 10 0 12 */
.oi-name {
  width: 258px;
  height: 21px;
  margin: 10px 0 12px;
  font-size: 14px;
  font-weight: 500;
  color: #23262f;
  line-height: 15px;
  white-space: nowrap;
  overflow: hidden;
}
.oi-sub {
  display: flex;
  align-items: center;
  width: 258px;
  height: 24px;
}
/* 「汇」标识 13x13 黑底 br2，文字 9px #fff */
.oi-hui {
  display: block;
  width: 13px;
  height: 13px;
  margin-right: 10px;
  border-radius: 2px;
  background: #000;
  font-size: 9px;
  font-weight: 500;
  color: #fff;
  line-height: 13px;
  text-align: center;
  flex: none;
}
.oi-time {
  font-size: 12px;
  font-weight: 400;
  color: #999;
  line-height: 14px;
  white-space: nowrap;
}
.oi-amount {
  margin-left: auto;
  font-size: 16px;
  font-weight: 400;
  color: #222;
  line-height: 17px;
  white-space: nowrap;
}
</style>
