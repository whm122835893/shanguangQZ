<script setup>
/**
 * 我的页 —— 像素级复刻原站 pages/my/my
 *
 * 原站 390x844 实测（getBoundingClientRect + getComputedStyle，唯一权威）：
 *   根容器 .container.page-container.plr-16.pb-20  bg #fff
 *   标题「个人中心」   @16,0   358×34   16px #23262f /600  mb16
 *     设置图标 setting.png  @354,12  20×20
 *   .my-info-bg       @16,65  358×92   （无背景图）
 *     .info-area-avatar @16,65 69×92
 *       头像 logo.png    @18,88  66×66   圆形 r50%
 *     右侧区 ml-10 ptb-7 @95,65 279×92
 *       昵称 GqdusoI30    @95,72  79.1×21  15px #23262f /700
 *       未实名标签        @184.1,73 44×19  bg #ececec r46px；文字 10px #666 /500 @191.1,75 30×15
 *       区块链地址行      @95,101 184.7×15  10px #23262f
 *         「区块链地址：」 @95,101 60×15
 *         地址值          @155,101 108.7×15  text-center
 *         复制图标 copy-white.png @267.7,102.5 12×12
 *       钱包按钮          @301,94.5 73×33  bg #000 r16px  12px #fff /600
 *   功能宫格卡 .bg-color-item.br-12  @16,167 358×144  bg #fff r12px
 *     内层 flex flex-w  两行，每行 4 个
 *     每格 89.5×52，列起点 16 / 105.5 / 195 / 284.5（flex-w 均分）
 *       图标 29×29   行1 @46.3 / 135.8 / 225.3 / 314.8   y=167
 *       图标   行2 同列                              y=239
 *       文案 12px #23262f /400  行1 @y=202  行2 @y=274  mt6
 *   产品卡 .bg-color-item.br-12  @16,321 358×198
 *     u-tabs  @16,321 358×20
 *       我的产品 @16,321 62×20  bg #f23d4d  10px #fff /700  r10px
 *       幸运盒   @78,321 62×20  10px #1a1512 /500  h15（无背景）
 *     空态 @16,399 358×100，search-empty.png @134.5,399 121×100
 */
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { getMyCollection, getOrderList } from '@/api'
import { oss as apiOss } from '@/api/http'

const router = useRouter()
const user = useUserStore()

/* OSS 图片统一走本项目本地代理（oss 工具函数，图片来源仍为原站下载） */
const oss = (p) => (p ? apiOss(p) : '/static/logo.png')

/* 功能宫格：图标与文案顺序与原站一致（图标均为原站 /static/icon/*.png）
   跳转目标为原站登录态逐个点击实测结果（/tmp/grids.py），逐条对齐：
     我的订单 -> /pages/order/list
     幸运抽奖 -> /pages/lottery/lottery?id=1
     产品合成 -> /pages/compound/compound?thm=2
     合成记录 -> /pages/compound/record
     幸运抽签 -> /pages/activity/draw?thm=3
     邀请好友 -> /pages/invitation/invitation
     实名认证 -> /pages/certification/certification
     转售     -> /pages/regift/record */
const grids = [
  { key: 'order',     icon: 'my-order.png',   text: '我的订单', path: '/pages/order/list' },
  { key: 'lottery',   icon: 'my-lottery.png', text: '幸运抽奖', path: '/pages/lottery/lottery?id=1' },
  { key: 'synthesis', icon: 'my-record.png',  text: '产品合成', path: '/pages/compound/compound?thm=2' },
  { key: 'record',    icon: 'my-ys.png',      text: '合成记录', path: '/pages/compound/record' },
  { key: 'draw',      icon: 'my-draw.png',    text: '幸运抽签', path: '/pages/activity/draw?thm=3' },
  { key: 'invite',    icon: 'my-share.png',   text: '邀请好友', path: '/pages/invitation/invitation' },
  { key: 'certi',     icon: 'my-certi.png',   text: '实名认证', path: '/pages/certification/certification' },
  { key: 'resale',    icon: 'gift.png',       text: '转售',     path: '/pages/regift/record' }
]

/* 产品 Tab：我的产品 / 幸运盒 */
const tabs = ['我的产品', '幸运盒']
const tabIdx = ref(0)

const list = ref([])

/**
 * 藏品详情页要的是 user_collection_id（如 121926），
 * 但列表接口 POST /user_collection/user_collection/antMycollection 返回的记录
 * **只有 collection_id（如 8），没有 uc_ids / user_collection_id**。
 * 因此这里复用「订单接口映射」把 collection_id 翻成详情页要的 id：
 * POST /order/order/orderList 的 goods_id 即 collection_id，uc_ids 为对应的 user_collection_id 列表。
 * 映射不到时不跳（否则进详情页拿到空数据，表现为「点了没反应」）。
 */
const ucIdMap = ref({})

async function loadUcIdMap() {
  const map = {}
  try {
    for (let page = 1; page <= 5; page++) {
      const r = await getOrderList({ page, status: '' })
      const d = r && r.data
      const orders = (d && (d.data || d.list)) || []
      if (!orders.length) break
      for (const o of orders) {
        const gid = o.goods_id
        if (gid === undefined || gid === null) continue
        String(o.uc_ids || '')
          .split(',')
          .map((s) => s.trim())
          .filter(Boolean)
          .forEach((v) => {
            if (!map[gid]) map[gid] = v
          })
      }
      const last = d && d.last_page
      if (last && page >= last) break
    }
  } catch (e) {
    /* 映射拉取失败不阻塞列表展示 */
  }
  ucIdMap.value = map
}

async function loadList() {
  try {
    /* 原站实测：我的产品 tab 走 types=2（藏品），幸运盒走 types=1（盲盒） */
    const types = tabIdx.value === 0 ? 2 : 1
    const res = await getMyCollection({ page: 1, per_page: 10, types })
    const d = res.data
    const arr = Array.isArray(d) ? d : (d && d.data) || []
    list.value = tabIdx.value === 0 ? arr : []
  } catch (e) {
    list.value = []
  }
}

/* ---------- 产品卡字段映射（原站 chunk 实测） ---------- */
const coverOf = (it) => {
  const c = it.collection || it.product || it
  const raw = c.listimg || c.main_image || c.goods_cover_image || ''
  return raw ? oss(raw) : ''
}
const nameOf = (it) => {
  const c = it.collection || it.product || it
  return c.name || c.goods_name || ''
}
/* 持有数：num 优先，其次 number */
const numOf = (it) => (it.num !== undefined ? it.num : it.number)
/* 稀有度标签：原站右上角浮层，非闪光藏品显示 rarity 文本 */
const rarityOf = (it) => {
  const c = it.collection || it.product || it
  const r = c.rarity_name || c.rarity || ''
  if (r === '闪光' || r === '') return ''
  return r
}
const goDetail = (it) => {
  /* 优先用订单映射表翻译出的 user_collection_id，缺省回落到记录自带字段 */
  const id =
    ucIdMap.value[it.collection_id] || it.user_collection_id || it.uc_ids || it.id
  if (!id) return
  router.push('/pages/myCollection/collectionDetails?id=' + id)
}

function switchTab(i) {
  tabIdx.value = i
  loadList()
}

/* 昵称 / 实名状态 / 区块链地址 */
const nickname = () => user.userInfo?.nickname || user.userInfo?.nick || ''
const isAuth = () => Number(user.userInfo?.real_name_authentication) === 1
const authText = () => (isAuth() ? '已实名' : '未实名')
const nftAddr = () => user.userInfo?.nft_addr || user.userInfo?.address || ''
/* 头像：原站默认渲染 /static/icon/logo.png（闪光标识默认头像） */
const avatar = () => '/static/logo.png'

function copyAddr() {
  const a = nftAddr()
  if (!a) return
  if (navigator.clipboard) navigator.clipboard.writeText(a)
}

onMounted(() => {
  user.fetchUserInfo()
  loadList()
  loadUcIdMap()
})
</script>

<template>
  <div class="container page-container my-page plr-16">
    <!-- 标题栏 -->
    <div class="my-title font-color-dark font-size-16 font-w-600">
      个人中心
      <img class="setting" src="/static/setting.png" alt="设置" @click="router.push('/pages/my/info')" />
    </div>

    <!-- 用户信息区 -->
    <div class="my-info-bg">
      <div class="info-area flex">
        <div class="info-area-avatar">
          <span class="avatar-decor"></span>
          <img class="avatar" :src="avatar()" alt="头像" />
        </div>

        <div class="info-right ml-10 flex-1 flex flex-js-sb flex-ai-center">
          <div class="flex flex-dir-col info-col">
            <div class="username flex flex-ai-center">
              <span class="nickname">{{ nickname() }}</span>
              <div v-if="!isAuth()" class="auth-tags ml-4 no-auth">
                <span class="font-size-10">{{ authText() }}</span>
              </div>
            </div>

            <div class="nft-addr flex flex-ai-center font-size-10">
              <span class="font-color-dark">区块链地址：</span>
              <span class="addr-val text-center text-overflow">{{
                nftAddr() || '**拥有藏品后自动生成**'
              }}</span>
              <span class="copy flex-center ml-4" @click="copyAddr">
                <img src="/static/copy-white.png" alt="复制" />
              </span>
            </div>
          </div>

          <div class="wallet-btn flex flex-center" @click="router.push('/pages/wallet/wallet')">钱包</div>
        </div>
      </div>
    </div>

    <!-- 功能宫格 -->
    <div class="bg-color-item br-12 grid-card">
      <div class="flex flex-w">
        <div
          v-for="g in grids"
          :key="g.key"
          class="grid-cell flex flex-dir-col flex-center"
          @click="router.push(g.path)"
        >
          <div class="flex-center grid-icon">
            <img :src="'/static/' + g.icon" :alt="g.text" />
          </div>
          <div class="grid-text font-color-dark font-size-12">{{ g.text }}</div>
        </div>
      </div>
    </div>

    <!-- 我的产品 / 幸运盒 -->
    <div class="bg-color-item br-12 product-card">
      <div class="prod-tabs">
        <div
          v-for="(t, i) in tabs"
          :key="t"
          class="prod-tab"
          :class="{ 'is-active': tabIdx === i }"
          @click="switchTab(i)"
        >
          {{ t }}
        </div>
      </div>

      <div class="prod-body">
        <div v-if="list.length" class="prod-list">
          <div
            v-for="it in list"
            :key="it.collection_id || it.id"
            class="item-con"
            @click="goDetail(it)"
          >
            <div class="img-con">
              <img v-if="coverOf(it)" :src="coverOf(it)" alt="" class="prod-cover" />
              <!-- 原站右上角收藏角标 @157,370 22x22：is_collection!=0 显示红心 -->
              <img
                v-if="Number(it.is_collection) !== 0"
                src="/static/icon/has-like.png"
                alt=""
                class="corner-like"
              />
            </div>
            <div class="prod-info">
              <div class="prod-name">{{ nameOf(it) }}</div>
              <div class="prod-sub">
                <span v-if="rarityOf(it)" class="tag-rarity">{{ rarityOf(it) }}</span>
                <span v-else class="tag-flash">闪光</span>
                <span class="tag-hold">持有{{ numOf(it) }}</span>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="prod-empty">
          <img src="/static/search-empty.png" alt="暂无产品" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.my-page {
  background: #fff;
  min-height: 100vh;
  padding-bottom: 20px;
}

/* ---- 标题栏 @16,0 358x34，mb16；设置图标 @354,12 20x20 ---- */
.my-title {
  position: relative;
  height: 34px;
  display: flex;
  align-items: center;
  font-size: 16px;
  font-weight: 600;
  color: #23262f;
  margin-bottom: 16px;
}
.setting {
  position: absolute;
  right: 0;
  top: 12px;
  width: 20px;
  height: 20px;
}

/* ---- 用户信息区 @16,65 358x92（实测 margin-top 31px） ---- */
.my-info-bg { height: 92px; margin-top: 31px; }
.info-area { height: 92px; }

.info-area-avatar {
  position: relative; /* 原站实测 position:relative，作头像绝对定位基准 */
  width: 69px;
  height: 92px;
  box-sizing: border-box;
}
/* 头像实测 @18,88 66x66（左侧留 2px）
   圆形来自 u-avatar 组件（u-avatar__image--circle，border-radius 50% + overflow hidden）
   红色圆环 + 右上角闪光角标来自原站 /static/image/my/avatar-bg.png（199x265，contain 定位） */
.avatar-decor {
  position: absolute;
  top: 0;
  left: 0;
  width: 69px;
  height: 92px;
  background: url('/static/my/avatar-bg.png') no-repeat 0 0 / contain;
  pointer-events: none;
}
.avatar {
  position: absolute;
  top: 23px;
  left: 2px;
  width: 66px;
  height: 66px;
  border-radius: 50%;
  object-fit: cover;
  display: block;
}

.info-right {
  height: 92px;
  padding-top: 7px;
  padding-bottom: 7px;
  box-sizing: border-box;
  align-items: center;
}
/* 内层文字列顶对齐：原站 @95,72 高78（覆盖父级居中） */
.info-right > .info-col {
  height: 78px;
  flex-shrink: 0;
  align-self: flex-start;
}
.ml-10 { margin-left: 10px; }

.username { height: 21px; }
.username > .nickname { height: 21px; line-height: 21px; }
.nickname {
  font-size: 15px;
  font-weight: 700;
  color: #23262f;
  max-width: 80px;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}

/* 未实名标签 @184.1,73 44x19 bg #ececec r46px；文字 10px #666 /500 */
.auth-tags {
  height: 19px;
  padding: 0 7px;
  border-radius: 46px;
  background: #ececec;
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.ml-4 { margin-left: 4px; }
.no-auth { color: #666; font-weight: 500; }

/* 区块链地址行 @95,101 高15，10px */
.nft-addr {
  margin-top: 8px;
  height: 15px;
  color: #23262f;
  font-size: 10px;
}
.font-color-dark { color: #23262f; }
.addr-val {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
}
.copy {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
}
.copy img { width: 12px; height: 12px; display: block; }

/* 钱包按钮 @301,94.5 73x33 bg #000 r16px 12px #fff /600 */
.wallet-btn {
  width: 73px;
  height: 33px;
  border-radius: 16px;
  background: #000;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  flex-shrink: 0;
}

/* ---- 功能宫格卡 @16,167 358x144 r12 ---- */
.br-12 { border-radius: 12px; }
.bg-color-item { background: #fff; }
.grid-card {
  /* 原站 class="bg-color-item br-12 mt-10 mb-10"：实测 358x146 @16,167，下间距 10px */
  margin-top: 10px;
  margin-bottom: 10px;
  height: 146px;
}
.flex-w { flex-wrap: wrap; }

/* 每格 89.5x52，两行；图标 29x29；文案 12px mt6 */
.grid-cell {
  width: 25%;
  height: 52px;
  margin-bottom: 20px;
}
.grid-icon { width: 29px; height: 29px; }
.grid-icon img { width: 29px; height: 29px; display: block; }
.grid-text {
  margin-top: 6px;
  font-size: 12px;
  color: #23262f;
  line-height: 17px;
  height: 17px;
}

/* ---- 产品卡 @16,323 358x316（有数据时高度随内容）
   原站实测：tabs 20 + 间距 17 + 网格 247 + 底部留白 32 = 316 ---- */
.product-card {
  padding-bottom: 32px;
}

/* u-tabs @16,321 358x20；item 62x20 */
.prod-tabs {
  display: flex;
  height: 20px;
}
.prod-tab {
  width: 62px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 500;
  color: #1a1512;
  border-radius: 10px;
  flex-shrink: 0;
}
.prod-tab.is-active {
  background: var(--gd-primary);
  color: #fff;
  font-weight: 700;
}

/* 空态 @16,399 358x100；图 121x100 居中 @134.5 */
.prod-body { padding-top: 12px; }
.prod-empty {
  margin-top: 46px;
  height: 100px;
  display: flex;
  justify-content: center;
  align-items: flex-start;
}
.prod-empty img { width: 121px; height: 100px; display: block; }

/* 产品网格 @16,360 358x247；tabs 底 343 -> 网格顶 360 间距 17（prod-body padding-top 12 + margin-top 5） */
.prod-list {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  margin-top: 5px;
}
/* 卡片 .item_con @16,360 173x231  bg #f1efef br6 shadow 0 2px 9px rgba(0,0,0,.1) */
.img-con {
  position: relative;
}
/* 原站收藏角标 @157,370 22x22：相对封面图(16,360) 右偏移 10、上偏移 10 */
.corner-like {
  position: absolute;
  right: 10px;
  top: 10px;
  width: 22px;
  height: 22px;
}

.item-con {
  width: 173px;
  margin-bottom: 16px;
  background: #f1efef;
  border-radius: 6px;
  overflow: hidden;
  box-shadow: 0 2px 9px 0 rgba(0, 0, 0, 0.1);
  box-sizing: border-box;
}
/* 图区 @16,360 173x173 br6 */
.img-con {
  width: 173px;
  height: 173px;
  border-radius: 6px;
  overflow: hidden;
}
.prod-cover {
  display: block;
  width: 173px;
  height: 173px;
  object-fit: cover;
}
/* 信息区 @16,543 173x48  mt10 pad 0 8 10 */
.prod-info {
  width: 173px;
  height: 48px;
  margin-top: 10px;
  padding: 0 8px 10px;
  box-sizing: border-box;
}
.prod-name {
  width: 157px;
  height: 21px;
  margin-bottom: 4px;
  font-size: 14px;
  font-weight: 700;
  color: #23262f;
  line-height: 21px;
  white-space: nowrap;
  overflow: hidden;
}
/* 标签行 @24,568 157x13 */
.prod-sub {
  display: flex;
  align-items: center;
  width: 157px;
  height: 13px;
}
.tag-flash,
.tag-rarity {
  display: block;
  height: 13px;
  padding: 1px 4px;
  border-radius: 2px;
  font-size: 8px;
  font-weight: 700;
  color: #fff;
  line-height: 11px;
  box-sizing: border-box;
  flex: none;
}
.tag-flash { background: #f23d4d; }
.tag-rarity { background: #404040; }
.tag-hold {
  display: block;
  height: 13px;
  margin-left: 4px;
  padding: 1px 4px;
  border-radius: 2px;
  background: #404040;
  font-size: 8px;
  font-weight: 700;
  color: #fff;
  line-height: 11px;
  box-sizing: border-box;
  flex: none;
}
</style>
