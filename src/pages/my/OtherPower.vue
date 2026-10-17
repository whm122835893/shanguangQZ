<script setup>
/**
 * 其他功能 —— 1:1 复刻原站 pages/my/other-power
 * 个人中心的功能总览入口，11 项功能分组列出。
 *
 * 原站登录态实测（getBoundingClientRect + getComputedStyle）：
 *   .container 高 854
 *   返回 uni-btn-icon@5,6 27x27（字体图标 27px/400）
 *   导航标题「其他功能」uni-page-head__title @70,7 250x30  16px/700 #000
 *
 *   分组列表（4 组，行高 44px，组内行距 44px，组间多27px 空隙）：
 *     图标 22x22  @35,<行y>+4     文字 13px/500 #333 @72,<行y>+4.5
 *     右箭头 right.png 5x8 @350,<行y>+7
 *     组①个人信息@70 / 实名认证@114
 *     组② 我的求购@185 / 我的寄售@229 / 合成记录@273
 *     组③ 隐私设置@344 / 收货地址@388 / 我的客服@432 / 分享邀请@476 / 关于我们@520
 *     组④ APP下载@564
 *
 *   全部图标原图 44x44，按 22x22 显示（2倍图）
 *
 * 跳转目标（按原站路由表；未复刻的页面暂时留空，避免跳到死链）：
 *   个人信息 -> /pages/my/info              （已复刻）
 *   实名认证 -> /pages/certification/certification（未复刻）
 *   我的求购 -> /pages/order/beg/list        （未复刻）
 *   我的寄售 -> /pages/consignments/myConsignment（未复刻）
 *   合成记录 -> /pages/compound/record       （未复刻）
 *   隐私设置 -> /pages/my/privacy
 *   收货地址 -> /pages/my/address/list       （本批次复刻）
 *   我的客服 -> /pages/my/service            （未复刻）
 *   分享邀请 -> /pages/invitation/invitation （未复刻）
 *   关于我们 -> /pages/my/about              （未复刻）
 *   APP下载  -> /pages/index/downloadApp（未复刻）
 */
import { useRouter } from 'vue-router'

const router = useRouter()

/**
 * 4 组分组，y 为原站**像素级实测**的行首 y 坐标。
 *
 * 重要：原站行距并非恒定 44px —— 原站用 flex + padding 撑开，
 * 实际渲染行距在 43 / 43.5 / 45px 之间波动（2x 实测 86/87/90）。
 * 若按固定 44px 推算会累积偏移（实测第 3 项起错位，到末项偏差约 4px）。
 * 故此处一律硬编码像素级实测值，不做公式推算。
 *
 * 2x 像素实测图标顶 y：143 233 375 465 551 691 780 870 957 1044 1133
 * 换算 CSS（÷2）：  71.5 116.5 187.5 232.5 275.5 345.5 390 435 478.5 522 566.5
 */
const groups = [
  {
    items: [
      { y: 71.5, label: '个人信息', icon: 'grxx-image.png', to: '/pages/my/info' },
      { y: 116.5, label: '实名认证', icon: 'smrz-image.png', to: null }
    ]
  },
  {
    items: [
      { y: 187.5, label: '我的求购', icon: 'wdqg-image.png', to: null },
      { y: 232.5, label: '我的寄售', icon: 'wdjs-image.png', to: null },
      { y: 275.5, label: '合成记录', icon: 'hcjl-image.png', to: null }
    ]
  },
  {
    items: [
      { y: 345.5, label: '隐私设置', icon: 'yssz.png', to: '/pages/my/privacy' },
      { y: 390, label: '收货地址', icon: 'shdz.png', to: '/pages/my/address/list' },
      { y: 435, label: '我的客服', icon: 'wdkf-image.png', to: '/pages/my/service' },
      { y: 478.5, label: '分享邀请', icon: 'fxyq.png', to: '/pages/invitation/invitation' },
      { y: 522, label: '关于我们', icon: 'gywm.png', to: '/pages/my/about' }
    ]
  },
  {
    items: [
      { y: 566.5, label: 'APP下载', icon: 'app-xz.png', to: null }
    ]
  }
]

const rows = groups.flatMap((g) => g.items)

function go(to) {
  if (to) router.push(to)
}
</script>

<template>
  <div class="op-page">
    <!-- 导航：标题「其他功能」@70,7 250x30 -->
    <div class="navbar">
      <div class="back" @click="router.back()">
        <svg viewBox="0 0 27 27" width="27" height="27" aria-hidden="true">
          <path d="M18 6 L9 13.5 L18 21" fill="none" stroke="#000" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </div>
      <div class="nav-title">其他功能</div>
    </div>

    <!-- 11 行功能项。rows 容器 top=69.5（避开导航区），
         行内 top = 绝对 y - 71.5，转为相对容器的偏移 -->
    <div class="rows">
      <div
        v-for="(r, i) in rows"
        :key="r.label"
        class="row"
        :style="{ top: r.y - 69.5 + 'px' }"
        @click="go(r.to)"
      >
        <img class="icon" :src="'/static/image/my/' + r.icon" :alt="r.label" />
        <span class="label">{{ r.label }}</span>
        <img class="arrow" src="/static/icon/right.png" alt="" />
      </div>
    </div>
  </div>
</template>

<style scoped>
/* .container 高 854 */
.op-page {
  position: relative;
  width: 390px;
  min-height: 854px;
  background: #fff;
}

/* 导航：标题像素实测文字中心 x=195（页面正中）
   注：原站 DOM 实测 uni-page-head__title @70,7 250x30 是「标题容器」，
   容器内文字居中于容器，故文字中心 = 70+125 = 195，正好是页面中线。
   复刻直接把文字块居中于页面即可（left:70 + width:250 + text-align:center）。 */
.navbar { position: relative; width: 390px; height: 44px; }
/* 返回图标 @5,6 27x27 —— 原站为字体图标，此处按实测形状用内联 SVG 1:1 还原
   （不使用 emoji，遵循 P0 规则） */
.back {
  position: absolute;
  left: 5px;
  top: 6px;
  width: 27px;
  height: 27px;
  z-index: 2;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.nav-title {
  position: absolute;
  left: 70px;
  top: 7px;
  width: 250px;
  height: 30px;
  line-height: 30px;
  text-align: center;
  color: #000;
  font-size: 16px;
  font-weight: 700;
}

/* 行容器：left/top 归零，每行按实测绝对 y 定位
   注：容器不能覆盖顶部导航区（会拦截返回点击），故从首行 71.5 开始 */
.rows { position: absolute; left: 0; top: 69.5px; width: 390px; height: 530px; }
.row {
  position: absolute;
  left: 0;
  width: 390px;
  height: 22px;
}
/* 图标 22x22 @35,<行首y>（原站图标顶即行首 y） */
.icon {
  position: absolute;
  left: 35px;
  top: 0;
  width: 22px;
  height: 22px;
}
/* 文字 13px/500 #333 @72,<行首y>+4.5 */
.label {
  position: absolute;
  left: 72px;
  top: 4.5px;
  color: #333;
  font-size: 13px;
  font-weight: 500;
  line-height: 15px;
}
/* 右箭头 right.png 5x8 @350,<行首y>+7 */
.arrow {
  position: absolute;
  right: 35px;
  top: 7px;
  width: 5px;
  height: 8px;
}
</style>