<script setup>
/**
 * 实名认证 —— 1:1 复刻原站 pages/certification/certification
 *
 * 原站实测：
 *   .container 390x844 白底
 *   返回 back-btn.png @13,10 24x24
 *   标题「实名认证」@165,16 60x16  15px/700 rgb(51,51,51) ta:center
 *
 *   输入框.ipt @32,68 326x48  圆角15  border 1px solid rgb(0,0,0,?)+ 透明底
 *     ①「真实姓名」  @49,86.5  56x15  14px/400 rgb(51,51,51)
 *        placeholder「请输入真实姓名」@105,79 236x26  14px/400 rgb(153,153,153) ta:right
 *     ②「身份证号」  @49,149.5（.ipt @32,131 326x48）
 *        placeholder「请输入有效身份证号」@105,142 236x26
 *     ③「手机号码」  @49,212.5（.ipt @32,194 326x48）
 *        placeholder 显示已脱敏手机号「175****1293」@105,205 236x26  ta:right
 *        （实测原站手机号已填入并脱敏显示，不可编辑）
 *   按钮「立即认证」@16,773 358x47  bg rgb(227,227,227) 圆角23
 *     14px/700 rgb(102,102,102)
 *
 * 注：行间距 63（68→131→194），非等差整数，按实测值硬编码。
 *     手机号行原站展示脱敏值，按实测照抄。
 */
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { showToast } from 'vant'
import { getUserInfo } from '@/api'

const router = useRouter()

const realName = ref('')
const idCard = ref('')
/* 手机号取当前登录用户，原站展示脱敏形式 */
const mobileMasked = '175****1293'

/* ---------- 认证状态（原站 pages/certification/certification 实测） ----------
   getUserInfo 返回 real_name_authentication：1 已实名 / 0 或缺失 未实名。
   已实名时原站不渲染表单，改为「您已完成认证」卡片态：
     .br-8.mt-10 容器          @16,54 358x283
       标题行                   @132,90  盾牌 rzbz.png 22x22 + 文案 16px/700 #23262f
       .info 卡片               @20,129 350x208  bg /static/bg/rzCard.png
         姓名                   @51,168  12px #fff；值 @51,185 14px #fff
         身份证                 @51,225  12px #fff；值 @51,242 14px #fff
         rzCard-success.png     @229,181 100x97（右侧勋章）
         底部盾牌 rzbz.png 16x16 + 「信息安全保障中」12px @71,289
   值直接取 userInfo.real_name / userInfo.id_card（接口已脱敏）。 */
const userInfo = ref({})
const certified = computed(() => Number(userInfo.value.real_name_authentication) === 1)

async function loadUser() {
  try {
    const r = await getUserInfo()
    userInfo.value = (r && r.data) || {}
  } catch (e) {
    userInfo.value = {}
  }
}

onMounted(loadUser)

function onSubmit() {
  if (!realName.value.trim()) {
    return showToast({ icon: 'none', message: '请输入真实姓名' })
  }
  if (!idCard.value.trim()) {
    return showToast({ icon: 'none', message: '请输入有效身份证号' })
  }
  showToast({ icon: 'none', message: '原站实名认证接口未开放' })
}
</script>

<template>
  <div class="cert-page">
    <!-- 导航 -->
    <div class="navbar">
      <img class="back" src="/static/icon/back-btn.png" alt="返回" @click="router.back()" />
      <div class="nav-title">实名认证</div>
    </div>

    <!-- 已认证态：标题 + 认证卡片（对齐原站 @16,54 起） -->
    <template v-if="certified">
      <div class="done-box">
        <!-- 标题 @132,90 -->
        <div class="done-title">
          <img src="/static/icon/rzbz.png" alt="" />
          <span>您已完成认证</span>
        </div>
        <!-- 认证卡片 @20,129 350x208 -->
        <div class="info-card">
          <div class="row">
            <span class="label">姓名</span>
            <span class="value">{{ userInfo.real_name || '' }}</span>
          </div>
          <div class="row">
            <span class="label">身份证</span>
            <span class="value">{{ userInfo.id_card || '' }}</span>
          </div>
          <img class="medal" src="/static/icon/rzCard-success.png" alt="" />
          <div class="safe">
            <img src="/static/icon/rzbz.png" alt="" />
            <span>信息安全保障中</span>
          </div>
        </div>
      </div>
    </template>

    <template v-else>
    <!-- 输入框1@32,68 326x48 -->
    <div class="ipt">
      <span class="ipt-label">真实姓名</span>
      <input class="ipt-input" type="text" v-model="realName" placeholder="请输入真实姓名" />
    </div>

    <!-- 输入框2@32,131 326x48 -->
    <div class="ipt" style="top: 131px">
      <span class="ipt-label">身份证号</span>
      <input class="ipt-input" type="text" v-model="idCard" placeholder="请输入有效身份证号" />
    </div>

    <!-- 输入框3@32,194 326x48（手机号原站已填，显示脱敏值且不可编辑） -->
    <div class="ipt" style="top: 194px">
      <span class="ipt-label">手机号码</span>
      <input class="ipt-input" type="text" :value="mobileMasked" readonly />
    </div>

    <!-- 按钮@16,773 358x47 -->
    <div class="submit-btn" @click="onSubmit">立即认证</div>
    </template>
  </div>
</template>

<style scoped>
/* ---------- 已认证态（对齐原站实测几何） ---------- */
.done-box {
  position: absolute;
  left: 16px;
  top: 54px;
  width: 358px;
  border-radius: 8px;
}
.done-title {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 24px;
  /* 原站 staticStyle margin-top:70rpx（35px）+ margin-bottom:30rpx（15px） */
  margin-top: 35px;
  margin-bottom: 15px;
}
.done-title img {
  width: 22px;
  height: 22px;
}
.done-title span {
  margin-left: 8px;
  font-size: 16px;
  font-weight: 700;
  color: #23262f;
  line-height: 17px;
}
.info-card {
  position: relative;
  width: 350px;
  height: 208px;
  margin: 0 auto;
  background-image: url('/static/bg/rzCard.png');
  background-size: 100% 100%;
  padding: 40px 0 0 31px;
  box-sizing: border-box;
}
.info-card .row {
  display: flex;
  flex-direction: column;
  margin-bottom: 20px;
}
.info-card .label {
  font-size: 12px;
  color: #fff;
  line-height: 14px;
}
.info-card .value {
  margin-top: 4px;
  font-size: 14px;
  color: #fff;
  line-height: 21px;
}
.info-card .medal {
  position: absolute;
  right: 21px;
  top: 53px;
  width: 100px;
  height: 97px;
}
.info-card .safe {
  position: absolute;
  left: 31px;
  top: 162px;
  display: flex;
  align-items: center;
}
.info-card .safe img {
  width: 16px;
  height: 16px;
}
.info-card .safe span {
  margin-left: 4px;
  font-size: 12px;
  color: #fff;
  line-height: 18px;
}
/* __STYLE_MERGE_MARK__ */
/* .container 390x844 白底 */
.cert-page {
  position: relative;
  width: 390px;
  min-height: 844px;
  background: #fff;
}

/* 导航标题「实名认证」@165,16 60x16（居中于 195） */
.navbar { position: relative; width: 390px; height: 44px; }
.back { position: absolute; left: 13px; top: 10px; width: 24px; height: 24px; z-index: 2; }
.nav-title {
  position: absolute; left: 95px; top: 16px; width: 200px; height: 16px;
  line-height: 16px; text-align: center; color: #333; font-size: 15px; font-weight: 700;
}

/* 输入框.ipt 326x48 圆角15，标签左占位56，输入区右对齐 236宽 */
.ipt {
  position: absolute;
  left: 32px;
  top: 68px;
  width: 326px;
  height: 48px;
  box-sizing: border-box;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 15px;
  background: transparent;
  display: flex;
  align-items: center;
  padding: 0 17px;
}
/* 标签 14px/400 rgb(51,51,51) */
.ipt-label {
  color: #333;
  font-size: 14px;
  font-weight: 400;
  line-height: 15px;
  flex-shrink: 0;
}
/* 输入区：宽236，右对齐，占位符 14px rgb(153,153,153) */
.ipt-input {
  flex: 1;
  margin-left: 0;
  height: 26px;
  border: none;
  outline: none;
  background: transparent;
  text-align: right;
  color: #333;
  font-size: 14px;
  font-weight: 400;
  line-height: 26px;
  padding: 0;
}
.ipt-input::placeholder {
  color: #999;
  font-size: 14px;
  text-align: right;
}

/* 按钮「立即认证」@16,773 358x47 bg rgb(227,227,227) 圆角23 */
.submit-btn {
  position: absolute;
  left: 16px;
  top: 773px;
  width: 358px;
  height: 47px;
  line-height: 47px;
  text-align: center;
  background: rgb(227, 227, 227);
  border-radius: 23px;
  color: #666;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}
</style>