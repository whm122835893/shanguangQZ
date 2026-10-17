<script setup>
/**
 * 工会商城 —— 100% 复刻原站 pages/shop/shop
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle）：
 *   整页背景  /static/shop/bg.png  390x844  background-size:100%
 *   u-navbar  @0,0 390x44（透明，无底色）
 *     返回    back-btn-white.png  @13,10   24x24
 *     标题「工会商城」            @165,16  60x16   15px/700 白  居中
 *     右侧「兑换记录」            @329,13  48x18   12px/400 白
 *   header  @15.5,44 359x166  背景 /static/shop/top-bg.png  size 100%
 *     user_card @31.5,60 327x155（padding 0 5px 20px）
 *       头像 logo.png      @32.5,61   38x38  圆形
 *       昵称               @76.5,71.5 14px 白（ml-6）
 *       右箭头 right5.png  @138.7,67.5 24x24
 *       「积分攻略」胶囊   @285.5,68.5 68x22  12px/500 黑字 白底 br19 padding 2px 10px
 *     sjnum @31.5,99 322x96（padding 0 11px 10px）三列各 103.7
 *       数字   20px/700 白     @78.3,99 / @182,99 / @285.7,99
 *       图标   28x28           @69.3,130 lsj / @173,130 zsj / @276.7,130 hsj
 *       文案   13px/500 白     @y=158（基础积分/高阶积分/钻石）
 *   shop 区 @0,210 390x634  圆角 10px 10px 0 0  padding 18px
 *     一级 u-tabs @18,228 354x22
 *       「精选藏品」选中 @33,228 104x22 15px/700 白
 *       「盲盒」         @167,228 104x22 15px/400 #999
 *       （item 134 宽，padding 0 15px，内文 104 居中）
 *     二级 u-tabs @18,265 354x31（plr-30 mt-2）wrapper @49,265 292x31
 *       「全部」@88,271 13px/500 白（选中）
 *       「基础积分」@179,271 /「高阶积分」@283,271 /「钻石」@400,271 #999
 *       （item 104 宽，横向可滚动，第 4 项 x=361 已超出可视区）
 *     空态 @18,364 354x100（mt-12）search-empty.png @134.5,364 121x100
 *
 * 接口：GET /crystal_goods/list  原站实测返回 total:0, data:[]（无上架商品）
 *  → 故本页固定展示空态图，不臆造商品。
 *
 * 积分字段（/user/getUserInfo）：crystal_blue 基础积分 / crystal_yellow 高阶积分 / crystal_purple 钻石
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { getCrystalGoods } from '@/api'

const router = useRouter()
const user = useUserStore()

/* 一级分类：精选藏品 / 盲盒 */
const mainTabs = [
  { key: 'goods', name: '精选藏品' },
  { key: 'blind', name: '盲盒' }
]
const mainTab = ref('goods')

/* 二级分类：全部 / 基础积分 / 高阶积分 / 钻石（横向可滚动） */
const subTabs = [
  { key: 'all', name: '全部' },
  { key: 'blue', name: '基础积分' },
  { key: 'yellow', name: '高阶积分' },
  { key: 'purple', name: '钻石' }
]
const subTab = ref('all')

/* 商品列表：原站实测为空，保留真实请求逻辑以便有数据时正常渲染 */
const goods = ref([])
const loading = ref(false)

async function loadGoods () {
  loading.value = true
  try {
    const res = await getCrystalGoods({
      page: 1,
      type: mainTab.value === 'blind' ? 2 : 1,
      crystal: subTab.value
    })
    const d = (res && res.data) || {}
    goods.value = d.data || []
  } catch (e) {
    goods.value = []
  } finally {
    loading.value = false
  }
}

const nickname = computed(() => (user.userInfo && user.userInfo.nickname) || '')
const basePoint = computed(() => (user.userInfo && user.userInfo.crystal_blue) || 0)
const highPoint = computed(() => (user.userInfo && user.userInfo.crystal_yellow) || 0)
const diamond = computed(() => (user.userInfo && user.userInfo.crystal_purple) || 0)

onMounted(() => {
  user.fetchUserInfo()
  loadGoods()
})

function switchMain (k) {
  if (mainTab.value === k) return
  mainTab.value = k
  loadGoods()
}
function switchSub (k) {
  if (subTab.value === k) return
  subTab.value = k
  loadGoods()
}
</script>

<template>
  <div class="shop-page">
    <!-- 导航栏 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn-white.png" alt="返回" />
      </div>
      <div class="nav-title">工会商城</div>
      <div class="nav-right" @click="router.push('/pages/shop/record')">兑换记录</div>
    </div>

    <!-- 头部 @15.5,44 359x166 -->
    <div class="header">
      <div class="user-card">
        <div class="user-row">
          <div class="avatar" @click="router.push('/pages/shop/detailList')">
            <img src="/static/icon/logo.png" alt="头像" />
          </div>
          <div class="nick" @click="router.push('/pages/shop/detailList')">{{ nickname }}</div>
          <div class="arrow" @click="router.push('/pages/shop/detailList')">
            <img src="/static/icon/right5.png" alt="进入" />
          </div>
          <div class="guide" @click="router.push('/pages/rules/rules?type=3')">积分攻略</div>
        </div>
        <div class="point-row">
          <div class="point-item">
            <div class="p-num">{{ basePoint }}</div>
            <div class="p-icon"><img src="/static/icon/lsj.png" alt="基础积分" /></div>
            <div class="p-name">基础积分</div>
          </div>
          <div class="point-item">
            <div class="p-num">{{ highPoint }}</div>
            <div class="p-icon"><img src="/static/icon/zsj.png" alt="高阶积分" /></div>
            <div class="p-name">高阶积分</div>
          </div>
          <div class="point-item">
            <div class="p-num">{{ diamond }}</div>
            <div class="p-icon"><img src="/static/icon/hsj.png" alt="钻石" /></div>
            <div class="p-name">钻石</div>
          </div>
        </div>
      </div>
    </div>

    <!-- 商品区 @0,210 390x634 -->
    <div class="shop">
      <!-- 一级分类 -->
      <div class="tabs">
        <div
          v-for="t in mainTabs"
          :key="t.key"
          class="tab-item"
          :class="{ active: mainTab === t.key }"
          @click="switchMain(t.key)"
        >{{ t.name }}</div>
      </div>

      <!-- 二级分类（横向滚动） -->
      <div class="sub-tabs">
        <div class="sub-tabs-inner">
          <div
            v-for="t in subTabs"
            :key="t.key"
            class="sub-item"
            :class="{ active: subTab === t.key }"
            @click="switchSub(t.key)"
          >{{ t.name }}</div>
        </div>
      </div>

      <!-- 列表 / 空态 -->
      <div v-if="goods.length" class="goods-list">
        <div v-for="(g, i) in goods" :key="i" class="goods-item">
          <img class="g-img" :src="g.img || g.image" :alt="g.name" />
          <div class="g-name">{{ g.name }}</div>
        </div>
      </div>
      <div v-else class="empty">
        <img src="/static/empty/search-empty.png" alt="暂无商品" />
      </div>
    </div>
  </div>
</template>

<style scoped>
.shop-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  min-height: 844px;
  overflow: hidden;
  color: rgb(74, 74, 74);
  font-family: 'Times New Roman', serif;
  font-size: 15px;
  /* 原站整页背景图 bg.png（750x1624，390 宽等比 844） */
  background: rgb(255, 255, 255) url('/static/shop/bg.png') no-repeat 0 0 / 100% 100%;
}

/* ---------------- 导航栏 @0,0 390x44 ---------------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.nav-back img {
  width: 24px;
  height: 24px;
}
/* 标题 @165,16 60x16 15px/700 白 */
.nav-title {
  position: absolute;
  left: 91px;
  top: 11px;
  width: 208px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
}
/* 右侧「兑换记录」容器 @316,0 74x44（padding 0 13px）→ 文字 @329,13 48x18 */
.nav-right {
  position: absolute;
  left: 316px;
  top: 0;
  width: 74px;
  height: 44px;
  padding: 0 13px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  color: #fff;
  white-space: nowrap;
  cursor: pointer;
}

/* ---------------- 头部 @15.5,44 359x166 ---------------- */
.header {
  position: absolute;
  left: 15.5px;
  top: 44px;
  width: 359px;
  height: 166px;
  background: url('/static/shop/top-bg.png') no-repeat 0 0 / 100% 100%;
}
.user-card {
  position: absolute;
  left: 16px;
  top: 16px;
  width: 327px;
  height: 155px;
  padding: 0 5px 20px 0;
  box-sizing: border-box;
}
/* 第一行 @31.5,60 高 39 */
.user-row {
  position: relative;
  width: 322px;
  height: 39px;
  display: flex;
  align-items: center;
}
/* 头像外圈 @31.5,60 39x39（白底 1px 边框）；内图 @32.5,61 38x38 */
.avatar {
  width: 39px;
  height: 39px;
  padding: 0;
  border-radius: 50%;
  background: #fff;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.avatar img {
  width: 38px;
  height: 38px;
  border-radius: 50%;
}
/* 昵称 @76.5,71.5 14px 白 */
.nick {
  margin-left: 6px;
  max-width: 62px;
  height: 16px;
  line-height: 16px;
  font-size: 14px;
  color: #fff;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
/* 右箭头 @138.7,67.5 24x24 */
.arrow {
  margin-left: 0.2px;
  width: 24px;
  height: 24px;
  flex-shrink: 0;
}
.arrow img {
  width: 24px;
  height: 24px;
}
/* 「积分攻略」胶囊 @285.5,68.5 68x22 */
.guide {
  position: absolute;
  right: 0;
  top: 8.5px;
  width: 68px;
  height: 22px;
  padding: 2px 10px;
  box-sizing: border-box;
  border-radius: 19px;
  background: #fff;
  color: #000;
  font-size: 12px;
  font-weight: 500;
  line-height: 18px;
  text-align: center;
  white-space: nowrap;
  cursor: pointer;
}
/* 积分三列 @31.5,99 322x96 */
.point-row {
  position: absolute;
  left: 0;
  top: 39px;
  width: 322px;
  height: 96px;
  display: flex;
  padding: 0 11px 10px 0;
  box-sizing: border-box;
}
.point-item {
  width: 103.7px;
  display: flex;
  flex-direction: column;
  align-items: center;
}
/* 数字 20px/700 白 */
.p-num {
  height: 23px;
  margin-bottom: 8px;
  font-size: 20px;
  font-weight: 700;
  line-height: 23px;
  color: #fff;
}
/* 图标 28x28 */
.p-icon {
  width: 28px;
  height: 28px;
}
.p-icon img {
  width: 28px;
  height: 28px;
}
/* 文案 13px/500 白 */
.p-name {
  height: 19px;
  font-size: 13px;
  font-weight: 500;
  line-height: 19px;
  color: #fff;
  white-space: nowrap;
}

/* ---------------- 商品区 @0,210 390x634 ---------------- */
.shop {
  position: absolute;
  left: 0;
  top: 210px;
  width: 390px;
  height: 634px;
  padding: 18px;
  border-radius: 10px 10px 0 0;
  background: rgba(0, 0, 0, 0);
  box-sizing: border-box;
  overflow: hidden;
}
/* 一级分类 @18,228 354x22 */
.tabs {
  position: relative;
  display: flex;
  width: 354px;
  height: 22px;
}
.tab-item {
  width: 134px;
  height: 22px;
  padding: 0 15px;
  box-sizing: border-box;
  font-size: 15px;
  line-height: 22px;
  color: rgb(153, 153, 153);
  text-align: center;
  white-space: nowrap;
  cursor: pointer;
}
.tab-item.active {
  font-weight: 700;
  color: #fff;
}
/* 二级分类 @18,265 354x31（plr-30 mt-2） */
.sub-tabs {
  position: relative;
  width: 354px;
  height: 31px;
  margin-top: 15px;
  padding: 0 31px;
  box-sizing: border-box;
  overflow: hidden;
}
.sub-tabs-inner {
  display: flex;
  width: 292px;
  height: 31px;
}
.sub-item {
  width: 104px;
  height: 31px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 500;
  color: rgb(153, 153, 153);
  text-align: center;
  white-space: nowrap;
  cursor: pointer;
  flex-shrink: 0;
}
.sub-item.active {
  color: #fff;
}
/* 空态 @18,364 354x100（mt-12） */
.empty {
  width: 354px;
  height: 100px;
  margin-top: 68px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.empty img {
  width: 121px;
  height: 100px;
}
/* 有数据时的商品网格（保留结构，原站当前无数据不触发） */
.goods-list {
  width: 354px;
  margin-top: 12px;
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}
.goods-item {
  width: 171px;
  margin-bottom: 12px;
}
.g-img {
  width: 171px;
  height: 171px;
  object-fit: cover;
  border-radius: 8px;
}
.g-name {
  margin-top: 8px;
  font-size: 13px;
  color: #fff;
}
</style>
