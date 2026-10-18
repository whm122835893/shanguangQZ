<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 统一订单模型（多态）
 *
 * 字段依据：原站 order/order/orderList 实测行数据 18 字段
 * 样本：recon/raw2/order_order_orderlist.json（uid 16797 实测 2 条真实订单）
 *
 * ============================ 多态设计 ============================
 * 原站**没有** buy_order / sell_order / transfer_order 等分表，
 * 全部收敛到一张 artel_order，用 `artel_order` 字段区分业务场景：
 *
 *   实测值：artel_order = "mh_secondary_market_order"（二手市场订单）
 *
 *   ⚠️ 该字段值是**带 mh_ 前缀的字符串**，不是裸模块名。
 *      已实测确认的取值只有一个（secondary_market），
 *      其他场景（转账/充值/提现/合成）原站端点未实测到样本，
 *      不可凭命名猜。实现新场景前必须先抓到真实值。
 *
 *   为什么判定是单表多态而不是多表：
 *     ① 18 个字段里没有一个是「某场景特有」的（无 transfer_uid / recharge_way 之类）
 *     ② 实测两条不同状态的订单字段集完全一致
 *     ③ 字段名 goods_* / total_price / num 是通用交易语义
 *
 * ============================ 三个类型陷阱 ============================
 *
 * ⚠️ 陷阱 1：同一条记录里 order_status 是 int，order_type 和 num 是 string
 *    实测样本（完全同一条记录）：
 *        "order_status": 3      ← int  裸数字
 *        "order_type": "2"      ← str  带引号
 *        "num": "1"             ← str  带引号
 *    这不是我们建模随意，是原站就如此。前端若写 === '3' 判断订单状态会永远失效。
 *    出参时 order_status 转 int，另两个保持 string。
 *
 * ⚠️ 陷阱 2：uc_ids 有两种形态——null 与 "121926"
 *    实测样本 uc_ids = null；另一场景实测为字符串 "121926"（可能是逗号分隔多 ID）。
 *    DB 用 varchar(255) NULL 承载。出参：空 → null，非空 → explode(',')
 *    ⚠️ 实测样本只有单个 ID，多 ID 分隔符未实测确认，explode(',') 是合理推断，
 *    抓到多 ID 样本后必须复核分隔符。
 *
 * ⚠️ 陷阱 3：collection_code 是数组，且实测双形态
 *    id=18554 -> []              （空数组）
 *    id=14642 -> ["1926/100000"] （字符串数组，元素形如 "编号/总量"）
 *    ⚠️ 早期只看首条样本曾误判为「恒空」，建表时漏了该列，
 *       契约测试（tools/contract/run.php）在全量比对时抓到该回归。
 *    DB 存原样字符串，出参拆分逻辑见 TradeFormatter::collectionCode()。
 *    教训：样本有 N 条就必须全看，只看首条得出的结论不可信。
 *
 * ⚠️ 补充：goods_type 是 int（实测 2），但 blind_box.goods_type 是 string（实测 "2"）。
 *    同名不同表、不同类型 —— 这正是「禁止按字段名跨表推断类型」的活案例。
 *
 * @property int    $id
 * @property string $artel_order      业务场景标识，实测 "mh_secondary_market_order"
 * @property int    $order_status     状态，实测 2 / 3（int！不是 string）
 * @property string $order_type       子类型，实测 "2"（string！不是 int）
 * @property string $num              数量，实测 "1"（string）
 * @property string $total_price      实测 "100.00"
 * @property string $uc_ids           可为 null / "121926"
 * @property string $collection_code  实测 "" 或 "1926/100000"，出参转数组
 * @property int    $goods_type       int（注意与 blind_box.goods_type 的 string 相反）
 * @property int    $createtime
 */
class ArtelOrder extends Model
{
    protected $table = 'artel_order';

    protected $autoWriteTimestamp = false;

    /**
     * 订单状态枚举
     *
     * ⚠️ 只收录**实测到**的值（2 与 3），其余为推断未验证。
     *    原站 orderDetail 的状态文案映射未抓到，实现状态页前须补抓，
     *    否则前端会出现「状态码有值但无对应文案」的空白格。
     */
    public const STATUS = [
        2 => '待付款（实测订单存在此状态，具体文案未确认）',
        3 => '已完成（实测订单存在此状态，具体文案未确认）',
    ];

    protected $schema = [
        'id'               => 'int',
        'order_sn'         => 'string',
        'user_id'          => 'int',
        'artel_order'      => 'string',
        'order_type'       => 'string',   // ⚠️ string，不可改 int
        'order_status'     => 'int',      // ⚠️ int，不可改 string
        'total_price'      => 'decimal',
        'num'              => 'string',   // ⚠️ string
        'goods_id'         => 'int',
        'goods_type'       => 'int',      // ⚠️ int（与 blind_box 同名字段相反）
        'goods_name'       => 'string',
        'goods_cover_image'=> 'string',
        'series_name'      => 'string',
        'crystal_type'     => 'string',
        'crystal_number'   => 'string',   // ⚠️ 实测 "0" 是 string
        'uc_ids'           => 'string',
        'collection_code'  => 'string',   // ⚠️ 实测双形态 "" / "1926/100000"
        'pay_channel'      => 'string',
        'pay_time'         => 'int',
        'createtime'       => 'int',
        'updatetime'       => 'int',
    ];

    /**
     * 关联用户（IDOR 防护的锚点）
     *
     * 🔐 查订单详情时必须同时校验 user_id 匹配，不匹配返回 404 而非 403。
     *    返回 403 等于确认「该订单存在」，会泄露订单号空间。
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 取订单并校验归属 —— IDOR 防护统一入口
     *
     * @param  int $id      订单 ID
     * @param  int $userId  当前登录用户 ID
     * @return ArtelOrder|null  归属不符返回 null（由调用方转 404）
     */
    public static function findOwned(int $id, int $userId): ?ArtelOrder
    {
        /** @var ArtelOrder|null */
        return self::where('id', $id)
            ->where('user_id', $userId)   // 🔴 这一行就是 IDOR 防护本身
            ->find();
    }

    /**
     * 归属状态文案（实测值有限，未命中的返回原始码而非空串）
     */
    public function statusText(): string
    {
        $s = (int) $this['order_status'];
        return self::STATUS[$s] ?? ('状态' . $s);
    }
}
