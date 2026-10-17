<script setup>
/**
 * 批量合成记录 —— 1:1 复刻原站 pages/compound/btchrecord
 *
 * 路由：#/pages/compound/btchrecord
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg-colorfull.plr-16.pb-16
 *                                  @0,0 390x844  bg rgb(245,245,245)  padding 0 16px 16
 *     .u-navbar（容器内）          @16,0 358x44
 *       .u-navbar--fixed          @0,0 390x44  bg #fff
 *         返回 back-btn.png       @13,10 24x24
 *         标题「活动记录」        @165,16 60x16  15px/700 rgb(51,51,51)
 *     空态容器                    @16,148 358x100
 *       search-empty.png          @134.5,148 121x100
 *
 * 与 pages/compound/record 结构完全一致（同为「活动记录」空态页），
 * 差异仅在路由与业务语义（批量 vs 单个合成记录）。
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="btch-record">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">活动记录</div>
    </div>

    <!-- 空态 @16,148 358x100 -->
    <div class="empty">
      <img src="/static/empty/search-empty.png" alt="暂无记录" />
    </div>
  </div>
</template>

<style scoped>
.btch-record {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  padding: 0 16px 16px;
  background: rgb(245, 245, 245);
  box-sizing: border-box;
  overflow: hidden;
}

/* ---------- 导航 @0,0 390x44（白底通栏） ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  margin: 0 -16px;
  background: #fff;
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

/* ---------- 空态 @16,148 358x100 ---------- */
.empty {
  position: absolute;
  left: 16px;
  top: 148px;
  width: 358px;
  height: 100px;
  display: flex;
  align-items: flex-start;
  justify-content: center;
}
.empty img {
  display: block;
  width: 121px;
  height: 100px;
}
</style>