<script setup>
/**
 * 加入社群 —— 1:1 复刻原站 pages/my/join
 *
 * 路由：#/pages/my/join
 *
 * 原站 390x844 实测：
 *   .page-bg-colorfull.flex.flex-dir-col.flex-ai-center @0,0 390x844  bg #fff
 *     navbar 占位 @0,83 390x44（上方留 83px）
 *     .u-navbar--fixed  @0,0 390x44  bg #fff
 *       back-btn.png    @13,10 24x24
 *       标题「加入社群」  @165,16 60x16  15px
 *     .join-image       @90,127 210x204
 *       原站 background-image: none（图片未加载），实测为纯白不可见区域
 */
</script>

<template>
  <div class="my-join">
    <div class="top-gap"></div>
    <div class="navbar">
      <div class="nav-back" @click="$router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">加入社群</div>
    </div>

    <!-- 占位图 @90,127 210x204（原站未加载，纯白） -->
    <div class="join-image"></div>
  </div>
</template>

<style scoped>
.my-join {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
  color: rgb(74, 74, 74);
  font-size: 15px;
  display: flex;
  flex-direction: column;
  align-items: center;
}

/* 顶部留白 83px */
.top-gap {
  width: 390px;
  height: 83px;
  flex: none;
}

/* 导航占位 @0,83 390x44 */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  flex: none;
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
  top: 0;
  width: 390px;
  height: 44px;
  font-size: 15px;
  font-weight: 400;
  color: rgb(51, 51, 51);
  line-height: 44px;
  text-align: center;
}

/* 占位图 @90,127 210x204（原站 background-image: none，纯白不可见） */
.join-image {
  width: 210px;
  height: 204px;
  flex: none;
}
</style>