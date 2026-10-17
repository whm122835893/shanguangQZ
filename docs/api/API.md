# 闪光集市后端 API 文档

> **用途**：为「重新实现后端」提供完整的数据契约依据。本文所有端点、方法、参数、字段均来自对生产站 `api.gandart.shop` 的实测（2026-10-17），非推测。

> **实测方式**：逆向 126 个前端 chunk 提取端点 → 遍历 118 条路由抓取真实请求 → 逐端点 curl 采样响应结构。

---

## 一、技术栈鉴定

### 1.1 框架：ThinkPHP

**证据链**：

| 证据 | 观察到的内容 | 指向 |
|---|---|---|
| 非法路由响应 | 返回 ThinkPHP 原生错误页模板（`<title>发生错误</title>` + 「你所浏览的页面暂时无法访问」+ shortcut icon） | ThinkPHP 异常页模板 |
| 统一响应结构 | `{"code":1,"msg":"","time":<unix>,"data":{...}}` | ThinkPHP `JsonResponse` / `success()` helper |
| 错误码语义 | `code:0` = 业务失败（如「账户不正确」）、`code:1` = 成功、`code:401` = 未登录、`code:404`/`code:500` = `An error occurred` | ThinkPHP 异常 |
| 控制器路由 | `/index/afficheList`、`/market/trade/collectionDetail`（**模块/控制器/方法** 三段式） | ThinkPHP 多应用（module）路由约定 |
| 插件机制 | `/addons/qrcode/index/build`、`/addons/signin/api.index/index` | ThinkPHP `addons` 插件机制 |
| 路径参数 | `/index/afficheDetail/ids/134`（`ids` 为路由变量名） | ThinkPHP URL 参数绑定 |
| 分页结构 | `{"total","per_page","current_page","last_page","data"}` | ThinkPHP `Paginator` |

**推断的版本与运行环境**：ThinkPHP 5.x 或 6.x（`addons` 与多应用结构在 5.1+ 常见）。生产环境已关闭 debug（错误页不显示堆栈，统一降级为「发生错误」模板）。

### 1.2 基础设施

| 层 | 证据 |
|---|---|
| 前端 H5 | `h5.gandart.shop` — uni-app/Vue2 编译产物，Tengine + 阿里云 CDN（`via: cache31.l2cn8045`、`ali-swift-*` 响应头） |
| API 网关 | `api.gandart.shop` — 独立域名，CORS 仅放行 `https://h5.gandart.shop` |
| 对象存储 | `img.gandart.shop` — 阿里云 OSS，带 `image_process` 图片处理参数（裁剪/缩放） |
| 数据库推断 | `createtime`/`updatetime` 为 Unix 时间戳整数；金额字段为字符串（如 `"100.00"`）；布尔以 int `0/1` 表示 → 典型 MySQL + PHP 弱类型输出 |

---

## 二、通用约定

### 2.1 请求

| 项 | 约定 |
|---|---|
| BaseURL | `https://api.gandart.shop/api` |
| **HTTP 方法** | **全部为 POST**（实测 117 次真实调用 100% 为 POST）。即使是「列表」「详情」类接口也用 POST，参数放 JSON body。⚠️ 不存在 GET 语义 |
| Content-Type | `application/json` |
| 鉴权 | 请求头 `token: <uuid>`；**同时也接受 query 参数 `?token=<uuid>`**（实测：`config.headers.token = token` 且 `config.params.token = token` 双通道下发，用于绕过 CDN 网关剥自定义头） |
| CORS | `Access-Control-Allow-Origin: https://h5.gandart.shop`，`Allow-Credentials: true` |

### 2.2 响应

```json
{
  "code": 1,
  "msg": "",
  "time": 1792175153,
  "data": { }
}
```

| 字段 | 类型 | 说明 |
|---|---|---|
| `code` | int | **0=业务失败，1=成功**。注意是「0 成功变 1 成功」的反直觉约定 |
| `msg` | string | 失败时的中文提示，可直接展示给用户（如「账户不正确」「藏品不存在」「存在其他尚未支付的订单」） |
| `time` | int\|string | 服务端 Unix 时间戳（实测两种类型都出现过） |
| `data` | object\|array\|null | 业务数据 |

**特殊 code：**

| code | 含义 | 处理方式 |
|---|---|---|
| `0` | 业务失败 | 读 `msg` 展示 |
| `1` | 成功 | 读 `data` |
| `401` | 未登录/token 失效 | **HTTP 状态码也是 401**；需重新登录后重放原请求 |
| `404` / `500` | `msg: "An error occurred"` | 服务端异常，已被 debug 关闭屏蔽细节 |

### 2.3 分页约定

所有列表接口返回 **ThinkPHP 标准分页结构**：

```json
{
  "total": 100,
  "per_page": 10,
  "current_page": 1,
  "last_page": 10,
  "data": [ ... ]
}
```

**入参不统一，需逐接口确认**（实测三种写法并存）：

| 写法 | 示例接口 |
|---|---|
| `page` + `list_rows` | `/box/blind_box/list` `{"page":1,"list_rows":10}` |
| `page` + `limit` | `/activity/index` `{"page":1,"limit":10}` |
| `page` + `pageSize` | `/union/index/logs` `{"page":1,"pageSize":15}` |
| 仅 `page`（服务端默认 10） | 多数详情类列表 |

### 2.4 类型约定

| 约定 | 说明 |
|---|---|
| 金额 = string | `"100.00"`、`"0.00"` — **不要用 float**，新后端应保持字符串或用 decimal |
| 布尔 = int | `0`/`1`（如 `is_show`、`is_collection`、`lock_num`） |
| 时间 = int | Unix 秒级时间戳（`createtime`/`updatetime`/`utime`） |
| 空值 | 大量字段为 `null`（如 `uc_ids`、`person_intro`），前端普遍做了空值兜底 |
| 图片路径 = 相对路径 | `/uploads/20261016/xxx.jpg`，**不带域名**，需前端拼接 OSS 域名；且常带 `?image_process=resize,l_420` 裁剪参数 |

---

## 三、端点全量清单

共 **221 个端点**，分布 **31 个模块**。「实测参数」为生产抓包的真实请求体；为空表示本次抓包未触发该接口（多为需有效业务参数或触发条件），不代表无需参数。

> 全部端点 HTTP 方法均为 **POST**，BaseURL `https://api.gandart.shop/api`。路径参数统一写作 `{ids}`。

### 3.1 `index` 模块（13 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/index/afficheList` | 公告列表 | `` |
| `/index/affichetypelist` | 公告分类列表 | `` |
| `/index/customSeriesAdd` | 新增自定义系列 | `` |
| `/index/customSeriesDel` | 删除自定义系列 | `` |
| `/index/customSeriesList` | 自定义系列列表 | `` |
| `/index/getCollectionGoods` | 系列下的藏品商品 | `` |
| `/index/getConfig` | 读取全局配置 | `` |
| `/index/getSeries` | 藏品系列（分区）列表 | `` |
| `/index/getVersion` | 版本检查（APK 更新） | `` |
| `/index/gettest` | 测试接口 | `` |
| `/index/index/agreement` | 用户协议 | `` |
| `/index/index/consignmentSales` | 寄卖说明 | `` |
| `/index/index/usageterms` | 使用条款 | `` |

### 3.2 `banner` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/banner/show/getBanner` | 首页轮播图列表 | `` |

### 3.3 `content` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/content/list` | 内容列表 | `` |

### 3.4 `user` 模块（38 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/user/LoginForMobile` | 手机号验证码登录 | `` |
| `/user/ParamsToUser` | 写入用户参数 | `` |
| `/user/RealAuthentication` | 实名认证 | `` |
| `/user/aboutGift` | 关于礼包 | `` |
| `/user/address_detail` | 地址详情 | `` |
| `/user/address_list` | 收货地址列表 | `` |
| `/user/aliPhoneOauth` | 阿里云号授权 | `` |
| `/user/applyIssuer` | 申请发行人 | `` |
| `/user/bei_yao_list` | 贝邀列表 | `` |
| `/user/bindBank` | 绑定银行卡 | `` |
| `/user/checkPaypass` | 校验支付密码 | `` |
| `/user/commision_info` | 佣金信息 | `` |
| `/user/commison_rank` | 佣金排行 | `` |
| `/user/delAccount` | 注销账号 | `` |
| `/user/delUserAddress` | 删除地址 | `` |
| `/user/edit_address` | 新增/编辑地址 | `` |
| `/user/getBankInfo` | 银行卡信息 | `` |
| `/user/getIssuer` | 发行人信息 | `` |
| `/user/getLlOrder` | 联动订单 | `` |
| `/user/getUserInfo` | 获取当前用户完整信息 | `` |
| `/user/getWalletInfo` | 钱包信息 | `` |
| `/user/getllbank` | 联动银行信息 | `` |
| `/user/getybdjbtoken` | 易宝令牌 | `` |
| `/user/login` | 账号密码登录 | `` |
| `/user/masterinfo` | 达人信息 | `` |
| `/user/masterlist` | 达人列表 | `` |
| `/user/newList` | 新版用户列表 | `` |
| `/user/newrank1` | 新版排行榜 | `` |
| `/user/oneKeyRead` | 一键已读 | `` |
| `/user/profile` | 修改个人资料 | `` |
| `/user/ranking` | 用户排行榜 | `` |
| `/user/readMsgById` | 按 id 标记已读 | `` |
| `/user/register` | 注册 | `` |
| `/user/resetpwd` | 重置密码 | `` |
| `/user/score_list` | 积分流水 | `` |
| `/user/setPayPassword` | 设置支付密码 | `` |
| `/user/setbuynum` | 设置购买数量 | `` |
| `/user/setlldef` | 设置联动默认 | `` |

### 3.5 `auth` 模块（3 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/auth/index/gettoken` | 获取鉴权 token | `` |
| `/auth/v1/signin/anonymously` | 匿名登录 | `` |
| `/auth/v1/user/me` | 当前用户 | `` |

### 3.6 `sms` 模块（2 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/sms/getCapt` | 图形验证码 | `` |
| `/sms/send` | 发送短信验证码 | `` |

### 3.7 `user_collection` 模块（22 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/user_collection/exchange/usercollectionList` | 兑换商城藏品列表 | `` |
| `/user_collection/user_collection/BoxCollectionList` | 盲盒藏品列表 | `` |
| `/user_collection/user_collection/HaveBoxCollection` | 已有盲盒藏品 | `` |
| `/user_collection/user_collection/ListDetail` | 系列详情 | `` |
| `/user_collection/user_collection/ListGoodsDetail` | 系列商品详情 | `` |
| `/user_collection/user_collection/addlike` | 收藏 | `` |
| `/user_collection/user_collection/antMycollection` | 我的藏品列表（含在售/锁定统计） | `` |
| `/user_collection/user_collection/batch_resale` | 批量转售 | `` |
| `/user_collection/user_collection/collectionCertificate` | 藏品证书 | `` |
| `/user_collection/user_collection/compoundLog` | 合成记录 | `` |
| `/user_collection/user_collection/dellike` | 取消收藏 | `` |
| `/user_collection/user_collection/doBoxCollection` | 合成盲盒藏品 | `` |
| `/user_collection/user_collection/getDetail` | 藏品详情 | `` |
| `/user_collection/user_collection/getList` | 藏品通用列表 | `` |
| `/user_collection/user_collection/getRecord` | 购买/流转记录 | `` |
| `/user_collection/user_collection/likeCollection` | 我的收藏列表 | `` |
| `/user_collection/user_collection/openBoxs` | 批量开盒 | `` |
| `/user_collection/user_collection/openbox` | 开盒 | `` |
| `/user_collection/user_collection/reGift` | 转赠 | `` |
| `/user_collection/user_collection/reGiftOrder` | 转赠订单 | `` |
| `/user_collection/user_collection/resale` | 转售 | `` |
| `/user_collection/user_collection/show_resale_money` | 转售手续费配置 | `` |

### 3.8 `market` 模块（23 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/market/Prepayment/PrepaymentListMain` | 预付主列表 | `` |
| `/market/Prepayment/cancelOrder` | 取消预付订单 | `` |
| `/market/Prepayment/collectionDetail` | 预付藏品详情 | `` |
| `/market/Prepayment/createOrder` | 创建预付订单 | `` |
| `/market/Prepayment/myOrderInfo` | 预付订单详情 | `` |
| `/market/Prepayment/myOrderlist` | 我的预付订单 | `` |
| `/market/Prepayment/show_resale_money` | 预付转售配置 | `` |
| `/market/market/batchchangeMarketGoods` | 批量修改挂单 | `` |
| `/market/market/changeMarketGoods` | 修改挂单 | `` |
| `/market/market/getMarketGoodsListByGoodsId` | 按 goods_id 取挂单列表 | `` |
| `/market/market/getMarketList` | 市场挂单列表 | `` |
| `/market/market/marketGoodsDetail` | 市场商品详情（含在售/求购列表） | `` |
| `/market/market/marketList` | 市场行情列表 | `` |
| `/market/market/marketrebang` | 市场热榜 | `` |
| `/market/trade/begBuyList` | 求购单列表 | `` |
| `/market/trade/begBuyOrderInfo` | 求购单详情 | `` |
| `/market/trade/begBuyOrderList` | 我的求购单 | `` |
| `/market/trade/cancelBegBuyOrder` | 取消求购单 | `` |
| `/market/trade/collectionDetail` | 交易藏品详情 | `` |
| `/market/trade/createBegBuyOrder` | 创建求购单 | `` |
| `/market/trade/getSellCollectionInfo` | 出售藏品信息（下单前校验） | `` |
| `/market/trade/sell` | 挂单出售 | `` |
| `/market/trade/tradingInformation` | 委托挂单列表 | `` |

### 3.9 `order` 模块（31 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/order/Ll/blindMobile` | 盲盒手机号 | `` |
| `/order/Ll/blindMobileCheck` | 盲盒手机号校验 | `` |
| `/order/Ll/getApply` | 获取申请 | `` |
| `/order/Ll/userApply` | 用户申请 | `` |
| `/order/cash/applyCash` | 提现申请 | `` |
| `/order/cash/cashList` | 提现记录 | `` |
| `/order/ll/uploadToLianLian` | 连连支付上传 | `` |
| `/order/order/batchPayOrder` | 批量支付订单列表 | `` |
| `/order/order/cancelbatchorder` | 取消批量订单 | `` |
| `/order/order/changeGiveOrderStatus` | 变更转赠订单状态 | `` |
| `/order/order/changeMarketOrderStatus` | 变更市场订单状态 | `` |
| `/order/order/changeOrderStatus` | 变更订单状态 | `` |
| `/order/order/myConsignment` | 我的寄售 | `` |
| `/order/order/orderList` | 我的订单列表 | `` |
| `/order/order/orderdanmu` | 订单弹幕 | `` |
| `/order/order/saleMarketOrderDetail` | 出售市场订单详情 | `` |
| `/order/pay/CreateHuifuAccountOrder` | 创建汇付钱包订单 | `` |
| `/order/pay/CreateMarketOrder` | 创建市场订单 | `` |
| `/order/pay/CreateMarketOrderRush` | 创建市场订单（抢购） | `` |
| `/order/pay/CreateOrder` | 创建订单 | `` |
| `/order/pay/CreateVipOrder` | 创建会员订单 | `` |
| `/order/pay/Recharge` | 充值 | `` |
| `/order/pay/batchBuy` | 批量购买 | `` |
| `/order/pay/batchdopay` | 批量支付 | `` |
| `/order/pay/doPay` | 发起支付 | `` |
| `/order/pay/doQualification` | 下单资质校验 | `` |
| `/order/pay/fastOrderNew` | 快捷下单（核心交易接口） | `` |
| `/order/pay/huodouOrder` | 货豆订单 | `` |
| `/order/pay/lookQualification` | 查看资质 | `` |
| `/order/pay/paySmsCheck` | 支付短信校验 | `` |
| `/order/pay/qualification` | 资质校验 | `` |

### 3.10 `wallet` 模块（3 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/wallet/huifu/openAccount` | 汇付钱包开户 | `` |
| `/wallet/huifu/wallet` | 汇付钱包信息 | `` |
| `/wallet/yibao/openAccount` | 易宝开户 | `` |

### 3.11 `box` 模块（3 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/box/blind_box/list` | 盲盒列表 | `` |
| `/box/blind_box/openlog` | 开盒记录 | `` |
| `/box/collection/CollectionList` | 藏品系列列表 | `` |

### 3.12 `synthesis` 模块（13 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/synthesis/btchSynthesizeNow` | 批量合成 | `` |
| `/synthesis/btchplayInfo` | 批量合成方案 | `` |
| `/synthesis/btchrecordDetails` | 批量合成详情 | `` |
| `/synthesis/btchrecordInfo` | 批量合成记录详情 | `` |
| `/synthesis/btchrecordList` | 批量合成记录 | `` |
| `/synthesis/material` | 合成材料 | `` |
| `/synthesis/playInfo` | 合成方案信息 | `` |
| `/synthesis/recordInfo` | 合成记录详情 | `` |
| `/synthesis/recordList` | 合成记录列表 | `` |
| `/synthesis/selectCollection` | 选择藏品 | `` |
| `/synthesis/synthesizeNow` | 立即合成 | `` |
| `/synthesis/themeList` | 合成主题列表 | `` |
| `/synthesis/themeinfo` | 合成主题详情 | `` |

### 3.13 `draw` 模块（5 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/draw/doQualification` | 抽签资质校验 | `` |
| `/draw/draw` | 发起抽签 | `` |
| `/draw/drawLog` | 抽签日志 | `` |
| `/draw/drawRecord` | 抽签记录 | `` |
| `/draw/get` | 抽签配置 | `` |

### 3.14 `lottery` 模块（4 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/lottery/info` | 抽奖详情 | `` |
| `/lottery/record` | 抽奖记录 | `` |
| `/lottery/start` | 开始抽奖 | `` |
| `/lottery/statistics` | 抽奖统计 | `` |

### 3.15 `crystal_goods` 模块（6 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/crystal_goods/crystalDetail` | 水晶详情 | `` |
| `/crystal_goods/exchange` | 兑换 | `` |
| `/crystal_goods/exchangeRecord` | 兑换记录 | `` |
| `/crystal_goods/getCardCode` | 获取卡密 | `` |
| `/crystal_goods/info` | 水晶首页 | `` |
| `/crystal_goods/list` | 水晶列表 | `` |

### 3.16 `activity` 模块（3 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/activity/accessNewActivities` | 参与新活动 | `` |
| `/activity/detail` | 活动详情 | `` |
| `/activity/index` | 活动列表 | `` |

### 3.17 `goods` 模块（5 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/goods/detail` | 商品详情 | `` |
| `/goods/getcate` | 商品分类 | `` |
| `/goods/getlist` | 商品列表 | `` |
| `/goods/huodouDetail` | 货豆详情 | `` |
| `/goods/huodouList` | 货豆列表 | `` |

### 3.18 `union` 模块（14 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/union/index/logs` | 公会动态 | `` |
| `/union/index/union_list` | 公会列表 | `` |
| `/union/index/union_user_power_list` | 成员权限列表 | `` |
| `/union/manage/inviter` | 邀请成员 | `` |
| `/union/manage/kick` | 踢出成员 | `` |
| `/union/manage/refresh` | 刷新公会 | `` |
| `/union/user/agree` | 同意申请 | `` |
| `/union/user/applyJoin` | 申请加入 | `` |
| `/union/user/logs` | 公会日志 | `` |
| `/union/user/message` | 公会消息 | `` |
| `/union/user/myunion` | 我的公会 | `` |
| `/union/user/quit` | 退出公会 | `` |
| `/union/user/read` | 已读 | `` |
| `/union/user/refuse` | 拒绝申请 | `` |

### 3.19 `score` 模块（2 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/score/get_my_share_details` | 我的分享详情 | `` |
| `/score/share` | 积分分享 | `` |

### 3.20 `scoreguess` 模块（6 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/scoreguess/get_my_guess` | 我的竞猜 | `` |
| `/scoreguess/get_my_score_guess_list` | 我的竞猜列表 | `` |
| `/scoreguess/getlist` | 竞猜列表 | `` |
| `/scoreguess/guess_detail` | 竞猜详情 | `` |
| `/scoreguess/guess_success` | 竞猜成功记录 | `` |
| `/scoreguess/join_guess` | 参与竞猜 | `` |

### 3.21 `discover` 模块（9 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/discover/ant_news/category` | 资讯分类 | `` |
| `/discover/ant_news/info` | 资讯详情 | `` |
| `/discover/ant_news/list` | 资讯列表 | `` |
| `/discover/rumor_wall/comment` | 发表评论 | `` |
| `/discover/rumor_wall/getReply` | 回复列表 | `` |
| `/discover/rumor_wall/getcomment` | 评论列表 | `` |
| `/discover/rumor_wall/info` | rumor 详情 | `` |
| `/discover/rumor_wall/list` | rumor 墙列表 | `` |
| `/discover/rumor_wall/release` | 发布 rumor | `` |

### 3.22 `subscribe` 模块（2 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/subscribe/cancel` | 取消订阅 | `` |
| `/subscribe/index` | 订阅列表 | `` |

### 3.23 `common` 模块（2 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/common/getAttachment` | 获取附件 | `` |
| `/common/uploadaccess` | 上传文件 | `` |

### 3.24 `extend` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/extend/getDetails` | 扩展详情 | `` |

### 3.25 `address` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/address/address.json` | 省市区地址库 | `` |

### 3.26 `diypage` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/diypage/index` | 自定义页面 | `` |

### 3.27 `addons` 模块（3 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/addons/signin/api.index/dosign` | 执行签到 | `` |
| `/addons/signin/api.index/index` | 签到首页 | `` |
| `/addons/signin/api.index/monthSign` | 月度签到 | `` |

### 3.28 `components` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/components/w-dxPop/w-dxPop` | 第三方弹窗组件（钉钉） | `` |

### 3.29 `system` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/system/ping` | 健康检查 | `` |

### 3.30 `functions` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/functions/invokeFunction` | 动态函数调用（高危，建议移除） | `` |

### 3.31 `Activity` 模块（1 个）

| 端点 | 功能 | 实测参数 |
|---|---|---|
| `/Activity/category` | 活动分类 | `` |

## 四、核心实体字段（实测样本）

新后端的数据库设计应以下面这些**实测字段**为准——这些都是生产环境真实返回的列名与类型。

### 4.1 User 用户（`/user/getUserInfo` → `data`）

实测返回 **90+ 字段**，这是最核心的表。按业务分组整理：

| 分组 | 字段（实测值示例） |
|---|---|
| 身份 | `id:16797` `username:"17587881293"` `nickname:"GqdusoI30"` `mobile` `avatar:"/defautAvatar.png"` `level:1` `gender:1` |
| 资金 | `money:"0.00"` `agent_money:"0.00"` `recharge_price:"0.00"` `service_price:"0.00"` `user_ore:"0.000000"` `team_ore` `person_ore` |
| 积分/连签 | `score:0` `successions:4` `maxsuccessions:4` `prevtime:1792171727` |
| 邀请 | **`inviter_code:"Q28116797"`** `inviter_user_id:0` `inviter_status:0` `invite_type:1` `new_inviter_user_id` `yao_count` |
| 实名 | `real_name_authentication:1` `real_name:"王*绵"` `id_card:"520******838"` `id_card_front/back` `real_name_time` |
| 支付通道 | `huifu_account:1` `yibao_status:0` `sandpay_status:0` `tipay_status:0` `yifutong_status:0` `pay_password:0` |
| 费率 | `huifu_service_charge:1` `sandpay_service_charge:0` `yifutong_service_charge:0` |
| 藏品统计 | `collection_num:1` `collection_total_price:"1.00"` `buy_collection_total_price:"1.00"` `blind_box_num:0` `crystal_yellow/purple/blue` |
| 权限 | `freeze:0` `is_white:0` `market_open:1` `transfer_permission:0` `transfer_permission_level:1` `is_vip:0` `is_show_rank:1` `show_wallet:1` |
| 统计 | `statistics:{invited_count,buy_count,sale_count,join_day}` |
| 账号 | `createtime/updatetime/logintime/joinip/loginip/status:"normal"` `url:"/u/16797"` |

> **注意**：`inviter_code` 是邀请码（形如 `Q28116797`）——前端曾误用 `invite_code` 导致二维码为空，这是易错点。

### 4.2 Order 订单（`/order/order/orderList` → `data.data[0]`，18 字段）

| 字段 | 实测值 | 说明 |
|---|---|---|
| `artel_order` | `"mh_secondary_market_order"` | 订单来源场景标识（多态来源） |
| `id` | `18554` | 订单 id |
| `order_sn` | `"2026101623075357101499336911"` | 订单号（时间戳+随机） |
| `order_type` | `2` | 订单类型（int，枚举待确认） |
| `order_status` | `3` | 订单状态（int，枚举待确认） |
| `total_price` | `"100.00"` | **字符串金额** |
| `num` | `"1"` | 数量（字符串） |
| `goods_id` / `goods_type` | `155` / `2` | 商品 id / 类型（2=藏品） |
| `goods_name` / `goods_cover_image` | `"赤壁之战"` / `/uploads/20261016/5cb9...jpg` | 商品快照 |
| `series_name` | `"历史区"` | 所属系列 |
| `crystal_type` / `crystal_number` | `"crystal_no"` / `"0"` | 水晶标记 |
| `uc_ids` | `null` | 关联 user_collection id 列表 |
| `collection_code` | `[]` | 藏品编号（转赠/凭证用） |
| `createtime` / `updatetime` | `1792163273` / `1792163274` | Unix 时间戳 |

**⚠️ 待补齐**：`order_status` 与 `order_type` 的枚举含义，实测样本只见到 `3`，完整枚举需查原站页面文案或DB。新后端实现时应与产品确认。

### 4.3 UserCollection 用户藏品（`/user_collection/user_collection/antMycollection` → `data.data[0]`）

| 字段 | 实测值 | 说明 |
|---|---|---|
| `collection_id` | `8` | 藏品本体 id |
| `num` / `sell_num` / `lock_num` | `1` / `"0"` / `0` | 持有数 / 在售数 / 锁定数 |
| `is_collection` | `1` | 是否已持有 |
| `can_compound` | `0` | 是否可合成 |
| `utime` | `1792049907` | 更新时间 |
| `collection` | 见下（嵌套对象） | 藏品本体 |

分页对象额外字段：`current_count`（当前持有统计）、`current_consignment`（当前寄售统计）。

**嵌套的 collection 本体（10 字段）**：

| 字段 | 实测值 |
|---|---|
| `id` | `8` |
| `name` | `"思维粒子"` |
| `main_image` | `/uploads/20261010/f0094d1aba622b6aceb2c91896af3eb8.jpg` |
| `listimg` | 同上（列表图，可与主图不同） |
| `collection_level` | `""` 等级 |
| `total_num_show` | `0` 展示用发行量 |
| `total_num` | `100000` 真实发行量 |
| `is_img_diff` | `0` 图片是否差异化 |
| `unfreeze_collection_id` | `0` 解冻关联 |
| `freeze_time` | `0` 冻结时间 |

### 4.4 其他实体

| 实体 | 接口 | 字段 |
|---|---|---|
| Banner | `/banner/show/getBanner` → `data.data[0]` | `id:2` `image` `url` `url_type:1` `is_show:1` |
| Affiche 公告 | `/index/afficheList` → `data.data[0]` | `id:134` `image` `title` `createtime` `type:"1"` `url` `affiche_type:"运营公告"` |
| LoginResult | `/user/login` → `data.userinfo` | `id` `nickname` `mobile` **`token`** `user_id` `expiretime` `expires_in` `is_exist` |

---

## 五、核心交易链路（实测时序）

这是最重要的业务流，新后端必须优先保证正确性。

### 5.1 快捷下单 → 支付

```
用户点击「快捷下单」
  ↓
GET  /market/market/getMarketGoodsListByGoodsId
     {page, sort:"price", order:"asc", type:2, keyword:""}
     → data.list[] 在售挂单（每项含 key）
  ↓
POST /market/market/marketGoodsDetail   → 取商品详情（含 detail.key）
  ↓
POST /user/getUserInfo                 → 取支付通道开通状态
     字段：huifu_account / yibao_status / sandpay_status（值 1=已开通）
  ↓
弹层展示支付方式（汇付/易宝/杉德），仅显示 able=true 的通道
  ↓
用户选通道 → 点「确认支付」
  ↓
POST /order/pay/fastOrderNew
     {"goods_id":"155", "key":"<取自 marketGoodsDetail 的 detail.key>", "pay_way":"huifu"}
  ↓
返回订单 → 弹「支付风险确认页」（三条条款 + 知晓风险确认按钮）
  ↓
POST /order/pay/doPay  发起支付
```

### 5.2 三个极易踩坑的点（实测踩过）

| 坑 | 错误做法 | 正确做法 |
|---|---|---|
| 端点名 | 猜成 `fastCreateOrder`（404） | **`fastOrderNew`**（抓 chunk 才确认） |
| `pay_way` 取值 | 传列表行的 `"A"` | 传**通道名** `"huifu"` / `"yibao"` / `"sandpay"` |
| `key` 来源 | 从 `priceList[0].key` 取（列表项不含该字段 → 后端返 `code:0未找到合适商品`） | 从**商品详情的 `detail.key`** 取 |
| 列表数据位置 | 解析 `data.data` | 实际在 **`data.list`**（优先级 `d.list \|\| d.data`） |

> 另注：`/addons/qrcode/index/build` 是**唯一不带 `/api` 前缀**且**返回 PNG 而非 JSON** 的接口，网关/反代必须单独放行，否则前端 `<img>` 会拿到 HTML 导致图裂。

---

## 六、新后端实现建议

### 6.1 选型

| 方案 | 评价 |
|---|---|
| **继续用 ThinkPHP** | ✅ 迁移成本最低——221 个端点的路由约定（`模块/控制器/方法` + 路径参数绑定 + `addons`）可直接复用，前端零改动。推荐 |
| Laravel / Node / Go | ❌ 需重写全部路由与 `code/msg/time/data` 响应约定，前端 100+ 处解构逻辑全部要跟着改 |

### 6.2 必须保持兼容的契约（改了就会坏前端）

1. `code` 用 **1=成功 / 0=失败**（不要改成常见的 0=成功）——前端按 `code===1` 判断。

2. 响应信封固定 `{code, msg, time, data}`，四个字段一个不能少。

3. 列表统一返回 `{total, per_page, current_page, last_page, data}`。

4. **金额用字符串**（`"100.00"`），避免 float 精度丢失。

5. 布尔用 int `0/1`，不要输出 `true/false`。

6. 图片字段返回**相对路径**（`/uploads/...`），前端负责拼 OSS 域名；如需裁剪用 `?image_process=resize,l_420`。

7. 路径参数保持 `detail/ids/{ids}` 这种 `ids` 命名（前端已有硬编码拼接）。

8. `token` **同时支持 header 和 query** 两种传递方式（线上网关会剥自定义 header）。

### 6.3 建议改进（原站的缺陷）

| 项 | 原站现状 | 建议 |
|---|---|---|
| `GET` 语义 | 全POST | 可逐步引入 GET/REST，但需前端同步改；过渡期双轨兼容 |
| 订单状态枚举 | 裸 int，前端硬编码猜测 | 建立 `order_status` 字典表并输出可读文案 |
| 金额类型 | 字符串 | 服务端用 decimal 存储，输出保持字符串 |
| `/functions/invokeFunction` | 动态函数调用 | `` |
| `/index/gettest` | 测试接口 | `` |
| CORS | 硬编码单域名 | 改为白名单配置 |
| 错误信息 | 统一 `An error occurred` | 增加 trace_id 便于排查，但不外泄堆栈 |

### 6.4 建议的模块划分（沿用原站，保证前端零改动）

```
模块/控制器        职责
─────────────────────────────────────────
index              首页聚合、全局配置、公告、系列
banner             轮播
user               账户、实名、地址、银行卡、钱包
auth/sms           登录鉴权、短信
user_collection    用户藏品、合成记录、转赠、收藏
market             市场挂单、求购、预付
orderorder         订单生命周期
order/pay          下单与支付通道
wallet             支付通道开户
box                盲盒
synthesis          合成
draw / lottery     抽签 / 抽奖
crystal_goods      水晶
activity           活动
goods              商品
union              公会
score/scoreguess   积分 / 竞猜
discover           资讯 / rumor
addons             插件（二维码、签到）
```

---

## 七、数据契约来源与局限

**证据强度**：

| 内容 | 依据 | 置信度 |
|---|---|---|
| 端点清单（221 个） | 逆向 126 个前端 chunk 全量提取 | **高**（前端调用即真实存在） |
| HTTP 方法（全 POST） | 117 次生产抓包 | **高** |
| 请求参数 | 生产抓包的真实 body | **高**（实际调用过的） |
| 响应字段 | curl 采样 66 个端点 | **中高**（部分端点因参数为 `undefined` 未取到成功响应） |
| 实体字段（User/Order/Collection） | 带 token 采样 | **高**（真实数据） |
| ThinkPHP 判定 | 错误页模板 + 路由结构 + 分页器 + 响应信封四重印证 | **高** |
| 具体版本号 | 无直接证据 | **低**（仅推断为 5.x/6.x，需源码或服务器信息确认） |

**已知局限**：

- `order_status` / `order_type` 的完整枚举未取得（仅观测到 `order_status=3`）。

- 部分写操作接口（下单、支付、开盒、签到、提现）**未实际提交**，其参数契约来自前端代码与chunk 逆向，非抓包实测——新后端实现时应逐个与产品确认。

- 约 155 个端点本次未抓到成功响应（多因需要有效业务参数或触发条件），文档中其「实测参数」列为空属正常。

---

**文档生成时间**：2026-10-17 | **数据来源**：`api.gandart.shop` 生产环境实测 | **覆盖端点**：221 / 31 模块