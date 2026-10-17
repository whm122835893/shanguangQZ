<script setup>
/**
 * 榜单列表 —— 1:1 复刻原站 pages/rank/rankList
 *
 * 原站实测：
 *   .rank @0,0 390x842  bg rgb(233,241,252) + bg/dragonTigerList1.png 背景
 *   返回 back-btn-white.png @13,10 24x24
 *   标题栏无标题文字（仅返回按钮）
 *
 *   榜单卡片 .mlr-16.pt-4.rank-list.br-8 @16,194 358x390 圆角8
 *     背景 bg/rank-list-bg.png（原图686x681）
 *     标题「榜单每3分钟刷新」@131,239 128x32  10px/700 rgb(149,113,81) ta:center
 *     表头「排名」@16,289 94x15  10px/400 rgb(255,255,255) ta:center
 *          「用户」@110,289 131x15  10px/400 rgb(255,255,255) ta:center
 *     问号图标 q.png @330.5,289.5 14x14（原图20x20）
 *   空态 search-empty.png @134.5,327 121x100
 *
 * 注：原站榜单无数据（当前账号无榜单记录），故为空态。
 *     表头文字是白色 —— 卡片背景图本身带深色，表头文字在深色条上。
 */
import { useRouter } from 'vue-router'
import { showToast } from 'vant'

const router = useRouter()

function onQ() {
  showToast({ icon: 'none', message: '原站未提供榜单规则说明' })
}
</script>

<template>
  <div class="rank-page">
    <!-- 导航：仅返回按钮，无标题 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn-white.png" alt="返回" @click="router.back()" />
    </div>

    <!-- 榜单卡片@16,194 358x390 圆角8 -->
    <div class="rank-card">
      <!-- 标题「榜单每3分钟刷新」@131,239 128x32 -->
      <div class="card-title">榜单每3分钟刷新</div>

      <!-- 表头：排名@16,289 94x15 / 用户@110,289 131x15 / 问号@330.5,289.5 -->
      <div class="thead">
        <span class="th th-rank">排名</span>
        <span class="th th-user">用户</span>
        <img class="th-q" src="/static/icon/q.png" alt="规则说明" @click="onQ" />
      </div>

      <!-- 空态@134.5,327 121x100 -->
      <img class="empty-img" src="/static/empty/search-empty.png" alt="暂无榜单数据" />
    </div>
  </div>
</template>

<style scoped>
/* .rank 390x842 bg rgb(233,241,252) + dragonTigerList1 背景 */
.rank-page {
  position: relative;
  width: 390px;
  min-height: 842px;
  background: rgb(233, 241, 252) url('/static/bg/dragonTigerList1.png') no-repeat center top;
  background-size: 100% auto;
  overflow: hidden;
}

/* 导航：仅返回按钮 */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }

/* 榜单卡片@16,194 358x390 圆角8 + rank-list-bg 背景 */
.rank-card {
  position: absolute;
  left: 16px;
  top: 194px;
  width: 358px;
  height: 390px;
  border-radius: 8px;
  background: url('/static/bg/rank-list-bg.png') no-repeat center;
  background-size: 100% 100%;
}

/* 标题「榜单每3分钟刷新」@131,239 128x32  10px/700 rgb(149,113,81) */
.card-title {
  position: absolute;
  left: 95px;
  top: 45px;             /* 194+45=239 */
  width: 168px;
  height: 32px;
  line-height: 32px;
  text-align: center;
  color: rgb(149, 113, 81);
  font-size: 10px;
  font-weight: 700;
}

/* 表头行@16,289（相对卡片 top=95） */
.thead {
  position: absolute;
  left: 0;
  top: 95px;             /* 194+95=289 */
  width: 358px;
  height: 15px;
}
/* 「排名」@16,289 94x15 10px/400 #fff ta:center */
.th-rank {
  position: absolute;
  left: 0;
  width: 94px;
  height: 15px;
  line-height: 15px;
  text-align: center;
  color: #fff;
  font-size: 10px;
  font-weight: 400;
}
/* 「用户」@110,289 131x15 */
.th-user {
  position: absolute;
  left: 94px;            /* 16+94=110 */
  width: 131px;
  height: 15px;
  line-height: 15px;
  text-align: center;
  color: #fff;
  font-size: 10px;
  font-weight: 400;
}
/* 问号图标@330.5,289.5 14x14 */
.th-q {
  position: absolute;
  left: 314.5px;         /* 16+314.5=330.5 */
  top: 0.5px;
  width: 14px;
  height: 14px;
  cursor: pointer;
}

/* 空态@134.5,327 121x100（相对卡片 top=133） */
.empty-img {
  position: absolute;
  left: 118.5px;         /* 16+118.5=134.5 */
  top: 133px;            /* 194+133=327 */
  width: 121px;
  height: 100px;
}
</style>