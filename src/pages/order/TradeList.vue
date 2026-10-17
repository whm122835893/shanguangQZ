<script setup>
/**
 * 交易委托/求购列表 —— 复刻原站
 *   /pages/order/entrust/list 我的委托
 *   /pages/order/beg/list      我的求购
 *
 * 原站 390x844 实测：
 *   .container.page-container.plr-16   390x844 bg #fafafa padding 0 16px
 *   .u-navbar--fixed                   @0,0 390x44 bg #fafafa
 *     返回 back-btn.png                @13,10 24x24
 *     标题                             @165,16 60x16 15px/700 #333
 *   .u-tabs                            @16,44 358x44（通栏，无胶囊底色）
 *     tab item                         89.5x44 padding 0 11px
 *     「全部」   @45.8,60  30x16  15px/700 #000  （选中）
 *     「求购中」 @127.8,60 45x16  15px/500 #333
 *     「已成交」 @217.3,60 45x16  15px/500 #333
 *     「已取消」 @306.8,60 45x16  15px/500 #333
 *   空态 search-empty.png              @134.5,104 121x100
 *
 * 接口（原站实测）：
 *   委托页 POST /market/Prepayment/myOrderlist {page:1,status:null}
 *   求购页 POST /market/trade/begBuyOrderList
 *
 * 注：当前账号两类委托均为空，展示原站空态。
 */
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { getMyOrderList, getBegBuyOrderList } from '@/api'

const props = defineProps({
  /* 页面标题：我的委托 / 我的求购 */
  title: { type: String, default: '我的委托' },
  /* true=我的委托（Prepayment/myOrderlist）  false=我的求购（trade/begBuyOrderList） */
  isEntrust: { type: Boolean, default: true }
})

const router = useRouter()

const tabs = ['全部', '求购中', '已成交', '已取消']
const idx = ref(0)
const list = ref([])
const loading = ref(false)

/* 原站 status 传 null 表示全部；tab 索引对应后端状态值 */
const STATUS = [null, 0, 1, 2]

async function load() {
  loading.value = true
  try {
    const r = props.isEntrust
      ? await getMyOrderList({ page: 1, status: STATUS[idx.value] })
      : await getBegBuyOrderList({ page: 1, status: STATUS[idx.value] })
    const d = r.data
    list.value = (d && (d.data || d.list)) || []
  } catch (e) {
    list.value = []
  } finally {
    loading.value = false
  }
}

function switchTab(i) {
  if (idx.value === i) return
  idx.value = i
  load()
}

watch(idx, load)
onMounted(load)
</script>

<template>
  <div class="container page-container page-bg plr-16">
    <!-- 导航 @0,0 390x44 -->
    <div class="u-navbar--fixed">
      <div class="u-navbar__content">
        <div class="u-navbar__content__left">
          <div class="flex-center" @click="router.back()">
            <img class="back-img" src="/static/icon/back-btn.png" alt="返回" />
          </div>
        </div>
        <span class="nav-title">{{ title }}</span>
      </div>
    </div>

    <!-- Tabs @16,44 358x44 -->
    <div class="u-tabs">
      <div class="u-tabs__wrapper">
        <div class="u-tabs__wrapper__nav">
          <div
            v-for="(t, i) in tabs"
            :key="t"
            class="u-tabs__wrapper__nav__item"
            :class="'u-tabs__wrapper__nav__item-' + i"
            @click="switchTab(i)"
          >
            <span class="tab-text" :class="{ active: i === idx }">{{ t }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 列表区：原站当前账号无数据，展示空态 @16,104 358x100 -->
    <div v-if="!list.length" class="flex flex-dir-col flex-ai-center empty-box">
      <img class="empty-img" src="/static/empty/search-empty.png" alt="" />
    </div>
    <div v-else class="list-box">
      <div v-for="g in list" :key="g.id" class="list-item">
        <span class="li-name">{{ g.name }}</span>
        <span class="li-price">￥{{ g.price }}</span>
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
}

.flex { display: flex; }
.flex-center { display: flex; align-items: center; justify-content: center; }
.flex-dir-col { flex-direction: column; }
.flex-ai-center { align-items: center; }
.plr-16 { padding-left: 16px; padding-right: 16px; }

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

/* Tabs @16,44 358x44 */
.u-tabs {
  margin-top: 44px;
  width: 358px;
  height: 44px;
}

.u-tabs__wrapper {
  width: 358px;
  height: 44px;
  overflow: hidden;
}

.u-tabs__wrapper__nav {
  display: flex;
  width: 100%;
  height: 44px;
}

.u-tabs__wrapper__nav__item {
  width: 89.5px;
  height: 44px;
  padding: 0 11px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.tab-text {
  font-size: 15px;
  line-height: 16px;
  color: #333;
  white-space: nowrap;
}

.tab-text.active {
  font-weight: 700;
  color: #000;
}

/* 空态 @16,104 358x100 */
.empty-box {
  width: 358px;
  height: 100px;
  margin-top: 16px;
}

.empty-img {
  width: 121px;
  height: 100px;
  object-fit: fill;
  display: block;
}

/* 有数据时的列表 */
.list-box {
  margin-top: 16px;
}

.list-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 78px;
  padding: 0 10px;
  margin-bottom: 10px;
  background: #fff;
  border-radius: 12px;
}

.li-name {
  font-size: 14px;
  color: #23262f;
}

.li-price {
  font-size: 14px;
  font-weight: 700;
  color: #23262f;
}
</style>
