<script setup>
/**
 * 合成结果 —— 1:1 复刻原站 pages/compound/btchresult
 *
 * 路由：#/pages/compound/btchresult
 *
 * 原站 390x844 实测：
 *   .container.page-bg.flex-col  @0,0 390x844  bg rgb(250,250,250)
 *     navbar fixed @0,0 390x44  bg rgb(250,250,250)
 *       返回 @16,12 24x24；标题「合成结果」15px/700 rgb(51,51,51)
 *     礼盒图 result-box.png @90,64 210x162
 *     「恭喜您合成成功」@135.5,296 119x23  18px rgb(3,139,255)
 *     白卡 @16,350 358x126 br15 bg #fff
 *       「您可在 我的藏品 中查看」@126.8,381 136.5x19  13px/700 rgb(85,85,85)
 *       分割装饰图 @32,418 326x15（base64 装饰线）
 *     底部 footer @0,777 390x67 bg #fff
 *       「水晶商城」@20,787 166x47 br26 bg rgb(241,240,240) 文字 rgb(153,153,153)
 *       「再合一次」@204,787 166x47 br26 bg #000 文字 #fff
 */
import { useRouter } from 'vue-router'

const router = useRouter()
</script>

<template>
  <div class="btchresult">
    <div class="navbar">
      <img class="nav-back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">合成结果</div>
    </div>

    <img class="gift" src="/static/compose/result-box.png" alt="合成成功" />
    <div class="success">恭喜您合成成功</div>

    <div class="card">
      <div class="card-txt">您可在 我的藏品 中查看</div>
      <div class="divider">
        <span class="dot dot--l"></span>
        <span class="line"></span>
        <span class="glyph"></span>
        <span class="line"></span>
        <span class="dot dot--r"></span>
      </div>
    </div>

    <div class="footer">
      <div class="btn btn--gray">水晶商城</div>
      <div class="btn btn--black">再合一次</div>
    </div>
  </div>
</template>

<style scoped>
.btchresult {
  position: relative;
  width: 390px;
  height: 844px;
  background: rgb(250, 250, 250);
  color: rgb(74, 74, 74);
  font-size: 15px;
  overflow: hidden;
}

.navbar {
  position: fixed;
  left: 0;
  top: 0;
  z-index: 10;
  width: 390px;
  height: 44px;
  background: rgb(250, 250, 250);
}
.nav-back {
  position: absolute;
  left: 16px;
  top: 12px;
  width: 24px;
  height: 24px;
  cursor: pointer;
}
.nav-title {
  width: 390px;
  height: 44px;
  font-size: 15px;
  font-weight: 700;
  color: rgb(51, 51, 51);
  line-height: 44px;
  text-align: center;
}

/* 礼盒图 @90,64 210x162 */
.gift {
  position: absolute;
  left: 90px;
  top: 64px;
  width: 210px;
  height: 162px;
}
.success {
  position: absolute;
  left: 0;
  top: 296px;
  width: 390px;
  height: 23px;
  font-size: 18px;
  line-height: 23px;
  color: rgb(3, 139, 255);
  text-align: center;
}

/* 白卡 @16,350 358x126 */
.card {
  position: absolute;
  left: 16px;
  top: 350px;
  width: 358px;
  height: 126px;
  background: #fff;
  border-radius: 15px;
  box-sizing: border-box;
}
.card-txt {
  position: absolute;
  left: 0;
  top: 31px;
  width: 358px;
  height: 19px;
  font-size: 13px;
  font-weight: 700;
  color: rgb(85, 85, 85);
  line-height: 19px;
  text-align: center;
}
/* 装饰分割线 @32,418 326x15 */
.divider {
  position: absolute;
  left: 32px;
  top: 68px;
  display: flex;
  align-items: center;
  width: 326px;
  height: 15px;
}
.line {
  flex: 1;
  height: 1px;
  background: linear-gradient(
    90deg,
    rgba(3, 139, 255, 0) 0%,
    rgba(3, 139, 255, 0.55) 50%,
    rgba(3, 139, 255, 0) 100%
  );
}
.dot {
  width: 3px;
  height: 3px;
  flex: none;
  border-radius: 50%;
  background: rgb(3, 139, 255);
  opacity: 0.55;
}
.dot--l {
  margin-right: 2px;
}
.dot--r {
  margin-left: 2px;
}
/* 中间的双环小图标 */
.glyph {
  position: relative;
  width: 22px;
  height: 14px;
  flex: none;
}
.glyph::before,
.glyph::after {
  content: '';
  position: absolute;
  top: 2px;
  width: 9px;
  height: 9px;
  border: 1.6px solid rgb(3, 139, 255);
  border-radius: 50%;
  opacity: 0.55;
}
.glyph::before {
  left: 2px;
}
.glyph::after {
  right: 2px;
}

/* 底部 footer @0,777 390x67 */
.footer {
  position: absolute;
  left: 0;
  top: 777px;
  display: flex;
  justify-content: space-between;
  width: 390px;
  height: 67px;
  padding: 10px 20px;
  box-sizing: border-box;
  background: #fff;
}
.btn {
  width: 166px;
  height: 47px;
  border-radius: 26px;
  font-size: 14px;
  font-weight: 700;
  line-height: 47px;
  text-align: center;
}
.btn--gray {
  background: rgb(241, 240, 240);
  color: rgb(153, 153, 153);
}
.btn--black {
  background: #000;
  color: #fff;
}
</style>
