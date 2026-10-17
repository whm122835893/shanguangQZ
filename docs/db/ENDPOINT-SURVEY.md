# 原站端点全量测绘报告

> 生成方式：自适应参数抓取（裸请求 → 解析报错 → 喂候选参数 → 复请求）
> 样本目录：`recon/raw/`（公开）、`recon/raw/auth/`（带 token）、`recon/raw2/`（本轮 174 个）
> 抓取日期：2026-10-17　账号：真实用户 uid=16797

---

## 一、总体结果

| 指标 | 数量 | 说明 |
|---|---|---|
| 文档记录端点 | 213 | `docs/api/API.md` 去重后 |
| 本轮抓取 | 174 | 此前未覆盖的全部端点 |
| 返回成功 `code=1` | 56 | 结构可信 |
| 其中**有真实行数据** | 22 | 字段可完全实测 |
| 原站实际 404 | 24 | **文档记录有误，功能已下线** |
| 业务拒绝/500 | 94 | 写操作端点或需特定参数 |

## 二、按业务域分布

| 业务域 | 成功 | 404 | 其他 | 说明 |
|---|---|---|---|---|
| 用户与账户 | 12 | 1 | 22 | |
| 市场与交易 | 9 | 0 | 13 | |
| 内容与配置 | 7 | 0 | 4 | |
| 订单与支付 | 6 | 4 | 20 | |
| 藏品与交易 | 5 | 1 | 15 | |
| 社区内容 | 4 | 0 | 2 | |
| 藏品与盲盒 | 3 | 4 | 3 | |
| 合成玩法 | 3 | 5 | 3 | |
| 抽奖活动 | 2 | 1 | 1 | |
| 公会 | 2 | 0 | 9 | |
| 活动任务 | 1 | 0 | 1 | |
| 公共 | 1 | 1 | 0 | |
| 扩展 | 1 | 0 | 0 | |
| 积分体系 | 0 | 1 | 0 | |
| 竞猜 | 0 | 5 | 0 | |
| 订阅 | 0 | 0 | 1 | |
| 函数 | 0 | 1 | 0 | |

## 三、文档需修正：原站已 404 的端点

> 这些端点在 `API.md` 中有记录，但原站实际返回 404，说明功能已下线或路径变更。
> **对应的表不需要建。**

| 端点 | 文档描述 | 所属模块 |
|---|---|---|
| `/scoreguess/get_my_guess` | 我的竞猜 | 竞猜 |
| `/scoreguess/get_my_score_guess_list` | 我的竞猜列表 | 竞猜 |
| `/scoreguess/guess_detail` | 竞猜详情 | 竞猜 |
| `/scoreguess/guess_success` | 竞猜成功记录 | 竞猜 |
| `/scoreguess/join_guess` | 参与竞猜 | 竞猜 |
| `/synthesis/btchSynthesizeNow` | 批量合成 | 合成玩法 |
| `/synthesis/btchplayInfo` | 批量合成方案 | 合成玩法 |
| `/synthesis/btchrecordDetails` | 批量合成详情 | 合成玩法 |
| `/synthesis/btchrecordInfo` | 批量合成记录详情 | 合成玩法 |
| `/synthesis/btchrecordList` | 批量合成记录 | 合成玩法 |

## 四、反推出的实体（有实测字段）

> 方法：按「嵌套对象 = 实体边界」切分，分页信封字段已排除。

| 实体 | 字段数 | 依据端点 |
|---|---|---|
| `userinfo` | 26 | _login |
| `collection` | 13 | user_collection_user_collection_antMycollection, user_collection_user_collection_antmycollection |
| `floor_price` | 11 | market_trade_collectiondetail |
| `info` | 10 | wallet_huifu_wallet |
| `list` | 8 | market_market_getMarketGoodsListByGoodsId, market_market_getMarketGoodsListByGoodsId_v2 |
| `my` | 8 | union_user_myunion |
| `config` | 6 | union_user_myunion |
| `lotteryList` | 6 | lottery_info_v2 |
| `statistics` | 4 | user_getUserInfo, user_getuserinfo |
| `consecutive_draws_1` | 4 | lottery_info_v2 |
| `consecutive_draws_5` | 4 | lottery_info_v2 |
| `collection_json` | 3 | lottery_info_v2 |
| `data` | 2 | activity_index, box_blind_box_openlog |
| `verification` | 2 | user_getUserInfo, user_getuserinfo |
| `collection_code` | 1 | order_order_orderList, order_order_orderlist |
| `headUser` | 1 | user_newrank1 |
| `rank1` | 1 | user_newrank1 |
| `rank2` | 1 | user_newrank1 |
| `join` | 1 | union_user_myunion |
| `custom_series` | 1 | index_customSeriesList, index_customserieslist |
| `platform_series` | 1 | index_customSeriesList, index_customserieslist |
| `needBuyCollectionList` | 1 | synthesis_selectcollection |
| `userHaveCollectionList` | 1 | synthesis_selectcollection |

## 五、本轮新发现的实体（原 30 张表之外）

| 实体 | 依据端点 | 实测字段 | 用途 |
|---|---|---|---|
| **寄售限价规则** | `market/trade/collectionDetail` | minprice / price_pro / price_price_ratio / pay_platform_service_rate / share_proportion_rate / platform_service_price / share_proportion_price / real_price / low_price / low_price_text / tips / reprice_limit / reprice_min_limit / reprice_beg_max_limit / max_buy_price / floor_price | 交易核心必备：决定「能不能寄售、寄售价多少、平台抽多少」 |
| **支付渠道配置** | `wallet/huifu/wallet + order/pay/*` | openstatus / sys_id / acct_id / mer_cust_id / user_cust_id / biz_product_id / redirect_url / resp_code / resp_desc | 对接汇付/砂付/易付通需要，原站是每用户开户 |
| **OSS 上传凭证** | `common/uploadAccess` | accessid / host / policy / signature / expire / callback / key / code | 阿里云 STS 临时凭证，前端直传用 |
| **图形验证码** | `sms/getCapt` | originalImageBase64 / jigsawImageBase64 / secretKey | 滑块验证码，需要 captcha 表 |
| **转赠记录** | `user/aboutGift` | 标准分页信封（当前账号 total=0，无行数据） | P2 转赠表入口 |
| **自定义系列** | `index/customSeriesList` | custom_series[] / platform_series[] | 系列表有「自定义/平台」两类标记 |
| **合成材料选择** | `synthesis/selectCollection` | needBuyCollectionList[] / userHaveCollectionList[] | 合成所需材料的两个来源 |
| **盲盒开盒日志** | `box/blind_box/openLog` | 标准分页信封（当前账号 total=0） | 开盒流水表 |
| **排行榜** | `user/newrank1` | headUser[] / list[] / rank1[] / rank2[] | 四个榜单，需快照表或 Redis |

## 六、方法论天花板（诚实说明）

本轮 56 个成功端点中，仅 **22 个有真实行数据**。
其余返回空信封 —— 结构可信但字段完全未知，原因有三：

1. **测试账号数据几近为空**：uid 16797 仅 1 条藏品、2 条订单，市场/求购/寄售/转赠全为空
2. **写操作端点无法裸调**：`CreateOrder` / `doPay` / `Recharge` 等需要合法入参，裸请求必然被拒
3. **多态表掩盖表数量**：`artel_order` 一张表承载 N 种业务场景，拆分会看到多张表

**因此：**
- 能确定的是「表的数量级」与「业务域归属」
- 不能确定的是空信封端点的具体字段，以及后台管理表（前端永远碰不到）
- 按原站经验，后台管理表（`admin_user` / `operation_log` / 素材管理等）估计还有 10–20 张

## 七、表数结论（三档口径）

| 口径 | 张数 | 依据 |
|---|---|---|
| 原站业务表（前端可见） | **≈ 45** | 213 端点归并 + 本轮反推实体 + 下线端点扣减 |
| 本项目需要（前端 62 端点） | **≈ 35** | 原站 221 端点里 159 个前端从未调用 |
| **已落库** | **30** | P0 内容藏品 11 + P1 交易核心 17 + 系统安全 3 |

相比 v2.0 文档的估算（原 52 张），本轮修正为 **45 张**，
原因是发现 **24 个端点在原站已 404**（整个 `scoreguess` 竞猜模块 5 个 + `synthesis/btch*` 批量合成 5 个 + `goods/*` 商品模块 4 个 + 资质校验类 4 个），
这些功能对应的表原站自己都没有。
