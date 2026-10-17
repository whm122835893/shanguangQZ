<script setup>
/**
 * 底部导航 —— 像素级复刻原站 u-tabbar
 * 实测基线（390x844 视口）：
 *   容器        390 x 62  bg #fff     fixed bottom
 *   三个 item   各 130 x 62
 *   图标        24 x 24   距顶 11px
 *   文案10px  距图标 2px  选中 #000 / 未选 #989faa
 *   探索 item 图标区带周年庆胶囊 47x13 bg #f23d4d radius 10px
 */
defineProps({ active: { type: String, default: 'index' } })

const tabs = [
  { key: 'index',  text: '首页', icon: '/static/tabBar/index-unactive.png', active: '/static/tabBar/index2.png',        path: '/pages/index/index' },
  { key: 'market', text: '探索', icon: '/static/tabBar/market1.png',  active: '/static/tabBar/market12.png', path: '/pages/market/market', ts: true },
  { key: 'my',     text: '我的', icon: '/static/tabBar/my.png',      active: '/static/tabBar/my-active.png', path: '/pages/my/my' }
]
</script>

<template>
  <nav class="u-tabbar">
    <div class="u-tabbar__content">
      <div
        v-for="t in tabs"
        :key="t.key"
        class="u-tabbar-item"
        :class="{ 'u-tabbar-item-ts': t.ts }"
        @click="$router.push(t.path)"
      >
        <div class="u-tabbar-item__icon">
          <img :src="active === t.key ? t.active : t.icon" :alt="t.text" />
        </div>
        <div class="u-tabbar-item__text" :class="{ 'is-active': active === t.key }">
          {{ t.text }}
        </div>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.u-tabbar__content {
  position: fixed;
  left: 50%;
  transform: translateX(-50%);
  bottom: 0;
  width: 100%;
  max-width: 750px;
  height: var(--gd-tabbar-h);
  background: var(--gd-bg-card);
  display: flex;
  z-index: 100;
}
.u-tabbar-item {
  flex: 1;
  height: var(--gd-tabbar-h);
  display: flex;
  flex-direction: column;
  align-items: center;
}
/* 实测：icon 容器 78x24（@26,792.5 相对 item），img 24x24 居中 */
.u-tabbar-item__icon {
  margin-top: 10.5px;
  width: 78px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.u-tabbar-item__icon img {
  width: 24px;
  height: 24px;
}
/* 探索 Tab 周年庆胶囊：实测 72x21 容器居中于 130 宽 item，bg #f23d4d r10，内部图 47x13 */
/* 实测：探索项 @159,794 72x21 bg #f23d4d r10，内部 img 47x13 @171.5,798 */
.u-tabbar-item-ts .u-tabbar-item__icon {
  width: 72px;
  height: 21px;
  background: var(--gd-primary);
  border-radius: var(--gd-r-ts);
  margin-top: 12px;
}
.u-tabbar-item-ts .u-tabbar-item__icon img {
  width: 47px;
  height: 13px;
}
.u-tabbar-item__text {
  margin-top: 6px;
  font-size: var(--gd-fs-10);
  line-height: 11px;
  color: var(--gd-text-grey);
}
.u-tabbar-item__text.is-active {
  color: #000;
}
</style>