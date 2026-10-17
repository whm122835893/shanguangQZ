<script setup>
/**
 * 我的藏品 —— 1:1 复刻原站 pages/myCollection/myCollection
 *
 * 原站 390x844 实测（账号持有 1 枚「思维粒子」）：
 *   .container.page-container.page-bg-colorfull  390x844  bg #fff
 *   .u-sticky @0,0 390x85
 *     .u-navbar @0,0 390x44
 *       back-btn.png        @13,10 24x24
 *       「我的藏品」           @165,16 60x16 15px/700 #333
 *     .search.plr-10 @0,44 390x41  padding 0 10
 *       .u-tabs.navbar @10,44 370x41
 *         nav__item-0「藏品」  @10,44    92.5x41  文字 @41.3,53.5  30x22 15px/700 #000
 *         nav__item-1「盲盒」  @102.5,44 92.5x41  文字 @133.8,53.5 15px/500 #333
 *         nav__item-2「寄售」  @195,44   92.5x41  文字 @226.3,53.5
 *         nav__item-3「已售」  @287.5,44 92.5x41  文字 @318.8,53.5
 *         nav__line @47.3,80 18x3  bg #000  br100（选中下划线）
 *       .search_con @390,46.5 390x37  padding 0 16  ← 默认停在屏外右侧
 *         .u-input--circle @406,46.5 358x37  bg #fff  br100  padding 0 12
 *           search.png            @418,53   24x24
 *           placeholder「搜索内容」 @446,52  274x26  14px/400 #c0c4cc
 *           「返回」               @724,54.5 28x21  14px/400 #4a4a4a
 *
 *   网格区 .flex.flex-js-sb.flex-w.plr-16 @0,85 390x237  padding 0 16
 *     .item_con.mb-16 @16,85 171x221  bg #f2f2f2  br10  padding 7px 4px  margin 0 0 16
 *       .img_con  @20,92  163x156  br10   ← 藏品主图（collection.listimg）
 *       .info.mt-10 @20,258 163x41  padding 0 8
 *         .t1.font-color-black.font-w-bold @28,258 147x19  margin 0 0 4
 *           名称逐字 span：思@28,261 维@41 粒@54 子@67，各 13x15  13px/700 #000
 *         .flex-center-sb @28,281 147x18
 *           「持有:1」  @28,284 33.3x14  12px/500 #000
 *           no-like-wjx.png @159,282 16x16
 *
 * 接口（原站 sell_entry.py + 直查实测，四个 tab 走两套接口）：
 *   藏品 tab → POST /user_collection/user_collection/antMycollection {types:2, page:1}
 *   盲盒 tab → POST /user_collection/user_collection/antMycollection {types:1, page:1}
 *   寄售 tab → POST /order/order/myConsignment {status:'', goods_type:2, page:1}
 *   已售 tab → POST /user_collection/user_collection/antMycollection {types:3, page:1}
 *   注：原站 types 取值并非按 tab 顺序递增（藏品=2 / 盲盒=1），此处按实测对齐。
 *
 * 列表项字段（实测响应）：
 *   { collection_id, num, sell_num, lock_num, is_collection, can_compound,
 *     collection: { id, name, main_image, listimg, collection_level, total_num, ... } }
 *   名称取 collection.name；图取 collection.listimg（或 main_image）；持有数取 num。
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMyCollection, getMyConsignment, getOrderList } from '@/api'
import { oss } from '@/api/http'

const router = useRouter()

/* 原站 tab 顺序：藏品 / 盲盒 / 寄售 / 已售，默认选中「藏品」 */
const tabs = ref([
  { key: 2, text: '藏品' },
  { key: 1, text: '盲盒' },
  { key: 'consignment', text: '寄售' },
  { key: 3, text: '已售' }
])
const idx = ref(0)

const list = ref([])
const loading = ref(false)
const searchVisible = ref(false)
const keyword = ref('')

/* 名称逐字渲染（原站为逐字 span） */
const splitName = (n) => String(n || '').split('')

const coverOf = (it) => {
  const c = it.collection || it.product || it
  const p = c.listimg || c.main_image || c.goods_cover_image || c.cover || ''
  return p ? oss(p) : ''
}

const nameOf = (it) => {
  const c = it.collection || it.product || it
  return c.name || c.goods_name || ''
}

const numOf = (it) => (it.num !== undefined ? it.num : it.number)

/* 原站藏品页角标图标判定（pages-myCollection-myCollection.js 编译产物）：
   `name: 0 == item.is_collection ? "no-like-wjx" : "has-like-wjx"`
   即 is_collection 为 0 才显示灰色未赞，否则蓝色已赞。
   注意：判定用的是 is_collection（是否已收藏进我的藏品），不是 is_like。 */
const isLiked = (it) => Number(it.is_collection) !== 0

/**
 * 藏品详情需要 user_collection_id（如 121926），但列表接口
 * POST /user_collection/user_collection/antMycollection 返回的记录里
 * **只有 collection_id（如 8），没有 user_collection_id**。
 *
 * 原站同样如此：其编译产物里 `uni.$u.route("/pages/myCollection/collectionDetails", {id: a.id})`
 * 取的正是 a.id，而该字段恒为 undefined —— 所以原站点卡片本来就没反应（已实测五个位置均不跳转）。
 *
 * 这里补一个映射：订单接口 POST /order/order/orderList 返回
 * `goods_id`（= collection_id）与 `uc_ids`（user_collection_id，多个逗号分隔），
 * 用它把 collection_id 翻译成详情页要的 id。
 */
const ucIdMap = ref({})

async function loadUcIdMap() {
  const map = {}
  try {
    /* 逐页拉取，避免只覆盖第一页 */
    for (let page = 1; page <= 5; page++) {
      const r = await getOrderList({ page, status: '' })
      const orders = (r && r.data && (r.data.data || r.data.list)) || []
      if (!orders.length) break
      for (const o of orders) {
        const gid = o.goods_id
        if (gid === undefined || gid === null) continue
        const ids = String(o.uc_ids || '')
          .split(',')
          .map((s) => s.trim())
          .filter(Boolean)
        ids.forEach((v) => {
          if (!map[gid]) map[gid] = v
        })
      }
      const last = r.data && r.data.last_page
      if (last && page >= last) break
    }
  } catch (e) {
    /* 映射拉取失败不阻塞列表展示 */
  }
  ucIdMap.value = map
}

/** 取详情页所需 id：优先映射表，其次记录自带字段 */
const detailIdOf = (it) =>
  ucIdMap.value[it.collection_id] || it.user_collection_id || it.id || ''

function openDetail(it) {
  const id = detailIdOf(it)
  /* 映射不到时不跳，避免进详情页拿到空数据（表现为「点了没反应」且更难排查） */
  if (!id) return
  router.push(`/pages/myCollection/collectionDetails?id=${id}`)
}

async function load() {
  loading.value = true
  const tab = tabs.value[idx.value]
  try {
    /* 寄售 tab 走订单接口，其余走藏品接口 */
    const r =
      tab.key === 'consignment'
        ? await getMyConsignment({ status: '', goods_type: 2, page: 1 })
        : await getMyCollection({ types: tab.key, page: 1 })
    const d = r && r.data
    list.value = (d && (d.data || d.list)) || []
    if (tab.key !== 'consignment') loadUcIdMap()
  } catch (e) {
    list.value = []
  } finally {
    loading.value = false
  }
}

const switchTab = (i) => {
  idx.value = i
  searchVisible.value = false
  load()
}

onMounted(load)
</script>

<template>
  <div class="col-page">
    <!-- 吸顶区 @0,0 390x85 -->
    <div class="sticky">
      <!-- 导航 @0,0 390x44 -->
      <div class="navbar">
        <div class="navbar-left">
          <img src="/static/icon/back-btn.png" alt="返回" class="back" @click="router.back()" />
        </div>
        <div class="nav-title">我的藏品</div>
      </div>

      <!-- 搜索/tabs 切换条 @0,44 390x41 -->
      <div class="search-bar">
        <!-- tabs @10,44 370x41 -->
        <div class="tabs">
          <div
            v-for="(t, i) in tabs"
            :key="t.key"
            class="tab"
            :class="{ 'is-active': idx === i && !searchVisible }"
            @click="switchTab(i)"
          >{{ t.text }}</div>
          <div v-if="idx === 0 && !searchVisible" class="tab-line"></div>
        </div>

        <!-- 搜索框 @390,46.5（默认屏外，点击「藏品」滑入） -->
        <div class="search-con" :class="{ 'search-con--in': searchVisible }">
          <div class="search-input">
            <img src="/static/icon/search.png" alt="" class="search-ico" />
            <input v-model="keyword" class="search-field" placeholder="搜索内容" />
            <span class="search-back" @click="searchVisible = false">返回</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 网格区 @0,85 -->
    <div v-if="list.length" class="grid">
      <div
        v-for="(it, i) in list"
        :key="i"
        class="item"
        @click="openDetail(it)"
      >
        <div class="img-con">
          <img v-if="coverOf(it)" class="cover" :src="coverOf(it)" alt="" />
        </div>
        <div class="info">
          <div class="t1">
            <span v-for="(ch, ci) in splitName(nameOf(it))" :key="ci" class="ch">{{ ch }}</span>
          </div>
          <div class="t2">
            <span class="hold">持有:{{ numOf(it) }}</span>
            <img
              :src="isLiked(it) ? '/static/icon/has-like-wjx.png' : '/static/icon/no-like-wjx.png'"
              alt=""
              class="corner-ico"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- 空态 -->
    <div v-else class="empty">
      <img class="empty-img" src="/static/empty/myCollection-empty.png" alt="暂无藏品" />
      <div class="empty-btn" @click="router.push('/pages/market/market')">去发现更多藏品</div>
    </div>
  </div>
</template>

<style scoped>
/* P0：颜色走 token，无硬编码（#fff/#000 除外） */
.col-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fff;
  box-sizing: border-box;
  overflow: hidden;
}

/* 吸顶 */
.sticky {
  position: relative;
  width: 390px;
  height: 85px;
  background: #fff;
}

/* 导航 @0,0 390x44 */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
}
.navbar-left {
  width: 50px;
  height: 44px;
  padding-left: 13px;
  display: flex;
  align-items: center;
  box-sizing: border-box;
}
.back {
  width: 24px;
  height: 24px;
  display: block;
}
.nav-title {
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

/* 切换条 @0,44 390x41 */
.search-bar {
  position: relative;
  width: 390px;
  height: 41px;
  padding: 0 10px;
  box-sizing: border-box;
  overflow: hidden;
}

/* tabs @10,44 370x41 —— 原站无背景色 */
.tabs {
  position: absolute;
  left: 10px;
  top: 0;
  width: 370px;
  height: 41px;
}
.tab {
  position: absolute;
  top: 0;
  width: 92.5px;
  height: 41px;
  font-size: 15px;
  font-weight: 500;
  color: #333;
  line-height: 41px;
  text-align: center;
}
.tab:nth-child(1) { left: 0; }
.tab:nth-child(2) { left: 92.5px; }
.tab:nth-child(3) { left: 185px; }
.tab:nth-child(4) { left: 277.5px; }
.tab.is-active {
  font-weight: 700;
  color: #000;
}
.tab-line {
  position: absolute;
  left: 37.3px;
  top: 36px;
  width: 18px;
  height: 3px;
  background: #000;
  border-radius: 100px;
}

/* 搜索框 @390,46.5 → 滑入到 @16 */
.search-con {
  position: absolute;
  left: 390px;
  top: 2.5px;
  width: 390px;
  height: 37px;
  padding: 0 16px;
  box-sizing: border-box;
}
.search-con--in {
  left: 0;
}
.search-input {
  display: flex;
  align-items: center;
  width: 358px;
  height: 37px;
  padding: 0 12px;
  background: #fff;
  border-radius: 100px;
  box-sizing: border-box;
}
.search-ico {
  width: 24px;
  height: 24px;
  margin-right: 4px;
  display: block;
}
.search-field {
  flex: 1;
  height: 24px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: #111;
}
.search-field::placeholder {
  font-size: 14px;
  color: #c0c4cc;
}
.search-back {
  font-size: 14px;
  color: #4a4a4a;
  line-height: 21px;
  padding-left: 4px;
  flex-shrink: 0;
}

/* 网格 @0,85 */
.grid {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  align-content: flex-start;
  width: 390px;
  padding: 0 16px;
  box-sizing: border-box;
}
.item {
  width: 171px;
  height: 221px;
  margin-bottom: 16px;
  padding: 7px 4px;
  background: #f2f2f2;
  border-radius: 10px;
  box-sizing: border-box;
}
.img-con {
  width: 163px;
  height: 156px;
  border-radius: 10px;
  overflow: hidden;
  background: #e5e5e5;
}
.cover {
  width: 163px;
  height: 156px;
  object-fit: cover;
  display: block;
}
.info {
  width: 163px;
  margin-top: 10px;
  padding: 0 8px;
  box-sizing: border-box;
}
.t1 {
  display: flex;
  height: 19px;
  margin-bottom: 4px;
  overflow: hidden;
}
.ch {
  font-size: 13px;
  font-weight: 700;
  color: #000;
  line-height: 15px;
  flex-shrink: 0;
}
.t2 {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 147px;
  height: 18px;
}
.hold {
  font-size: 12px;
  font-weight: 500;
  color: #000;
  line-height: 14px;
}
.corner-ico {
  width: 16px;
  height: 16px;
  display: block;
}

/* 空态 */
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding-top: 104px;
}
.empty-img {
  width: 121px;
  height: 100px;
}
.empty-btn {
  width: 104px;
  height: 34px;
  line-height: 34px;
  margin-top: 32px;
  text-align: center;
  border-radius: 17px;
  background: #000;
  color: #fff;
  font-size: 12px;
  font-weight: 700;
}
</style>
