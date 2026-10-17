<script setup>
/**
 * 探索页 —— 对齐原站 pages/market/market
 * 基线（390x844，白底）：
 *   标题「探索」@0,0 390x34  16px #23262f /600
 *   四宫格    @16,50起，两行，每行 175x92，列间距 7，行间距 12
 *   主Tab     高39（寄售62/幸运盒62）15px 选中#000/700 未选#8c8c8c/400
 *   系列Tab   @16,294 高31，选中胶囊白字10px/700，未选 #1a1512 10px/500
 *   内容区    @0,329 通栏白底 圆角 9px 9px 0 0
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMarketList, getMarketBoxList, getSeries } from '@/api'
import MarketCard from '@/components/MarketCard.vue'

const router = useRouter()

/* 主 Tab：寄售 / 幸运盒（对应 goods_type 2 / 1） */
const mainTabs = [
  { key: 'consign', name: '寄售', goodsType: 2 },
  { key: 'lucky', name: '幸运盒', goodsType: 1 }
]
const mainIdx = ref(0)

/* 系列 Tab：由 /index/getSeries?type=10 真实返回 */
const seriesTabs = ref([])
const seriesIdx = ref(0)

const list = ref([])
const isGrid = ref(true) // 布局切换（vertical.png 按钮）

/* 原站 form 初始值（逆向自 pages-market-market chunk） */
function buildForm() {
  const goodsType = mainTabs[mainIdx.value].goodsType
  const form = {
    goods_type: goodsType,
    page: 1,
    list_rows: 10,
    series_id: seriesTabs.value[seriesIdx.value]?.series_id ?? 0,
    add_deal_num: true,
    deal: null
  }
  // 原站实测寄售分支请求体：{goods_type:2,page:1,list_rows:10,series_id:N,add_deal_num:true,new:1,price:null,deal:null}
  form.new = 1
  form.price = null
  return form
}

const entries = [
  { key: 'consignment', img: '/static/market/icon-1.png', path: '/pages/consignments/consignments' },
  { key: 'blindbox', img: '/static/market/icon-2.png', path: '/pages/blindBox/index' },
  { key: 'market', img: '/static/market/icon-3.png', path: '/pages/market/tradingInformation' },
  { key: 'shop', img: '/static/market/icon-4.png', path: '/pages/shop/shop' }
]

async function loadList() {
  const form = buildForm()
  try {
    const res =
      form.goods_type === 2 ? await getMarketList(form) : await getMarketBoxList(form)
    const d = res.data
    // 藏品分支返回数组；盲盒分支返回分页对象
    list.value = Array.isArray(d) ? d : (d && d.data) || []
  } catch (e) {
    list.value = []
  }
}

function switchMain(i) {
  mainIdx.value = i
  loadList()
}
function switchSeries(i) {
  seriesIdx.value = i
  loadList()
}

onMounted(async () => {
  try {
    const res = await getSeries({ type: 10 })
    const d = res.data
    seriesTabs.value = Array.isArray(d) ? d : (d && d.data) || []
    // 原站默认选中 is_def 的系列（当前为「全部区」series_id=3）
    const def = seriesTabs.value.findIndex((s) => s.is_def === 1)
    seriesIdx.value = def > -1 ? def : 0
  } catch (e) {
    seriesTabs.value = []
  }
  loadList()
})
</script>

<template>
  <div class="container page-container market flex flex-dir-col">
    <!-- 标题（原站高34，下方留 16px） -->
    <div class="font-color-black font-size-16 flex flex-center font-w-600 market-title">
      探索
    </div>

    <!-- 四宫格入口（原站 y=50 起） -->
    <div class="plr-16 entry-area">
      <div class="entry-row">
        <div
          v-for="e in entries.slice(0, 2)"
          :key="e.key"
          class="entry-img"
          @click="router.push(e.path)"
        >
          <img :src="e.img" :alt="e.key" />
        </div>
      </div>
      <div class="entry-row entry-row-last">
        <div
          v-for="e in entries.slice(2, 4)"
          :key="e.key"
          class="entry-img"
          @click="router.push(e.path)"
        >
          <img :src="e.img" :alt="e.key" />
        </div>
      </div>
    </div>

    <!-- 主 Tab + 布局切换 -->
    <div class="flex plr-16 flex-ai-center flex-js-sb main-tab-row">
      <div class="flex main-tabs">
        <div
          v-for="(t, i) in mainTabs"
          :key="t.key"
          class="main-tab"
          :class="{ 'is-active': mainIdx === i }"
          @click="switchMain(i)"
        >
          {{ t.name }}
          <!-- 原站 u-tabs lineColor #f23d4d lineWidth 20 lineHeight 2 -->
          <span v-if="mainIdx === i" class="main-line"></span>
        </div>
      </div>
      <img
        class="layout-toggle"
        src="/static/img/vertical.png"
        alt="切换布局"
        @click="isGrid = !isGrid"
      />
    </div>

    <!-- 系列 Tab：原站 2==goods_type 才渲染（幸运盒走 BoxSeries 组件） -->
    <div v-if="mainTabs[mainIdx].goodsType === 2" class="series-row">
      <div class="series-tabs">
        <div
          v-for="(s, i) in seriesTabs"
          :key="s.series_id"
          class="series-tab"
          :class="{ 'is-active': seriesIdx === i }"
          @click="switchSeries(i)"
        >
          <span class="series-text">{{ s.series_name }}</span>
        </div>
      </div>
    </div>

    <!-- 内容区 -->
    <div class="warp flex-1">
      <div v-if="list.length" class="goods-wrap">
        <MarketCard
          v-for="g in list"
          :key="g.id"
          :market="g"
          @click="router.push('/pages/market/marketGoodsList?id=' + g.id + '&goodsType=' + mainTabs[mainIdx].goodsType)"
        />
      </div>

      <!-- 空态：原站实测 /static/empty/search-empty.png @140,443 121x100 -->
      <div v-else class="empty-state">
        <img src="/static/empty/search-empty.png" alt="暂无藏品" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.market {
  position: relative;
  background: #fff;
  /* 原站 uni-page 固定 844，内容区内部滚动，body 恒为 844 */
  width: 390px;
  max-width: 100%;
  height: 844px;
  overflow: hidden;
  flex-direction: column;
}
.market-title {
  height: 34px;
  font-weight: 600;
  margin-bottom: 16px;
  flex-shrink: 0;
}

/* 四宫格区：原站 @0,50 plr-16，容器 357 宽，两行 92 高，行间距 12，无圆角 */
.entry-area { flex-shrink: 0; }

/* ---- 四宫格：175x92，列间距 7，行间距 12 ----
   原站实测右列 x=198（间距 7，容器 358 剩余 1px 归左），故不用 space-between */
.entry-row {
  display: flex;
  justify-content: flex-start;
  gap: 7px;
  margin-bottom: 12px;
}
.entry-row-last { margin-bottom: 0; }
.entry-img {
  width: 175px;
  height: 92px;
  overflow: hidden;
}
.entry-img img { width: 100%; height: 100%; object-fit: fill; display: block; }

/* ---- 主 Tab：@16,245 高39，item 宽62 padding 0 5px，选中 #000/700，未选 #8c8c8c/400 ---- */
.main-tab-row { height: 39px; margin-top: -1px; flex-shrink: 0; }
.main-tabs { height: 39px; }
.main-tab {
  position: relative;
  height: 39px;
  width: 62px;
  padding: 0 5px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  color: #8c8c8c;
  flex-shrink: 0;
}
.main-tab.is-active {
  color: #000;
  font-weight: 700;
}
/* 选中下划线：实测 @38,282 18x2 bg #f23d4d */
.main-line {
  position: absolute;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 20px;
  height: 2px;
  background: var(--gd-primary);
  border-radius: 1px;
}
.layout-toggle {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

/* ---- 系列 Tab：@16,294 高31，nav 宽374，item padding 0 5px ---- */
.series-row {
  height: 31px;
  margin-top: 10px;
  padding-left: 16px;
  display: flex;
  align-items: center;
  overflow: hidden;
  flex-shrink: 0;
}
.series-tabs {
  display: flex;
  align-items: center;
  height: 31px;
  overflow-x: auto;
  white-space: nowrap;
  -webkit-overflow-scrolling: touch;
}
.series-tabs::-webkit-scrollbar { display: none; }
.series-tab {
  height: 31px;
  padding: 0;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.series-text {
  display: block;
  padding: 0 15px;
  height: 20px;
  line-height: 20px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 500;
  color: #1a1512;
  white-space: nowrap;
}
.series-tab.is-active .series-text {
  background: #f23d4d;
  color: #fff;
  font-weight: 700;
}

/* ---- 内容区：@0,329 通栏白底，padding 0 16px，圆角 9px 9px 0 0 ---- */
.warp {
  margin-top: 4px;
  background: #fff;
  border-radius: 9px 9px 0 0;
  padding: 0 16px 20px;
  /* 原站内容区占满余下高度并可内部滚动 */
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
}
.goods-wrap {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  padding-top: 10px;
}





/* 原站实测：空态图 @140,443 121x100（容器内居中后右移 5.5px 对齐原站） */
.empty-state {
  padding-top: 114px;
  display: flex;
  justify-content: center;
}
.empty-state img { width: 121px; height: 100px; margin-left: 11px; }
</style>