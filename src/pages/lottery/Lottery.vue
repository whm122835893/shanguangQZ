<script setup>
/**
 * 幸运抽奖 —— 复刻原站 pages/lottery/lottery
 *
 * 原站 390x844 登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container.page-container @0,0 390x913  bg rgb(28,22,15)
 *   .game-view               @0,0 390x872  bg lottery-bg.png (752x1700)
 *   返回 img                 @13,10 24x24  /static/icon/back-btn-white.png
 *   .icons (fixed)           @316,72 74x80
 *     hdgz-image.png          @316,72 74x36   玩法说明
 *     cjjl-image.png          @316,118 74x34  中奖记录（mt-10）
 *   .cj-u-notice-bar         @112,187 166x31
 *     .notice                 同尺寸  bg rgba(0,0,0,.6)  r13
 *   骰子区                    @151,249 88x88  （未抽奖时为静态图）
 *   .zs-view 次数条           @32,584 327x60  bg rgba(147,97,25,.9) r2 plr-30
 *     钻石图标                 @62.5,601 31x26
 *     「可抽0次」              @271.5,602 56x24  16px/600 #fff
 *   .flex-center-sb.mt-30 按钮行 @32,675 327x58
 *     draw-1.png               @31.5,675 151x58   抽一次
 *     draw-5.png               @207.5,675 151x58  抽五次
 *   .prize-record             @32,753 327x119  bg bg.png (630x230)
 *     「奖品列表」             @31.5,757 327x21  14px/600 #fff
 *
 * 接口（原站登录态实测）：
 *   POST /lottery/info        body {id:0}          -> code:0 "暂无抽奖活动"
 *   POST /lottery/statistics  body {lottery_id:0}  -> code:1 data:[]
 * 注：原站当前无进行中的抽奖活动，故奖品列表为空。此处按原站实测原样调用，
 *     不编造奖品；动画与按钮交互完整实现。
 */
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { http, oss } from '@/api/http'
import { showToast } from 'vant'

const router = useRouter()

/* 剩余可抽次数（来自 info.remaining） */
const remain = ref(0)
/* 奖品列表（来自 info.collection_json） */
const prizes = ref([])
/* 抽奖活动完整信息（对齐原站 info 默认值结构） */
const info = ref({ remaining: 0, collection_json: [], lotteryList: [] })
/* 中奖记录列表（原站 prizeRecord + prizeList，取 statistics 返回） */
const prizeRecord = ref([])
/* 动画状态：idle 平靜 / rolling 抽奖中 */
const rolling = ref(false)
const rollingTimes = ref(0)

/* 抽奖活动 id：原站「幸运抽奖」入口带 ?id=1（「幸运抽签」走另一入口 activity/draw?thm=3）。
   未带 id 时默认 0，与原站未开启活动时的语义一致。 */
const route = useRoute()
const lotteryId = ref(Number(route.query.id) || 0)

/* 骰子点数：1-6，动画中快速变化 */
const diceNum = ref(1)
let diceTimer = null

/** 原站 crystal()：按 info.crystal（消耗货币类型）选图标与可用数
 *  "10"->黄(crystal_yellow) "20"->紫(crystal_purple) "30"->蓝(crystal_blue) */
const crystal = computed(() => {
  const t = String(info.value.crystal ?? '')
  if (t === '10') return { icon: 'hsj', name: '黄', totalNum: info.value.crystal_yellow ?? 0 }
  if (t === '20') return { icon: 'zsj', name: '紫', totalNum: info.value.crystal_purple ?? 0 }
  return { icon: 'lsj', name: '蓝', totalNum: info.value.crystal_blue ?? 0 }
})

/** 原站 activeState()：checkTimeBetween(now, start_time, end_time) → 0未开始 1进行中 2已结束 */
const activeState = computed(() => {
  const s = Date.parse(String(info.value.start_time || '').replace(/-/g, '/'))
  const e = Date.parse(String(info.value.end_time || '').replace(/-/g, '/'))
  const n = Date.now()
  if (!s || !e) return 1
  if (n < s) return 0
  if (n > e) return 2
  return 1
})

/** 原站 cj-u-notice-bar：公告取 info.lotteryList[].msg，纵向无缝滚动。
 *  为保证滚动首尾一致，列表复制一份（两倍长度，位移50% 即为一个周期）。 */
const notices = computed(() =>
  (info.value.lotteryList || [])
    .map((it) => (it && (it.msg || it.message)) ? String(it.msg || it.message) : '')
    .filter(Boolean)
)
const noticeLoop = computed(() => (notices.value.length ? [...notices.value, ...notices.value] : []))

/** 原站骰子位在未抽奖时展示奖品主图（statistics 首项 image） */
const heroPrize = computed(() => {
  const p = prizes.value[0]
  if (!p) return ''
  return oss(p.image || p.lottery_image || p.collectionInfo?.main_image || '')
})

async function load() {
  /* 原站 onShow -> getDetails() + getLotteryList() */
  try {
    const r = await http.post('/lottery/info', { id: lotteryId.value })
    info.value = r.data || { remaining: 0, collection_json: [], lotteryList: [] }
    remain.value = Number(info.value.remaining) || 0
    prizes.value = Array.isArray(info.value.collection_json) ? info.value.collection_json : []
  } catch (e) {
    info.value = { remaining: 0, collection_json: [], lotteryList: [] }
    remain.value = 0
    prizes.value = []
  }
  /* 奖品列表：原站渲染的是 statistics 返回的 prizeRecord（每项含 name/image/lottery_image），
     不是 info.collection_json —— 后者是消耗道具清单。 */
  try {
    const r = await http.post('/lottery/statistics', { lottery_id: lotteryId.value })
    const d = r.data
    prizeRecord.value = Array.isArray(d) ? d : []
    prizes.value = prizeRecord.value
  } catch (e) {
    prizeRecord.value = []
    prizes.value = []
  }
}

onMounted(load)
onUnmounted(() => diceTimer && clearInterval(diceTimer))

/** 抽奖：完全对齐原站 isStartDraw 的判定顺序
 *   activeState 0 -> toast「暂未开始」
 *   activeState 2 -> toast「活动已结束」
 *   remaining<=0 -> toast「次数不足」
 *   抽奖中       -> toast「抽奖进行中...」
 *   否则 -> POST /lottery/start { lotteryId, number }，成功后 remaining -= number 并弹结果 */
function draw(times) {
  if (rolling.value) {
    showToast({ icon: 'none', message: '抽奖进行中...' })
    return
  }
  if (activeState.value === 0) {
    showToast({ icon: 'none', message: '暂未开始' })
    return
  }
  if (activeState.value === 2) {
    showToast({ icon: 'none', message: '活动已结束' })
    return
  }
  if (Number(remain.value) <= 0) {
    showToast({ icon: 'none', message: '次数不足' })
    return
  }
  rolling.value = true
  rollingTimes.value = times
  diceTimer && clearInterval(diceTimer)
  diceTimer = setInterval(() => {
    diceNum.value = 1 + Math.floor(Math.random() * 6)
  }, 80)

  ;(async () => {
    let ok = false
    let msg = ''
    try {
      const r = await http.post('/lottery/start', {
        lotteryId: lotteryId.value,
        number: times
      })
      ok = true
      const prize = r.data
      remain.value = Math.max(0, Number(remain.value) - times)
      info.value = { ...info.value, remaining: remain.value }
      msg = (() => {
        const p = Array.isArray(prize) ? prize[0] : prize
        if (p && (p.collection_name || p.name)) {
          return `恭喜获得${p.collection_name || p.name}`
        }
        return '抽奖成功'
      })()
    } catch (e) {
      ok = false
      msg = (e && e.msg) || '抽奖失败，请稍后重试'
    }
    /* 动画至少跑满 1.2s，与原站抽奖动效时长相称 */
    setTimeout(() => {
      diceTimer && clearInterval(diceTimer)
      diceTimer = null
      rolling.value = false
      showToast({ icon: 'none', message: ok ? msg : msg })
    }, 1200)
  })()
}

function toRule() { router.push('/pages/lottery/rule') }
function toRecord() { router.push('/pages/lottery/record') }
</script>

<template>
  <div class="lottery-page">
    <!-- 返回 @13,10 24x24 -->
    <img class="back" src="/static/icon/back-btn-white.png" alt="返回" @click="router.back()" />

    <!-- 主视觉区 lottery-bg.png 390x872 -->
    <div class="game-view">
      <!-- 公告条 @112,187 166x31
           原站实测：内部为纵向无缝滚动的 SPAN 列表（11px 白色文本，宽 140，垂直步进 16px），
           文本来自 info.lotteryList[].msg 形如「obNY*** 2小时前抽中了盲僧合成资格卡藏品」 -->
      <div class="notice-bar">
        <div class="notice">
          <div class="notice-track" :class="{ rolling: notices.length > 1 }">
            <span v-for="(n, i) in noticeLoop" :key="i" class="notice-item">{{ n }}</span>
          </div>
        </div>
      </div>

      <!-- 骰子位 @151,249 88x88
           原站实测：未抽奖时此处为一张88x88 奖品主图（statistics 首项 image），
           抽奖时切换为问号骰子动效（骰子已绘制在 lottery-bg.png 背景内，此处仅叠转动画）。 -->
      <div class="dice-box" :class="{ rolling }">
        <img v-if="!rolling && heroPrize" :src="heroPrize" alt="" class="dice-hero" />
      </div>
    </div>

    <!-- 次数条 @32,584 327x60 -->
    <div class="zs-view">
      <div class="zs-left">
        <!-- 钻石图标 @62.5,601 31x26（原站内联 SVG，此处按实测配色 1:1 还原） -->
        <svg class="zs-icon" viewBox="0 0 31 26" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="dzg" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#e8a8ff" />
              <stop offset="0.45" stop-color="#c364e8" />
              <stop offset="1" stop-color="#9148e5" />
            </linearGradient>
          </defs>
          <polygon points="9,1 22,1 30,9 15.5,25 1,9" fill="url(#dzg)" />
          <polygon points="9,1 15.5,9 1,9" fill="#f0bcff" opacity="0.55" />
          <polygon points="22,1 30,9 15.5,9" fill="#d89bf5" opacity="0.5" />
          <polygon points="1,9 15.5,9 15.5,25" fill="#a952e6" opacity="0.45" />
          <polygon points="30,9 15.5,9 15.5,25" fill="#8b3fe0" opacity="0.4" />
        </svg>
        <span class="zs-num">{{ crystal.totalNum }}</span>
      </div>
      <div class="zs-right">可抽{{ remain }}次</div>
    </div>

    <!-- 按钮行 @32,675 327x58 -->
    <div class="btn-row">
      <img class="draw-btn" src="/static/image/lottery/draw-1.png" alt="抽一次" @click="draw(1)" />
      <img class="draw-btn" src="/static/image/lottery/draw-5.png" alt="抽五次" @click="draw(5)" />
    </div>

    <!-- 奖品列表 @32,753 327x119（原站为一排 72x72 缩略图，横向可滑动） -->
    <div class="prize-record">
      <div class="prize-title">奖品列表</div>
      <div class="prize-scroll">
        <div v-for="(p, i) in prizes" :key="i" class="prize-item">
          <img :src="oss(p.lottery_image || p.image || p.collectionInfo?.main_image)" alt="" />
        </div>
      </div>
    </div>

    <!-- 右上角固定图标 @316,72 -->
    <div class="icons">
      <img class="icon-item" src="/static/image/lottery/hdgz-image.png" alt="玩法说明" @click="toRule" />
      <img class="icon-item second" src="/static/image/lottery/cjjl-image.png" alt="中奖记录" @click="toRecord" />
    </div>
  </div>
</template>

<style scoped>
/* .container @0,0 390x913 bg rgb(28,22,15) */
.lottery-page {
  position: relative;
  width: 390px;
  min-height: 913px;
  background: rgb(28, 22, 15);
  overflow-x: hidden;
}

/* 返回 @13,10 24x24 */
.back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  z-index: 10;
}

/* .game-view @0,0 390x872 bg lottery-bg.png
   注：原站实测为 background-size:100% auto —— 源图 752x1700 等比缩放到 390宽
   （实际渲染高 882px，被容器 872 裁掉底部 10px）。此处按原站锁定为 100% auto，
   不可改成390x872 强制拉伸（会造成整体纵向压缩 ~1.1%，全屏像素差升高到 20%）。 */
.game-view {
  position: relative;
  width: 390px;
  height: 872px;
  background: url('/static/image/lottery/lottery-bg.png') no-repeat center top;
  background-size: 100% auto;
}

/* 公告条 @112,187 166x31 */
.notice-bar {
  position: absolute;
  left: 112px;
  top: 187px;
  width: 166px;
  height: 31px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.notice {
  position: relative;
  width: 166px;
  height: 31px;
  background: rgba(0, 0, 0, 0.6);
  border-radius: 13px;
  overflow: hidden;
}
/* 原站 SPAN 宽 140、11px 白色、垂直步进 16px、无缝上滚 */
.notice-track {
  position: absolute;
  left: 13px;
  top: 0;
  width: 140px;
  text-align: center;
}
.notice-item {
  display: block;
  width: 140px;
  height: 16px;
  line-height: 16px;
  color: #fff;
  font-size: 11px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.notice-track.rolling {
  animation: notice-up 2.4s linear infinite;
}
@keyframes notice-up {
  0%   { transform: translateY(0); }
  100% { transform: translateY(-50%); }
}

/* 骰子位 @151,249 88x88 */
.dice-box {
  position: absolute;
  left: 151px;
  top: 249px;
  width: 88px;
  height: 88px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.dice-hero {
  display: block;
  width: 88px;
  height: 88px;
  object-fit: cover;
  border-radius: 4px;
}
/* 抽奖中：骰子旋转 + 缩放脉动 1.2s（原站为抽奖动画，此处保持同节奏） */
.dice-box.rolling { animation: dice-roll 0.4s linear infinite; }
@keyframes dice-roll {
  0%   { transform: rotate(0deg) scale(1); }
  50%  { transform: rotate(180deg) scale(1.12); }
  100% { transform: rotate(360deg) scale(1); }
}

/* 次数条 @32,584 327x60 bg rgba(147,97,25,.9) */
.zs-view {
  position: absolute;
  left: 32px;
  top: 584px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 327px;
  height: 60px;
  padding: 0 30px;
  box-sizing: border-box;
  background: rgba(147, 97, 25, 0.9);
  border-radius: 2px;
}
.zs-left { display: flex; align-items: center; }
/* 钻石图标 31x26 */
.zs-icon {
  display: block;
  width: 31px;
  height: 26px;
  margin-right: 8px;
  flex-shrink: 0;
}
.zs-num {
  color: #fff;
  font-size: 16px;
  font-weight: 600;
}
.zs-right {
  color: #fff;
  font-size: 16px;
  font-weight: 600;
}

/* 按钮行 @32,675 327x58 */
.btn-row {
  position: absolute;
  left: 32px;
  top: 675px;
  display: flex;
  justify-content: space-between;
  width: 327px;
  height: 58px;
}
.draw-btn {
  width: 151px;
  height: 58px;
  cursor: pointer;
}
.draw-btn:active { opacity: 0.85; }

/* 奖品列表 @32,753 327x119 bg bg.png */
.prize-record {
  position: absolute;
  left: 32px;
  top: 753px;
  width: 327px;
  height: 119px;
  padding-top: 4px;
  box-sizing: border-box;
  background: url('/static/image/lottery/bg.png') no-repeat center;
  background-size: 327px 119px;
}
.prize-title {
  width: 327px;
  height: 21px;
  line-height: 21px;
  text-align: center;
  color: #fff;
  font-size: 14px;
  font-weight: 600;
}
.prize-empty { width: 327px; height: 94px; }
/* 原站奖品区：单排 72x72 缩略图，横向可滑动（间距 19，起点 51，y=790） */
.prize-scroll {
  display: flex;
  flex-wrap: nowrap;
  gap: 19px;
  padding-left: 19px;
  margin-top: 12px;
  width: 327px;
  height: 94px;
  align-items: flex-start;
  overflow-x: auto;
  overflow-y: hidden;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.prize-scroll::-webkit-scrollbar { display: none; }
.prize-item {
  flex: none;
  width: 72px;
  height: 72px;
}
.prize-item img {
  display: block;
  width: 72px;
  height: 72px;
  border-radius: 4px;
  object-fit: cover;
  background: rgb(238, 238, 238);
}

/* 右上角图标 @316,72 74x80 */
.icons {
  position: fixed;
  left: 316px;
  top: 72px;
  width: 74px;
  height: 80px;
  z-index: 20;
}
.icon-item {
  display: block;
  width: 74px;
  height: 36px;
  cursor: pointer;
}
.icon-item.second {
  margin-top: 10px;
  height: 34px;
}
</style>
