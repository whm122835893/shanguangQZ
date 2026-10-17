<script setup>
/**
 * 设置 —— 1:1 复刻原站 pages/my/info
 * 个人中心的设置总入口，隐私设置/收货地址/实名认证/操作密码/注销账号
 * 等子页均从此页进入。
 *
 * 原站登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container.page-container高 844
 *   返回 back-btn.png@13,10 24x24
 *   导航标题「设置」        @180,16 30x16  15px/700 rgb(51,51,51)
 *
 *   设置行（9 行，行高恒定，y起点 65，行间距 53px）：
 *     昵称      @16,65   28x15   值 GqdusoI30      @297.8,62.5 62.2x15
 *     手机号    @16,11842x15   值 175****1293      @283,115.577x15
 *     隐私设置  @16,171 56x15
 *     收货地址  @16,224 56x15
 *     区块链地址@16,277 70x15   值 ****拥有藏品后自动生成**** @168,277 182x15
 *       复制图标 copy-dark.png @358,274.5 16x16
 *     我的认证  @16,330 56x15   值「未实名」标签   @320,328 40x15
 *     操作密码  @16,383 56x15
 *     注销账号  @16,436 56x15
 *     下载APP   @16,489 53.7x15
 *     标签.auth-tags @320,328 40x15  bg #fff  r46px  padding 2px 8px  8px/400 #222
 *   右箭头 right.png @368,<行y> 6x11（除「区块链地址」行外每行都有）
 *
 *   退出按钮 .z-btn @16,562 358x47  bg rgb(241,240,240)  r23px  margin-top 41px
 *     文字「退出账号」14px/700 #999居中
 *
 * 接口（原站实测）：GET index/getConfig（页面配置），用户信息来自登录态
 *注：nickname / mobile / 实名状态 均取自本地登录态userInfo，
 *    与原站一致；区块链地址在拥有藏品前为空，原站显示占位文案。
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useUserStore } from '@/stores/user'
import { showToast, showConfirmDialog } from 'vant'

const router = useRouter()
const userStore = useUserStore()

/* 原站实测 localStorage.userInfo 为包装格式 {"type":"object","data":{...}}，
   本项目 stores/user.js 存的是扁平 u.data。两种格式都要能取到，故统一解包。
   字段名与原站一致：nickname / mobile / chain_address(未实测，原站拥有藏品后才有)。 */
const info = computed(() => {
  const u = userStore.userInfo || {}
  return u.data && typeof u.data === 'object' ? u.data : u
})

/* 昵称：原站实测「GqdusoI30」 */
const nickname = computed(() => info.value.nickname || '')
/* 手机号脱敏：175****1293 */
const mobile = computed(() => {
  const m = info.value.mobile || ''
  return m.length === 11 ? m.slice(0, 3) + '****' + m.slice(7) : m
})
/* 实名状态标签文案 */
const authText = ref('未实名')
/* 区块链地址：拥有藏品后生成 */
const chainAddr = computed(() => info.value.chain_address || info.value.block_address || '')
const CHAIN_PLACEHOLDER = '****拥有藏品后自动生成****'

const rows = [
  { key: 'nickname', label: '昵称', value: 'nickname', arrow: true, to: '' },
  { key: 'mobile', label: '手机号', value: 'mobile', arrow: true, to: '' },
  { key: 'privacy', label: '隐私设置', arrow: true, to: '/pages/my/privacy' },
  { key: 'address', label: '收货地址', arrow: true, to: '/pages/my/address/list' },
  { key: 'chain', label: '区块链地址', value: 'chain', copy: true, arrow: false },
  { key: 'auth', label: '我的认证', tag: 'auth', arrow: true, to: '/pages/certification/certification' },
  { key: 'pwd', label: '操作密码', arrow: true, to: '/pages/my/actionPwd/setPwd' },
  { key: 'cancel', label: '注销账号', arrow: true, to: '/pages/my/cancellation' },
  { key: 'app', label: '下载APP', arrow: true, to: '/pages/index/downloadApp' }
]

function valueOf(r) {
  if (r.value === 'nickname') return nickname.value
  if (r.value === 'mobile') return mobile.value
  if (r.value === 'chain') return chainAddr.value || CHAIN_PLACEHOLDER
  return ''
}

function onRow(r) {
  if (r.to) router.push(r.to)
}

function onCopy() {
  const text = navigator.clipboard.writeText(chainAddr.value)
  if (text && text.then) {
    text.then(() => showToast({ icon: 'none', message: '复制成功' }))
      .catch(() => showToast({ icon: 'none', message: '复制失败' }))
  } else {
    showToast({ icon: 'none', message: '复制失败' })
  }
}

function onLogout() {
  showConfirmDialog({ title: '提示', message: '确定要退出登录吗？' })
    .then(() => { userStore.logout(); router.replace('/pages/login/login') })
    .catch(() => {})
}
</script>

<template>
  <div class="info-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">设置</div>
    </div>

    <!-- 9 行设置列表，页面绝对 y = 65 + i*53（行间距恒定 53px） -->
    <div class="rows">
      <div
        v-for="(r, i) in rows"
        :key="r.key"
        class="row"
        :class="{ 'has-copy': r.copy }"
        :style="{ top: i * 53 + 'px' }"
        @click="onRow(r)"
      >
        <span class="label">{{ r.label }}</span>
        <span v-if="r.value" class="value">{{ valueOf(r) }}</span>
        <!-- 「未实名」标签 @320,328 40x15 -->
        <span v-if="r.tag" class="auth-tag">{{ authText }}</span>
        <img v-if="r.copy" class="copy" src="/static/icon/copy-dark.png" alt="复制" @click.stop="onCopy" />
        <img v-if="r.arrow" class="arrow" src="/static/icon/right.png" alt="" />
      </div>
    </div>

    <!-- 退出按钮 @16,562 358x47 -->
    <div class="logout" @click="onLogout">退出账号</div>
  </div>
</template>

<style scoped>
/* .container.page-container 高 844 */
.info-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航：标题「设置」@180,16 30x16
   注：标题为整行宽的居中文字，必须让出左侧返回按钮的点击区域，
   故宽度收到 200px并 left 偏移，保证 left:13 的返回按钮可点。 */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 设置行容器：行绝对定位，行高 15px（与原站文字盒一致）
   注：容器 top 必须从首行（y=65）开始，不能从 0 开始，
   否则会覆盖顶部导航区、拦截返回按钮的点击。 */
.rows {
  position: absolute;
  left: 0;
  top: 65px;
  width: 390px;
  height: 460px;
}
.row {
  position: absolute;
  left: 16px;
  width: 358px;
  height: 15px;
  display: flex;
  align-items: center;
}
/* 标签 14px/400 #4a4a4a */
.label { color: #4a4a4a; font-size: 14px; font-weight: 400; line-height: 15px; flex-shrink: 0; }

/* 值 14px/400 #23262f，右对齐
   原站实测：昵称值 @297.8,62.5 62.2x15 -> 右边缘 360
   手机号值 @283,115.5 77x15   -> 右边缘 360
   区块链值 @168,277 182x15    -> 右边缘 350（因右侧是复制图标 @358宽16）
   故值区右边界 = 360，行内坐标 360-16=344，距行右 358-344 = 14px */
.value {
  flex: 1;
  text-align: right;
  color: #23262f;
  font-size: 14px;
  font-weight: 400;
  line-height: 15px;
  margin-right: 14px;
}

/* 「未实名」标签 @320,328 40x15 bg #fff r46px padding 2px 8px 8px/400 #222
   原站：标签 @320,328 宽 40 -> 右边缘 360；箭头 @368 宽 6 -> 左边缘 368，间距 8px
   行内坐标（行左 = 页面 16）：标签 right = 358-344 = 14px，箭头 right = 358-338 = 20px
   注意：此处不设 line-height:normal，避免 Vant reset 引入基线偏移 */
.auth-tag {
  position: absolute;
  right: 14px;
  top: 0;
  width: 40px;
  height: 15px;
  box-sizing: border-box;
  background: #fff;
  border-radius: 46px;
  color: #222;
  font-size: 8px;
  font-weight: 400;
  text-align: center;
  line-height: 15px;
}

/* 复制图标 copy-dark.png @358,274.5 16x16
   右边缘 358+16=374 = 行右（16+358）✓ 故 right:0
   垂直：原站 @274.5，行 y=277（文字顶）-> 图标比文字高 2.5px 居中对齐
   行高15，图标高16 -> top = (15-16)/2 = -0.5，取 -0.5px */
.copy {
  position: absolute;
  right: 0;
  top: -0.5px;
  width: 16px;
  height: 16px;
}

/* 右箭头 right.png @368,<行y> 6x11
   行左 = 页面 16，行宽 358 -> 箭头左边缘行内 352，距行右 358-352 = 6px（箭宽6）
   即箭头右边缘行内 358，距行右 0；但页面实测箭头 @368 宽 6 -> 右边缘 374，
   行右 = 16+358 = 374 ✓故 right:0 */
.arrow {
  position: absolute;
  right: 0;
  top: 2px;
  width: 6px;
  height: 11px;
}

/* 含复制图标的行（区块链地址）：值区右边缘让出图标宽度
   原站实测值 @168,277182x15 -> 右边缘 350，图标 @358 宽 16-> 左边缘 358
   即值与图标间距 8px。图标占16px+间距8px = 24px，故行内 right 需 +24。 */
.row.has-copy .value { margin-right: 38px; }

/* 退出按钮 .z-btn @16,562 358x47 bg rgb(241,240,240) r23px margin-top 41px */
.logout {
  position: absolute;
  left: 16px;
  top: 562px;
  width: 358px;
  height: 47px;
  line-height: 47px;
  text-align: center;
  background: rgb(241, 240, 240);
  border-radius: 23px;
  color: #999;
  font-size: 14px;
  font-weight: 700;
}
</style>