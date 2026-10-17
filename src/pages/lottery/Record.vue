<script setup>
/**
 * 中奖记录 —— 1:1 复刻原站 pages/lottery/record
 * 由抽奖页右上角 cjjl-image.png @316,118 74x34 点击进入。
 *
 * 原站登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container.page-container.page-bg-colorfu @0,0 390x844
 *     background linear-gradient(136deg, rgb(69,69,67), rgb(20,17,13))
 *   导航标题「中奖记录」@165,16 60x16  15px/700 #fff
 *   返回 back-btn-white.png      @13,10 24x24
 *
 *   .card.bg-color-item ①       @16,60 358x119  bg rule-bg.png 100% 100%  r11  padding 20px
 *     19.png @95,86 50x13  |「中奖记录」@161,85 68x19 17px/700 #fff | 20.png @245,86 50x13
 *     「暂无中奖记录」@36,131 318x18  12px/400 #ababab
 *   .card.bg-color-item ②       @16,195 358x170 bg rule-bg.png 100% 100%  r11 padding 20px
 *     19.png @95,221 50x13 |「抽奖记录」@161,220 68x19 17px/700 #fff | 20.png @245,221 50x13
 *     .raffle_item              @36,256 318x89  r10  border 1px rgb(194,149,84)
 *       「抽奖结果」@53,276 56x15   14px/400 #fff
 *       「抽奖时间」@281,276 56x15  14px/400 #fff
 *       「暂无抽奖记录」@37,316 316x18  12px/400 #ababab
 *
 * 接口（原站登录态实测，均返回 200）：
 *   POST /lottery/record       -> 中奖记录列表
 *   POST /lottery/statistics   -> 抽奖记录列表
 * 当前账号两项均为空，故两处均渲染原站空态，不编造数据。
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { http } from '@/api/http'

const router = useRouter()

/* 中奖记录列表 */
const winList = ref([])
/* 抽奖记录列表 */
const drawList = ref([])

async function load() {
  try {
    const r = await http.post('/lottery/record', { page: 1, limit: 20 })
    const d = r.data
    winList.value = (d && (d.list || d.data || (Array.isArray(d) ? d : []))) || []
  } catch (e) { winList.value = [] }
  try {
    const r = await http.post('/lottery/statistics', { page: 1, limit: 20 })
    const d = r.data
    drawList.value = (d && (d.list || d.data || (Array.isArray(d) ? d : []))) || []
  } catch (e) { drawList.value = [] }
}

onMounted(load)
</script>

<template>
  <div class="record-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn-white.png" alt="返回" @click="router.back()" />
      <div class="nav-title">中奖记录</div>
    </div>

    <div class="cards">
      <!-- 卡片① 中奖记录 @16,60 358x119 -->
      <div class="card">
        <div class="card-head">
          <img class="arrow left" src="/static/image/lottery/19.png" alt="" />
          <div class="card-title">中奖记录</div>
          <img class="arrow right" src="/static/image/lottery/20.png" alt="" />
        </div>
        <div v-if="winList.length" class="list">
          <div v-for="(it, i) in winList" :key="i" class="list-item">{{ it.prize_name || it.name }}</div>
        </div>
        <!-- 原站空态 @36,131 318x18 -->
        <div v-else class="empty">暂无中奖记录</div>
      </div>

      <!-- 卡片② 抽奖记录 @16,195 358x170 -->
      <div class="card second">
        <div class="card-head">
          <img class="arrow left" src="/static/image/lottery/19.png" alt="" />
          <div class="card-title">抽奖记录</div>
          <img class="arrow right" src="/static/image/lottery/20.png" alt="" />
        </div>
        <!-- .raffle_item @36,256 318x89 -->
        <div class="raffle-item">
          <div class="raffle-head">
            <span class="col">抽奖结果</span>
            <span class="col">抽奖时间</span>
          </div>
          <div v-if="drawList.length" class="list">
            <div v-for="(it, i) in drawList" :key="i" class="list-item">
              <span class="col left">{{ it.prize_name || '未中奖' }}</span>
              <span class="col right">{{ it.create_time || it.time }}</span>
            </div>
          </div>
          <!-- 原站空态 @37,316 316x18 -->
          <div v-else class="empty">暂无抽奖记录</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* .container.page-container.page-bg-colorfu @0,0 390x844 */
.record-page {
  position: relative;
  min-height: 844px;
  background: linear-gradient(136deg, rgb(69, 69, 67), rgb(20, 17, 13));
  padding: 0 16px;
  box-sizing: border-box;
}

/* 导航：标题「中奖记录」@165,16 60x16 */
.navbar { position: relative; width: 358px; height: 60px; }
/* 返回 @13,10 24x24 —— 相对页面 padding-left 16px，溢出 3px */
.back { position: absolute; left: -3px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 0; top: 16px; width: 358px; height: 16px;
  line-height: 16px; text-align: center; color: #fff; font-size: 15px; font-weight: 700;
}

.cards { width: 358px; }

/* .card.bg-color-item @16,60 358x119 bg rule-bg.png r11 padding 20px margin-bottom 16px */
.card {
  position: relative;
  width: 358px;
  height: 119px;
  padding: 20px;
  box-sizing: border-box;
  background: url('/static/image/lottery/rule-bg.png') no-repeat center;
  background-size: 100% 100%;
  border-radius: 11px;
  margin-bottom: 16px;
}
/* 卡片② @16,195（60+119+16）358x170 */
.card.second { height: 170px; }

/* 卡片标题行（相对各卡片 padding box 原点）
   卡片①: 19.png@95,86 |标题@161,85 | 20.png@245,86  -> 相对(16,60) = 79,26 / 145,25 / 229,26
   卡片②: 19.png@95,221|标题@161,220| 20.png@245,221 -> 相对(16,195)= 79,26 / 145,25 / 229,26 */
.card-head { position: absolute; left: 0; top: 0; width: 358px; height: 50px; }
.arrow { position: absolute; top: 26px; width: 50px; height: 13px; }
.arrow.left { left: 79px; }
.arrow.right { left: 229px; height: 13px; }
.card-title {
  position: absolute; left: 145px; top: 25px; width: 68px; height: 19px;
  line-height: 19px; text-align: center; color: #fff; font-size: 17px; font-weight: 700;
}

/* 空态「暂无中奖记录」@36,131 318x18 12px/400 #ababab
   相对卡片①(16,60) = 20,71
   注：原站该元素为 .ptb-10（上下 padding 10），内部文字水平居中，
   故此处 text-align:center 对齐原站视觉。 */
.empty {
  position: absolute;
  left: 20px;
  top: 71px;
  width: 318px;
  height: 18px;
  line-height: 18px;
  text-align: center;
  color: #ababab;
  font-size: 12px;
  font-weight: 400;
}

/* .raffle_item @36,256 318x89 r10 border 1px rgb(194,149,84)
   相对卡片②(16,195) = 20,61 */
.raffle-item {
  position: absolute;
  left: 20px;
  top: 61px;
  width: 318px;
  height: 89px;
  box-sizing: border-box;
  border: 1px solid rgb(194, 149, 84);
  border-radius: 10px;
}
/* 表头「抽奖结果」@53,276 /「抽奖时间」@281,276
   相对 .raffle_item(36,256) = 17,20 */
.raffle-head {
  position: absolute; left: 0; top: 19px; width: 316px; height: 16px;
  display: flex; justify-content: space-between; padding: 0 16px; box-sizing: border-box;
}
.col { color: #fff; font-size: 14px; font-weight: 400; line-height: 16px; }

/* 「暂无抽奖记录」@37,316 316x18 —— 相对 .raffle_item(36,256) = 0,59 */
.raffle-item .empty { left: 0; top: 59px; width: 316px; }

.list { position: absolute; left: 0; top: 40px; width: 316px; }
.list-item {
  display: flex; justify-content: space-between; padding: 0 16px;
  height: 24px; line-height: 24px; color: #fff; font-size: 13px; box-sizing: border-box;
}
</style>