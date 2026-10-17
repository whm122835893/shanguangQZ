<script setup>
/**
 * 我的藏品详情 —— 1:1 复刻原站 pages/myCollection/collectionDetails
 *
 * 入口（原站实测）：/pages/myCollection/collectionDetails?id=<user_collection_id>
 *   注：id 为 user_collection_id（我的藏品记录 ID），非 collection_id。
 *
 * 实测有数据结构（id=121926，藏品「思维粒子」）：
 *   .container.page-container   390x887  bg #f5f5f5  padding 0 0 62px
 *   .u-navbar--fixed           @0,0    390x44（返回按钮为 white 变体，压在图上）
 *   .product-card              @0,0    390x486  bg #fff
 *     大图                     @0,0    390x390  object-fit fill
 *     .goods-info.plr-20.flex-center-sb.ptb-10  @0,395 390x91
 *       .product-name           @20,411 64x59
 *         名称 16px/700 #000     @20,411 64x24
 *         价格行 mt-8            @20,443 64x27  （￥ 16px + 数字 24px，均 #d94c4a）
 *       标签列 .flex.flex-dir-col.flex-ai-start  @253,424 117x33
 *         .mr-8.mb-10.tag-size-default  @253,424 109x23  br4
 *           .name  @253,424 40x23 bg #000      「限量」12px #fff
 *           .value @281,424 69x23 bg #eceef0  「100000份」12px #000
 *   .ptb-12                    @0,486 390x339（pad 12/0，承载下方所有白块）
 *     品牌方块 .pa-12.product-builder  @0,513 390x65  bg #fff  mar 15 0 0
 *       logo.png @50,525 41x41   「闪光数字」14px #23262f  「创作者」12px #999
 *       logo.png @243,525 41x41  「闪光」14px #23262f     「品牌方」12px #999
 *     藏品编号块 .plr-12.ptb-16  @0,590 390x53  bg #fff  mar 12 0 0
 *       「藏品编号」@12,611 14px/700 #23262f   「#1926」右对齐 14px #23262f
 *     产品描述块 .pa-12  @0,655 390x57  bg #fff  mar 12 0 0
 *     购买须知块 .pa-12.buy-need  @0,724 390x57  bg #fff  mar 12 0 0
 *   .product-action            @0,765 390x79  bg #fff  pad 10  shadow 0 2px 2px rgba(219,219,219,.5)
 *     按钮 .btn-size-lg.circle  @26,781 338x47  br23  bg #000  「立即寄售」14px/700 #fff
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { getUserCollectionDetail } from '@/api'
import { oss } from '@/api/http'

const route = useRoute()

const id = computed(() => String(route.query.id || ''))

const detail = ref(null)
const loading = ref(true)

const product = computed(() => detail.value?.product || null)
const code = computed(() => detail.value?.collection_code || '')

const cover = computed(() => {
  const p = product.value
  if (!p) return ''
  const raw = p.main_image || p.listimg || ''
  return raw ? oss(raw) : ''
})

const price = computed(() => {
  if (!product.value) return ''
  return product.value.crystal ? product.value.crystal.crystal_price : product.value.price
})

/* 原站未对 total_num 做兜底，空值时原样显示 undefined，与原站保持一致 */
const totalNum = computed(() => (product.value ? product.value.total_num : undefined))

/**
 * 底部按钮 —— 严格按原站编译产物 pages-myCollection-collectionDetails.js 的分支还原：
 *   weither_resale==0 && status==1                                   -> 立即寄售（可点）
 *   status==2 && !is_lock                                           -> 取消寄售（可点）
 *   status==1 && !is_lock && weither_regift==1 && weither_resale==1
 *                 && can_compose==0                                 -> 尚未开启寄售（禁用）
 *   can_compose==1 && !is_lock && status==1                         -> 合成（可点）
 *   其余                                                            -> 上链中（禁用）
 * 注：原站判断顺序上「尚未开启寄售」在 can_compose 之前，故 can_compose==1 时
 *     不会命中该分支（合成与它互斥）。
 */
const btn = computed(() => {
  const d = detail.value
  const p = d && d.product
  if (!d || !p) return { text: '上链中', disabled: true }
  const status = d.status
  const isLock = p.is_lock
  if (p.weither_resale === 0 && status === 1) return { text: '立即寄售', disabled: false }
  if (status === 2 && isLock !== 1) return { text: '取消寄售', disabled: false }
  if (
    status === 1 &&
    isLock !== 1 &&
    p.weither_regift === 1 &&
    p.weither_resale === 1 &&
    p.can_compose === 0
  ) {
    return { text: '尚未开启寄售', disabled: true }
  }
  if (p.can_compose === 1 && isLock !== 1 && status === 1) return { text: '合成', disabled: false }
  return { text: '上链中', disabled: true }
})

const load = async () => {
  if (!id.value) {
    loading.value = false
    return
  }
  loading.value = true
  try {
    const res = await getUserCollectionDetail(id.value)
    if (res && res.code === 1 && res.data) detail.value = res.data
  } catch (e) {
    detail.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="cd-page">
    <!-- 顶部导航（透明压图，返回按钮为白色变体） -->
    <div class="cd-navbar">
      <div class="cd-navbar__back">
        <img src="/static/icon/back-btn-white.png" alt="" class="cd-navbar__back-icon" />
      </div>
    </div>

    <!-- 商品卡 @0,0 390x486 -->
    <div class="product-card">
      <div class="cover-box">
        <img v-if="cover" :src="cover" alt="" class="cover" />
      </div>

      <div class="goods-info">
        <div class="product-name">
          <div class="name">{{ product?.name || '' }}</div>
          <div class="price-row">
            <span class="price-symbol">￥</span><span class="price-num">{{ price }}</span>
          </div>
        </div>

        <div class="tag-col">
          <div class="tag">
            <span class="tag-name">限量</span>
            <span class="tag-value">{{ totalNum }}份</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 主体 @0,486 -->
    <div class="cd-body">
      <!-- 品牌方 / 创作者 -->
      <div class="builder">
        <div class="builder-cell">
          <img src="/static/bg/logo.png" alt="" class="builder-logo" />
          <div class="builder-text">
            <div class="builder-name">{{ product?.publisher?.user?.nickname || '' }}</div>
            <div class="builder-role">创作者</div>
          </div>
        </div>
        <div class="builder-cell">
          <img src="/static/bg/logo.png" alt="" class="builder-logo" />
          <div class="builder-text">
            <div class="builder-name">{{ product?.publisher?.name || '' }}</div>
            <div class="builder-role">品牌方</div>
          </div>
        </div>
      </div>

      <!-- 藏品编号 -->
      <div class="code-block">
        <div class="code-line">
          <span class="code-title">藏品编号</span>
          <span class="code-value">#{{ code }}</span>
        </div>
      </div>

      <!-- 产品描述 -->
      <div class="info-block">
        <div class="info-line">
          <span class="info-title">产品描述</span>
        </div>
      </div>

      <!-- 购买须知 -->
      <div class="info-block need">
        <div class="info-line">
          <span class="info-title">购买须知</span>
        </div>
      </div>

      <!-- 原站 .ptb-16 占位块 @0,781 390x32（购买须知下方无内容的留白） -->
      <div class="body-tail"></div>
    </div>

    <!-- 底部操作条 @0,765 -->
    <div class="action-bar">
      <div class="action-btn" :class="{ disabled: btn.disabled }">{{ btn.text }}</div>
    </div>
  </div>
</template>

<style scoped>
.cd-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  min-height: 844px;
  background: #f5f5f5;
  box-sizing: border-box;
  padding-bottom: 62px;
}

/* 顶部导航 */
.cd-navbar {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 10;
  width: 390px;
  height: 44px;
}
.cd-navbar__back {
  display: flex;
  align-items: center;
  width: 50px;
  height: 44px;
  padding-left: 13px;
  box-sizing: border-box;
}
.cd-navbar__back-icon {
  display: block;
  width: 24px;
  height: 24px;
}

/* 商品卡 */
.product-card {
  position: relative;
  width: 390px;
  height: 486px;
  background: #fff;
}
.cover-box {
  position: relative;
  width: 390px;
  height: 390px;
  overflow: hidden;
}
.cover {
  display: block;
  width: 390px;
  height: 390px;
  object-fit: cover;
}

.goods-info {
  position: absolute;
  left: 0;
  top: 395px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 390px;
  height: 91px;
  padding: 10px 20px;
  background: #fff;
  box-sizing: border-box;
}
.product-name {
  width: 64px;
  height: 59px;
  color: #000;
}
.name {
  width: 64px;
  height: 24px;
  font-size: 16px;
  font-weight: 700;
  color: #000;
  /* 原站实测 lh=normal（@20,411 64x24），不用固定 24px，否则字形偏上 3px */
  line-height: normal;
  white-space: nowrap;
}
.price-row {
  display: flex;
  align-items: flex-end;
  width: 64px;
  height: 27px;
  margin-top: 8px;
}
.price-symbol {
  font-size: 16px;
  font-weight: 700;
  color: #d94c4a;
  line-height: 17px;
}
.price-num {
  font-size: 24px;
  font-weight: 700;
  color: #d94c4a;
  line-height: 26px;
}

/* 标签列 @253,424 117x33 */
.tag-col {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  width: 117px;
  height: 33px;
}
/* 原站标签组：黑底「限量」与灰底「N份」左右拼接，整体随份数文本长度自适应
   （实测 100000份 时：黑底 x253-292 / 灰底 x293-349，总宽 97px），
   故此处不写死宽度，用 flex 由内容撑开。 */
.tag {
  display: flex;
  align-items: stretch;
  height: 23px;
  margin: 0 8px 10px 0;
  border-radius: 4px;
  overflow: hidden;
}
.tag-name {
  display: flex;
  align-items: center;
  justify-content: center;
  flex: none;
  height: 23px;
  padding: 3px 6px;
  background: #000;
  font-size: 12px;
  color: #fff;
  line-height: 14px;
  box-sizing: border-box;
}
.tag-value {
  display: block;
  flex: none;
  height: 23px;
  padding: 3px 5px 3px 5px;
  background: #eceef0;
  font-size: 12px;
  color: #000;
  line-height: 14px;
  box-sizing: border-box;
  white-space: nowrap;
}

/* 主体 */
.cd-body {
  box-sizing: border-box;
  /* 原站 .ptb-12：上下各 12px padding，容器总高 339（@0,486 -> 825） */
  padding: 12px 0;
}

/* 原站购买须知下方的空白占位块（.ptb-16） */
.body-tail {
  height: 32px;
}

/* 品牌方 / 创作者 @0,513 390x65 */
.builder {
  display: flex;
  align-items: center;
  width: 390px;
  height: 65px;
  margin-top: 15px;
  padding: 12px;
  background: #fff;
  box-sizing: border-box;
}
.builder-cell {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 183px;
  height: 41px;
}
.builder-logo {
  display: block;
  width: 41px;
  height: 41px;
  margin-right: 10px;
  flex: none;
  /* 原站 publisher.company_logo 本身是圆形图（实测渲染为正圆 41x41），
     本地 logo.png 是不透明正方形，需圆形裁切对齐 */
  border-radius: 50%;
  object-fit: cover;
  overflow: hidden;
}
.builder-name {
  font-size: 14px;
  color: #23262f;
  line-height: 21px;
  white-space: nowrap;
}
.builder-role {
  font-size: 12px;
  color: #999;
  line-height: 18px;
  white-space: nowrap;
}

/* 编号 / 描述 / 须知 通用白块 */
.code-block,
.info-block {
  width: 390px;
  background: #fff;
  margin-top: 12px;
  box-sizing: border-box;
}
.code-block {
  height: 53px;
  padding: 16px 12px;
}
.code-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 366px;
  height: 21px;
}
.code-title {
  font-size: 14px;
  font-weight: 700;
  color: #23262f;
  line-height: normal;
}
.code-value {
  font-size: 14px;
  color: #23262f;
  line-height: normal;
}

.info-block {
  height: 57px;
  padding: 12px;
}
.info-line {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 366px;
  height: 21px;
}
.info-title {
  font-size: 14px;
  font-weight: 700;
  color: #333;
  line-height: normal;
}
.need .info-title {
  color: #000;
}

/* 底部操作条 @0,765 */
.action-bar {
  position: absolute;
  left: 0;
  top: 765px;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 390px;
  height: 79px;
  padding: 10px;
  background: #fff;
  box-shadow: 0 2px 2px 2px rgba(219, 219, 219, 0.5);
  box-sizing: border-box;
}
.action-btn {
  width: 338px;
  height: 47px;
  border-radius: 23px;
  background: #000;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  line-height: 47px;
  text-align: center;
  box-sizing: border-box;
}
/* 原站 w-button disabled 态：浅灰底、深灰字（实测 rgb(217,217,217) 底 / rgb(153,153,153) 字） */
.action-btn.disabled {
  background: rgb(217, 217, 217);
  color: rgb(153, 153, 153);
}
</style>
