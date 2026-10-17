<script setup>
/**
 * 藏品列表页 —— 像素级复刻原站 pages/market/marketGoodsList
 *
 * 路由：#/pages/market/marketGoodsList?id=120&goodsType=2
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle，2026-10-13）：
 *   .good-info          @0,0    390×232  bg #b5b5b5  br 0 0 15 15  overflow:visible
 *     >.goods-info-bg   @0,0    390×390  absolute    bg #b5b5b5
 *                                    bgi:藏品图?image_process=resize,l_420
 *                                    background-size:100%  position:50% 50%
 *                                    br 0 0 15 15   ← 独立层，按宽100%铺，不是 cover
 *     >.goodsImg        @131,82 130×130  absolute z1 br10 of-hidden bg #fff
 *                                    border:3px solid #fff（内层 124×124 object-fit:fill）
 *     >(毛玻璃块)       @0,178  390×85   absolute bottom:-31px
 *                                    bg #f7f7f7  filter:blur(18px)  opacity:1
 *   .tab-view           @0,224  390×613  bg #f7f7f7  mt -8px  br 0 0 8 8  pt 10px
 *     .product-name     @0,249  390×19   mt 15px   13px/800 #111   「Sunny！Sunny！」
 *     .flex-center      @0,277  390×26   mt 9px    ← 限量/流通整行居中
 *       .tag-size-...   @108,277 82×26   br 7px（居中后 x=108）
 *         .name         @110,279 28×22  br 4 0 0 4  pt/pb2 pl/pr4  10px #fff（黑底）
 *         .value        @138,279 50×22  br 0 3 3 0  pt/pb2 pl/pr4  12px/700 #000（白底）
 *       + 同款 ml:10px（流通）
 *     .u-tabs.navbar    @0,303  195×41（两等分）
 *       市场价格文字     @18.8,312.5  60×22  15px/700 #000
 *     .flex-ai-center.flex-js-sb  @0,363 390×37  pl/pr 15px  mt 10px
 *       .market-tab     @15,363 108×37  bg #e8e8e8  br 18px  pt/pl 2px
 *         .it(选中)     @17,365 52×33   bg #fff  br 16px
 *         .i(未选)      @69,365 52×33   无底色
 *           文字        14px/700   寄售 #000 / 求购 #4a4a4a
 *       表头（同行右侧）
 *         价格          @298,377.5 22×12  11px/500 #999
 *         编号          @344,377.5 22×12  11px/500 #999
 *   .plr-14.ptb-8       @0,410  390×2356 pt 8px pl 14px
 *     .tab-item-0       @14,418 362×78  pt 15px
 *       .flex-1.mr-18   @14,433 338×47
 *         行文字        @14,465 338×15  12px
 *   .action             @0,777  390×67   bg #fff  plr 16
 *     快捷下单按钮       @16,787 358×47  14px/700 #fff bg #000 br 23px
 *
 * 接口（抓包实测）：
 *   POST /market/market/marketGoodsDetail          {id, type}
 *   POST /market/market/getMarketGoodsListByGoodsId {page,sort,order,type,keyword,id}
 *   POST /market/trade/begBuyList                  {page,sort,order,type,keyword,id,collection_id}
 *   POST /index/afficheList                        {page,category,list_rows,search}
 *
 * 支付钱包映射：逆向 pages-market-marketGoodsList chunk 得出
 *   data(){ return { payway: { huifu: 'A' } } }
 */
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
  getMarketGoodsDetail,
  getMarketGoodsListByGoodsId,
  getBegBuyList,
  getAfficheListBySearch,
  getUserInfo,
  fastCreateOrder
} from '@/api'
import { oss } from '@/api/http'
import { showToast, showConfirmDialog } from 'vant'

const router = useRouter()
const route = useRoute()

const goodsId = computed(() => String(route.query.id || ''))
const goodsType = computed(() => Number(route.query.goodsType || 2))

const detail = ref(null)
const priceList = ref([])
const buyList = ref([])
const noticeList = ref([])
const tabIdx = ref(0)
/* 市场价格内层：寄售 / 求购 胶囊切换 */
const sideIdx = ref(0)

const tabs = ['市场价格', '相关公告']

/* 原站支付钱包映射 */
const PAYWAY = { huifu: 'A', yibao: 'B', sandpay: 'C' }
function walletName(g) {
  const list = g.income_type || []
  return list.map((t) => PAYWAY[t] || t).join('、')
}

/* ---------------- 快捷下单：支付方式弹层 ----------------
   原站实测（逆向 pages-market-marketGoodsList chunk + 弹层 DOM）：
     u-popup show=showCreate mode=bottom round=12 bgColor=transparent
       .pick-pay.plr-16.ptb-16.br-t-12.bg-color-item  @0,663 390x181
         标题「选择支付方式」        @138,685 90x16  15px/600
         关闭 w-icon close size=24  @350,679 24x24
         支付方式行（单选互斥）mlr-8.ptb-12 + border-bottom 1px rgba(255,255,255,.2)
           图标 18px + 文字 ml-8 15px
         确认按钮 .btn-size-lg.br-23  @16,781 358x47
   交互：
     点击「快捷下单」→ showCreate=true（不是直接跳下单页）
     payTypeInit：按用户钱包开通状态判定 able，默认选中汇付(A)
     确认 → fastCreateOrder({goods_id, key, pay_way}) → order_sn/order_id → 收银台

   钱包开通状态字段（getUserInfo 实测）：
     huifu_account=1 已开通汇付 / yibao_status / sandpay_status / tipay_status
*/
const showCreate = ref(false)
const submitting = ref(false)
/* basePayList：钱包通道。icon 用 SVG 语义名（禁止 emoji），
   statusField 对应 getUserInfo 中的开通状态字段。 */
const basePayList = ref([
  { pay_way: 'A', payWay: 'huifu', statusField: 'huifu_account', name: '汇付钱包', icon: 'wallet-huifu', check: true, able: true },
  { pay_way: 'B', payWay: 'yibao', statusField: 'yibao_status', name: '易宝钱包', icon: 'wallet-yibao', check: false, able: false },
  { pay_way: 'C', payWay: 'sandpay', statusField: 'sandpay_status', name: '杉德钱包', icon: 'wallet-sand', check: false, able: false }
])

/** payTypeInit：按 getUserInfo 的钱包开通状态判定 able，默认选中汇付(A) */
async function payTypeInit() {
  try {
    const r = await getUserInfo()
    const u = (r && r.data) || {}
    basePayList.value = basePayList.value.map((t) => {
      const able = Number(u[t.statusField]) === 1
      return { ...t, able, check: able && t.pay_way === 'A' }
    })
  } catch (e) {
    /* 用户信息拉取失败时保持默认（汇付已开通）*/
  }
}

/** checkFn：原站为单选互斥（点已选中项则取消选中） */
function checkFn(item) {
  basePayList.value = basePayList.value.map((t) =>
    t.pay_way === item.pay_way ? { ...t, check: !t.check } : { ...t, check: false }
  )
}

const selectedPay = computed(() => basePayList.value.find((t) => t.check))

/** 确认下单：调用原站 fastOrderNew
 *  实测抓包（2026-10-16 23:07）：
 *    POST /order/pay/fastOrderNew
 *    {"goods_id":155,"key":"db52d2597fe01aaeac78d7f10331e677","pay_way":"huifu"}
 *    -> {"code":1,"msg":"下单成功","data":{"order_sn":"...","order_id":"18554","time":"300"}}
 *  注意两点：
 *    1. pay_way 传钱包名（huifu/yibao/sandpay），不是列表行 code(A/B/C)；
 *    2. key 来自在售列表项，缺 key 后端直接返回 code:0「未找到合适商品」。
 */
async function confirmOrder() {
  const pay = selectedPay.value
  if (!pay) {
    showToast({ icon: 'none', message: '请选择支付方式' })
    return
  }
  /* key 来自商品详情（marketGoodsDetail.product.key），不在列表项里 */
  const key = detail.value && detail.value.key
  if (!key) {
    showToast({ icon: 'none', message: '商品信息加载中，请稍候' })
    return
  }
  if (submitting.value) return
  submitting.value = true
  try {
    const r = await fastCreateOrder({
      goods_id: goodsId.value,
      key: key,
      pay_way: pay.payWay
    })
    const d = (r && r.data) || {}
    showCreate.value = false
    submitting.value = false
    /* 原站下单成功后弹「支付风险确认」页（已知晓交易风险并确认支付），
       此处按原站文案与二段式确认还原 */
    await showConfirmDialog({
      title: '支付风险确认',
      message:
        '1. 您确认购买本数字藏品，已知悉本藏品为虚拟数字商品，一旦购买完成不支持退换。\n' +
        '2. 您的支付行为代表您已经知晓并理解此次数字藏品购买的交易规则，若对此存在不理解或异议，请放弃此次支付。\n' +
        '3. 您应严格保管支付密码和验证码，密码代表您的支付意愿，因密码泄露所造成的经济损失将自行承担。',
      confirmButtonText: '已知晓交易风险并确认支付',
      cancelButtonText: '放弃支付'
    })
      .then(() => {
        router.push('/pages/order/orderSubmit?order_id=' + (d.order_id || ''))
      })
      .catch(() => {})
  } catch (e) {
    showToast({ icon: 'none', message: (e && e.msg) || '下单失败，请稍后重试' })
    submitting.value = false
  }
}

async function loadDetail() {
  try {
    const res = await getMarketGoodsDetail({ id: goodsId.value, type: goodsType.value })
    const d = res.data
    detail.value = d && (d.data || d)
  } catch (e) {
    detail.value = null
  }
  /* 原站 onLoad：getInfo() 后 getUserInfo().then(() => payTypeInit()) */
  payTypeInit()
  loadTab()
}

async function loadTab() {
  if (tabIdx.value === 0) {
    try {
      const res = await getMarketGoodsListByGoodsId({
        page: 1,
        sort: 'price',
        order: 'asc',
        type: goodsType.value,
        keyword: '',
        id: goodsId.value
      })
      const d = res.data
      /* 实测结构：{"code":1,"data":{"list":[{id,collection_code,price,status,...}],"last_page":n}} */
      priceList.value = Array.isArray(d) ? d : (d && (d.list || d.data)) || []
    } catch (e) {
      priceList.value = []
    }
  } else {
    try {
      const res = await getAfficheListBySearch({
        page: 1,
        category: 0,
        list_rows: 25,
        search: detail.value && detail.value.name ? detail.value.name : ''
      })
      const d = res.data
      noticeList.value = Array.isArray(d) ? d : (d && d.data) || []
    } catch (e) {
      noticeList.value = []
    }
  }
}

async function loadBuyList() {
  try {
    const res = await getBegBuyList({
      page: 1,
      sort: 'desc',
      order: 'asc',
      type: goodsType.value,
      keyword: '',
      id: goodsId.value,
      collection_id: goodsId.value
    })
    const d = res.data
    buyList.value = Array.isArray(d) ? d : (d && (d.list || d.data)) || []
  } catch (e) {
    buyList.value = []
  }
}

function switchTab(i) {
  tabIdx.value = i
  if (i === 0) sideIdx.value = 0
  loadTab()
}

function switchSide(i) {
  if (sideIdx.value === i) return
  sideIdx.value = i
  if (i === 1) loadBuyList()
  else loadTab()
}

function onSort(field) {
  // 原站表头可点排序（价格/编号），此处保持切换排序字段
  if (sortField.value === field) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortField.value = field
    sortOrder.value = 'asc'
  }
  loadTab()
}
const sortField = ref('price')
const sortOrder = ref('asc')

/* 藏品图：统一走本地 /static/oss。
   原站用 img.gandart.shop + ?image_process=resize,l_420 做 CDN 缩放，
   但那会让页面直接依赖外网 CDN —— 断网/域名变更即整块白图。
   本项目做法：oss() 走本地静态资源；未预下载的图由 server.cjs 的
   /static/oss 兜底从原站拉取并落盘（首次回源，之后命中本地）。
   缩放交给 CSS object-fit + 固定尺寸，不依赖 CDN 处理参数。 */
const bgImg = computed(() => oss(detail.value && detail.value.listimg))

function back() {
  if (window.history.length > 1) router.back()
  else router.replace('/pages/market/market')
}

onMounted(loadDetail)
</script>

<template>
  <div class="goods-list-page">
    <!-- 头部：原站 good-info 高 232，overflow:visible
         >.goods-info-bg 独立 390×390 层按宽 100% 铺藏品图
         >.goodsImg     130×130 缩略图（3px 白边）
         >毛玻璃块       bottom:-31px 高 85 blur(18px) —— 不是名称区 -->
    <div class="good-info">
      <div class="goods-info-bg" :style="bgImg ? { backgroundImage: `url(${bgImg})` } : null"></div>

      <img class="status-back" src="/static/icon/back-btn-blank.png" alt="返回" @click="back" />
      <img class="status-share" src="/static/icon/zj-cjjl.png" alt="分享" />
      <img class="status-like" src="/static/icon/no-like-wjx.png" alt="点赞" />

      <div class="goodsImg">
        <img v-if="bgImg" :src="bgImg" :alt="detail && detail.name" />
      </div>

      <!-- 毛玻璃过渡块：原站 bottom:-31px + blur(18px) -->
      <div class="goods-mask"></div>
    </div>

    <!-- Tab 区：名称 / 限量流通 / Tabs / 胶囊+表头 / 列表 全在 tab-view 内 -->
    <div class="tab-view">
      <!-- 名称 @0,249 mt15 -->
      <div class="product-name">
        <span>{{ detail ? detail.name : '' }}</span>
      </div>

      <!-- 限量 / 流通 @0,277 mt9 居中 -->
      <div class="stat-line flex-center">
        <div class="tag-size">
          <span class="tag-name">{{ detail && detail.is_limit !== undefined ? '限量' : '限量' }}</span>
          <span class="tag-value">{{ detail && detail.total_num }}份</span>
        </div>
        <div class="tag-size tag-size-flex">
          <span class="tag-name">流通</span>
          <span class="tag-value">{{ detail && detail.flux }}份</span>
        </div>
      </div>

      <!-- Tabs @0,303 195×41 -->
      <div class="tabs-nav">
        <div
          v-for="(t, i) in tabs"
          :key="t"
          class="tab-cell"
          :class="{ 'is-active': tabIdx === i }"
          @click="switchTab(i)"
        >
          <span class="tab-text">{{ t }}</span>
        </div>
      </div>

      <!-- 市场价格：胶囊 + 排序表头 同一行 @0,363 -->
      <div v-if="tabIdx === 0" class="switch-bar">
        <div class="market-tab">
          <div
            class="it"
            :class="{ active: sideIdx === 0 }"
            @click="switchSide(0)"
          >
            <span>寄售</span>
          </div>
          <div
            class="i"
            :class="{ active: sideIdx === 1 }"
            @click="switchSide(1)"
          >
            <span>求购</span>
          </div>
        </div>

        <div class="sort-group">
          <span class="th" @click="onSort('price')">
            价格 <i class="sort-arrow"></i>
          </span>
          <span class="th" @click="onSort('code')">
            编号 <i class="sort-arrow"></i>
          </span>
        </div>
      </div>

      <!-- 列表 @0,410 plr14 ptb8 -->
      <div v-if="tabIdx === 0" class="goods-list">
        <template v-if="sideIdx === 0">
          <div
            v-for="g in priceList"
            :key="g.id"
            class="tab-item-0"
            @click="router.push('/pages/market/detail?id=' + g.id)"
          >
            <!-- 原站实测 .flex-1.mr-18 @14,433 338×47
                 行内两行：① 名称 + 价格（价格右对齐到 x=352）② 编号 + 支付钱包
                 行右 6px 处有 base64 箭头图 6×11 @370 -->
            <div class="row-inner">
              <div class="row-line1 flex-ai-center flex-js-sb mb-10">
                <span class="row-name">{{ detail && detail.name }}</span>
                <span class="row-price">
                  <span class="yen">￥</span><span class="val">{{ g.price }}</span>
                </span>
              </div>
              <div class="row-line2">
                <span class="row-no">#{{ g.collection_code }}</span>
                <span class="row-wallet">
                  支付钱包：<span class="wallet-tag">{{ walletName(g) }}</span>
                </span>
              </div>
            </div>
            <img class="row-arrow" src="/static/icon/row-arrow.png" alt="" />
          </div>

          <div v-if="!priceList.length" class="empty">
            <img src="/static/gif/loading.gif" alt="加载中" />
          </div>
        </template>

        <template v-else>
          <div v-for="g in buyList" :key="g.id" class="tab-item-0">
            <div class="row-inner">
              <div class="row-line1 flex-ai-center flex-js-sb mb-10">
                <span class="row-name">{{ detail && detail.name }}</span>
                <span class="row-price">
                  <span class="yen">￥</span><span class="val">{{ g.price }}</span>
                </span>
              </div>
              <div class="row-line2">
                <span class="row-no">#{{ g.collection_code }}</span>
                <span class="row-wallet">求购</span>
              </div>
            </div>
            <img class="row-arrow" src="/static/icon/row-arrow.png" alt="" />
          </div>

          <div v-if="!buyList.length" class="empty">
            <img src="/static/empty/search-empty.png" alt="暂无" />
          </div>
        </template>
      </div>

      <!-- 相关公告 -->
      <div v-else class="notice-list">
        <div
          v-for="n in noticeList"
          :key="n.id"
          class="notice-row"
          @click="router.push('/pages/notice/notice?id=' + n.id)"
        >
          <div class="notice-title">{{ n.title || n.name }}</div>
          <div class="notice-sub">
            <span class="notice-type">{{ n.type_name || n.cate_name || '公告' }}</span>
            <span class="notice-date">{{ n.create_time || n.add_time }}</span>
          </div>
        </div>

        <div v-if="!noticeList.length" class="empty">
          <img src="/static/empty/search-empty.png" alt="暂无" />
        </div>
      </div>
    </div>

    <!-- 底部操作栏 @0,777 -->
    <div class="action">
      <div class="btn-quick" @click="showCreate = true">快捷下单</div>
    </div>

    <!-- 支付方式弹层（u-popup mode=bottom round=12 bgColor=transparent）
         .pick-pay @0,663 390x181 -->
    <div v-if="showCreate" class="pay-mask" @click="showCreate = false">
      <div class="pick-pay" @click.stop>
        <div class="pick-pay-title">
          <span class="pick-pay-title-text">选择支付方式</span>
          <!-- 关闭图标：SVG 描边，禁 emoji -->
          <svg class="pick-pay-close" viewBox="0 0 24 24" @click="showCreate = false">
            <path
              d="M5 5 L19 19 M19 5 L5 19"
              stroke="currentColor"
              stroke-width="1.6"
              stroke-linecap="round"
              fill="none"
            />
          </svg>
        </div>

        <div
          v-for="t in basePayList"
          v-show="t.able"
          :key="t.pay_way"
          class="pay-row"
          :class="{ disabled: !t.able }"
          @click="t.able && checkFn(t)"
        >
          <div class="pay-row-left">
            <!-- 钱包图标：按 pay_way 区分的描边 SVG -->
            <svg v-if="t.icon === 'wallet-huifu'" class="pay-icon" viewBox="0 0 18 18">
              <rect x="1.5" y="3.5" width="15" height="11" rx="2" stroke="currentColor" stroke-width="1.3" fill="none" />
              <path d="M1.5 7 H16.5" stroke="currentColor" stroke-width="1.3" />
              <circle cx="12.5" cy="11.5" r="1.2" fill="currentColor" />
            </svg>
            <svg v-else class="pay-icon" viewBox="0 0 18 18">
              <rect x="1.5" y="3.5" width="15" height="11" rx="2" stroke="currentColor" stroke-width="1.3" fill="none" />
              <path d="M6 3.5 V1.8 M12 3.5 V1.8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" />
              <circle cx="9" cy="9.5" r="2.6" stroke="currentColor" stroke-width="1.3" fill="none" />
            </svg>
            <span class="pay-name">{{ t.name }}</span>
          </div>
          <!-- 选中态：描边勾选图标，禁 emoji -->
          <svg v-if="t.check" class="pay-check" viewBox="0 0 16 16">
            <circle cx="8" cy="8" r="7" fill="#F23D4D" />
            <path d="M4.6 8.2 L7 10.6 L11.4 5.8" stroke="#fff" stroke-width="1.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>

        <div class="btn-confirm" @click="confirmOrder">
          {{ submitting ? '提交中...' : '确认' }}
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.goods-list-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  background: #f6f6f6;
  min-height: 844px;
  overflow: hidden;
  box-sizing: border-box;
}

/* ========== 头部 good-info（实测 390×232 overflow:visible） ========== */
.good-info {
  position: relative;
  height: 232px;
  background: #b5b5b5;
  border-radius: 0 0 15px 15px;
}

/* 原站独立背景层：390×390，background-size:100%，position:50% 50%
   —— 按宽 100% 铺（不是 cover），圆角同父级 */
.goods-info-bg {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 390px;
  background-color: #b5b5b5;
  background-position: 50% 50%;
  background-size: 100%;
  background-repeat: no-repeat;
  border-radius: 0 0 15px 15px;
}

.status-back {
  position: absolute;
  top: 10px;
  left: 13px;
  width: 24px;
  height: 24px;
  z-index: 3;
}
.status-share {
  position: absolute;
  top: 4px;
  right: 54px;
  width: 31px;
  height: 31px;
  z-index: 3;
}
.status-like {
  position: absolute;
  top: 4px;
  right: 13px;
  width: 31px;
  height: 31px;
  z-index: 3;
}

/* 缩略图 @131,82 130×130（border-box，含 3px 白边 → 内层 124×124） */
.goodsImg {
  position: absolute;
  top: 82px;
  left: 131px;
  width: 130px;
  height: 130px;
  box-sizing: border-box;
  border: 3px solid #fff;
  border-radius: 10px;
  background: #fff;
  overflow: hidden;
  z-index: 1;
}
.goodsImg img {
  position: absolute;
  top: 0;
  left: 0;
  width: 124px;
  height: 124px;
  object-fit: fill;
  display: block;
}

/* 毛玻璃过渡块 @0,178 390×85 bottom:-31px blur(18px) */
.goods-mask {
  position: absolute;
  left: 0;
  top: 178px;
  width: 390px;
  height: 85px;
  background: #f7f7f7;
  filter: blur(18px);
  opacity: 1;
  z-index: 2;
}

/* ========== tab-view @0,224 390×613 pt10 mt-8 ==========
   原站 z-index: auto，靠 DOM 顺序盖住 good-info 下沿；
   .action 同样 z-index:auto 且在其后 → 底栏不被盖。
   （早期版本给 tab-view 加了 z-index:3，导致底部快捷下单被遮住） */
.tab-view {
  position: relative;
  margin-top: -8px;
  padding-top: 10px;
  background: #f7f7f7;
  border-radius: 0 0 8px 8px;
}

/* 名称 @0,249 390×19 mt15 13px/800 #111 */
.product-name {
  position: relative;
  margin-top: 15px;
  height: 19px;
  line-height: 19px;
  text-align: center;
  font-size: 13px;
  font-weight: 800;
  color: #111;
}

/* 限量/流通行 @0,277 390×26 mt9 居中 */
.stat-line {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 26px;
  margin-top: 9px;
}
.tag-size {
  display: flex;
  align-items: stretch;
  height: 26px;
  padding: 0 2px;
  border-radius: 7px;
  overflow: hidden;
}
.tag-size-flex {
  margin-left: 10px;
}
.tag-name {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 22px;
  width: 28px;
  padding: 2px 4px;
  box-sizing: border-box;
  background: #000;
  border-radius: 4px 0 0 4px;
  font-size: 10px;
  line-height: 15px;
  color: #fff;
}
.tag-value {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 22px;
  width: 50px;
  padding: 2px 4px;
  box-sizing: border-box;
  background: #fff;
  border-radius: 0 3px 3px 0;
  font-size: 12px;
  font-weight: 700;
  line-height: 15px;
  color: #000;
}

/* Tabs @0,303 每格 195×41
   原站实测：文字 @18.8,312.5（格内左对齐 +18.8，非居中）
   市场价格 = 第1格，格宽 195 → 文字 x = 18.8
   相关公告 = 第2格，格起点 195 → 文字 x = 213.8 */
.tabs-nav {
  display: flex;
  height: 41px;
  margin-top: 0;
}
.tab-cell {
  position: relative;
  flex: 1;
  height: 41px;
  display: flex;
  align-items: flex-start;
  justify-content: flex-start;
  padding-left: 18.8px;
  box-sizing: border-box;
}
.tab-text {
  font-size: 15px;
  line-height: 22px;
  margin-top: 9.5px;
  color: #999;
}
.tab-cell.is-active .tab-text {
  color: #000;
  font-weight: 700;
}
.tab-cell.is-active::after {
  content: '';
  position: absolute;
  left: 18.8px;
  top: 33px;
  width: 20px;
  height: 2px;
  background: #000;
  border-radius: 1px;
}

/* 胶囊 + 表头 同一行 @0,363 390×37 pl/pr15 mt19
   （原站 tabs 底部 344 → 胶囊 363，间距 19 = mt10 + u-tabs 内部 9） */
.switch-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 37px;
  margin-top: 19px;
  padding: 0 15px;
}
/* .market-tab 108×37 bg #e8e8e8 br18 pt/pl 2 */
.market-tab {
  display: flex;
  padding: 2px;
  height: 37px;
  border-radius: 18px;
  background: #e8e8e8;
}
.it,
.i {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 33px;
  border-radius: 16px;
  font-size: 14px;
  font-weight: 700;
  line-height: 21px;
  color: #4a4a4a;
}
.it.active {
  background: #fff;
  color: #000;
}

/* 表头 @298,377.5 / @344,377.5 22×12 11px/500 #999 */
.sort-group {
  display: flex;
  align-items: center;
  padding-right: 9px;
}
.th {
  display: inline-flex;
  align-items: center;
  width: 22px;
  font-size: 11px;
  font-weight: 500;
  color: #999;
  white-space: nowrap;
}
.th + .th {
  margin-left: 24px;
}
.sort-arrow {
  display: inline-block;
  width: 0;
  height: 0;
  margin-left: 2px;
  border-left: 3px solid transparent;
  border-right: 3px solid transparent;
  border-bottom: 4px solid #ccc;
}

/* ========== 列表 @0,410 390×416 plr14 ptb8（固定高，内部滚动） ========== */
.goods-list,
.notice-list {
  /* 原站 scroll-view @0,410 390×416（胶囊行底 400 + 10 间距），固定高度内部滚动 */
  margin-top: 10px;
  height: 416px;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding: 8px 14px 20px;
  box-sizing: border-box;
}
/* 行 @14,418 362×78 pt15（内容 338×47 @14,433）
   原站实测：价格 ￥5 @330,439 12×14 / 5 @342,433 10×22 → 整组右端 x=352
   行右侧箭头 @370,451 6×11（居中） */
.tab-item-0 {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 78px;
  padding: 15px 0;
  box-sizing: border-box;
  border-bottom: 1px solid #f5f5f5;
}
/* 原站实测 .flex-1.mr-18 @14,433 338×47
   行宽 362，mr-18 + 行右 6px 内边距 → 内容 338（价格右端 x=352，箭头 @370 起） */
.row-inner {
  flex: 1;
  min-width: 0;
  height: 47px;
  margin-right: 18px;
  padding-right: 6px;
  box-sizing: border-box;
}
/* 行右箭头 @370,451 6×11（原站 base64 内联图，已提取为 row-arrow.png） */
.row-arrow {
  position: absolute;
  right: 4px;
  top: 50%;
  margin-top: -5.5px;
  width: 6px;
  height: 11px;
  object-fit: fill;
}
/* 原站第1行 @14,433 338×22（名称 + 价格）
   价格整组右端 x=352（行内容右界），箭头占 x=370 起 */
.row-line1 {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 22px;
  margin-bottom: 10px;
  padding-right: 0;
}
.row-name {
  font-size: 12px;
  font-weight: 700;
  color: #4a4a4a;
  line-height: 18px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.row-price {
  flex-shrink: 0;
  display: flex;
  align-items: baseline;
}
.yen {
  font-size: 12px;
  font-weight: 500;
  color: #23262f;
}
.val {
  font-size: 18px;
  font-weight: 500;
  color: #23262f;
  line-height: 22px;
}
/* 原站第2行 @14,465 338×15（编号 + 支付钱包）
   钱包组 @282.8,465 69.2×15；胶囊「A」@332.8,466 17.2×13（padding:1px 5px, br2） */
.row-line2 {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 15px;
}
.row-no {
  font-size: 12px;
  font-weight: 700;
  color: #23262f;
  line-height: 14px;
}
.row-wallet {
  display: flex;
  align-items: center;
  font-size: 10px;
  font-weight: 700;
  color: #666;
  line-height: 15px;
}
.wallet-tag {
  display: inline-block;
  margin-left: 2px;
  padding: 1px 5px;
  border-radius: 2px;
  background: #e8e8e8;
  color: #666;
  font-size: 10px;
  line-height: 11px;
  height: 13px;
  box-sizing: border-box;
}

.notice-row {
  padding: 12px 0;
  border-bottom: 1px solid #f0f0f0;
}
.notice-title {
  font-size: 13px;
  color: #23262f;
  line-height: 18px;
  overflow: hidden;
  text-overflow: ellipsis;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.notice-sub {
  margin-top: 6px;
}
.notice-type {
  font-size: 10px;
  color: #f23d4d;
  margin-right: 8px;
}
.notice-date {
  font-size: 11px;
  color: #999;
}

.empty {
  padding-top: 46px;
  display: flex;
  justify-content: center;
}
.empty img {
  width: 121px;
  height: 100px;
}

/* ========== 底部操作栏 @0,777 390×67 ========== */
.action {
  position: fixed;
  left: 0;
  bottom: 0;
  width: 390px;
  max-width: 100%;
  height: 67px;
  padding: 10px 16px;
  box-sizing: border-box;
  background: #fff;
  display: flex;
  align-items: center;
}
.btn-quick {
  flex: 1;
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

/* ---------------- 支付方式弹层（原站 u-popup mode=bottom round=12）
   实测：.pick-pay @0,663 390x181
        标题 @138,685 90x16 15px/600
        关闭 @350,679 24x24
        支付行 mlr-8 ptb-12 + border-bottom 1px rgba(255,255,255,.2)
        确认 .btn-size-lg.br-23 @16,781 358x47---------------- */
.pay-mask {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 844px;
  background: rgba(0, 0, 0, 0.6);
  z-index: 60;
  display: flex;
  align-items: flex-end;
}
.pick-pay {
  width: 390px;
  height: 181px;
  box-sizing: border-box;
  padding: 16px;
  background: #f7f5f2;
  border-radius: 12px 12px 0 0;
  box-sizing: border-box;
}
.pick-pay-title {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 24px;
}
.pick-pay-title-text {
  font-size: 15px;
  font-weight: 600;
  color: #1a1512;
}
.pick-pay-close {
  position: absolute;
  right: 0;
  top: 4px;
  width: 24px;
  height: 24px;
  color: #4a4a4a;
  cursor: pointer;
}
.pay-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 0 8px;
  padding: 12px 0;
  height: 39px;
  box-sizing: border-box;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}
.pay-row.disabled {
  opacity: 0.4;
}
.pay-row-left {
  display: flex;
  align-items: center;
}
/* 钱包图标 18x18 */
.pay-icon {
  width: 18px;
  height: 18px;
  color: #1a1512;
  flex-shrink: 0;
}
.pay-name {
  margin-left: 8px;
  font-size: 15px;
  color: #1a1512;
}
.pay-check {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
}
/* 确认按钮 @16,781 358x47 br23 */
.btn-confirm {
  margin-top: 39px;
  width: 358px;
  height: 47px;
  border-radius: 23px;
  background: #1a1512;
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>