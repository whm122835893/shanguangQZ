<script setup>
/**
 * 首页 —— 严格对齐原站 pages/index/index
 * 布局基线（390 宽）：
 *   导航栏      44px高，搜索胶囊 @8,7 261x30 r15 bg rgba(255,255,255,.7)
 *   轮播        @16,54 358x169 r10
 *   公告条      @16,203 358x33 bg #f23d4d r17
 *   四宫格      @16,252 左156x202 / 右195x97 两块
 *   首发藏品     标题16px/500 #333，副标10px #000
 *   藏品卡      358x91 白底 r7，缩略图74x74 r6
 */
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { getBanner, getAfficheList, getBoxList } from '@/api'
import { oss } from '@/api/http'

const banners = ref([])
const notices = ref([])
const goods = ref([])
const noticeIdx = ref(0)
const swiperIdx = ref(0)
let timer = null

const noticeImg = '/static/icon/new-msg.png'

onMounted(async () => {
  try {
    const b = await getBanner()
    banners.value = (b.data && b.data.data) || []
  } catch (e) { /* 原站同样静默失败 */ }
  try {
    const a = await getAfficheList()
    notices.value = (a.data && a.data.data) || []
  } catch (e) {}
  try {
    const g = await getBoxList()
    // 原站 blind_box/list 直接返回数组
    const d = g.data
    goods.value = Array.isArray(d) ? d : (d && d.data) || []
  } catch (e) {}

  // 公告轮播：与原站一致，3s 切换
  timer = setInterval(() => {
    if (notices.value.length) {
      noticeIdx.value = (noticeIdx.value + 1) % notices.value.length
    }
  }, 3000)
})

// Banner 轮播：3.5s 自动切换（对齐原站 u-swiper 间隔）
let swiperTimer = null
function startSwiper() {
  if (banners.value.length < 2) return
  swiperTimer = setInterval(() => {
    swiperIdx.value = (swiperIdx.value + 1) % banners.value.length
  }, 3500)
}
watch(banners, () => nextTick(startSwiper), { immediate: true })

onUnmounted(() => {
  if (timer) clearInterval(timer)
  if (swiperTimer) clearInterval(swiperTimer)
})
</script>

<template>
  <div class="container page-container home-bg">
    <!-- 顶部导航 -->
    <div class="nav">
      <div class="u-navbar__content">
        <div class="u-navbar__content__left"></div>
        <div class="nav-mid">
          <div class="search-box" @click="$router.push('/pages/market/search')">
            <img src="/static/icon/search.png" alt="搜索" class="search-icon" />
            <span class="search-text">搜索</span>
          </div>
        </div>
        <div class="nav-right">
          <!-- 原站实测（2026-10-17）：左图标 /static/icon/sign/sign.png -> /pages/sign/index 签到中心
               右图标 /static/image/index/noticee-image.png -> /pages/notice/notice 公告中心
               注意：左图标文件名是 sign（签到），此前被误接到消息页，此处已按原站修正。 -->
          <div class="flex-center nav-btn" @click="$router.push('/pages/sign/index')">
            <img class="nav-sign" src="/static/icon/sign/sign.png" alt="签到" />
          </div>
          <div class="flex-center nav-btn" @click="$router.push('/pages/notice/notice')">
            <img class="nav-bell" src="/static/image/index/noticee-image.png" alt="公告" />
          </div>
        </div>
      </div>
    </div>

    <!-- 轮播 + 公告 -->
    <div class="mb-12 mt-10 plr-16">
      <div class="u-swiper">
        <div class="swiper-track" :style="{ transform: `translateX(-${swiperIdx * 100}%)` }">
          <div v-for="(b, i) in banners" :key="b.id" class="swiper-item">
            <img :src="oss(b.image)" :alt="`banner${i}`" />
          </div>
        </div>
      </div>

      <div class="notice-view">
        <div class="notice-pill" @click="$router.push('/pages/notice/notice')">
          <div class="notice-icon">
            <img :src="noticeImg" alt="公告" />
          </div>
          <div class="notice-viewport">
            <div class="notice-track" :style="{ transform: `translateY(-${noticeIdx * 27}px)` }">
              <div v-for="(n, i) in notices" :key="i" class="notice-item">{{ n.title }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 四宫格入口 -->
    <div class="flex flex-js-center mt-16 entry-group">
      <div class="entry-left" @click="$router.push('/pages/index/shequn')">
        <img src="/static/icon/1.png" alt="加入社群" />
      </div>
      <div class="entry-right">
        <div class="entry-item" @click="$router.push('/pages/compound/compound?thm=2&=')">
          <img src="/static/icon/2.png" alt="藏品合成" />
        </div>
        <div class="entry-item entry-gap" @click="$router.push('/pages/invitation/invitation')">
          <img src="/static/icon/3.png" alt="邀请好友" />
        </div>
      </div>
    </div>

    <!-- 首发藏品 -->
    <div class="mt-31">
      <div class="flex-center-sb plr-16">
        <div class="font-w-500 font-size-16 font-color-dark">首发藏品</div>
        <div class="more-entry" @click="$router.push('/pages/market/market')">
          更多更有趣的新藏品
        </div>
      </div>

      <div class="plr-16 pb-10 goods-list">
        <div
          v-for="g in goods"
          :key="g.id"
          class="flex flex-js-sb flex-ai-center mb-12 goods-card"
          @click="$router.push('/pages/blindBox/blindBoxDetails?ids=' + g.id)"
        >
          <div class="flex flex-ai-center flex-js-center goods-left">
            <div class="goods-img">
              <img :src="oss(g.main_image || g.listimg)" :alt="g.name" />
            </div>
            <div class="flex flex-dir-col goods-info">
              <div class="flex flex-ai-center flex-js-sb">
                <div class="name">{{ g.name }}</div>
              </div>
              <div class="flex flex-dir-col">
                <div class="limit-tag">限量{{ g.total_num }}份</div>
                <div class="flex flex-ai-center flex-js-sb price-row">
                  <div class="price"><span class="yen">￥</span>{{ g.price }}</div>
                  <div v-if="!g.soldout" class="buy-btn">立即购买</div>
                  <div v-else class="soldout-btn">已售罄</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="empty-wrap">
        <img src="/static/empty/search-empty.png" alt="暂无数据" />
      </div>
    </div>
  </div>
</template>

<style scoped>
/* 原站 page-container 实测高 851px：内容 706 + 41 + 32(ptb-16) + 62(tabbar) + 10(padding-bottom) */
.home-bg { min-height: 851px; padding-bottom: 10px; box-sizing: border-box; }

/* ---------- 顶部导航 44px ---------- */
.nav { height: var(--gd-nav-h); }
.u-navbar__content {
  height: var(--gd-nav-h);
  background: var(--gd-bg);
  display: flex;
  align-items: center;
  position: relative;
}
.u-navbar__content__left {
  width: 50px;
  height: 100%;
  padding: 0 13px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.nav-logo { width: 24px; height: 24px; }
.nav-mid {
  position: absolute;
  left: 0;
  right: 0;
  top: 7px;
  height: 30px;
  padding: 0 8px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  pointer-events: none;
}
.search-box {
  width: 261px;
  height: 30px;
  background: var(--gd-nav-bg);
  border-radius: var(--gd-r-nav);
  display: flex;
  align-items: center;
  padding: 0 12px;
  box-sizing: border-box;
  pointer-events: auto;
}
.search-icon { width: 13px; height: 13px; flex-shrink: 0; }
.search-text { font-size: 10px; color: #999; margin-left: 6px; }
.nav-right {
  position: absolute;
  right: 8px;
  top: 7px;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.nav-btn {
  width: 30px;
  height: 30px;
  background: var(--gd-nav-bg);
  border-radius: var(--gd-r-circle);
  display: flex;
  align-items: center;
  justify-content: center;
  box-sizing: border-box;
}
.nav-btn + .nav-btn { margin-left: 11px; }
.nav-sign { width: 15px; height: 13px; }
.nav-bell { width: 12px; height: 12px; }

/* ---------- 轮播 358x169 ---------- */
.u-swiper {
  width: 100%;
  height: var(--gd-banner-h);
  border-radius: var(--gd-r-banner);
  overflow: hidden;
}
.swiper-track {
  display: flex;
  height: 100%;
  transition: transform 0.35s ease;
}
.swiper-item {
  flex: 0 0 100%;
  height: 100%;
}
.swiper-item img { width: 100%; height: 100%; object-fit: fill; display: block; }

/* ---------- 公告条 @16,203 358x33（margin-top:-20px 上移叠在 banner 上）
   内部红条 multiLine @67,203 256x33 bg rgb(242,61,77) r17 */
.notice-view {
  position: relative;
  margin-top: -20px;
  height: 33px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.notice-pill {
  width: 256px;
  height: var(--gd-notice-h);
  background: var(--gd-primary);
  border-radius: var(--gd-r-notice);
  display: flex;
  align-items: center;
  padding: 0 14px;
  box-sizing: border-box;
}
.notice-icon {
  width: 25px;
  height: 25px;
  background: #fff;
  border-radius: var(--gd-r-circle);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.notice-icon img { width: 13px; height: 12px; }
.notice-viewport {
  width: 182.4px;
  height: 45px;
  padding: 9px 12px;
  box-sizing: border-box;
  overflow: hidden;
  display: flex;
  align-items: center;
}
.notice-track {
  flex-shrink: 0;
  transition: transform 0.35s ease;
}
.notice-item {
  width: 158.4px;
  height: 27px;
  font-size: 9px;
  line-height: 13px;
  color: #fff;
  overflow: hidden;
  box-sizing: border-box;
}

/* ---------- 四宫格 ---------- */
.entry-group {
  padding: 0 var(--gd-plr);
  height: 211px;
  box-sizing: border-box;
  align-items: flex-start;
}
.entry-left {
  width: 156px;
  height: var(--gd-entry-l-h);
  overflow: hidden;
  border-radius: 8px;
  flex-shrink: 0;
}
.entry-left img { width: 100%; height: 100%; object-fit: fill; display: block; }
.entry-right {
  width: 195px;
  margin-left: 7px;
}
.entry-item {
  width: 195px;
  height: var(--gd-entry-r-h);
  overflow: hidden;
  border-radius: 8px;
}
.entry-item img { width: 100%; height: 100%; object-fit: fill; display: block; }
.entry-gap { margin-top: 12px; }

/* ---------- 首发藏品 ---------- */
.more-entry {
  font-size: var(--gd-fs-10);
  color: #000;
}
.goods-list { padding-top: 16px; }
.goods-card {
  width: 100%;
  height: var(--gd-goods-card-h);
  background: var(--gd-bg-card);
  border-radius: var(--gd-r-card);
  padding: 0 9px;
  box-sizing: border-box;
}
.goods-left { flex: 1; min-width: 0; align-items: center; }
.goods-img {
  width: var(--gd-goods-img);
  height: var(--gd-goods-img);
  border-radius: var(--gd-r-img);
  overflow: hidden;
  flex-shrink: 0;
}
.goods-img img { width: 100%; height: 100%; object-fit: cover; }
.goods-info {
  margin-left: 24px;
  flex: 1;
  min-width: 0;
  min-height: 71px;
  justify-content: center;
}
.name {
  font-size: var(--gd-fs-15);
  line-height: 21px;
  font-weight: 500;
  color: var(--gd-text-black);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.limit-tag {
  align-self: flex-start;
  background: var(--gd-bg-tag);
  color: #fff;
  font-size: var(--gd-fs-8);
  font-weight: 500;
  line-height: 15px;
  height: 15px;
  padding: 0 4px;
  border-radius: 2px;
  box-sizing: border-box;
  margin-top: 0;
}
.price-row {
  width: 100%;
  margin-top: 8px;
  height: 27px;
}
.price {
  font-size: var(--gd-fs-16);
  font-weight: 700;
  color: var(--gd-primary);
  line-height: 25px;
}
.price .yen { font-size: var(--gd-fs-12); margin-right: 2px; }
.buy-btn {
  height: 27px;
  padding: 0 12px;
  background: var(--gd-primary);
  color: #fff;
  font-size: var(--gd-fs-12);
  font-weight: 700;
  border-radius: var(--gd-r-btn);
  display: flex;
  align-items: center;
  justify-content: center;
}
.soldout-btn {
  height: 27px;
  padding: 0 10px;
  background: var(--gd-bg-mask);
  color: var(--gd-text);
  font-size: var(--gd-fs-10);
  border-radius: var(--gd-r-btn);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.empty-wrap {
  height: 100px;
  display: flex;
  justify-content: center;
  align-items: flex-start;
}
.empty-wrap img { width: 121px; height: 100px; object-fit: fill; }
</style>