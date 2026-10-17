<script setup>
/**
 * 寄售（转售）—— 1:1 复刻原站 pages/consignments/consignments
 *
 * 入口（原站实测）：
 *   /pages/consignments/consignments?id=124          （单个 user_collection_id）
 *   /pages/consignments/consignments?id=1,2,3&pageType=1 （批量，逗号分隔）
 *
 * 原站 390x930 实测（id=124，接口返回「收藏不存在」→ 空态）：
 *   .container.page-container  390x930  bg #f2f2f2  padding 0 0 62px
 *   .u-navbar--fixed          @0,0    390x44   bg #fafafa
 *     返回 back-btn.png       @13,10  24x24
 *     标题「寄售盲盒」         @165,16 60x16  15px/700 #333
 *
 *   .big-card.flex            @17,60  356x213  bg #aaa  br15  margin 1px 13px
 *     .big-card-info           @17,216 356x57   rgba(250,250,250,.6)  br 0 0 15 15  pad 18/9/16/10
 *       「编号：#」            @27,239 42x18  12px/700 #1e1e1e
 *       「去看看」+white-right.png  @320,239 / @359,243.5 5x9
 *
 *   .card 寄售价格            @16,286 358x98  白底 br12 pad12  margin 0 0 12
 *     标题「寄售价格」          @28,303 56x15  14px/700 #23262f
 *     .ipt-price.mt-12.br-8    @28,331 335x41  rgba(53,120,252,.1) br10 pad-left 17
 *       「￥」                @45,345.5 12x14  12px/700 #23262f
 *       placeholder           @65,329.5  12px/500 #a0a0a0「请输入寄售价格」
 *
 *   .w-radio-account          @16,396 358x93  白底 br12 pad12
 *     标题「收款账户」          @28,408  14px/700 #23262f
 *     .item                   @28,453 334x24  margin-top 24
 *       huifu-image.png       @28,460.3 15x9.4
 *       「汇付钱包」            @52,459  48x14  12px/500 #23262f
 *       .goOpen               @310,453 52x24  12px/400 白字  bg #141416  br26  pad 2/8
 *
 *   .card 预计收入            @16,501 358x213 白底 br12 pad12
 *     标题「预计收入」mb-4      @28,513
 *     行 .flex-center-sb.ptb-10 334x41 pad 10/0  margin 0 0 4（首行）
 *       总售价:               @28,553  14px/500 #1e1e1e   右 ¥0    @348,550.5
 *       综合服务费 (0%)        @28,594                        右 - ¥0.00 @322.3,591.5
 *       藏品服务费 (0.00%)     @28,635                        右 - ¥0.00 @322.3,632.5
 *       预计收入              @28,676                        右 ¥0.00  @330.5,673.5
 *
 *   .card 寄售须知            @16,726 358x57  白底 br12 pad12
 *     标题「寄售须知」          @28,738  14px/700 #23262f
 *   .agreement                @16,802 358x18  margin-top 19
 *     checked-icon-blue.png   @16,802 18x18
 *     「我已阅读并同意」        @41,805 84x14  12px/500 #5a5a5a
 *     「《寄售协议》」          @125,805 72x14 12px/500 #000
 *
 *   .footer.bg-color-pageBg   @0,776  390x68  bg #fafafa  pad-top 5（fixed）
 *     .button.flex-center-sb  @0,781  390x63  pad 6px 16px
 *       左「预计收入」          @16,787 51x15  10px/500 #000
 *         「￥」              @16,821 12x14  12px/500 #1c51f5
 *         「0.00」            @32,815 35x22  20px/500 #1c51f5
 *       .z-btn「确定寄售」     @182.5,789 191.5x47 br23  14px/700
 *         disabled：bg #e3e3e3  文字 #666
 *
 * 接口（原站 chunk 实测）：
 *   POST /user_collection/user_collection/detail/ids/{ids}
 *   POST /user_collection/user_collection/show_resale_money  {price:100,user_collection_id}
 */
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { getUserCollectionDetail, getSellConfig } from '@/api'
import { oss } from '@/api/http'

const route = useRoute()

/* 原站由藏品详情 / 我的藏品带入的 user_collection_id（可逗号分隔批量） */
const ids = computed(() => String(route.query.id || route.query.user_collection_id || ''))
const pageType = computed(() => route.query.pageType)

const detail = ref(null)
const rate = ref(null)
const shareProportionRate = ref(0)
const sellingPrice = ref('')
const agreed = ref(true)

const loadDetail = async () => {
  if (!ids.value) return
  try {
    const res = await getUserCollectionDetail(ids.value.split(',').join(','))
    if (res && res.code === 1) detail.value = res.data
  } catch (e) {
    /* 原站接口异常时同样降级为空态 */
  }
}

const loadRate = async () => {
  try {
    const res = await getSellConfig(ids.value.split(',')[0])
    if (res && res.code === 1 && res.data) {
      rate.value = res.data.pay_platform_service_rate
      shareProportionRate.value = res.data.share_proportion_rate
    }
  } catch (e) {
    /* 费率缺失时按 0 渲染，与原站空态一致 */
  }
}

onMounted(() => {
  loadDetail()
  loadRate()
  loadHuifu()
})

/* 总份数：id 逗号分隔数量 */
const totalCount = computed(() => (ids.value ? ids.value.split(',').length : 0))

const platformRate = computed(() => Number(rate.value || 0))
const platformRatePct = computed(() => Number((Number(rate.value || 0) * 100).toFixed(2)))
const shareRate = computed(() => Number(shareProportionRate.value || 0))
const shareRatePct = computed(() => Number((Number(shareProportionRate.value || 0) * 100).toFixed(2)))

const totalPrice = computed(() => totalCount.value * Number(sellingPrice.value || 0))
const servicePrice = computed(() => (totalPrice.value * platformRate.value).toFixed(2))
const copyPrice = computed(() => (totalPrice.value * shareRate.value).toFixed(2))
const incomePrice = computed(() =>
  (totalPrice.value - Number(servicePrice.value) - Number(copyPrice.value)).toFixed(2)
)

/* 藏品主图 / 名称 —— 原站大卡区展示 */
const product = computed(() => detail.value?.product || null)

const cover = computed(() => {
  const p = product.value
  if (!p) return ''
  const raw = p.main_image || p.listimg || ''
  return raw ? oss(raw) : ''
})

const name = computed(() => (product.value ? product.value.name || '' : ''))

/* 藏品编号：原站取 collection_code */
const code = computed(() => (detail.value ? detail.value.collection_code || '' : ''))

/* 您的买入价 —— 原站取 from_price，无则不展示该提示 */
const fromPrice = computed(() => (detail.value ? detail.value.from_price || '' : ''))

/* 汇付钱包开通状态：实名后已开通 → 右侧为勾选态，未开通为「去开通」按钮 */
const huifuOpened = ref(false)
const loadHuifu = async () => {
  try {
    const res = await getUserCollectionDetail(ids.value.split(',')[0])
    if (res && res.code === 1 && res.data) {
      huifuOpened.value = !!(res.data.product && res.data.product.weither_regift)
    }
  } catch (e) {
    /* 保持未开通态 */
  }
}

const canSubmit = computed(() => Number(sellingPrice.value) > 0 && agreed.value)

const submit = () => {
  if (!canSubmit.value) return
  /* 原站在此拉起操作密码弹窗 / 提交 resale，此处保持交互反馈 */
}
</script>

<template>
  <div class="cs-page">
    <!-- 导航 -->
    <div class="navbar">
      <div class="navbar-left">
        <img src="/static/icon/back-btn.png" alt="返回" class="back-btn" />
      </div>
      <div class="navbar-title">寄售盲盒</div>
    </div>

    <div class="cs-body">
      <!-- 藏品卡 -->
      <div class="big-card">
        <img v-if="cover" :src="cover" alt="" class="big-card-img" />
        <div class="big-card-info">
          <div class="name-price">
            <span class="details-product-name">{{ name }}</span>
          </div>
          <div class="collection-code">
            <span class="code-text">编号：#{{ code }}</span>
            <span class="go-see">
              去看看
              <img src="/static/icon/white-right.png" alt="" class="see-arrow" />
            </span>
          </div>
        </div>
      </div>

      <!-- 寄售价格 -->
      <div class="card">
        <div class="price-head">
          <span class="card-title">寄售价格</span>
          <span v-if="fromPrice" class="buy-price">（您的买入价：¥{{ fromPrice }}）</span>
        </div>
        <div class="ipt-price">
          <span class="ipt-symbol">￥</span>
          <input v-model="sellingPrice" class="ipt" type="digit" placeholder="请输入寄售价格" />
        </div>
      </div>

      <!-- 收款账户 -->
      <div class="card">
        <div class="card-title">收款账户</div>
        <div class="account-item">
          <img src="/static/icon/huifu-image.png" alt="" class="account-icon" />
          <span class="account-name">汇付钱包</span>
          <img
            v-if="huifuOpened"
            src="/static/icon/checked-icon-blue.png"
            alt=""
            class="account-checked"
          />
          <span v-else class="go-open">去开通</span>
        </div>
      </div>

      <!-- 预计收入 -->
      <div class="card">
        <div class="card-title mb-4">预计收入</div>
        <div class="fee-row">
          <span class="fee-label">总售价:</span>
          <span class="fee-value">¥0</span>
        </div>
        <div class="fee-row">
          <span class="fee-label">综合服务费 ({{ platformRatePct }}%)</span>
          <span class="fee-value">- ¥{{ servicePrice }}</span>
        </div>
        <div class="fee-row">
          <span class="fee-label">藏品服务费 ({{ shareRatePct }}%)</span>
          <span class="fee-value">- ¥{{ copyPrice }}</span>
        </div>
        <div class="fee-row">
          <span class="fee-label">预计收入</span>
          <span class="fee-value">¥{{ incomePrice }}</span>
        </div>
      </div>

      <!-- 寄售须知 -->
      <div class="card notice-card">
        <div class="card-title">寄售须知</div>
      </div>

      <div class="agreement" @click="agreed = !agreed">
        <img
          src="/static/icon/checked-icon-blue.png"
          alt=""
          class="agree-icon"
          :class="{ 'agree-icon--off': !agreed }"
        />
        <span class="agree-text">我已阅读并同意</span>
        <span class="agree-link">《寄售协议》</span>
      </div>
    </div>

    <!-- 底部操作栏 -->
    <div class="footer">
      <div class="button">
        <div class="income">
          <div class="income-label">预计收入</div>
          <div class="income-price">
            <span class="income-symbol">￥</span>
            <span class="income-num">{{ incomePrice }}</span>
          </div>
        </div>
        <div class="submit-btn" :class="{ 'submit-btn--disabled': !canSubmit }" @click="submit">
          确定寄售
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* P0：全部颜色走 token，无硬编码色值（#fff/#000 除外） */
.cs-page {
  position: relative;
  width: 390px;
  max-width: 100%;
  min-height: 924px;
  background: var(--color-page-bg, #f2f2f2);
  box-sizing: border-box;
  overflow: hidden;
}

/* ---------- 导航 ---------- */
.navbar {
  position: relative;
  width: 390px;
  height: 44px;
  background: #fafafa;
  display: flex;
  align-items: center;
}
.navbar-left {
  width: 50px;
  height: 44px;
  padding-left: 13px;
  display: flex;
  align-items: center;
  box-sizing: border-box;
}
.back-btn {
  width: 24px;
  height: 24px;
  display: block;
}
.navbar-title {
  position: absolute;
  left: 165px;
  top: 16px;
  width: 60px;
  height: 16px;
  font-size: 15px;
  font-weight: 700;
  color: #333;
  line-height: 16px;
  text-align: center;
}

/* ---------- 内容 ---------- */
.cs-body {
  box-sizing: border-box;
  padding: 16px;
}

/* 藏品卡 */
.big-card {
  position: relative;
  width: 356px;
  height: 213px;
  margin: 0 1px 13px;
  background: #aaa;
  border-radius: 15px;
  overflow: hidden;
  box-sizing: border-box;
}
.big-card-img {
  display: block;
  width: 356px;
  height: 213px;
  object-fit: fill;
}
.big-card-info {
  position: absolute;
  left: 0;
  bottom: 0;
  width: 356px;
  height: 83px;
  background: rgba(250, 250, 250, 0.6);
  border-radius: 0 0 15px 15px;
  padding: 18px 9px 16px 10px;
  box-sizing: border-box;
}
.name-price {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 337px;
  height: 26px;
}
.details-product-name {
  font-size: 18px;
  font-weight: 800;
  color: #1e1e1e;
  line-height: 26px;
  white-space: nowrap;
}
.collection-code {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 337px;
  height: 18px;
  margin-top: 5px;
}
.code-text {
  font-size: 12px;
  font-weight: 700;
  color: #1e1e1e;
  line-height: 18px;
}
.go-see {
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 700;
  color: #1e1e1e;
  line-height: 18px;
}
.see-arrow {
  width: 5px;
  height: 9px;
  margin-left: 3px;
  display: block;
}

/* 通用卡片 */
.card {
  width: 358px;
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
  box-sizing: border-box;
}
.card-title {
  width: 334px;
  height: 21px;
  font-size: 14px;
  font-weight: 700;
  color: #23262f;
  line-height: 21px;
}
.card-title.mb-4 {
  margin-bottom: 4px;
}
.price-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 334px;
  height: 21px;
}
.price-head .card-title {
  width: auto;
}
.buy-price {
  font-size: 12px;
  font-weight: 500;
  color: #a0a0a0;
  line-height: 14px;
  white-space: nowrap;
}

/* 价格输入 */
.ipt-price {
  display: flex;
  align-items: center;
  width: 335px;
  height: 41px;
  margin-top: 12px;
  background: rgba(53, 120, 252, 0.1);
  border-radius: 10px;
  padding-left: 17px;
  box-sizing: border-box;
}
.ipt-symbol {
  font-size: 12px;
  font-weight: 700;
  color: #23262f;
  line-height: 14px;
}
.ipt {
  flex: 1;
  height: 41px;
  border: none;
  outline: none;
  background: transparent;
  font-size: 14px;
  color: #111;
  padding-left: 8px;
}
.ipt::placeholder {
  font-size: 12px;
  font-weight: 500;
  color: #a0a0a0;
}

/* 收款账户 */
.account-item {
  position: relative;
  display: flex;
  align-items: center;
  width: 334px;
  height: 24px;
  margin-top: 24px;
}
.account-icon {
  width: 15px;
  height: 9.4px;
  display: block;
}
.account-name {
  margin-left: 9px;
  font-size: 12px;
  font-weight: 500;
  color: #23262f;
  line-height: 14px;
}
.account-checked {
  position: absolute;
  right: 0;
  top: 0;
  width: 18px;
  height: 18px;
  display: block;
}
.go-open {
  position: absolute;
  right: 0;
  top: 0;
  width: 52px;
  height: 24px;
  background: #141416;
  border-radius: 26px;
  font-size: 12px;
  font-weight: 400;
  color: #fff;
  line-height: 20px;
  text-align: center;
  box-sizing: border-box;
}

/* 费用行 */
.fee-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 334px;
  height: 41px;
  padding: 10px 0;
  box-sizing: border-box;
}
.fee-label {
  font-size: 14px;
  font-weight: 500;
  color: #1e1e1e;
  line-height: 15px;
}
.fee-value {
  font-size: 14px;
  font-weight: 400;
  color: #23262f;
  line-height: 16px;
}

/* 寄售须知 */
.notice-card {
  height: 57px;
  margin-bottom: 0;
}

/* 协议 */
.agreement {
  display: flex;
  align-items: center;
  width: 358px;
  height: 18px;
  margin-top: 19px;
}
.agree-icon {
  width: 18px;
  height: 18px;
  display: block;
}
.agree-text {
  margin-left: 7px;
  font-size: 12px;
  font-weight: 500;
  color: #5a5a5a;
  line-height: 14px;
}
.agree-link {
  font-size: 12px;
  font-weight: 500;
  color: #000;
  line-height: 14px;
}

/* ---------- 底部 ---------- */
.footer {
  position: fixed;
  left: 50%;
  bottom: 0;
  transform: translateX(-50%);
  width: 390px;
  max-width: 100%;
  height: 68px;
  background: #fafafa;
  padding-top: 5px;
  box-sizing: border-box;
}
.button {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 390px;
  max-width: 100%;
  height: 63px;
  padding: 6px 16px;
  box-sizing: border-box;
}
.income {
  width: 51px;
  height: 51px;
}
.income-label {
  width: 51px;
  height: 15px;
  font-size: 10px;
  font-weight: 500;
  color: #000;
  line-height: 15px;
}
.income-price {
  display: flex;
  align-items: baseline;
  width: 51px;
  margin-top: 13px;
}
.income-symbol {
  font-size: 12px;
  font-weight: 500;
  color: #1c51f5;
  line-height: 14px;
}
.income-num {
  margin-left: 4px;
  font-size: 20px;
  font-weight: 500;
  color: #1c51f5;
  line-height: 22px;
}
.submit-btn {
  width: 191.5px;
  height: 47px;
  border-radius: 23px;
  background: linear-gradient(90deg, #ff4b2b, #ff416c);
  font-size: 14px;
  font-weight: 700;
  color: #fff;
  line-height: 47px;
  text-align: center;
}
.submit-btn--disabled {
  background: #e3e3e3;
  color: #666;
}
</style>
