<script setup>
/**
 * 藏品行情（卖出信息）—— 1:1 复刻原站 pages/market/sellInfo
 *
 * 注意：原站 sellInfo 不是「卖出表单」，而是单个藏品的成交/求购/寄售三态行情页。
 *
 * 原站 390x844 实测（?id=124 天才企鹅）：
 *   .u-navbar--fixed          @0,0  390x44 bg #fafafa
 *     返回 back-btn.png       @13,10  24x24
 *     标题「天才企鹅」          @165,16 60x16  15px/700 #333
 *     收藏 no-like.png        @353,10 24x24
 *
 *   Tab 区（三个并列）
 *     「成交记录」.title       @75,60   64x24  16px/600 #333  （选中）
 *       「6单」               @99.5,84 15x15  10px #333
 *     「当前求购」.title       @163,60  64x24  16px/400 #999
 *       「0单」               @187.5,84 15x15 10px #999
 *     「当前寄售」.title       @251,60  64x24  16px/400 #999
 *       「26单」              @273,84   20x15 10px #999
 *
 *   成交行（行高 55px）
 *     时间「11小时前」          @24,120  59.5x16  15px #333
 *     编号 .font-size-14       @164.9,115 78.3x22 14px #333
 *     总量 span 12px #999     @209.9,115 33.3x14「/20000」
 *     价格                   @324.8,115 41.3x22  15px #333「￥3.00」
 *     分隔线 y≈146 / 201 / 256 ...
 *
 *   到底提示 @0,445 390x18  12px #ababab「已经到底了~」
 *
 * 接口（原站实测）：
 *   POST /market/trade/tradingInformation {status:1,page:1,goods_id:"124"}
 *     data: {list:[{price,collection_code,total_num,time,...}],
 *            transaction_record_number, current_buying, current_consignment}
 *     status: 1=成交记录  2=当前求购  3=当前寄售
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getTradingInformation } from '@/api'

const route = useRoute()
const router = useRouter()

const goodsId = computed(() => route.query.id || '')

/* status: 1=成交记录 2=当前求购 3=当前寄售 */
const TABS = [
  { status: 1, title: '成交记录', countKey: 'transaction_record_number' },
  { status: 2, title: '当前求购', countKey: 'current_buying' },
  { status: 3, title: '当前寄售', countKey: 'current_consignment' }
]
const idx = ref(0)
const data = ref({})
const list = ref([])

const name = computed(() => (list.value[0] && list.value[0].goods_name) || '')

function countOf(t) {
  return data.value[t.countKey] ?? 0
}

async function load() {
  if (!goodsId.value) return
  try {
    const r = await getTradingInformation({
      status: TABS[idx.value].status,
      page: 1,
      goods_id: String(goodsId.value)
    })
    const d = r.data || {}
    if (idx.value === 0) {
      /* 三个 tab 的计数只在成交记录接口里返回，需保留 */
      data.value = { ...data.value, ...d }
    } else {
      data.value = { ...data.value, ...d }
    }
    list.value = d.list || []
  } catch (e) {
    list.value = []
  }
}

function switchTab(i) {
  if (idx.value === i) return
  idx.value = i
  load()
}

onMounted(load)
</script>

<template>
  <div class="sellinfo-page">
    <!-- 导航 @0,0 390x44 -->
    <div class="u-navbar--fixed">
      <div class="u-navbar__content">
        <div class="u-navbar__content__left">
          <div class="flex-center" @click="router.back()">
            <img class="back-img" src="/static/icon/back-btn.png" alt="返回" />
          </div>
        </div>
        <span class="nav-title">{{ name }}</span>
        <div class="u-navbar__content__right">
          <img class="like-btn" src="/static/icon/no-like.png" alt="收藏" />
        </div>
      </div>
    </div>

    <!-- Tab 区 -->
    <div class="tabs-row">
      <div
        v-for="(t, i) in TABS"
        :key="t.status"
        class="tab-item"
        @click="switchTab(i)"
      >
        <div class="font-size-16 title" :class="{ active: i === idx }">{{ t.title }}</div>
        <div class="font-size-10 cnt" :class="{ active: i === idx }">{{ countOf(t) }}单</div>
      </div>
    </div>

    <!-- 列表 -->
    <div class="list-scroll">
      <div v-for="(g, i) in list" :key="g.user_collection_id || i" class="row-item">
        <span class="row-time">{{ g.time }}</span>
        <div class="row-code font-color-textPrimary font-size-14">
          <span class="code-no">#{{ g.collection_code }}</span>
          <span class="code-total">/{{ g.total_num }}</span>
        </div>
        <span class="row-price">￥{{ g.price }}</span>
      </div>
      <div v-if="list.length" class="font-size-12 font-color-describe flex-center list-end">
        已经到底了~
      </div>
    </div>
  </div>
</template>

<style scoped>
.sellinfo-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fafafa;
  box-sizing: border-box;
  overflow: hidden;
  color: #333;
}

.flex-center { display: flex; align-items: center; justify-content: center; }
.font-size-16 { font-size: 16px; }
.font-size-14 { font-size: 14px; }
.font-size-12 { font-size: 12px; }
.font-size-10 { font-size: 10px; }
.font-color-textPrimary { color: #333; }
.font-color-describe { color: #ababab; }

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

.u-navbar__content__right {
  position: absolute;
  right: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
}

.like-btn {
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

/* Tab 区：原站三项中心分别在 x=107/195/283（标题左边界 75/163/251），
   标题 64px 宽、计数紧跟标题下方居中。整体为 flex 布局居中排布。 */
.tabs-row {
  position: absolute;
  left: 0;
  top: 60px;
  width: 390px;
  height: 39px;
  display: flex;
  align-items: flex-start;
  justify-content: center;
}

.tab-item {
  width: 88px;
  height: 39px;
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.title {
  height: 24px;
  line-height: 24px;
  font-weight: 400;
  color: #999;
  white-space: nowrap;
}

.title.active {
  font-weight: 600;
  color: #333;
}

.cnt {
  height: 15px;
  line-height: 15px;
  color: #999;
}

.cnt.active {
  color: #333;
}

/* 成交行：行高 55px，底部 1px 分隔线 */
.list-scroll {
  position: absolute;
  left: 0;
  top: 105px;
  width: 390px;
  height: 360px;
  overflow-y: auto;
  background: #fafafa;
}

.row-item {
  position: relative;
  display: flex;
  align-items: center;
  width: 390px;
  height: 55px;
  box-sizing: border-box;
  padding: 0 24px;
  background: #fff;
}


.row-item::after {
  content: '';
  position: absolute;
  left: 24px;
  right: 0;
  bottom: 0;
  height: 1px;
  background: #f0f0f0;
}

.row-time {
  position: absolute;
  top: 15px;
  left: 24px;
  width: 110px;
  font-size: 15px;
  line-height: 16px;
  color: #333;
  flex-shrink: 0;
}

.row-code {
  position: absolute;
  top: 10px;
  left: 141px;
  width: 178px;
  display: flex;
  align-items: baseline;
  justify-content: center;
  height: 22px;
}

.code-no {
  font-size: 14px;
  line-height: 22px;
  color: #333;
}

.code-total {
  margin-left: 4px;
  font-size: 12px;
  line-height: 14px;
  color: #999;
}

.row-price {
  position: absolute;
  top: 10px;
  right: 24px;
  width: 65px;
  font-size: 15px;
  line-height: 22px;
  color: #333;
  text-align: right;
  flex-shrink: 0;
}

/* 到底提示 @0,445 390x18 */
.list-end {
  width: 390px;
  height: 18px;
  line-height: 18px;
  font-size: 12px;
  color: #ababab;
}
</style>
