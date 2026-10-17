<script setup>
/**
 * 公告搜索 —— 1:1 复刻原站 pages/notice/search
 *
 * 路由：#/pages/notice/search
 *
 * 原站 390x844 实测：
 *   .container.page-container.diy.search @0,0 390x844  背景透明（继承 body 白）
 *     padding 44px 0 45px
 *     .diy-nav                    @0,0 390x44
 *       .nav.plr-13.plr-12         @0,5 390x34
 *         返回箭头（u-icon 图标字体）@13,12 20x20  rgb(34,34,34)
 *         .u-search                @58,5 319x34
 *           .u-search__content     @58,5 319x34  bg #fff  br 100px  padding 0 10px
 *             search3.png          @69,11 22x22
 *             输入框                @96,6 265x32  14px rgb(0,0,0)
 *               placeholder「请输入搜索内容」
 *     无输入时列表区为空（页面纯白）
 *
 * 说明：原站返回箭头为 iconfont（u-icon-arrow-left），
 *   本复刻用 CSS 边框绘制同尺寸左箭头，视觉等价。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()

const keyword = ref('')

const onSearch = () => {
  /* 原站在此调用 POST /index/afficheList {page:1,category:0,list_rows:25,search:keyword} */
}
</script>

<template>
  <div class="notice-search">
    <!-- 导航 @0,0 390x44 -->
    <div class="diy-nav">
      <div class="nav">
        <div class="nav-back" @click="router.back()"></div>
        <div class="search-box">
          <img class="search-icon" src="/static/icon/search3.png" alt="" />
          <input
            v-model="keyword"
            class="search-field"
            type="text"
            placeholder="请输入搜索内容"
            @keyup.enter="onSearch"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notice-search {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  padding: 44px 0 45px;
  background: #fff;
  box-sizing: border-box;
  overflow: hidden;
  color: rgb(74, 74, 74);
  font-size: 15px;
}

/* ---------- 导航 @0,0 390x44 ---------- */
.diy-nav {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 44px;
  display: flex;
  align-items: center;
}
.nav {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 390px;
  height: 34px;
  padding: 0 12px 0 13px;
  box-sizing: border-box;
}
/* 返回箭头 @13,12 20x20 —— CSS 绘制 */
.nav-back {
  position: absolute;
  left: 13px;
  top: 7px;
  width: 20px;
  height: 20px;
  cursor: pointer;
}
.nav-back::before {
  content: '';
  position: absolute;
  left: 7px;
  top: 4px;
  width: 9px;
  height: 9px;
  border-left: 2px solid rgb(34, 34, 34);
  border-bottom: 2px solid rgb(34, 34, 34);
  transform: rotate(45deg);
}

/* 搜索框 @58,5 319x34 */
.search-box {
  position: absolute;
  left: 58px;
  top: 0;
  display: flex;
  align-items: center;
  width: 319px;
  height: 34px;
  padding: 0 10px;
  background: #fff;
  border-radius: 100px;
  box-sizing: border-box;
}
.search-icon {
  display: block;
  width: 22px;
  height: 22px;
  flex: 0 0 22px;
}
.search-field {
  flex: 1;
  height: 32px;
  margin: 0;
  padding: 0 5px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  font-weight: 400;
  color: rgb(0, 0, 0);
  font-family: inherit;
}
.search-field::placeholder {
  color: rgb(153, 153, 153);
}
</style>