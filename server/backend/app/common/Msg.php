<?php
declare(strict_types=1);

namespace app\common;

/**
 * 端点 msg 常量表 —— 全部来自 240 个原站实测样本的统计
 *
 * 🔴 为什么要单独建表：
 *   原站每个成功端点的 msg 都不同且稳定（banner列表 / 个人信息 / 订单列表…），
 *   是 240 个样本统计出来的既有事实，不是可随手填的装饰文案。
 *   之前 Response::page() 没msg 参数，getBanner 返回空串，
 *   而契约测试只比 data 层 —— 这个漂移静默通过了很久。
 *
 * 🔴 硬门禁：新增端点时必须在本表登记实测 msg。
 *   没登记就是没抓样本，不许猜。执行：
 *     grep -rl '<endpoint-path>' app/controller/api/ | xargs ...
 *   然后到 recon/ 找对应样本，用 tools/contract/run.php 的信封校验兜住。
 *
 * 数据来源：recon/raw, recon/raw2, recon/raw3 共 240 个样本的 code==1 记录。
 *
 * @package app\common
 */
final class Msg
{
    // ---------- 内容域 ----------
    public const BANNER_LIST        = 'banner列表';
    public const AFFICHE_LIST       = '公告列表';
    public const AFFICHE_TYPE_LIST  = '查询成功';
    public const AFFICHE_DETAIL     = '公告列表';
    public const SERIES             = '系列';
    public const BLIND_BOX_LIST     = '发售日历';
    public const BLIND_BOX_OPENLOG  = '开盲盒记录';
    public const LOTTERY_STATISTICS = 'successfully !!!';
    public const LOTTERY_DRAWLOG    = '获取成功';
    public const LOTTERY_DRAWRECORD = '获取成功';
    public const CRYSTAL_DETAIL     = 'successfully !!!';
    public const CRYSTAL_EXCHANGE   = 'successfully !!!';
    public const SYNTHESIS_THEMELIST = 'successfully !!!';
    public const SYNTHESIS_SELECT   = 'successfully !!!';
    public const SYNTHESIS_RECORD   = 'successfully !!!';
    public const ACTIVITY_INDEX     = '请求成功';
    public const ACTIVITY_CATEGORY  = '分类列表';

    // ---------- 用户域 ----------
    public const LOGIN               = '登录成功';
    public const USER_INFO           = '个人信息';
    public const ORDER_LIST          = '订单列表';
    public const MY_COLLECTION       = '获取成功';
    public const MESSAGE_LIST        = '消息列表';
    public const READ_MSG            = '操作成功';
    public const COLLECTION_DETAIL   = '获取成功';
    public const LIKE_COLLECTION     = '获取成功';
    public const BOX_COLLECTION_LIST = '无列表';
    public const COMPOUND_LOG        = '合成记录';
    public const ABOUT_GIFT          = '转赠记录';
    public const RANKING             = '查询成功';
    public const ONE_KEY_READ        = '操作成功';
    public const PROFILE             = '操作成功';
    public const BANK_INFO           = '绑定信息';
    public const ISSUER_INFO         = '发行商信息';
    public const MASTER_LIST         = '成功';
    public const SET_BUY_NUM         = '成功';
    public const SMS_CAPTCHA         = '获取成功';

    // ---------- 交易域 ----------
    public const BATCH_PAY_ORDER     = '暂无订单';
    public const CREATE_VIP_ORDER    = '下单成功';
    public const MY_CONSIGNMENT      = '寄售列表';
    public const CASH_LIST           = '提现列表';
    public const APPLY_INFO          = '申请信息';

    // ---------- 市场域 ----------
    public const COLLECTION_DETAIL_TRADE = '藏品详情';
    public const COLLECTION_DETAIL_PREPAY = '相关金额';
    public const TRADING_INFORMATION  = '交易信息';
    public const MARKET_LIST         = '市场列表';
    public const GET_MARKET_LIST      = '操作成功';
    public const PREPAYMENT_ORDER     = '订单信息';
    public const BEGIN_BUY_ORDER      = '订单信息';

    // ---------- 公会域 ----------
    public const UNION_MY             = '成功';
    public const UNION_POWER_LIST     = '列表';
    public const UNION_MESSAGE        = '成功';

    // ---------- 通用 ----------
    public const SUCCESS     = '成功';
    public const REQUEST_OK  = '请求成功';
    public const OPERATION_OK = '操作成功';
    public const QUERY_OK    = '查询成功';
    public const GET_OK      = '获取成功';

    /**
     * 实测 msg 索引表 —— 供 tools/contract/run.php 自动交叉核对
     *
     * key 是端点路径（与 route/sub/route_api.php 里的规则一致），
     * value 是原站实测 msg。契约测试拿本地实际 msg 反查这里，
     * 避免把 msg 硬编码在测试里 —— 那样测试和实现会一起错。
     *
     * 🔴 收录原则：只收录 code==1 的实测值。code=0 的 msg 是错误文案，
     *    走 Response::fail() 单独处理，不进本表。
     */
    public const OBSERVED = [
        'banner/show/getBanner'         => self::BANNER_LIST,
        'index/afficheList'             => self::AFFICHE_LIST,
        'index/affichetypelist'         => self::AFFICHE_TYPE_LIST,
        'index/getSeries'               => self::SERIES,
        'blindBox/blind_box/list'       => self::BLIND_BOX_LIST,
        'blindBox/blind_box/openlog'    => self::BLIND_BOX_OPENLOG,
        'user/userinfo'                 => self::USER_INFO,
        'order/order/orderList'         => self::ORDER_LIST,
        'user/collection/antMycollection' => self::MY_COLLECTION,
        'user/newList'                  => self::MESSAGE_LIST,
        'union/user/myunion'             => self::UNION_MY,
        'order/order/batchPayOrder'     => self::BATCH_PAY_ORDER,
        'order/pay/CreateVipOrder'      => self::CREATE_VIP_ORDER,
        'market/trade/collectionDetail' => self::COLLECTION_DETAIL_TRADE,
        'market/Prepayment/collectionDetail' => self::COLLECTION_DETAIL_PREPAY,
        'market/trade/tradingInformation' => self::TRADING_INFORMATION,
    ];
}