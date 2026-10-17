<script setup>
/**
 * 大师动态 —— 1:1 复刻原站 pages/rank/masterRank
 *
 * 路由：#/pages/rank/masterRank
 *
 * 原站 390x844 实测：
 *   .container.page-container.ptb-12 @0,0 390x844  bg rgb(26,16,2)  padding 12px 0
 *     .u-navbar（占位）           @0,12 390x44
 *       .u-navbar--fixed（透明）  @0,0 390x44
 *         返回 back-btn-white.png @13,10 24x24
 *         标题「大师动态」        文字 @165,16  15px/700 #fff
 *     内容区为空（纯黑底 rgb(26,16,2)）
 *
 * 说明：底部原站常驻「光子荣誉榜」入口（y>1000 被采集过滤），
 *   本页当前为纯色空态，不渲染该入口。
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="master-rank">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn-white.png" alt="返回" />
      </div>
      <div class="nav-title">大师动态</div>
    </div>
  </div>
</template>

<style scoped>
.master-rank {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  padding: 12px 0;
  background: rgb(26, 16, 2);
  box-sizing: border-box;
  overflow: hidden;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* ---------- 导航 @0,0 390x44（透明，占位下移 12px） ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  margin-top: 12px;
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
  color: #fff;
  line-height: 16px;
  text-align: center;
}
</style>