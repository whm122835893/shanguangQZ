<script setup>
/**
 * 寄售管理 —— 1:1 复刻原站 pages/consignments/myConsignment
 *
 * 原站 390x844 实测：
 *   返回 back-btn.png              @13,10 24x24
 *   顶部 Tab（标题内嵌，非 navbar 居中）
 *     「藏品」                    @113,11 30x22  15px/700 #333（选中）
 *     「盲盒」                    @247,11 30x22  15px/400 #999
 *     下划线 .u-tabs__wrapper__nav__line @119,28 18x3  br100px
 *     搜索 search.png             @355,11 22x22
 *
 *   状态 Tab
 *     「全部」    @53,49  28x21 14px/500 #333（选中）
 *     「寄售中」  @180,49 42x21 14px/400 #606266
 *     「已成交」  @314,49 42x21 14px/400 #606266
 *
 *   空态 search-empty.png         @134.5,179 121x100
 *
 * 接口（原站实测）：
 *   POST /order/order/myConsignment {status:"",goods_type:2,page:1}
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getMyConsignment } from '@/api'

const router = useRouter()

const mainTabs = ['藏品', '盲盒']
const mainIdx = ref(0)

const statusTabs = ['全部', '寄售中', '已成交']
const statusIdx = ref(0)
/* 原站 status 传空串表示全部 */
const STATUS = ['', 1, 2]

const list = ref([])

async function load() {
  try {
    const r = await getMyConsignment({
      status: STATUS[statusIdx.value],
      goods_type: 2,
      page: 1
    })
    const d = r.data
    list.value = (d && (d.data || d.list)) || []
  } catch (e) {
    list.value = []
  }
}

function switchStatus(i) {
  if (statusIdx.value === i) return
  statusIdx.value = i
  load()
}

onMounted(load)
</script>

<template>
  <div class="cons-page">
    <!-- 顶部标题 Tab + 搜索 @0,0 390x44 -->
    <div class="top-bar">
      <div class="flex-center left-wrap" @click="router.back()">
        <img class="back-img" src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="main-tabs">
        <div
          v-for="(t, i) in mainTabs"
          :key="t"
          class="main-tab"
          :class="{ active: i === mainIdx }"
          @click="mainIdx = i"
        >
          {{ t }}
        </div>
        <div class="nav-line" :style="{ left: mainIdx === 0 ? '113px' : '247px' }" />
      </div>
      <div class="flex-center right-wrap">
        <img class="search-btn" src="/static/icon/search.png" alt="搜索" />
      </div>
    </div>

    <!-- 状态 Tab @0,44 高 34 -->
    <div class="status-row">
      <div
        v-for="(t, i) in statusTabs"
        :key="t"
        class="status-tab"
        :class="{ active: i === statusIdx }"
        @click="switchStatus(i)"
      >
        {{ t }}
      </div>
    </div>

    <!-- 空态 @134.5,179 121x100（原站当前账号无寄售记录） -->
    <div v-if="!list.length" class="empty-wrap">
      <img class="empty-img" src="/static/empty/search-empty.png" alt="" />
    </div>
    <div v-else class="list-wrap">
      <div v-for="g in list" :key="g.id" class="list-item">
        <span class="li-name">{{ g.goods_name }}</span>
        <span class="li-price">￥{{ g.price }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.cons-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fff;
  box-sizing: border-box;
  overflow: hidden;
  color: #333;
}

.flex-center { display: flex; align-items: center; justify-content: center; }

/* 顶部标题 Tab @0,0 390x44 */
.top-bar {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 44px;
}

.left-wrap {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
}

.back-img {
  width: 24px;
  height: 24px;
  object-fit: fill;
  display: block;
}

.main-tabs {
  position: absolute;
  left: 113px;
  top: 11px;
  width: 164px;
  height: 22px;
  display: flex;
}

.main-tab {
  width: 30px;
  height: 22px;
  margin-right: 104px;
  font-size: 15px;
  line-height: 22px;
  color: #999;
  white-space: nowrap;
}

.main-tab.active {
  font-weight: 700;
  color: #333;
}

/* 下划线 @119,28 18x3 */
.nav-line {
  position: absolute;
  top: 28px;
  width: 18px;
  height: 3px;
  border-radius: 100px;
  background: #333;
}

.right-wrap {
  position: absolute;
  right: 13px;
  top: 11px;
  width: 22px;
  height: 22px;
}

.search-btn {
  width: 22px;
  height: 22px;
  object-fit: fill;
  display: block;
}

/* 状态 Tab @0,44 高 34 */
.status-row {
  position: absolute;
  left: 0;
  top: 44px;
  width: 390px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: space-around;
}

.status-tab {
  height: 21px;
  font-size: 14px;
  line-height: 21px;
  color: #606266;
  white-space: nowrap;
}

.status-tab.active {
  font-weight: 500;
  color: #333;
}

/* 空态 @134.5,179 121x100 */
.empty-wrap {
  position: absolute;
  left: 134.5px;
  top: 179px;
  width: 121px;
  height: 100px;
}

.empty-img {
  width: 121px;
  height: 100px;
  object-fit: fill;
  display: block;
}

/* 有数据时列表 */
.list-wrap {
  position: absolute;
  left: 16px;
  top: 90px;
  width: 358px;
}

.list-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 78px;
  padding: 0 12px;
  margin-bottom: 12px;
  background: #fafafa;
  border-radius: 12px;
}

.li-name {
  font-size: 14px;
  color: #333;
}

.li-price {
  font-size: 14px;
  font-weight: 700;
  color: #333;
}
</style>
