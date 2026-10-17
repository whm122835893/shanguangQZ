<script setup>
/**
 * 公告中心 —— 100% 复刻原站 pages/notice/notice
 *
 * 原站 390x844 实测（唯一权威数据，来自 getBoundingClientRect + getComputedStyle）：
 *   .uni-page-head                       @0,0    390x44   bg #fff  pad 7px 3px
 *     .uni-page-head-btn(返回)           @5,6    27x32
 *     .uni-page-head__title              @70,7   250x30   16px /700 #000  「公告中心」
 *   .container.page-container            @0,44   390x1681 bg rgb(245,245,245)
 *     > 内层 view                        @0,44   390x1681 bg #fff  br 9px
 *       搜索区容器                       @0,44   390x38   pad 9px 0 0
 *         .flex.flex-js-sb.flex-ai-center.plr-16  @0,53 390x29  pad 0 16px
 *           .search-view                 @16,53  358x29   bg rgb(247,247,247) br14 pad-left14
 *             img 搜索图标               @30,62  11x11    /static/icon/search-theme.png
 *             「输入关键词」             @45,59.5 55x16   11px /500 rgb(212,212,212)
 *       .notice-list.pt-10               @0,82   390x1643 pad 10px 0 0
 *         .u-tabs.border-b               @0,92   390x32
 *           .u-tabs__wrapper__nav        @0,92   390x31
 *             item0「全部」              @0,92   56x31    pad 0 5px（item1~5 为 82x31）
 *             文字 uni-text              @5,95.5 46x24    13px /400 br13 pad 0.5px 10px
 *               span                     @15,100 26x15    选中 #000 / 未选 #999
 *             间隔：5 -> 61 -> 143 -> 225 -> 307 -> 389（每档 82）
 *         .mt-16.plr-16                  @0,140  390x1585 pad 0 16px 10px
 *           行 .bg-color-item.mb-12.flex.pb-14  @16,140 358x93  bg #fff  pad 0 0 14px
 *             img 缩略图                 @16,140 104x78（无圆角）
 *             .flex-1(右侧)              @130,140 244x78  （与图间距 10px）
 *               标题                     @130,140 244x42  14px /700 rgb(35,38,47) 两行截断
 *                 span                   @130,145 238x36
 *               底部行 .font-size-10.mt-12  @130,197 244x21
 *                 分类标签               @130,197 52x21   10px #fff bg #000 br4 pad 3px 6px
 *                 时间                   @289.28,202 84.72x11  10px rgb(122,122,122)
 *           行间距：93 + mb-12 = 105（140 / 245 / 350 ...）
 *
 * 接口：
 *   POST /index/affichetypelist  body {}          -> 分类 [{id,name}]
 *   POST /index/afficheList      body {page,category,list_rows,search}
 *        -> data.data[{id,image,title,createtime,type,url,affiche_type}]
 *        createtime 为秒级时间戳，按 UTC+8 格式化为 YYYY-MM-DD HH:mm:ss
 */
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { getAfficheTypeList, getAfficheList } from '@/api'
import { oss } from '@/api/http'

const router = useRouter()

const categories = ref([{ id: 0, name: '全部' }])
const activeId = ref(0)
const list = ref([])
const keyword = ref('')
const loading = ref(false)

/* 原站：category 0 表示全部，其余为分类 id */
const activeCategory = computed(() => activeId.value)

function back() {
  if (history.length > 1) router.back()
  else router.replace('/pages/index/index')
}

/** 秒级时间戳 -> YYYY-MM-DD HH:mm:ss（原站展示为 UTC+8） */
function fmtTime(ts) {
  const n = Number(ts)
  if (!n) return ''
  const d = new Date((n < 1e12 ? n * 1000 : n) + 8 * 3600 * 1000)
  const p = (v) => String(v).padStart(2, '0')
  return `${d.getUTCFullYear()}-${p(d.getUTCMonth() + 1)}-${p(d.getUTCDate())} ` +
    `${p(d.getUTCHours())}:${p(d.getUTCMinutes())}:${p(d.getUTCSeconds())}`
}

async function loadList() {
  loading.value = true
  try {
    const res = await getAfficheList({
      page: 1,
      category: activeCategory.value,
      list_rows: 25,
      search: keyword.value || ''
    })
    const d = (res && res.data) || {}
    list.value = Array.isArray(d.data) ? d.data : []
  } catch (e) {
    list.value = []
  } finally {
    loading.value = false
  }
}

async function loadCategories() {
  try {
    const res = await getAfficheTypeList()
    const arr = (res && res.data) || []
    categories.value = [{ id: 0, name: '全部' }].concat(
      arr.map((x) => ({ id: x.id, name: x.name }))
    )
  } catch (e) {
    categories.value = [{ id: 0, name: '全部' }]
  }
}

function onTab(id) {
  if (activeId.value === id) return
  activeId.value = id
  loadList()
}

function onSearch() {
  loadList()
}

onMounted(async () => {
  await loadCategories()
  await loadList()
})
</script>

<template>
  <div class="notice-page">
    <!-- 原生导航栏 @0,0 390x44 -->
    <div class="page-head">
      <img class="head-back" src="/static/icon/back-btn.png" alt="返回" @click="back" />
      <div class="head-title">公告中心</div>
    </div>

    <div class="page-body">
      <!-- 搜索区 @0,44 390x38 -->
      <div class="search-wrap">
        <div class="search-row">
          <div class="search-view">
            <img class="search-icon" src="/static/icon/search-theme.png" alt="搜索" />
            <!-- 占位提示：原站 uni-text @45,59.5 55x16 > span @45,63.5 55x12 -->
            <span v-if="!keyword" class="search-ph"><span class="search-ph-in">输入关键词</span></span>
            <input
              v-model="keyword"
              class="search-input"
              @keyup.enter="onSearch"
            />
          </div>
        </div>
      </div>

      <!-- 列表区 @0,82 -->
      <div class="notice-list">
        <!-- 分类 tabs @0,92 390x32 -->
        <div class="tabs-nav">
          <div
            v-for="(c, i) in categories"
            :key="c.id"
            class="tab-item"
            :class="{ 'is-active': activeId === c.id, first: i === 0 }"
            @click="onTab(c.id)"
          >
            <span class="tab-text"><span class="tab-text-in">{{ c.name }}</span></span>
          </div>
        </div>

        <!-- 列表 @0,140 -->
        <div class="list-wrap">
          <div v-for="item in list" :key="item.id" class="notice-row">
            <img class="row-img" :src="oss(item.image)" :alt="item.title" />
            <div class="row-main">
              <div class="row-title">{{ item.title }}</div>
              <div class="row-meta">
                <span class="row-cat">{{ item.affiche_type }}</span>
                <span class="row-time">{{ fmtTime(item.createtime) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notice-page {
  min-height: 100vh;
  background: rgb(245, 245, 245);
}

/* ---- 原生导航栏 390x44 ---- */
.page-head {
  position: relative;
  height: 44px;
  padding: 7px 3px;
  box-sizing: border-box;
  background: #fff;
}
.head-back {
  position: absolute;
  top: 6px;
  left: 5px;
  width: 27px;
  height: 27px;
}
.head-title {
  position: absolute;
  top: 7px;
  left: 70px;
  width: 250px;
  height: 30px;
  line-height: 30px;
  text-align: center;
  font-size: 16px;
  font-weight: 700;
  color: #000;
}

/* ---- 内容区 @0,44 bg #fff br9 ---- */
.page-body {
  background: #fff;
  border-radius: 9px 9px 0 0;
  min-height: calc(100vh - 44px);
}

/* 搜索区 @0,44 390x38 pad 9px 0 0 */
.search-wrap {
  padding: 9px 0 0;
  height: 38px;
}
.search-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 29px;
  padding: 0 16px;
}
.search-view {
  position: relative;
  display: flex;
  align-items: center;
  flex: 1;
  width: 358px;
  height: 29px;
  padding-left: 14px;
  box-sizing: border-box;
  background: rgb(247, 247, 247);
  border-radius: 14px;
}
.search-icon {
  width: 11px;
  height: 11px;
  margin-right: 4px;
  flex-shrink: 0;
}
/* 占位提示：@45,59.5 55x16（外层 16 高居中于 29 高搜索框） */
.search-ph {
  display: inline-flex;
  align-items: center;
  height: 16px;
  font-size: 11px;
  font-weight: 500;
  line-height: 12px;
  color: rgb(212, 212, 212);
  pointer-events: none;
}
/* 真实输入框：绝对覆盖整个搜索区，保证可点击输入 */
.search-input {
  position: absolute;
  left: 14px;
  top: 0;
  width: calc(100% - 14px);
  height: 29px;
  font-size: 11px;
  font-weight: 500;
  color: rgb(35, 38, 47);
  background: transparent;
  border: none;
  outline: none;
  padding: 0;
  font-family: inherit;
}

/* 列表区 @0,82 pad 10px 0 0 */
.notice-list {
  padding: 10px 0 0;
}

/* ---- 分类 tabs @0,92 390x31 ---- */
/* 原站 .u-tabs.border-b @0,92 390x32（含 1px 底边），内部 nav 31px */
.tabs-nav {
  display: flex;
  height: 32px;
  align-items: center;
  box-sizing: border-box;
  border-bottom: 1px solid rgb(245, 245, 245);
}
.tab-item {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 82px;
  height: 31px;
  padding: 0 5px;
  box-sizing: border-box;
  flex-shrink: 0;
}
/* 「全部」两字，原站 item0 宽 56（其余 82） */
.tab-item.first {
  width: 56px;
}
/* 外层 uni-text @5,95.5 46x24（13px 胶囊，padding 0.5px 10px）；内层 span @15,100 26x15 */
.tab-text {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 24px;
  font-size: 13px;
  font-weight: 400;
  color: rgb(153, 153, 153);
  padding: 0 10px;
  border-radius: 12px;
}
.tab-text-in {
  font-size: 13px;
  line-height: 15px;
}
.tab-item.is-active .tab-text {
  color: #000;
}

/* ---- 列表 @0,140 pad 0 16px 10px ---- */
.list-wrap {
  margin-top: 16px;
  padding: 0 16px 10px;
}

/* 行 @16 358x93 pad 0 0 14px mb12 */
.notice-row {
  display: flex;
  width: 358px;
  height: 93px;
  margin-bottom: 12px;
  padding: 0 0 14px;
  box-sizing: border-box;
  background: #fff;
}
/* 原站实测：uni-image.br-4 容器圆角 4px，内部 img object-fit: fill（拉伸填满，非裁切） */
.row-img {
  width: 104px;
  height: 78px;
  flex-shrink: 0;
  object-fit: fill;
  border-radius: 4px;
}
.row-main {
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  width: 244px;
  height: 78px;
  margin-left: 10px;
  box-sizing: border-box;
}
.row-title {
  font-size: 14px;
  font-weight: 700;
  line-height: 18px;
  color: rgb(35, 38, 47);
  height: 42px;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}
.row-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 21px;
  margin-top: 12px;
  font-size: 10px;
}
.row-cat {
  display: inline-flex;
  align-items: center;
  padding: 3px 6px;
  border-radius: 4px;
  background: #000;
  color: #fff;
  font-size: 10px;
  line-height: 15px;
}
.row-time {
  font-size: 10px;
  color: rgb(122, 122, 122);
  line-height: 11px;
}
</style>
