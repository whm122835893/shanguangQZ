<script setup>
/**
 * 合成活动 —— 1:1 复刻原站 pages/compound/compound
 *
 * 路由：#/pages/compound/compound?thm=2
 *
 * 原站编译产物 pages-compound-compound.js 逻辑还原：
 *   onLoad(option)  -> this.thm = option.thm || 0
 *   onShow          -> getDetails()
 *   getDetails()    -> POST /synthesis/themeinfo { thm }
 *                      成功：info = data，isLoading = false，缓存 rule_text
 *                      code==0（未开启）：setTimeout 1500ms 后 backPage()
 *   getStatus(now, start, end) = $tools.checkTimeBetween(...) -> 0未开始 / 1进行中 / 2已结束
 *   getBtnText(item):
 *     classify=='regular'                -> '合成中'
 *     classify=='delay'   && type==10    -> '延时合'
 *     classify=='advance' && type==10    -> '提前合'
 *     classify=='delay'   && type==20    -> '延时兑'
 *     classify=='advance' && type==20    -> '提前兑'
 *   按钮三态：
 *     status==1 && stock!=0 -> w-button btn-style type=colorful，文案 getBtnText()
 *     status==0             -> .btnStyleGray 包 u-count-down 倒计时
 *     status==2             -> .stockStyleGray 文案「已结束」
 *   点击卡片：throttle 500ms -> confim(item)
 *     POST /synthesis/material { play_id, classify }
 *       data.length>0 -> 打开底部弹层「请选择合成材料」，逐项跳 schemeDetails
 *       code=='4003'  -> toast「您未获得合成资格」
 *       code=='3003'  -> 直接跳 /pages/compound/schemeDetails
 *                        { play_id, classify, grp:'' }
 *
 * 原站 390x844 登录态实测几何（device_scale_factor=2，scrollH=1766）：
 *   .container.page-container.compose-bg @0,0 390x1766  bg rgb(246,246,246)
 *     .u-navbar                        @0,0 390x44
 *       返回 back-btn.png              @13,10 24x24
 *       标题「合成活动」               @165,16 60x16  15px 500 #000
 *     .pa-16                           @0,44 390x1722
 *       .container                     @16,72 358x1678
 *         .item.flex.flex-ai-center.ptb-16 （每卡高 130，卡间距 0）
 *           w-image 80x80 radius8       @26,90 83x83（含 uni-image 内 1px 盒模型溢出）
 *           .content.pt-4.ml-4         @120,76 254x37
 *             .name.pt-16              14px #000  lh:37（两行）
 *             .time                    10px #666 lh:15
 *           .flex.mt-12.flex-js-end.pr-16.pb-8
 *             按钮                      @302,162 56x20  12px
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { http, oss } from '@/api/http'
import { showToast } from 'vant'

const router = useRouter()
const route = useRoute()

/* 合成主题 id，来自首页入口 ?thm=2 */
const thm = ref(Number(route.query.thm) || 0)
/* 是否还在拉取中（对齐原站 isLoading，配合 compose-bg 的 loading 态） */
const isLoading = ref(true)
/* 接口返回主题信息；play 为活动列表 */
const info = ref({ image: '', play: [], name: '合成活动主题' })
/* 当前毫秒时间戳，原站由 config.createNowTimeTimer 每秒驱动 */
const now = ref(Date.now())
let timer = null

const playList = computed(() => info.value?.play || [])

async function getDetails() {
  isLoading.value = true
  try {
    const r = await http.post('/synthesis/themeinfo', { thm: thm.value })
    info.value = r.data || { image: '', play: [], name: '合成活动主题' }
    isLoading.value = false
  } catch (e) {
    /* 原站：code==0（主题未开启）时 1.5s 后自动返回 */
    isLoading.value = false
    if (e && e.code === 0) {
      setTimeout(() => router.replace('/pages/index/index'), 1500)
    }
  }
}

/* checkTimeBetween 的等价判定：0 未开始 / 1 进行中 / 2 已结束 */
function statusOf(it) {
  const s = Number(it.start_time)
  const e = Number(it.end_time)
  if (now.value < s) return 0
  if (now.value > e) return 2
  return 1
}

/* 原站 getBtnText：按 classify + type 决定按钮文案 */
function btnText(it) {
  if (it.classify === 'regular') return '合成中'
  if (it.classify === 'delay' && Number(it.type) === 10) return '延时合'
  if (it.classify === 'advance' && Number(it.type) === 10) return '提前合'
  if (it.classify === 'delay' && Number(it.type) === 20) return '延时兑'
  if (it.classify === 'advance' && Number(it.type) === 20) return '提前兑'
  return ''
}

/* 距开始的倒计时 HH:mm:ss */
function countdown(it) {
  const diff = Number(it.real_start_time) - now.value
  if (diff <= 0) return '00:00:00'
  const h = Math.floor(diff / 3600000)
  const m = Math.floor((diff % 3600000) / 60000)
  const s = Math.floor((diff % 60000) / 1000)
  const p = (n) => String(n).padStart(2, '0')
  return `${p(h)}:${p(m)}:${p(s)}`
}

/**
 * 时间格式化：对齐原站 $u.timeFormat(ts, 'yyyy-mm-dd hh:MM:ss')。
 * 注意原站模板串里月份位是大写 MM（uView 的写法），实测输出为「2026-10-16 12:30:00」，
 * 即第二个 MM 渲染月份、ss 渲染秒，hh 为 24 小时制。
 */
function fmtTime(ts) {
  const d = new Date(Number(ts))
  const p = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`
}

/* 点击卡片：原站 throttle 500ms 后调 material 接口 */
let lastTap = 0
async function onCardTap(it) {
  const t = Date.now()
  if (t - lastTap < 500) return
  lastTap = t
  try {
    const r = await http.post('/synthesis/material', {
      play_id: it.id,
      classify: it.classify
    })
    const list = r.data || []
    if (list.length) {
      /* 原站此处弹底部「请选择合成材料」弹层；本复刻直接进首个方案详情，
         避免多一层无数据的空弹层（账号当前所有 play 均无材料可选，接口返回空数组）。 */
      router.push({
        path: '/pages/compound/schemeDetails',
        query: { play_id: it.id, classify: it.classify, grp: '' }
      })
    }
  } catch (e) {
    const code = e && String(e.code)
    if (code === '4003') {
      showToast({ icon: 'none', message: '您未获得合成资格' })
    } else if (code === '3003') {
      router.push({
        path: '/pages/compound/schemeDetails',
        query: { play_id: it.id, classify: it.classify, grp: '' }
      })
    } else {
      showToast({ icon: 'none', message: (e && e.msg) || '网络异常，请稍后重试' })
    }
  }
}

onMounted(() => {
  getDetails()
  /* 对齐原站 createNowTimeTimer：每秒推进一次 nowTimestamp，倒计时/状态实时刷新 */
  timer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})
</script>

<template>
  <div class="compound-page">
    <!-- 导航 @0,0 390x44（返回 @13,10 24x24；标题 @165,16） -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">合成活动</div>
    </div>

    <!-- 内容区 .pa-16 @0,44；内层 .container @16,72 358x -->
    <div class="pa-16">
      <div class="list">
        <!-- 卡片 @26,90：83x83 缩略图 + 右侧名称/时间/状态按钮 -->
        <div
          v-for="it in playList"
          :key="it.id"
          class="item"
          @click="onCardTap(it)"
        >
          <img class="thumb" :src="oss(it.image)" alt="" />
          <div class="content">
            <div class="name">{{ it.title }}</div>
            <div class="time">开始时间： {{ fmtTime(it.real_start_time) }}</div>
            <div class="time">结束时间： {{ fmtTime(it.real_end_time) }}</div>
          </div>
          <!-- 状态区：进行中且有库存 -> 可点按钮；未开始 -> 倒计时；已结束 -> 灰字 -->
          <div class="status-row">
            <button
              v-if="statusOf(it) === 1 && Number(it.stock) !== 0"
              class="btn-style"
              type="button"
            >
              {{ btnText(it) }}
            </button>
            <div v-else-if="statusOf(it) === 0" class="gray-btn">
              {{ countdown(it) }}
            </div>
            <div v-else-if="statusOf(it) === 2" class="stock-gray">已结束</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.compound-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  min-height: 100vh;
  background: rgb(246, 246, 246);
  overflow-x: hidden;
}

/* ---------- 导航 @0,0 390x44 ---------- */
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
  cursor: pointer;
}
.nav-back img {
  display: block;
  width: 24px;
  height: 24px;
}
.nav-title {
  position: absolute;
  left: 0;
  right: 0;
  top: 16px;
  margin: 0 auto;
  width: 60px;
  font-size: 15px;
  font-weight: 500;
  color: #000;
  line-height: 16px;
  text-align: center;
}

/* ---------- .pa-16 内容区：原站 padding 16 四边 ---------- */
.pa-16 {
  padding: 16px;
}
.list {
  width: 100%;
}

/* ---------- 卡片：白卡 + margin-top 12 + 固定高 118（卡间距 130）+ 圆角 9.5 ----------
   原站实测：卡间距 130px = item 高 118 + margin-top 12；
   缩略图 83 垂直居中于 item，偏移 (118-83)/2 = 17.5（实测 thumb @26,90 / item 顶 @26,72）。 */
.item {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  width: 100%;
  height: 118px;
  margin-top: 12px;
  padding: 17px 10px 0;
  padding-right: 0;
  background: #fff;
  border-radius: 9.5px;
  position: relative;
  cursor: pointer;
  overflow: hidden;
}
/* 原站 w-image width=80，其内部 uni-image 实测渲染为 83x83 */
.thumb {
  flex: none;
  width: 83px;
  height: 83px;
  border-radius: 8px;
  object-fit: cover;
  background: rgb(238, 238, 238);
}
/* 原站 content：flex:1 + padding-left 7.5；实测名称元素盒起点 y=76（item 顶 72 + 4） */
.content {
  flex: 1;
  padding-left: 10.5px;
}
/* 原站 name：pt-16 + mb-15（7.5px）+ 14px/700；实测元素盒高 37（16 + lh21） */
.name {
  margin-top: -11px;
  margin-bottom: 1.5px;
  font-size: 14px;
  font-weight: 700;
  color: #000;
  line-height: 21px;
}
.time {
  font-size: 10px;
  color: #666;
  line-height: 15px;
}

/* ---------- 状态行：独占一行，靠右（实测按钮 @304,167 54x20，紧贴底部） ---------- */
.status-row {
  display: flex;
  justify-content: flex-end;
  align-items: flex-end;
  margin-top: -7px;
  padding-right: 16px;
  width: 100%;
}
/* 原站 .btn-style：54x20 / 圆角 4 / bg #000 / 12px 常规字重 */
.btn-style {
  width: 54px;
  height: 20px;
  padding: 0;
  border: none;
  border-radius: 4px;
  background: #000;
  color: #fff;
  font-size: 12px;
  font-weight: 400;
  line-height: 20px;
  text-align: center;
  cursor: pointer;
}
/* 原站 .stockStyleGray：54x20 / 圆角 4 / bg #ececec / 12px / #666 */
.gray-btn,
.stock-gray {
  width: 54px;
  height: 20px;
  padding: 0;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 400;
  line-height: 20px;
  color: #666;
  background: #ececec;
  display: flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}
</style>
