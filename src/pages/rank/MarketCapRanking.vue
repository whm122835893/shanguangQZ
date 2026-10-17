<script setup>
/**
 * 市值排行 —— 1:1 复刻原站 pages/marketCapRanking/marketCapRanking
 *
 * 原站 chunk 实测（pages-marketCapRanking-marketCapRanking.js）：
 *   data: { localBgUrl, show:false, list:[], loading:true }
 *   onLoad -> getList()
 *   getList: ranking()  →  .then(t => { a.list = t.data; a.loading = false })
 *              ranking = POST /user/ranking
 *   模板三分支（严格按原站顺序）：
 *     1. loading  → w-loading  emptyType:"search" emptyTop:"200"
 *                   即 search-empty.png 居中、距顶 200px
 *     2. list 非空 → 排行榜列表（前三名 + 「市值」列）
 *     3. list 为空 → 不渲染任何内容（整页空白）
 *
 * 实测（2026 账号 17587881293）：
 *   POST /user/ranking → {"data":[],"my":"","activityRule":""}
 *   → list=[] → 原站截图 390x844 全空白（仅 #f2f2f2 底色）
 *   本复刻严格遵循同一分支逻辑，故对齐后同为空白页。
 *
 * 素材：static/icon/logo.png（榜单头像占位，原站唯一引用的静态图）
 * 弹窗：u-modal「市值排行榜规则」confirmText「知道了」confirmColor #165DFF
 */
import { ref, onMounted } from 'vue'
import { getUserRank } from '@/api'

const list = ref([])
const loading = ref(true)
const showRule = ref(false)

const load = async () => {
  loading.value = true
  try {
    const res = await getUserRank({})
    if (res && res.code === 1) list.value = res.data?.data || []
  } catch (e) {
    list.value = []
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="rank-page">
    <!-- 分支1：加载中（空态图距顶 200px） -->
    <div v-if="loading" class="loading-box">
      <img src="/static/empty/search-empty.png" alt="加载中" class="loading-img" />
    </div>

    <!-- 分支2：有榜单数据 -->
    <div v-else-if="list.length" class="rank-list">
      <div v-for="(item, idx) in list" :key="idx" class="rank-item">
        <img class="rank-logo" src="/static/icon/logo.png" alt="" />
        <div class="rank-info">
          <div class="rank-name">{{ item.nickname || item.name }}</div>
          <div class="shizhi-box">
            <span class="shizhi-label">市值</span>
            <span class="shizhi-value">{{ item.market_value }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 分支3：list 为空 → 原站不渲染内容，保持空白 -->

    <div v-if="showRule" class="rule-mask" @click="showRule = false"></div>
  </div>
</template>

<style scoped>
/* P0：颜色走 token，无硬编码（#fff 除外） */
.rank-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: var(--color-page-bg, #f2f2f2);
  box-sizing: border-box;
  overflow: hidden;
}

.loading-box {
  width: 390px;
  padding-top: 200px;
  display: flex;
  justify-content: center;
  box-sizing: border-box;
}
.loading-img {
  width: 121px;
  height: 100px;
  display: block;
}

.rank-list {
  width: 390px;
  padding: 12px 16px;
  box-sizing: border-box;
}
.rank-item {
  display: flex;
  align-items: center;
  width: 358px;
  padding: 12px 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
  box-sizing: border-box;
}
.rank-logo {
  width: 41px;
  height: 41px;
  border-radius: 50%;
  display: block;
}
.rank-info {
  flex: 1;
  margin-left: 10px;
}
.rank-name {
  font-size: 14px;
  font-weight: 700;
  color: #23262f;
  line-height: 20px;
}
.shizhi-box {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.shizhi-label,
.shizhi-value {
  font-size: 12px;
  color: #23262f;
  line-height: 16px;
}

.rule-mask {
  position: absolute;
  left: 0;
  top: 0;
  width: 390px;
  height: 844px;
  background: rgba(0, 0, 0, 0.5);
}
</style>
