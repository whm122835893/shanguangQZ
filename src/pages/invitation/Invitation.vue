<script setup>
/**
 * 邀请好友 —— 1:1 复刻原站 pages/invitation/invitation
 * 由「其他功能」页「分享邀请」进入。
 *
 * 原站实测（页面总高 888）：
 *   返回图标 @5,6 27x27（uni-page-head 样式，与其他页 back-btn.png 不同，此处用同图缩放）
 *   标题「邀请好友」@70,7 250x30  16px/700 rgb(0,0,0) text-align:center
 *
 *   logo @30,75 46x46   logo.png（原图1536x1536）
 *   昵称 @86,90 66.2x16  14px/700 rgb(74,74,74)  「GqdusoI30」（当前用户）
 *     （ml-10 → 与 logo 间距 10）
 *
 *   「立即分享邀请码给好友」@30,150 330x24  16px/700 rgb(74,74,74)
 *
 *   下载图标1 share-download.png @122,194 45x45（原图88x90）
 *     文案「复制链接」@116.5,247 56x21  14px/600 rgb(193,193,193)
 *   下载图标2 invite-download.png @216,194 45x45（原图88x90）
 *     文案「保存二维码」@203.5,247 70x21  14px/600 rgb(193,193,193)
 *
 *   二维码 @160.5,288 69x69  （接口动态生成 addons/qrcode/index/build?text_type=h5&text=Q****）
 *   「邀请历史」@30,442 330x21  14px/400 rgb(74,74,74)
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { useUserStore } from '@/stores/user'

const router = useRouter()
const userStore = useUserStore()

const info = computed(() => {
  const u = userStore.userInfo || {}
  return u.data && typeof u.data === 'object' ? u.data : u
})

const nickname = computed(() => info.value.nickname || '')

/* 本页可能从「其他功能」直接进入（未经我的页），localStorage 里未必有 userInfo，
   邀请码取不到会导致二维码为空。原站进入本页必定请求 user/getUserInfo。*/
onMounted(() => {
  if (userStore.isLogin) userStore.fetchUserInfo()
})

/* 二维码由原站后端 addons/qrcode 动态生成（返回 PNG，不是 JSON）。
   原站实测请求：
     https://api.gandart.shop/addons/qrcode/index/build
       ?text_type=h5
       &text=Q28116797                               <- 邀请码
       &t=<时间戳>                                   <- 防缓存
       &url=%2F%23%2Fpages%2Finvitation%2Finvitation<- 二维码指向的页面
   关键点：这条接口 **不带 /api 前缀**（其余业务接口都带），
   写成 /api/addons/... 后端会404；须由 server.cjs 放行 /addons/ 代理。
   走同源相对路径，避免页面直连外部域名。*/
/* 邀请码字段：后端 getUserInfo 返回的是 inviter_code（如 Q28116797），
   不是 invite_code —— 早期误用 invite_code 导致二维码 src 为空、图裂。*/
const inviteCode = computed(() => info.value.inviter_code || info.value.invite_code || '')

const qrUrl = computed(() => {
  const code = inviteCode.value
  if (!code) return ''
  return (
    `/addons/qrcode/index/build?text_type=h5&text=${encodeURIComponent(code)}` +
    `&t=${Date.now()}` +
    `&url=${encodeURIComponent('/#/pages/invitation/invitation')}`
  )
})

/* 原站实测（2026-10-17）：点「复制链接」写入剪贴板的是完整邀请链接，
   不是纯邀请码：
     https://h5.gandart.shop/#/pages/login/register?a=1&content=Q28116797
   a=1 表示来自邀请渠道，content 携带邀请码（注册页据此回填）。*/
function onCopy() {
  const code = inviteCode.value
  if (!code) return showToast({ icon: 'none', message: '暂无可复制的邀请码' })
  const link = `${location.origin}/#/pages/login/register?a=1&content=${encodeURIComponent(code)}`
  const done = () => showToast({ icon: 'none', message: '邀请链接已复制' })
  if (navigator.clipboard) {
    navigator.clipboard.writeText(link).then(done).catch(() => fallbackCopy(link, done))
  } else {
    fallbackCopy(link, done)
  }
}

/* 非安全上下文（http 预览）下 clipboard API 不可用时的兜底 */
function fallbackCopy(text, done) {
  const ta = document.createElement('textarea')
  ta.value = text
  ta.style.position = 'fixed'
  ta.style.opacity = '0'
  document.body.appendChild(ta)
  ta.select()
  const ok = document.execCommand('copy')
  document.body.removeChild(ta)
  ok ? done() : showToast({ icon: 'none', message: '复制失败，请长按二维码保存' })
}

/* ---- 分享海报（原站实测 2026-10-17）----
   点「保存二维码」不是弹 toast，而是：
     1) 用 canvas 合成海报：底图 yq-code-img-bg.png（616x824）缩到 640x856 贴左上，
        再把二维码画在 x=187 y=312 的 268x268 白块上 -> 输出 641x888 的 PNG blob；
     2) 弹出 .poster_con 展示合成图（页面内 @36,202 318x441，2x 截图即 318x441）；
     3) 点海报（立即邀请按钮）触发下载，toast「图片已保存到系统相册」。
   关闭按钮 .close@264,202 80x41（在海报之上，右侧压边）。*/
const POSTER_BG = '/static/image/invitation/yq-code-img-bg.png'
const POSTER_W = 641
const POSTER_H = 888
const QR_BOX = { x: 187, y: 312, w: 268, h: 268 }

const showPoster = ref(false)
const posterUrl = ref('')

async function onSave() {
  if (!qrUrl.value) return showToast({ icon: 'none', message: '二维码生成中，请稍候' })
  showToast({ icon: 'none', message: '海报生成中...' })
  const url = await buildPoster()
  if (!url) return showToast({ icon: 'none', message: '海报生成失败，请重试' })
  posterUrl.value = url
  showPoster.value = true
}

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const im = new Image()
    im.onload = () => resolve(im)
    im.onerror = reject
    im.src = src
  })
}

async function buildPoster() {
  const cv = document.createElement('canvas')
  cv.width = POSTER_W
  cv.height = POSTER_H
  const ctx = cv.getContext('2d')
  try {
    /* 底图 */
    const bg = await loadImage(POSTER_BG)
    ctx.drawImage(bg, 0, 0, 640, 856)
    /* 二维码：先铺白底（原站海报中二维码坐在纯白方块上），再画图 */
    const qr = await loadImage(qrUrl.value)
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(QR_BOX.x, QR_BOX.y, QR_BOX.w, QR_BOX.h)
    ctx.drawImage(qr, QR_BOX.x, QR_BOX.y, QR_BOX.w, QR_BOX.h)
    return cv.toDataURL('image/png')
  } catch (e) {
    return ''
  }
}

/* 点海报上的「立即邀请」：原站行为是把海报保存到系统相册 */
function onPosterClick() {
  if (!posterUrl.value) return
  const a = document.createElement('a')
  a.href = posterUrl.value
  a.download = `邀请海报_${inviteCode.value || 'gandart'}.png`
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  showToast({ icon: 'none', message: '图片已保存到系统相册' })
}

function closePoster() {
  showPoster.value = false
}
</script>

<template>
  <div class="invite-page">
    <!-- 标题栏@70,7 250x30 -->
    <div class="page-head">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="head-title">邀请好友</div>
    </div>

    <!-- 用户信息行：logo@30,75 46x46 + 昵称@86,90 -->
    <div class="user-row">
      <img class="logo" src="/static/bg/logo.png" alt="logo" />
      <span class="nickname">{{ nickname }}</span>
    </div>

    <!-- 虚线1@30,137 330x1（分隔 logo 区与 slogan） -->
    <div class="divider" style="top: 137px"></div>

    <!-- 「立即分享邀请码给好友」@30,150 330x24（居中） -->
    <div class="slogan">立即分享邀请码给好友</div>

    <!-- 两个操作项 -->
    <div class="actions">
      <!-- 图标1@122,194 45x45，文案@116.5,247 56x21 -->
      <div class="action-item" style="left: 92px" @click="onCopy">
        <img class="act-icon" src="/static/icon/share-download.png" alt="复制链接" />
        <div class="act-text act-text-1">复制链接</div>
      </div>
      <!-- 图标2@216,194 45x45，文案@203.5,247 70x21 -->
      <div class="action-item" style="left: 186px" @click="onSave">
        <img class="act-icon" src="/static/icon/invite-download.png" alt="保存二维码" />
        <div class="act-text act-text-2">保存二维码</div>
      </div>
    </div>

    <!-- 二维码@160.5,288 69x69 -->
    <img class="qrcode" :src="qrUrl" alt="邀请二维码" @click="onSave" />

    <!-- 邀请历史卡片.yqhylb.pa-16 @14,426 362x255 圆角8 -->
    <div class="history-card">
      <div class="history-title">邀请历史</div>
      <!-- 虚线2 @30,479 330x1 -->
      <div class="divider" style="top: 53px"></div>
    </div>

    <!-- 分享海报弹层（原站 .poster_con @36,202 318x441；关闭按钮 @264,202 80x41） -->
    <div v-if="showPoster" class="poster-mask" @click.self="closePoster">
      <div class="poster-con">
        <img :src="posterUrl" alt="邀请海报" @click="onPosterClick" />
        <div class="close" @click.stop="closePoster"></div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* .container 390x888 白底 */
.invite-page {
  position: relative;
  width: 390px;
  min-height: 888px;
  background: #fff;
  overflow: hidden;
}

/* 标题栏：返回图标@5,6 27x27，标题@70,7 250x30 */
.page-head { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 5px; top: 6px; width: 27px; height: 27px; z-index: 2; }
/* 标题「邀请好友」@70,7 250x30 16px/700 #000 居中 */
.head-title {
  position: absolute; left: 70px; top: 7px; width: 250px; height: 30px;
  line-height: 30px; text-align: center; color: #000; font-size: 16px; font-weight: 700;
}

/* 用户信息行：logo@30,75 46x46 */
.user-row {
  position: absolute;
  left: 30px;
  top: 75px;
  width: 330px;
  height: 46px;
  display: flex;
  align-items: center;
}
/* logo 原站为圆形：border-radius 50% + overflow hidden */
.logo {
  width: 46px;
  height: 46px;
  flex-shrink: 0;
  border-radius: 50%;
  overflow: hidden;
}
/* 昵称 14px/700 rgb(74,74,74)，与 logo 间距 10 */
.nickname {
  margin-left: 10px;
  color: rgb(74, 74, 74);
  font-size: 14px;
  font-weight: 700;
  line-height: 16px;
}

/* 「立即分享邀请码给好友」@30,150 330x24 16px/700 */
.slogan {
  position: absolute;
  left: 30px;
  top: 153px;
  width: 330px;
  height: 24px;
  color: rgb(74, 74, 74);
  font-size: 16px;
  font-weight: 700;
  line-height: 24px;
  text-align: center;
}

/* 虚线分隔@30,137 / @30,479 330x1  1px dashed rgb(221,219,219) */
.divider {
  position: absolute;
  left: 30px;
  width: 330px;
  height: 1px;
  border-top: 1px dashed rgb(221, 219, 219);
  box-sizing: border-box;
}

/* 两个操作项：图标1@122,194 45x45 / 图标2@216,194 45x45
   文案1@116.5,247 / 文案2@203.5,247（mt-8 → 图标底 239 + 8 = 247） */
.actions {
  position: absolute;
  left: 30px;
  top: 194px;
  width: 330px;
  height: 74px;
}
/* 每项按原站实测坐标绝对定位（flex space-around 会因文案宽度不同产生偏移） */
.action-item {
  position: absolute;
  top: 0;
  width: 88px;
  cursor: pointer;
}
.act-icon { width: 45px; height: 45px; }
/* 文案 14px/600 rgb(193,193,193)，mt-8 → 顶部落在 247 */
.act-text {
  margin-top: 8px;
  color: rgb(193, 193, 193);
  font-size: 14px;
  font-weight: 600;
  line-height: 21px;
  text-align: center;
  white-space: nowrap;
}

/* 二维码@160.5,288 69x69 */
.qrcode {
  position: absolute;
  left: 160.5px;
  top: 288px;
  width: 69px;
  height: 69px;
  cursor: pointer;
}

/* 邀请历史卡片.yqhylb.pa-16 @14,426 362x255  圆角8 */
.history-card {
  position: absolute;
  left: 14px;
  top: 426px;
  width: 362px;
  height: 255px;
  box-sizing: border-box;
  padding: 16px;
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
}
/* 「邀请历史」@30,442 330x21  14px/400 rgb(74,74,74) */
.history-title {
  width: 330px;
  height: 21px;
  margin-top: 2px;
  color: rgb(74, 74, 74);
  font-size: 14px;
  font-weight: 400;
  line-height: 21px;
}

/* ---- 分享海报弹层（原站 uni-popup 居中：.poster_con @36,202 318x441） ---- */
.poster-mask {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.6);
}
.poster-con {
  position: relative;
  width: 318px;
  /* 合成图 641x888，等比 318/641 -> 440.5 */
  height: 441px;
}
.poster-con img {
  display: block;
  width: 318px;
  height: 441px;
  border-radius: 8px;
}
/* 原站 .close@264,202 80x41：源站 close.png 实为 404（SPA fallback），
   截图中也不可见 —— 是透明点击热区，故此处不绘制图标，只保留热区。 */
.poster-con .close {
  position: absolute;
  top: 0;
  right: 10px;
  width: 80px;
  height: 41px;
}
</style>