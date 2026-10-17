<script setup>
/**
 * 签到中心 —— 100% 复刻原站 pages/sign/index
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle）：
 *   .qd-bg.container                     @0,0    390x844  bg /image/sign/top-bg.png size 100%
 *     返回（back-btn-white.png）        @13,10  24x24    size 100% 100%
 *     标题「签到中心」                  @165,16 60x16    15px/700 #fff
 *     「您已连续签到」                  @0,88   390x24   16px/400 #fff   居中
 *     大数字「0」                       @16,132 15.5x35  31px/600 #fff
 *     「天」                            @41.5,144 16x17  16px/400 #fff
 *     「签到享好礼,连续签到会获得更多好礼哦！」@0,188 390x21 14px/400 #fff 居中
 *   浅灰区 rgb(246,246,246)  y≈293 起
 *     白卡                              @16,~295 358x~487  圆角 12px
 *       「‹」                            月份切换左箭头
 *       「2026 / 10」                    @157.2,315 75.6x22  20px/400 #000
 *       「›」                            月份切换右箭头
 *       星期表头 SUN..SAT                 y=364   14px/400 #000
 *         SUN  @34.8   MON @81.2  TUE @132.7  WED @179
 *         THU  @229.3 FRI @281.5 SAT @328.7
 *       日期网格（7 列，行高 57）
 *         上月补位（27,28,29,30 + 1,2,3）rgb(192,192,192)
 *         本月 1..13 / 15..31              rgb(114,112,120)
 *         今天 14：黑底圆角块 + 白字 + 「today」小字
 *       「立即签到」                      @16,793 358x39  14px/700 #fff  bg #000  r23
 *
 * 顶部深色区实测：y 0~292 为背景图，y≥293 为 rgb(246,246,246)
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

/* 当前日期（与原站一致：以系统时间定位今天所在月） */
const now = new Date()
const year = ref(now.getFullYear())
const month = ref(now.getMonth() + 1)
const today = now.getDate()

const WEEK = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT']

/* 月份标题「2026 / 10」 */
const monthText = computed(() => `${year.value} / ${month.value}`)

/* 日历网格：补上月尾部 + 本月，末尾补空位凑满整行 */
const days = computed(() => {
  const first = new Date(year.value, month.value - 1, 1)
  const firstWeekday = first.getDay()
  const total = new Date(year.value, month.value, 0).getDate()
  const prevTotal = new Date(year.value, month.value - 1, 0).getDate()
  const cells = []
  for (let i = firstWeekday - 1; i >= 0; i--) {
    cells.push({ d: prevTotal - i, out: true })
  }
  for (let d = 1; d <= total; d++) {
    cells.push({ d, out: false, today: d === today && month.value === now.getMonth() + 1 && year.value === now.getFullYear() })
  }
  return cells
})

/* 原站日历容器固定 6 行（w=358 / 7 列 ≈ 51.1 列宽，行高 57） */
const rows = computed(() => {
  const c = days.value.slice()
  while (c.length % 7 !== 0 || c.length < 42) c.push({ d: '', out: true, blank: true })
  const out = []
  for (let i = 0; i < c.length; i += 7) out.push(c.slice(i, i + 7))
  return out
})

const prevMonth = () => {
  if (month.value === 1) { month.value = 12; year.value -= 1 } else month.value -= 1
}
const nextMonth = () => {
  if (month.value === 12) { month.value = 1; year.value += 1 } else month.value += 1
}

const back = () => {
  if (history.length > 1) router.back()
  else router.replace('/pages/my/my')
}

/* 原站连续签到天数（接入用户信息；当前账号为 0） */
const continuousDays = ref(0)
const signed = ref(false)

onMounted(async () => {
  try {
    const { getUserInfo } = await import('@/api')
    const res = await getUserInfo()
    const info = (res.data && (res.data.userinfo || res.data.userInfo)) || res.data || {}
    continuousDays.value = Number(info.sign_continuous_days || info.continuous_days || 0) || 0
    signed.value = !!info.is_sign
  } catch (e) { /* 原站接口未返回该字段时保持 0 */ }
})
</script>

<template>
  <div class="sign-page">
    <!-- 顶部深色区 @0,0 390x293 -->
    <div class="qd-bg">
      <div class="head-back" @click="back"></div>
      <div class="head-title">签到中心</div>

      <div class="line1">您已连续签到</div>
      <div class="days">
        <span class="days-num">{{ continuousDays }}</span>
        <span class="days-unit">天</span>
      </div>
      <div class="line3">签到享好礼,连续签到会获得更多好礼哦！</div>
    </div>

    <!-- 日历白卡 -->
    <div class="cal-card">
      <div class="cal-head">
        <div class="arrow arrow-l" @click="prevMonth">
          <svg viewBox="0 0 8 14" width="8" height="14" aria-hidden="true">
            <path d="M6.5 1 L1.5 7 L6.5 13" fill="none" stroke="#000" stroke-width="1.6"
                  stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <div class="cal-month">{{ monthText }}</div>
        <div class="arrow arrow-r" @click="nextMonth">
          <svg viewBox="0 0 8 14" width="8" height="14" aria-hidden="true">
            <path d="M1.5 1 L6.5 7 L1.5 13" fill="none" stroke="#000" stroke-width="1.6"
                  stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
      </div>

      <div class="cal-week">
        <div v-for="w in WEEK" :key="w" class="wk">{{ w }}</div>
      </div>

      <div class="cal-body">
        <div v-for="(row, ri) in rows" :key="ri" class="cal-row">
          <div
            v-for="(cell, ci) in row"
            :key="ci"
            class="cell"
            :class="{ today: cell.today }"
          >
            <template v-if="!cell.blank">
              <span class="cell-d">{{ cell.d }}</span>
              <span v-if="cell.today" class="cell-tag">today</span>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- 立即签到 @16,793 358x39 -->
    <div class="sign-btn" :class="{ done: signed }">{{ signed ? '已签到' : '立即签到' }}</div>
  </div>
</template>

<style scoped>
.sign-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  min-height: 844px;
  overflow: hidden;
  background: rgb(246, 246, 246);
  font-family: 'Times New Roman', serif;
}
.sign-page .qd-bg {
  box-sizing: border-box;
  padding-bottom: 41px;
}

/* ---- 顶部容器 @0,0 390x844（底色 rgb246，bg top-bg size 100% no-repeat 0 0）
       背景图 1792x1344 按 390 宽缩放 = 292.5 高，故深色区止于 y≈293 ---- */
.qd-bg {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: rgb(246, 246, 246) url('/static/image/sign/top-bg.png') no-repeat 0 0 / 100% auto;
}
.head-back {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
  background: url('/static/icon/back-btn-white.png') no-repeat 0 0 / 100% 100%;
}
.head-title {
  position: absolute;
  left: 165px;
  top: 16px;
  width: 60px;
  height: 16px;
  line-height: 16px;
  text-align: center;
  font-size: 15px;
  font-weight: 700;
  color: #fff;
}
/* 「您已连续签到」@0,88 390x24 居中 */
.line1 {
  position: absolute;
  left: 16px;
  top: 88px;
  width: 358px;
  height: 24px;
  line-height: 24px;
  text-align: left;
  font-size: 16px;
  color: #fff;
}
/* 「0 天」：0 @16,132 31px/600；天 @41.5,144 16px */
.days {
  position: absolute;
  left: 16px;
  top: 132px;
  height: 36px;
  display: flex;
  align-items: center;
}
.days-num {
  font-size: 31px;
  font-weight: 600;
  line-height: 35px;
  color: #fff;
}
.days-unit {
  margin-left: 10px;
  font-size: 16px;
  line-height: 17px;
  color: #fff;
}
/* 「签到享好礼...」@0,188 390x21 居中 */
.line3 {
  position: absolute;
  left: 16px;
  top: 188px;
  width: 358px;
  height: 21px;
  line-height: 21px;
  text-align: left;
  font-size: 14px;
  color: #fff;
}

/* ---- 日历白卡 @13,306 364x451 圆角 8px ---- */
.cal-card {
  position: absolute;
  left: 13px;
  top: 306px;
  width: 364px;
  height: 451px;
  background: #fff;
  border-radius: 8px;
  box-sizing: border-box;
  overflow: hidden;
}
/* 月份行：2026 / 10 @157.22,315 */
.cal-head {
  position: absolute;
  left: 0;
  top: 9px;
  width: 364px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cal-month {
  font-size: 20px;
  font-weight: 400;
  line-height: 22px;
  color: #000;
}
.arrow {
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
/* 原站实测：左 chevron @125,320 8x14；右 @231,320 8x14 */
.arrow-l {
  position: absolute;
  left: 112px;
  top: 14px;
}
.arrow-r {
  position: absolute;
  right: 112px;
  top: 14px;
}

/* 星期表头 y=364 */
.cal-week {
  position: absolute;
  left: 29.5px;
  top: 58px;
  width: 364px;
  display: grid;
  grid-template-columns: repeat(7, 52px);
}
.wk {
  width: 52px;
  box-sizing: border-box;
  text-align: center;
  font-size: 14px;
  line-height: 15px;
  color: #000;
}

/* 日期网格：原站格子 42x43，列起点 42.5 步进 52，行起点 431 步进 57 */
.cal-body {
  position: absolute;
  left: 29.5px;
  top: 125px;
  width: 364px;
}
.cal-row {
  display: grid;
  grid-template-columns: repeat(7, 52px);
  height: 57px;
}
.cell {
  position: relative;
  width: 52px;
  height: 43px;
  box-sizing: border-box;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cell-d {
  font-size: 14px;
  line-height: 15px;
  color: rgb(114, 112, 120);
}
/* 上月补位灰字 */
.cal-row:first-child .cell .cell-d {
  color: rgb(192, 192, 192);
}
/* 今天：黑底圆角块 + today 小字（原站实测块 @199,1085 41.5x42.5） */
.cell.today::before {
  content: '';
  position: absolute;
  left: 50%;
  top: 50%;
  width: 41.5px;
  height: 42.5px;
  margin-left: -20.75px;
  margin-top: -21.25px;
  background: rgb(47, 47, 47);
  border-radius: 10px;
}
.cell-d,
.cell-tag {
  position: relative;
  z-index: 1;
}
/* 「14」在块内上部：y≈1087~1095 */
.cell.today .cell-d {
  color: #fff;
  align-self: flex-start;
  margin-top: 1px;
}
/* 「today」y≈1096~1104，12px */
.cell-tag {
  position: absolute;
  left: 50%;
  top: 10px;
  margin-left: -13.35px;
  font-size: 12px;
  line-height: 14px;
  color: #fff;
}

/* ---- 立即签到 @16,793 358x39 ---- */
.sign-btn {
  position: absolute;
  left: 16px;
  top: 793px;
  width: 358px;
  height: 39px;
  line-height: 39px;
  text-align: center;
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  background: #000;
  border-radius: 23px;
  /* 原站实测：按钮上下有柔和投影（y≈780 灰 237 → y≈793 纯黑，向上下扩散约 12px） */
  box-shadow: 0 0 6px 1px rgba(0, 0, 0, 0.55), 0 0 16px 4px rgba(0, 0, 0, 0.38);
}
.sign-btn.done {
  background: rgb(246, 246, 246);
  color: rgb(192, 192, 192);
}
</style>
