<script setup>
/**
 * 盲盒详情 —— 1:1 复刻原站 pages/blindBox/blindBoxDetails
 *
 * 路由：#/pages/blindBox/blindBoxDetails?ids=<blind_box.id>
 *
 * 原站 390x844 实测（container bg rgb(250,250,250)）：
 *   u-navbar @0,0 390x44（fixed）
 *     返回 @13,10 24x24 back-btn.png
 *     分享 @353,10 24x24 share.png
 *   .product-card @0,0 390x486 bg #fff
 *     主图 uni-image @0,0 390x390
 *     .goods-info @0,395 390x91（plr-20 ptb-10）
 *       价格「￥x.xx」@20,424.5 16px/700 rgb(217,76,74)
 *       标签组 @241,424 129x33
 *         「限量」黑底 @241,424 40x23 br左4 12px #fff
 *         「N份」灰底 @269,424 br右4 12px #000
 *   .plr-16.ptb-12 @0,486
 *     描述卡 @16,510 358x57 bg #fff pa-12
 *       标题「产品描述」@28,522 56x21 14px/700 rgb(51,51,51)
 *
 * 数据：后端只有 blind_box/list 列表接口，这里按 ids 从列表中取对应项。
 *      无 ids 或找不到时保持原站空数据态（￥ + undefined份）。
 */
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getBoxList } from '@/api'
import { oss } from '@/api/http'

const route = useRoute()
const router = useRouter()

const goods = ref(null)
const notFound = ref(false)

async function load() {
  goods.value = null
  const ids = Number(route.query.ids)
  if (!ids) { notFound.value = true; return }
  try {
    const r = await getBoxList()
    const d = r.data
    const list = Array.isArray(d) ? d : (d && d.data) || []
    goods.value = list.find((x) => Number(x.id) === ids) || null
    if (!goods.value) notFound.value = true
  } catch (e) { notFound.value = true }
}

onMounted(load)
/* hash 路由同页 query 变化时组件复用，onMounted 不会重跑 —— 必须监听 */
watch(() => route.query.ids, load)
</script>

<template>
  <div class="bb-detail">
    <div class="navbar">
      <img class="nav-back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <img class="nav-share" src="/static/icon/share.png" alt="分享" />
    </div>

    <div class="product-card">
      <div class="main-img">
        <img v-if="goods" :src="oss(goods.main_image || goods.listimg)" :alt="goods.name" />
      </div>

      <div class="goods-info">
        <span class="price">￥<template v-if="goods">{{ goods.price }}</template></span>
        <span class="tags">
          <span class="tag tag--dark">限量</span>
          <span class="tag tag--gray">{{ goods ? goods.total_num + '份' : 'undefined份' }}</span>
        </span>
      </div>
    </div>

    <div class="desc-wrap">
      <div class="desc-card">
        <div class="desc-title">产品描述</div>
        <div v-if="goods" class="desc-body">{{ goods.name }}</div>
        <div v-else class="desc-body"></div>
      </div>
      <div class="blank-row"></div>
    </div>
  </div>
</template>

<style scoped>
.bb-detail {
  position: relative;
  width: 390px;
  min-height: 844px;
  box-sizing: border-box;
  background: rgb(250, 250, 250);
  color: rgb(74, 74, 74);
  font-size: 15px;
}

.navbar {
  position: fixed;
  left: 0;
  top: 0;
  z-index: 10;
  width: 390px;
  height: 44px;
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  cursor: pointer;
}
.nav-share {
  position: absolute;
  left: 353px;
  top: 10px;
  width: 24px;
  height: 24px;
  cursor: pointer;
}

/* 商品卡 @0,0 390x486 */
.product-card {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 486px;
  background: #fff;
}
.main-img {
  width: 390px;
  height: 390px;
}
.main-img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* 商品信息 @0,395 390x91 */
.goods-info {
  position: absolute;
  left: 0;
  top: 395px;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  width: 390px;
  height: 91px;
  padding: 0 20px;
  box-sizing: border-box;
  background: #fff;
}
.price {
  font-size: 16px;
  font-weight: 700;
  line-height: 24px;
  color: rgb(217, 76, 74);
}
.tags {
  display: flex;
  align-items: flex-start;
  margin-top: 29px;
}
.tag {
  display: flex;
  align-items: center;
  height: 23px;
  padding: 0 8px;
  box-sizing: border-box;
  font-size: 12px;
  line-height: 23px;
}
.tag--dark {
  border-radius: 4px 0 0 4px;
  background: #000;
  color: #fff;
}
.tag--gray {
  border-radius: 0 4px 4px 0;
  background: rgb(236, 238, 240);
  color: #000;
}

/* 描述区 @0,486 390x125 */
.desc-wrap {
  position: absolute;
  left: 0;
  top: 486px;
  width: 390px;
  min-height: 125px;
  box-sizing: border-box;
  padding: 12px 16px;
}
.desc-card {
  width: 358px;
  min-height: 57px;
  padding: 12px;
  box-sizing: border-box;
  background: #fff;
}
.desc-title {
  width: 334px;
  height: 21px;
  font-size: 14px;
  font-weight: 700;
  line-height: 21px;
  color: rgb(51, 51, 51);
}
.desc-body {
  width: 334px;
  margin-top: 12px;
  font-size: 13px;
  line-height: 20px;
  color: rgb(153, 153, 153);
}
.blank-row {
  width: 358px;
  height: 32px;
  padding: 16px 0;
  box-sizing: border-box;
}
</style>