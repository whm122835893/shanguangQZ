import { createRouter, createWebHashHistory } from 'vue-router'

const routes = [
  { path: '/', redirect: '/pages/index/index' },
  {
    path: '/pages/index/index',
    component: () => import('@/pages/index/Index.vue'),
    meta: { title: '首页', tab: 'index' }
  },
  {
    path: '/pages/market/market',
    component: () => import('@/pages/market/Market.vue'),
    meta: { title: '探索', tab: 'market' }
  },
  {
    path: '/pages/my/my',
    component: () => import('@/pages/my/My.vue'),
    meta: { title: '我的', tab: 'my', auth: true }
  },
  {
    path: '/pages/blindBox/index',
    component: () => import('@/pages/blindBox/BlindBoxIndex.vue'),
    meta: { title: '我的盲盒', auth: true }
  },
  {
    path: '/pages/product/collection',
    component: () => import('@/pages/product/Collection.vue'),
    meta: { title: '藏品详情', auth: true }
  },
  {
    path: '/pages/blindBox/result',
    component: () => import('@/pages/blindBox/BlindBoxResult.vue'),
    meta: { title: '盲盒结果', auth: true }
  },
  {
    path: '/pages/blindBox/result2',
    component: () => import('@/pages/blindBox/BlindBoxResult2.vue'),
    meta: { title: '盲盒结果', auth: true }
  },
  {
    path: '/pages/regift/record',
    component: () => import('@/pages/order/RegiftRecord.vue'),
    meta: { title: '转售记录', auth: true }
  },
  {
    path: '/pages/regift/regift',
    component: () => import('@/pages/order/Regift.vue'),
    meta: { title: '转赠', auth: true }
  },
  /* ---- 原站静默重定向首页的路由（已实测确认） ---- */
  {
    path: '/pages/compound/compound',
    component: () => import('@/pages/compound/Compound.vue'),
    meta: { title: '合成活动', auth: true }
  },
  {
    path: '/pages/compound/batchcompound',
    component: () => import('@/pages/common/RedirectHome.vue'),
    meta: { title: '批量合成', auth: true }
  },
  {
    path: '/pages/shop/detail/box',
    component: () => import('@/pages/common/RedirectHome.vue'),
    meta: { title: '盒子详情', auth: true }
  },
  {
    path: '/pages/shop/detail/collection',
    component: () => import('@/pages/common/RedirectHome.vue'),
    meta: { title: '藏品详情', auth: true }
  },
  {
    path: '/pages/shop/detail/goods',
    component: () => import('@/pages/common/RedirectHome.vue'),
    meta: { title: '商品详情', auth: true }
  },
  {
    path: '/pages/product/box',
    component: () => import('@/pages/common/RedirectHome.vue'),
    meta: { title: '盒子', auth: true }
  },
  {
    path: '/pages/compound/record',
    component: () => import('@/pages/order/CompoundRecord.vue'),
    meta: { title: '活动记录', auth: true }
  },
  {
    path: '/pages/compound/schemeDetails',
    component: () => import('@/pages/order/SchemeDetails.vue'),
    meta: { title: '合成详情', auth: true }
  },
  {
    path: '/pages/compound/result',
    component: () => import('@/pages/order/CompoundResult.vue'),
    meta: { title: '合成结果', auth: true }
  },
  {
    path: '/pages/compound/btchrecord',
    component: () => import('@/pages/order/BtchRecord.vue'),
    meta: { title: '活动记录', auth: true }
  },
  {
    path: '/pages/compound/btchschemeDetails',
    component: () => import('@/pages/order/BtchSchemeDetails.vue'),
    meta: { title: '活动', auth: true }
  },
  {
    path: '/pages/shop/detailList',
    component: () => import('@/pages/shop/DetailList.vue'),
    meta: { title: '水晶明细', auth: true }
  },
  {
    path: '/pages/notice/noticeDetails',
    component: () => import('@/pages/notice/NoticeDetails.vue'),
    meta: { title: '公告详情', auth: true }
  },
  {
    path: '/pages/notice/search',
    component: () => import('@/pages/notice/NoticeSearch.vue'),
    meta: { title: '搜索', auth: true }
  },
  {
    path: '/pages/notice/sendRumor',
    component: () => import('@/pages/notice/SendRumor.vue'),
    meta: { title: '发表', auth: true }
  },
  {
    path: '/pages/leaderboard/leaderboard',
    component: () => import('@/pages/rank/Leaderboard.vue'),
    meta: { title: '光子荣誉榜', auth: true }
  },
  {
    path: '/pages/rank/masterRank',
    component: () => import('@/pages/rank/MasterRank.vue'),
    meta: { title: '大师动态', auth: true }
  },
  {
    path: '/pages/market/marketGoodsList',
    component: () => import('@/pages/market/MarketGoodsList.vue'),
    meta: { title: '藏品列表', auth: true }
  },
  {
    path: '/pages/market/detail',
    component: () => import('@/pages/market/Detail.vue'),
    meta: { title: '藏品详情', auth: true }
  },
  {
    path: '/pages/notice/notice',
    component: () => import('@/pages/notice/Notice.vue'),
    meta: { title: '公告中心' }
  },
  {
    path: '/pages/sign/index',
    component: () => import('@/pages/sign/Sign.vue'),
    meta: { title: '签到中心', auth: true }
  },
  {
    path: '/pages/task/index',
    component: () => import('@/pages/task/Task.vue'),
    meta: { title: '活动中心', auth: true }
  },
  {
    path: '/pages/shop/shop',
    component: () => import('@/pages/shop/Shop.vue'),
    meta: { title: '工会商城', auth: true }
  },
  {
    path: '/pages/shop/record',
    component: () => import('@/pages/shop/Record.vue'),
    meta: { title: '兑换记录', auth: true }
  },
  {
    path: '/pages/order/orderSubmit',
    component: () => import('@/pages/order/OrderSubmit.vue'),
    meta: { title: '确认订单', auth: true }
  },
  /* 幸运抽奖（原站实测路由） */
  {
    path: '/pages/lottery/lottery',
    component: () => import('@/pages/lottery/Lottery.vue'),
    meta: { title: '幸运抽奖', auth: true }
  },
  {
    path: '/pages/lottery/rule',
    component: () => import('@/pages/lottery/Rule.vue'),
    meta: { title: '玩法说明', auth: true }
  },
  {
    path: '/pages/lottery/record',
    component: () => import('@/pages/lottery/Record.vue'),
    meta: { title: '中奖记录', auth: true }
  },
  /* 我的页二级入口（原站实测路由） */
  {
    path: '/pages/order/list',
    component: () => import('@/pages/order/OrderList.vue'),
    meta: { title: '我的订单', auth: true }
  },
  {
    path: '/pages/order/order',
    redirect: '/pages/order/list'
  },
  {
    path: '/pages/myCollection/myCollection',
    component: () => import('@/pages/collection/MyCollection.vue'),
    meta: { title: '我的藏品', auth: true }
  },
  {
    path: '/pages/message/message',
    component: () => import('@/pages/message/Message.vue'),
    meta: { title: '消息', auth: true }
  },
  {
    path: '/pages/my/info',
    component: () => import('@/pages/my/Info.vue'),
    meta: { title: '设置', auth: true }
  },
  {
    path: '/pages/my/other-power',
    component: () => import('@/pages/my/OtherPower.vue'),
    meta: { title: '其他功能', auth: true }
  },
  {
    path: '/pages/my/address/list',
    component: () => import('@/pages/address/AddressList.vue'),
    meta: { title: '收货地址', auth: true }
  },
  {
    path: '/pages/my/address/edit',
    component: () => import('@/pages/address/AddressEdit.vue'),
    meta: { title: '添加收货地址', auth: true }
  },
  {
    path: '/pages/my/privacy',
    component: () => import('@/pages/my/Privacy.vue'),
    meta: { title: '隐私设置', auth: true }
  },
  {
    path: '/pages/my/cancellation',
    component: () => import('@/pages/my/Cancellation.vue'),
    meta: { title: '注销账号申请', auth: true }
  },
  {
    path: '/pages/my/actionPwd/setPwd',
    component: () => import('@/pages/my/SetPwd.vue'),
    meta: { title: '设置操作密码', auth: true }
  },
  {
    path: '/pages/my/actionPwd/getCode',
    component: () => import('@/pages/my/GetCode.vue'),
    meta: { title: '验证手机号', auth: true }
  },
  {
    path: '/pages/my/about',
    component: () => import('@/pages/my/About.vue'),
    meta: { title: '关于我们', auth: true }
  },
  {
    path: '/pages/my/service',
    component: () => import('@/pages/my/Service.vue'),
    meta: { title: '客服', auth: true }
  },
  {
    path: '/pages/my/svip',
    component: () => import('@/pages/my/Svip.vue'),
    meta: { title: '会员中心', auth: true }
  },
  {
    path: '/pages/my/flowDoc',
    component: () => import('@/pages/my/FlowDoc.vue'),
    meta: { title: '流转记录', auth: true }
  },
  {
    path: '/pages/wallet/wallet',
    component: () => import('@/pages/wallet/Wallet.vue'),
    meta: { title: '我的钱包', auth: true }
  },
  {
    path: '/pages/certification/certification',
    component: () => import('@/pages/certification/Certification.vue'),
    meta: { title: '实名认证', auth: true }
  },
  {
    path: '/pages/certification/certificationStatus',
    component: () => import('@/pages/certification/CertificationStatus.vue'),
    meta: { title: '认证结果', auth: true }
  },
  {
    path: '/pages/market/tradingInformation',
    component: () => import('@/pages/market/TradingInformation.vue'),
    meta: { title: '成交记录', auth: true }
  },
  {
    path: '/pages/market/submitEntrust',
    component: () => import('@/pages/market/SubmitEntrust.vue'),
    meta: { title: '发起委托', auth: true }
  },
  {
    path: '/pages/market/sellInfo',
    component: () => import('@/pages/market/SellInfo.vue'),
    meta: { title: '藏品行情', auth: true }
  },
  {
    path: '/pages/market/categoryManager',
    component: () => import('@/pages/market/CategoryManager.vue'),
    meta: { title: '分类', auth: true }
  },
  {
    path: '/pages/consignments/myConsignment',
    component: () => import('@/pages/consignments/MyConsignment.vue'),
    meta: { title: '寄售管理', auth: true }
  },
  {
    path: '/pages/consignments/search',
    component: () => import('@/pages/consignments/Search.vue'),
    meta: { title: '寄售搜索', auth: true }
  },
  {
    path: '/pages/consignments/consignments',
    component: () => import('@/pages/consignments/Consign.vue'),
    meta: { title: '寄售', auth: true }
  },
  {
    path: '/pages/order/defaultDetail',
    component: () => import('@/pages/order/DefaultDetail.vue'),
    meta: { title: '订单详情', auth: true }
  },
  {
    path: '/pages/order/marketDetail',
    component: () => import('@/pages/order/MarketDetail.vue'),
    meta: { title: '订单详情', auth: true }
  },
  {
    path: '/pages/order/regiftDetail',
    component: () => import('@/pages/order/RegiftDetail.vue'),
    meta: { title: '转售订单详情', auth: true }
  },
  {
    path: '/pages/myCollection/collectionDetails',
    component: () => import('@/pages/collection/CollectionDetails.vue'),
    meta: { title: '藏品详情', auth: true }
  },
  {
    path: '/pages/marketCapRanking/marketCapRanking',
    component: () => import('@/pages/rank/MarketCapRanking.vue'),
    meta: { title: '市值排行', auth: true }
  },
  {
    path: '/pages/order/entrust/info',
    component: () => import('@/pages/order/EntrustInfo.vue'),
    meta: { title: '订单详情', auth: true }
  },
  {
    path: '/pages/order/beg/info',
    component: () => import('@/pages/order/BegInfo.vue'),
    meta: { title: '订单详情', auth: true }
  },
  {
    path: '/pages/kline/buy',
    component: () => import('@/pages/kline/Buy.vue'),
    meta: { title: '行情购买', auth: true }
  },
  {
    path: '/pages/kline/info',
    component: () => import('@/pages/kline/Info.vue'),
    meta: { title: '行情详情', auth: true }
  },
  {
    path: '/pages/market/submitBegBuy',
    component: () => import('@/pages/market/SubmitBegBuy.vue'),
    meta: { title: '发起求购', auth: true }
  },
  {
    path: '/pages/order/entrust/list',
    component: () => import('@/pages/order/EntrustList.vue'),
    meta: { title: '我的委托', auth: true }
  },
  {
    path: '/pages/order/beg/list',
    component: () => import('@/pages/order/BegList.vue'),
    meta: { title: '我的求购', auth: true }
  },
  {
    path: '/pages/market/search',
    component: () => import('@/pages/market/Search.vue'),
    meta: { title: '搜索', auth: true }
  },
  {
    path: '/pages/rank/rankList',
    component: () => import('@/pages/rank/RankList.vue'),
    meta: { title: '榜单', auth: true }
  },
  {
    path: '/pages/union/unionRank',
    component: () => import('@/pages/union/UnionRank.vue'),
    meta: { title: '工会排名', auth: true }
  },
  {
    path: '/pages/rules/rules',
    component: () => import('@/pages/rules/Rules.vue'),
    meta: { title: '活动规则', auth: true }
  },
  {
    path: '/pages/invitation/invitation',
    component: () => import('@/pages/invitation/Invitation.vue'),
    meta: { title: '邀请好友', auth: true }
  },
  {
    path: '/pages/login/login',
    component: () => import('@/pages/login/Login.vue'),
    meta: { title: '登录' }
  },
  {
    path: '/pages/login/login-pass',
    component: () => import('@/pages/login/LoginPass.vue'),
    meta: { title: '账号登录' }
  },
  {
    path: '/pages/login/register',
    component: () => import('@/pages/login/Register.vue'),
    meta: { title: '注册' }
  },
  {
    path: '/pages/login/forgetPwd',
    component: () => import('@/pages/login/ForgetPwd.vue'),
    meta: { title: '忘记密码' }
  },
  /* ---- 第四批 ---- */
  {
    path: '/pages/rank/userBuy',
    component: () => import('@/pages/rank/UserBuy.vue'),
    meta: { title: '买入清单', auth: true }
  },
  {
    path: '/pages/blindBox/openRecord',
    component: () => import('@/pages/blindBox/OpenRecord.vue'),
    meta: { title: '开盒记录', auth: true }
  },
  {
    path: '/pages/rebate/leaderboard',
    component: () => import('@/pages/rebate/RebateLeaderboard.vue'),
    meta: { title: '返佣排行榜', auth: true }
  },
  {
    path: '/pages/rebate/rebateInviteDetail',
    component: () => import('@/pages/rebate/RebateInviteDetail.vue'),
    meta: { title: '被邀请人清单', auth: true }
  },
  {
    path: '/pages/activity/record',
    component: () => import('@/pages/activity/ActivityRecord.vue'),
    meta: { title: '中签记录', auth: true }
  },
  /* ---- 第五批 ---- */
  {
    path: '/pages/union/news',
    component: () => import('@/pages/union/UnionNews.vue'),
    meta: { title: '消息通知', auth: true }
  },
  {
    path: '/pages/union/userRank',
    component: () => import('@/pages/union/UserRank.vue'),
    meta: { title: '工会成员排名', auth: true }
  },
  {
    path: '/pages/union/tradeUnion',
    component: () => import('@/pages/union/TradeUnion.vue'),
    meta: { title: '闪光工会', auth: true }
  },
  {
    path: '/pages/activeSelected/activeSelected',
    component: () => import('@/pages/activity/ActiveSelected.vue'),
    meta: { title: '活动中心' }
  },
  {
    path: '/pages/compound/btchresult',
    component: () => import('@/pages/compound/BtchResult.vue'),
    meta: { title: '合成结果' }
  },
  {
    path: '/pages/draw/Leaderboard/Leaderboard',
    component: () => import('@/pages/activity/DrawLeaderboard.vue'),
    meta: { title: '参与人数', auth: true }
  },
  {
    path: '/pages/draw/communityShare/communityShare',
    component: () => import('@/pages/activity/CommunityShare.vue'),
    meta: { title: '分享', auth: true }
  },
  {
    path: '/pages/activity/draw',
    component: () => import('@/pages/activity/ActivityDraw.vue'),
    meta: { title: '抽签发售' }
  },
  {
    path: '/pages/my/join',
    component: () => import('@/pages/my/Join.vue'),
    meta: { title: '加入社群', auth: true }
  },
  {
    path: '/pages/index/start',
    component: () => import('@/pages/index/Start.vue'),
    meta: { title: '启动页' }
  },
  {
    path: '/pages/blindBox/blindBoxDetails',
    component: () => import('@/pages/blindBox/BlindBoxDetails.vue'),
    meta: { title: '盲盒详情' }
  },
  {
    path: '/pages/draw/InvitationFriendAssist/InvitationFriendAssist',
    component: () => import('@/pages/draw/InvitationFriendAssist.vue'),
    meta: { title: '邀请好友助力', auth: true }
  },
  {
    path: '/pages/draw/shareCommunityGroup/shareCommunityGroup',
    component: () => import('@/pages/draw/ShareCommunityGroup.vue'),
    meta: { title: '分享社群', auth: true }
  },
  {
    path: '/pages/index/downloadApp',
    component: () => import('@/pages/index/DownloadApp.vue'),
    meta: { title: 'APP下载' }
  },
  {
    path: '/pages/login/setPwd',
    component: () => import('@/pages/login/SetPwd.vue'),
    meta: { title: '设置新密码' }
  },
  {
    path: '/pages/login/login-code',
    component: () => import('@/pages/login/LoginCode.vue'),
    meta: { title: '验证码登录' }
  },
  {
    path: '/pages/index/calendar',
    component: () => import('@/pages/index/Calendar.vue'),
    meta: { title: '发售日历' }
  },
  {
    path: '/pages/index/shequn',
    component: () => import('@/pages/index/Shequn.vue'),
    meta: { title: '加入社群' }
  },
  {
    path: '/pages/webview/webview',
    component: () => import('@/pages/webview/WebView.vue'),
    meta: { title: '网页' }
  },
  {
    path: '/pages/webview/webview-storage',
    component: () => import('@/pages/webview/WebViewStorage.vue'),
    meta: { title: '网页' }
  },
  {
    path: '/pages/webview/pay-webview',
    component: () => import('@/pages/webview/PayWebView.vue'),
    meta: { title: '支付' }
  },
  {
    path: '/pages/notice/newsDetails',
    component: () => import('@/pages/notice/NewsDetails.vue'),
    meta: { title: '公告详情' }
  },
  {
    path: '/pages/notice/wallDetails',
    component: () => import('@/pages/notice/WallDetails.vue'),
    meta: { title: '动态详情' }
  },
  {
    path: '/pages/draw/drawResult/drawResult',
    component: () => import('@/pages/activity/DrawResult.vue'),
    meta: { title: '中签结果' }
  },
  {
    path: '/pages/login/verification',
    component: () => import('@/pages/login/Verification.vue'),
    meta: { title: '验证码验证' }
  },
  {
    path: '/pages/rebate/rebate',
    component: () => import('@/pages/rebate/Rebate.vue'),
    meta: { title: '邀请返利' }
  },
  {
    path: '/pages/shop/exchange',
    component: () => import('@/pages/shop/Exchange.vue'),
    meta: { title: '兑换商城' }
  },
  {
    // 原站实测：直接访问 /pages/market/sellBegBuy 会重定向到首页 tabbar
    path: '/pages/market/sellBegBuy',
    redirect: '/pages/index/index'
  }
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 })
})

/** 原站实测：未登录访问我的页（含 tabBar 点击）统一跳登录首页 */
function hasToken() {
  const raw = localStorage.getItem('token') || ''
  if (!raw) return false
  try {
    const v = JSON.parse(raw)
    return typeof v === 'string' ? !!v : !!raw
  } catch (e) {
    return true
  }
}

router.beforeEach((to, from, next) => {
  if (to.meta && to.meta.auth && !hasToken()) {
    next({ path: '/pages/login/login', replace: true })
    return
  }
  next()
})

export default router