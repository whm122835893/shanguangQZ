<script setup>
/**
 * 工会排名 —— 1:1 复刻原站 pages/union/unionRank
 *
 * 原站实测：
 *   .container 390x844  bg icon/gh/rank-bg.png（750x1624）
 *   返回 back-btn-white.png @13,10 24x24
 *   标题「工会排名」@165,16 60x16  15px/700 rgb(255,255,255) ta:center
 *
 *   表头：
 *     「排名」@32,80 62x18  12px/500 rgb(255,255,255)
 *     「工会」@94,80 24x18  12px/500 rgb(255,255,255)
 *     问号 gh/q.png @346,83 12x12（原图48x48）
 *
 * 注：原站无工会数据（当前账号未加入工会），表头以下为空。
 *     背景图为深色，文字白色。
 */
import { useRouter } from 'vue-router'
import { showToast } from 'vant'

const router = useRouter()

function onQ() {
  showToast({ icon: 'none', message: '原站未提供工会排名规则说明' })
}
</script>

<template>
  <div class="unionrank-page">
    <!-- 导航：标题白字 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn-white.png" alt="返回" @click="router.back()" />
      <div class="nav-title">工会排名</div>
    </div>

    <!-- 表头：排名@32,80 62x18 / 工会@94,80 24x18 / 问号@346,83 -->
    <div class="thead">
      <span class="th th-rank">排名</span>
      <span class="th th-union">工会</span>
      <img class="th-q" src="/static/icon/gh/q.png" alt="规则说明" @click="onQ" />
    </div>
  </div>
</template>

<style scoped>
/* .container 390x844 bg gh/rank-bg.png */
.unionrank-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #1b1b28 url('/static/icon/gh/rank-bg.png') no-repeat center top;
  background-size: 100% auto;
  overflow: hidden;
}

/* 导航标题「工会排名」@165,16 60x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #fff; font-size: 15px; font-weight: 700;
}

/* 表头行 */
.thead {
  position: absolute;
  left: 32px;
  top: 80px;
  width: 326px;
  height: 18px;
}
/* 「排名」62x18  12px/500 #fff */
.th-rank {
  position: absolute;
  left: 0;
  width: 62px;
  height: 18px;
  line-height: 18px;
  color: #fff;
  font-size: 12px;
  font-weight: 500;
}
/* 「工会」@94,80 24x18（相对表头 left=62） */
.th-union {
  position: absolute;
  left: 62px;
  width: 40px;
  height: 18px;
  line-height: 18px;
  color: #fff;
  font-size: 12px;
  font-weight: 500;
}
/* 问号@346,83 12x12（相对表头 left=314, top=3） */
.th-q {
  position: absolute;
  left: 314px;
  top: 3px;
  width: 12px;
  height: 12px;
  cursor: pointer;
}
</style>