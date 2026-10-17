<script setup>
/**
 * 寄售搜索 —— 1:1 复刻原站 pages/consignments/search
 *
 * 原站 390x844 实测：
 *   返回 back-btn.png            @13,10  24x24
 *   搜索框
 *     search.png                 @66.5,11 22x22
 *     占位「请输入搜索内容」      @93.5,15 245x14  14px rgb(144,147,153)
 *   「搜索」按钮                 @354.5,16.5 28x15  14px rgb(48,49,51)
 *
 * 注：原站该页仅导航栏可见（无历史记录、无结果列表）。
 */
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router = useRouter()
const kw = ref('')
</script>

<template>
  <div class="search-page">
    <div class="top-bar">
      <div class="flex-center left-wrap" @click="router.back()">
        <img class="back-img" src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="search-box">
        <div class="flex-center icon-wrap">
          <img class="search-icon" src="/static/icon/search.png" alt="搜索" />
        </div>
        <input
          class="search-input"
          type="text"
          placeholder="请输入搜索内容"
          :value="kw"
          @input="kw = $event.target.value"
        />
      </div>
      <div class="btn-wrap">
        <span class="search-btn">搜索</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.search-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fff;
  box-sizing: border-box;
  overflow: hidden;
}

.flex-center { display: flex; align-items: center; justify-content: center; }

.top-bar {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 44px;
}

.left-wrap {
  position: absolute;
  left: 13px;
  top: 10px;
  width: 24px;
  height: 24px;
}

.back-img {
  width: 24px;
  height: 24px;
  object-fit: fill;
  display: block;
}

/* 搜索框：原站占位文字 @93.5,15，图标 @66.5,11 22x22 */
.search-box {
  position: absolute;
  left: 56px;
  top: 8px;
  width: 285px;
  height: 28px;
  display: flex;
  align-items: center;
}

.icon-wrap {
  position: absolute;
  left: 10.5px;
  top: 3px;
  width: 22px;
  height: 22px;
}

.search-icon {
  width: 22px;
  height: 22px;
  object-fit: fill;
  display: block;
}

.search-input {
  position: absolute;
  left: 37.5px;
  top: 7px;
  width: 245px;
  height: 15px;
  font-size: 14px;
  line-height: 15px;
  color: #333;
  border: 0;
  background: transparent;
  outline: none;
}

.search-input::placeholder {
  color: rgb(144, 147, 153);
}

.btn-wrap {
  position: absolute;
  right: 7.5px;
  top: 12.5px;
  width: 28px;
  height: 19px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.search-btn {
  font-size: 14px;
  line-height: 15px;
  color: rgb(48, 49, 51);
  white-space: nowrap;
}
</style>
