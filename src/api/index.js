import { http } from './http'

/* ---------------- 首页 ---------------- */
export const getBanner = () => http.get('/banner/show/getBanner')
/* 原站实测：公告列表与分类均为 POST（早期误写为 GET，已按实测修正为 POST） */
export const getAfficheList = (params) =>
  http.post('/index/afficheList', params || {})
export const getAfficheTypeList = () => http.post('/index/affichetypelist', {})
export const getSeries = (params) => http.get('/index/getSeries', { params })
export const getConfig = () => http.get('/index/getConfig')

/* ---------------- 藏品 / 盲盒 ---------------- */
export const getBoxList = (params) => http.get('/box/blind_box/list', { params })
export const getCollectionList = (params) => http.get('/box/collection/CollectionList', { params })

/* ---------------- 合成 ---------------- */
/* 原站实测 pages/compound/compound 走 POST /synthesis/themeinfo { thm }（GET 无效） */
export const getSynthesisTheme = (params) =>
  http.post('/synthesis/themeinfo', params || {})
export const getSynthesisRecord = (params) => http.get('/synthesis/recordList', { params })
/* 点击合成活动卡片：查询可选合成材料。原站 4003=无资格 3003=直接进方案详情 */
export const getSynthesisMaterial = (data) =>
  http.post('/synthesis/material', data)

/* ---------------- 用户 ---------------- */
export const login = (data) => http.post('/user/login', data)
export const getUserInfo = () => http.post('/user/getUserInfo')
export const getMyCollection = (params) =>
  http.post('/user_collection/user_collection/antMycollection', params || {})
export const getCollectionRecord = (params) =>
  http.post('/user_collection/user_collection/getRecord', params || {})
export const getAddressList = (params) => http.post('/user/address_list', params || {})

/* ---------------- 订单 ---------------- */
export const getOrderList = (params) => http.post('/order/order/orderList', params || {})
export const getMyOrderList = (params) => http.post('/market/Prepayment/myOrderlist', params || {})

/* ---------------- 委托 / 求购（交易主链路） ---------------- */
export const getTradeCollectionDetail = (data) =>
  http.post('/market/trade/collectionDetail', data)
export const getPrepaymentCollectionDetail = (data) =>
  http.post('/market/Prepayment/collectionDetail', data)
export const getBegBuyOrderList = (params) =>
  http.post('/market/trade/begBuyOrderList', params || {})
export const getEntrustOrderList = (params) =>
  http.post('/market/trade/entrustOrderList', params || {})
export const getMyConsignment = (params) =>
  http.post('/order/order/myConsignment', params || {})
export const submitEntrust = (data) => http.post('/market/trade/entrust', data)
export const submitBegBuy = (data) => http.post('/market/trade/begBuy', data)

/* ---------------- 寄售（转售） ---------------- */
export const getUserCollectionDetail = (ids) =>
  http.post(`/user_collection/user_collection/detail/ids/${ids}`)
export const getSellConfig = (userCollectionId) =>
  http.post('/user_collection/user_collection/show_resale_money', {
    price: 100,
    user_collection_id: userCollectionId
  })

/* ---------------- 委托 / 求购 订单详情 ---------------- */
export const getMyEntrustInfo = (orderId) =>
  http.post('/market/Prepayment/myOrderInfo', { order_id: orderId })
export const getMyBegInfo = (orderId) =>
  http.post('/market/trade/begBuyOrderInfo', { order_id: orderId })

/* ---------------- 普通 / 市场 / 转售 订单详情 ---------------- */
export const getOrderDetails = (orderId, type = 1) =>
  http.post(
    type === '2' || type === 2
      ? `/order/order/MarketOrderDetail/ids/${orderId}`
      : `/order/order/orderDetail/ids/${orderId}`
  )
export const getRegiftOrderDetails = (orderId) =>
  http.post('/order/order/saleMarketOrderDetail', { order_id: orderId })

/* ---------------- 排行榜 / 社区 ---------------- */
export const getUserRank = (params) => http.post('/user/ranking', params || {})
export const getNewRank = (params) => http.post('/user/newrank1', params || {})
export const getMyUnion = (params) => http.post('/union/user/myunion', params || {})
export const getUnionLogs = (params) => http.post('/union/index/logs', params || {})

/* ---------------- 活动 ---------------- */
export const getActivityIndex = (params) => http.post('/activity/index', params || {})
export const getActivityCategory = () => http.get('/Activity/category')
export const getCrystalGoods = (params) => http.get('/crystal_goods/list', { params })

/* ---------------- 行情 ---------------- */
export const getMarketList = (params) => http.post('/market/market/getMarketList', params || {})
/* 幸运盒行情（原站 getBoxList 分支接口） */
export const getMarketBoxList = (params) =>
  http.post('/market/market/marketList', params || {})
export const getTradingInformation = (params) =>
  http.post('/market/trade/tradingInformation', params || {})
/* ---------------- 藏品列表页（marketGoodsList） ---------------- */
/* 原站实测：POST /market/market/marketGoodsDetail  {"id":"120","type":"2"} */
export const getMarketGoodsDetail = (data) =>
  http.post('/market/market/marketGoodsDetail', data)

/* 在售单品：{"page":1,"sort":"price","order":"asc","type":2,"keyword":"","id":"120"} */
export const getMarketGoodsListByGoodsId = (params) =>
  http.post('/market/market/getMarketGoodsListByGoodsId', params || {})

/* 求购列表：{"page":1,"sort":"desc","order":"asc","type":2,"keyword":"","id":"120","collection_id":"120"} */
export const getBegBuyList = (params) =>
  http.post('/market/trade/begBuyList', params || {})

/* 相关公告：{"page":1,"category":0,"list_rows":25,"search":"Sunny！Sunny！"} */
export const getAfficheListBySearch = (params) =>
  http.post('/index/afficheList', params || {})

/* ---------------- 交易下单链路（逆向原站 chunk 得出） ---------------- */
/* 快捷下单：POST /order/pay/fastOrderNew
   原站 payload: { goods_id, key, pay_way }（captcha 校验通过后可能附带额外参数）
   原站返回 data.order_sn / data.order_id，随后拉起收银台 pay_type=4 & order_type=2 */
export const fastCreateOrder = (data) =>
  http.post('/order/pay/fastOrderNew', data)

/* 市场订单（含抢购）：POST /order/pay/CreateMarketOrder */
export const createMarketOrder = (data) =>
  http.post('/order/pay/CreateMarketOrder', data)

/* 市场订单抢购：POST /order/pay/CreateMarketOrderRush */
export const createMarketOrderRush = (data) =>
  http.post('/order/pay/CreateMarketOrderRush', data)

/* 批量购买：POST /order/pay/batchBuy */
export const batchBuy = (data) =>
  http.post('/order/pay/batchBuy', data)

/* 汇付开户订单：POST /order/pay/CreateHuifuAccountOrder */
export const createHuifuAccountOrder = (data) =>
  http.post('/order/pay/CreateHuifuAccountOrder', data)

/* 通用创建订单：POST /order/pay/CreateOrder */
export const createOrder = (data) =>
  http.post('/order/pay/CreateOrder', data)

/* VIP 创建订单：POST /order/pay/CreateVipOrder */
export const createVipOrder = (data) =>
  http.post('/order/pay/CreateVipOrder', data)

/* 发起支付：POST /order/pay/doPay */
export const doPay = (data) =>
  http.post('/order/pay/doPay', data)

/* 批量支付：POST /order/pay/batchdopay */
export const batchDoPay = (data) =>
  http.post('/order/pay/batchdopay', data)

/* 支付短信校验：POST /order/pay/paySmsCheck */
export const paySmsCheck = (data) =>
  http.post('/order/pay/paySmsCheck', data)

/* 充值：POST /order/pay/Recharge */
export const recharge = (data) =>
  http.post('/order/pay/Recharge', data)

/* 批量下单列表：POST /order/order/batchPayOrder */
export const getBatchOrderList = (params) =>
  http.post('/order/order/batchPayOrder', params || {})

/* 市场订单详情 */
export const getMarketOrderDetails = (params) =>
  http.post('/order/order/getMarketOrderDetails', params || {})

/* ---------------- 登录注册（pages/login/*） ---------------- */
/* 原站实测：POST /sms/send  body {mobile, event}  event: register / resetpwd */
export const sendSms = (data) => http.post('/sms/send', data || {})
/* 原站实测：POST /user/register */
export const register = (data) => http.post('/user/register', data || {})
/* 原站实测：POST /user/resetpwd */
export const resetPwd = (data) => http.post('/user/resetpwd', data || {})

/* ---------------- 公告中心（pages/notice/notice） ---------------- */
/* 原站实测（公告中心）：POST /index/affichetypelist  body {}
   -> [{id:1,name:"寄售公告"},{id:2,"活动公告"},{id:3,"合成公告"},{id:4,"运营公告"},{id:5,"空投公告"}]
   注：第 7 行已有同名 GET 版本，此处统一以 POST + body {} 为准，故不再重复导出。*/
/* 原站实测：POST /index/afficheList  body {"page":1,"category":0,"list_rows":25,"search":""} */
/* 原站实测（公告详情）：GET /index/afficheDetail/ids/116
   -> data{id,title,content(html),image,affiche_type_id,publish_time,...} */
export const getAfficheDetail = (id) =>
  http.get(`/index/afficheDetail/ids/${id}`)


/* ---------------- 藏品详情页（market/detail） ---------------- */
/* 原站实测：POST /market/market/MarketDetail/ids/15991 body {} */
export const getMarketDetail = (id, data) =>
  http.post(`/market/market/MarketDetail/ids/${id}`, data || {})
