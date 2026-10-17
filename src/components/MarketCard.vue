<script setup>
/**
 * 行情卡片 —— 100% 复刻原站 MarketCard
 * 源码：h5.gandart.shop/static/js/pages-market-market.3594e456.js（CSS module 75ce3390）
 *
 * 原站 390x844 实测（唯一权威数据，来自 getBoundingClientRect + getComputedStyle）：
 *   .market-card        @16,339  173x273  bg #f1efef  r7px  mb16
 *   .img.flex-center    @16,339  173x173
 *     img               @16,339  173x173  r10px
 *     .market-out img   @71.5,394.5  62x62   /static/bg/market-out1.png
 *   .plr-6             @16,512  173x94   padding 0 6px
 *     名称              @22,520  137x20  14px #333 /600  mt8  max-width 137
 *     标签行             @22,544  161x15  mt4
 *       限量             @22,544  53.8x15  8px /500 #fff bg #404040 r2 pad 2px 6px
 *       流通             @79.8,544 53.8x15 8px /500 #fff bg #3368C9 r2 ml4
 *     价格行             @22,563  161x24  mt4
 *       ￥              @22,567.5 10x15  10px /700 #333
 *       --              @32,563  11.8x24 16px /700 #333
 *       闪光             @155,568.5 28x13 8px /500 #fff bg #f23d4d r2
 *     限价行             @22,591  161x15  mt4  10px #999
 *       限价             @22,591  20x15
 *       ¥2000.00        @46,591  41.6x15   （ml4）
 */
import { oss } from '@/api/http'

const props = defineProps({
  market: { type: Object, required: true }
})

const img = (p) => oss(p)

/* 名称：超 10 字截断加省略号（原站逻辑） */
const dispName = (n) => {
  const s = n || ''
  return s.length > 10 ? s.slice(0, 10) + '...' : s
}
</script>

<template>
  <div class="market-card">
    <!-- 图片区 173x173，图片 r10px 撑满 -->
    <div class="img flex-center">
      <img
        v-if="market.product && market.product.listimg"
        class="card-img"
        :src="img(market.product.listimg)"
        :alt="market.product.name"
      />
      <!-- 售罄遮罩：原站用图片 market-out1.png，居中 62x62 -->
      <div v-if="market.is_out" class="market-out">
        <img class="out-img" src="/static/bg/market-out1.png" alt="已售罄" />
      </div>
    </div>

    <!-- 内容区：仅 product 存在时渲染（原站 a.market.product ? ... : a._e()） -->
    <div v-if="market.product" class="plr-6">
      <!-- 名称 14px /600 #333，mt8，max-width 137 -->
      <div class="card-name">{{ dispName(market.product.name) }}</div>

      <!-- 标签行 mt4 -->
      <div class="row mt-4">
        <div class="tag tag-grey">限量{{ market.total_num }}份</div>
        <div class="tag tag-blue ml-4">流通{{ market.flux }}份</div>
      </div>

      <!-- 价格行 mt4 -->
      <div class="row mt-4">
        <div v-if="market.is_out" class="price">
          <span class="yen">￥</span><span class="val">--</span>
        </div>
        <div v-else class="price">
          <span class="yen">￥</span><span class="val">{{
            market.goods_type != 1 ? market.min_price : market.price
          }}</span>
        </div>
        <div class="flag">闪光</div>
      </div>

      <!-- 限价行 mt4 -->
      <div class="row mt-4">
        <span>限价</span>
        <span class="ml-4">¥{{ market.reprice_limit || market.product.reprice_limit || '--' }}</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* ===== 卡片容器 ===== */
.market-card {
  width: 173px;
  min-height: 250px;
  position: relative;
  background: #f1efef;
  border-radius: 7px;
  margin-bottom: 16px;
  padding-bottom: 6px;
  overflow: hidden;
  box-sizing: border-box;
}

/* ===== 图片区 173x173 ===== */
.img {
  position: relative;
  width: 100%;
  height: 173px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.card-img {
  width: 173px;
  height: 173px;
  border-radius: 10px;
  object-fit: cover;
  display: block;
}

/* 售罄水印 62x62 居中 */
.market-out {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  line-height: 0;
}
.out-img { width: 62px; height: 62px; }

/* ===== 内容区 padding 0 6px ===== */
.plr-6 { padding: 0 6px; width: 100%; }

.row { display: flex; align-items: center; }
.mt-4 { margin-top: 4px; }
.ml-4 { margin-left: 4px; }

/* 名称 14px /600 #333，mt8，max-width 137 */
.card-name {
  font-size: 14px;
  color: #333;
  font-weight: 600;
  margin-top: 8px;
  max-width: 137px;
  height: 20px;
  line-height: 20px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* 标签 8px /500 #fff r2 pad 2px 6px */
.tag {
  padding: 2px 6px;
  border-radius: 2px;
  font-weight: 500;
  font-size: 8px;
  color: #fff;
  white-space: nowrap;
  height: 15px;
  line-height: 11px;
  box-sizing: border-box;
}
.tag-grey { background: #404040; }
.tag-blue { background: #3368c9; }

/* 价格行：实测高 24px，align-items center；￥ 10px 居中，值 16px 撑满 24 */
.price { display: flex; align-items: center; height: 24px; }
.yen { font-size: 10px; font-weight: 700; color: #333; line-height: 15px; }
.val { font-size: 16px; font-weight: 700; color: #333; line-height: 24px; }

/* 闪光角标 28x13 8px r2 */
.flag {
  margin-left: auto;
  width: 28px;
  height: 13px;
  background: #f23d4d;
  border-radius: 2px;
  font-size: 8px;
  font-weight: 500;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* 限价行 10px #999 */
.plr-6 > .row:last-child {
  color: #999;
  font-size: 10px;
  height: 15px;
}
</style>
