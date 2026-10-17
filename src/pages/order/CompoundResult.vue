<script setup>
/**
 * 合成结果 —— 1:1 复刻原站 pages/compound/result
 *
 * 路由：#/pages/compound/result
 *
 * 原站 390x844 实测：
 *   .container.page-container.page-bg.flex.flex-dir-col.flex-ai-center
 *                                  @0,0 390x844  bg #fafafa  padding 0 16px
 *     .u-navbar--fixed            @0,0 390x44  bg #fafafa
 *       返回 back-btn.png         @13,10 24x24
 *       标题「合成结果」          @165,16 60x16  15px/700 rgb(51,51,51)
 *     result-box.png              @90,64 210x162
 *     「恭喜您合成成功」          @136,296 119x23  18px/400 rgb(3,139,255)
 *     .content.bg-color-item.pt-30 @16,350 358x126  bg #fff  br15  padding 31px 16px 16px
 *       「您可在 我的藏品 中查看」@127,381 137x19  13px/700 rgb(85,85,85)
 *       装饰分割图（base64 内联） @32,418 326x15
 *     .footer.bg-color-item       @0,777 390x67  bg #fff  padding 0 20px
 *       「水晶商城」              @20,787 166x47  bg #f1f0f0 br26 14px/700 #999
 *       「再合一次」              @204,787 166x47 bg #000    br26 14px/700 #fff
 *
 * 装饰图说明：原站该图以 data:image/png;base64 内联（326x15 的花线分隔），
 *   本复刻从原站截图裁切同等尺寸图形复用，避免内联超长 base64。
 */
import { useRouter } from 'vue-router'

const router = useRouter()

const toShop = () => {
  router.push('/pages/shop/shop')
}

const again = () => {
  router.back()
}
</script>

<template>
  <div class="compound-result">
    <!-- 导航 @0,0 390x44 -->
    <div class="navbar">
      <div class="nav-back" @click="router.back()">
        <img src="/static/icon/back-btn.png" alt="返回" />
      </div>
      <div class="nav-title">合成结果</div>
    </div>

    <!-- 结果礼盒 @90,64 210x162 -->
    <img class="result-box" src="/static/compose/result-box.png" alt="合成结果" />

    <!-- 提示 @136,296 -->
    <div class="tip">恭喜您合成成功</div>

    <!-- 内容卡 @16,350 358x126 -->
    <div class="content">
      <div class="content-text">
        您可在 <span class="link">我的藏品</span> 中查看
      </div>
      <img class="divider" src="/static/compose/result-divider.png" alt="" />
    </div>

    <!-- 底栏 @0,777 390x67 -->
    <div class="footer">
      <div class="fbtn fbtn--ghost" @click="toShop">水晶商城</div>
      <div class="fbtn fbtn--dark" @click="again">再合一次</div>
    </div>
  </div>
</template>

<style scoped>
.compound-result {
  position: relative;
  width: 390px;
  max-width: 100%;
  height: 844px;
  background: #fafafa;
  overflow: hidden;
}

/* ---------- 导航 @0,0 390x44 ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  background: #fafafa;
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
  left: 165px;
  top: 16px;
  width: 60px;
  height: 16px;
  font-size: 15px;
  font-weight: 700;
  color: rgb(51, 51, 51);
  line-height: 16px;
  text-align: center;
}

/* ---------- 结果礼盒 @90,64 210x162 ---------- */
.result-box {
  position: absolute;
  left: 90px;
  top: 64px;
  display: block;
  width: 210px;
  height: 162px;
}

/* ---------- 提示 @136,296 119x23 ---------- */
.tip {
  position: absolute;
  left: 136px;
  top: 296px;
  width: 119px;
  height: 23px;
  font-size: 18px;
  font-weight: 400;
  color: rgb(3, 139, 255);
  line-height: 23px;
  white-space: nowrap;
}

/* ---------- 内容卡 @16,350 358x126 ---------- */
.content {
  position: absolute;
  left: 16px;
  top: 350px;
  width: 358px;
  height: 126px;
  padding: 31px 16px 16px;
  background: #fff;
  border-radius: 15px;
  box-sizing: border-box;
}
.content-text {
  width: 326px;
  height: 19px;
  font-size: 13px;
  font-weight: 700;
  color: rgb(85, 85, 85);
  line-height: 19px;
  text-align: center;
}
.link {
  color: rgb(85, 85, 85);
}
/* 装饰花线 @32,418 326x15 */
.divider {
  position: absolute;
  left: 16px;
  top: 68px;
  display: block;
  width: 326px;
  height: 15px;
}

/* ---------- 底栏 @0,777 390x67 ---------- */
.footer {
  position: absolute;
  left: 0;
  top: 777px;
  width: 390px;
  height: 67px;
  padding: 10px 20px;
  background: #fff;
  box-sizing: border-box;
}
.fbtn {
  width: 166px;
  height: 47px;
  border-radius: 26px;
  font-size: 14px;
  font-weight: 700;
  line-height: 47px;
  text-align: center;
  box-sizing: border-box;
  display: inline-block;
}
.fbtn--ghost {
  background: #f1f0f0;
  color: rgb(153, 153, 153);
}
.fbtn--dark {
  margin-left: 18px;
  background: #000;
  color: #fff;
}
</style>