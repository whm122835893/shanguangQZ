<script setup>
/**
 * 公告详情 —— 1:1 复刻原站 pages/notice/noticeDetails
 *
 * 路由：#/pages/notice/noticeDetails?id=116&category=寄售公告
 *
 * 原站 390x844 实测（DOM + computed style，id=116「全宇宙唯一！700倍提价，武圣关羽！」）：
 *   .container.page-container.page-bg.plr-16 @0,0 390x844  bg rgb(250,250,250) padding 0 16px
 *     .u-navbar--fixed            @0,0 390x44  bg rgb(250,250,250)
 *       back-btn                  @13,10 24x24
 *       标题 span「公告详情」       @165,16 60x16  15px/700 rgb(51,51,51)
 *     .pt-10                      @16,44 358x680  padding 10px 0 0
 *       标题                       @16,54 358x24  16px/700 rgb(35,38,47)  mb-10
 *       meta行                     @16,88 358x18
 *         分类 mr-4                @16,88 48x18   12px/500 rgb(21,124,255)
 *         时间 font-color-999      @68,89.5 101.7x15  12px/400 rgb(153,153,153)
 *       正文卡.bg-color-item       @16,121 358x107  bg #fff  pad 12px 20px  br12  mt-15
 *         ._root                   @36,133 318x83   15px lh22  段间距 0
 *       提示卡.bg-color-item.tips  @16,240 358x484  bg #fff  pad 12px 20px  br12  mt-12
 *         段头 .font-size-16.section   @36,272 318x18  14px/500 rgb(51,51,51) lh18 mt-20
 *         段身 .font-size-14.mt-12     @36,310 318x54  同上 + letter-spacing 1px
 *         段头                       @36,384 318x18
 *         段身                       @36,422 318x162
 *         段身                       @36,604 318x108   卡底 724 = 712 + pad12
 *
 * 接口：GET /index/afficheDetail/ids/{id}
 *   -> data{ id, title, content(html), image, affiche_type_id, publish_time, ... }
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getAfficheDetail } from '@/api'

const route = useRoute()
const router = useRouter()

const detail = ref(null)

/** 分类文案：按 affiche_type_id 映射（原站公告中心 tab 同名） */
const TYPE_MAP = {
  1: '寄售公告',
  2: '活动公告',
  3: '合成公告',
  4: '运营公告',
  5: '空投公告'
}

const category = computed(() => {
  const q = route.query.category
  if (q) return String(q)
  const t = detail.value && detail.value.affiche_type_id
  return TYPE_MAP[t] || ''
})

const title = computed(() => (detail.value ? detail.value.title || '' : ''))

/** 正文：原站为富文本 HTML，uni-rich-text 直出（15px / lh22 / p 无间距） */
const contentHtml = computed(() => (detail.value ? detail.value.content || '' : ''))

/** 发布时间：原站格式 YYYY-MM-DD HH:mm:ss */
const publishText = computed(() => {
  const t = detail.value && (detail.value.publish_time || detail.value.createtime)
  if (!t) return ''
  const d = new Date(Number(t) * 1000)
  const p = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
})

/** 原站提示卡固定 5 段（head=段头粗体标题，body=说明文字） */
const TIPS = [
  { head: '【闪光风险提示】:', body: '闪光发售的数字藏品仅具备收藏欣赏价值，官方对藏品价格不构成任何指导意义，请谨慎购买，严防炒作。' },
  { head: '【闪光郑重提醒广大用户】:', body: '平台对于使用第三方工具锁单、抢单;以平台的名义进行承诺收益、承诺回购、非法集资、非法吸收公共存款;为了非法目的进行拉人头、要求缴纳入门费、团队计酬返利等涉嫌传销的行为或故意从事旨在对平台、平台用户或服务的履行造成不利影响的等行为秉持零容忍态度，一经发现将采取封号、冻结等措施。请大家规范抢购，不要轻信此类第三方工具或任何非官方的抢购信息，避免造成个人财产损失!' },
  { body: '根据国家反洗钱、反诈骗等法律法规规定，特提醒广大用户严格遵守国家法律法规，禁止在闪光平台进行洗钱、诈骗、赌博、开设赌场、非法集资、非法融资、传销等违法行为，否则一经平台查实，将对您的闪光账号作出封禁、冻结等处理，并移交国家司法机关追究其法律责任。' }
]

const load = async () => {
  const id = route.query.id
  if (!id) return
  try {
    const res = await getAfficheDetail(id)
    detail.value = res.data || null
  } catch (e) {
    detail.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="notice-detail">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">公告详情</div>
    </div>

    <!-- 内容 @16,44 -->
    <div class="content">
      <!-- 标题 @16,54 -->
      <h1 class="title">{{ title }}</h1>

      <!-- 分类 + 时间 @16,88 -->
      <div class="meta">
        <span class="meta-cat">{{ category }}</span>
        <span class="meta-time">{{ publishText }}</span>
      </div>

      <!-- 正文卡 @16,121 -->
      <div class="card">
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="rich" v-html="contentHtml"></div>
      </div>

      <!-- 提示卡 @16,240 -->
      <div class="card card--tips">
        <template v-for="(t, i) in TIPS" :key="i">
          <p v-if="t.head" class="tips-head">{{ t.head }}</p>
          <p class="tips-body">{{ t.body }}</p>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notice-detail {
  position: relative;
  width: 390px;
  max-width: 100%;
  min-height: 844px;
  padding: 0 16px;
  background: rgb(250, 250, 250);
  box-sizing: border-box;
  overflow: hidden;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* ---------- 导航 @0,0 390x44 ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  margin: 0 -16px;
  background: rgb(250, 250, 250);
}
.nav-back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  cursor: pointer;
}
.nav-back img {
  display: block;
  width: 24px;
  height: 24px;
}
.nav-title {
  position: absolute;
  left: 165px;
  top: 16px;
  width: 60px;
  height: 16px;
  font-size: 15px;
  font-weight: 700;
  color: rgb(51, 51, 51);
  line-height: 16px;
  text-align: center;
}

/* ---------- 内容 ---------- */
.content {
  width: 358px;
  padding-top: 10px;
}

/* 标题 @16,54 358x24 mb-10（line-height 用 normal，与原站基线一致） */
.title {
  margin: 0 0 10px;
  width: 358px;
  font-size: 16px;
  font-weight: 700;
  color: rgb(35, 38, 47);
}

/* 分类 + 时间 @16,88 358x18 */
.meta {
  display: flex;
  align-items: center;
  width: 358px;
  height: 18px;
}
.meta-cat {
  margin-right: 4px;
  font-size: 12px;
  font-weight: 500;
  color: rgb(21, 124, 255);
  line-height: 18px;
}
.meta-time {
  font-size: 12px;
  font-weight: 400;
  color: rgb(153, 153, 153);
  line-height: 15px;
}

/* 卡片 @16,121 bg #fff br12 padding 12px 20px */
.card {
  width: 358px;
  margin-top: 15px;
  padding: 12px 20px;
  background: #fff;
  border-radius: 12px;
  box-sizing: border-box;
}

/* 正文富文本 @36,133 318x83  15px lh22 段间距 0 */
.rich {
  width: 318px;
  font-size: 15px;
  color: rgb(74, 74, 74);
  line-height: 22px;
  word-break: break-word;
}
.rich :deep(p) {
  margin: 0;
  padding: 0;
}
/* 原站空段（只含 <br>）行高 17px，非 22px —— 影响卡片总高 5px */
.rich :deep(p:has(> br:only-child)) {
  line-height: 17px;
}

/* 提示卡 @16,240 mt-12 高 484 */
.card--tips {
  margin-top: 12px;
}
.tips-head,
.tips-body {
  margin: 20px 0 0;
  width: 318px;
  font-size: 14px;
  font-weight: 500;
  color: rgb(51, 51, 51);
  line-height: 18px;
  word-break: break-word;
}
.tips-body {
  letter-spacing: 1px;
}
</style>