<?php
declare(strict_types=1);

namespace app\model;

use think\facade\Db;
use think\Model;

/**
 * VIP 会员订单模型
 *
 * 字段依据：POST /order/pay/CreateVipOrder 实测响应（raw2/order_pay_createviporder.json）
 *   data = {order_sn:"2026101720203010157519849484", order_id:"1", time:"300"}
 *   三个字段全是 string —— order_id 不是 int，time 不是 int
 *
 * 🔴 三个实测事实（2026-10-18 复探原站确认）：
 *   1. 空 body 即下单成功，无需任何商品参数 —— 会员购买是无参数动作
 *   2. 同账号连续下单 order_id 递增（"1" → "2"）—— 订单真实落库，
 *      本模型 createForUser() 因此必须真写 DB，不能在内存里拼响应
 *   3. VIP 订单不在 artel_order：原站账号有 2 笔 VIP 待付单时，
 *      orderList total 仍为 2（全市场单）→ 独立建表，不蹭统一订单表
 *
 * 🔴 status 列无样本（没有 vip 订单列表端点），取值域是实现层设计，
 *    禁止对外暴露（没有任何已实测端点返回它）。
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $order_sn   28 位：YmdHis(14) + 随机(14)
 * @property int    $status     1待支付 2已支付 3已取消（无样本，实现层）
 * @property int    $createtime
 */
class VipOrder extends Model
{
    protected $table = 'vip_order';

    protected $autoWriteTimestamp = false;

    /** 待支付 */
    public const STATUS_UNPAID = 1;

    protected $schema = [
        'id'         => 'int',
        'user_id'    => 'int',
        'order_sn'   => 'string',
        'status'     => 'int',
        'createtime' => 'int',
    ];

    /**
     * 为用户创建一笔 VIP 订单（真写库）
     *
     * 🔴 为什么必须落库而不是内存生成：
     *    原站同账号第二次下单 order_id 从 "1" 变 "2" —— 序列递增
     *    只有持久化才能复现。内存实现会让每次调用都返回 "1"，
     *    两笔订单共用同一个 id，是静默的数据造假。
     *
     * order_sn 形态抄自实测：2026101720203010157519849484
     *   = date('YmdHis') 14 位 + random_int(14 位)，共 28 位
     *
     * 🔴 为什么用 Db::name()->insertGetId() 而不是 Model::save()（本环境实测）：
     *    ThinkPHP 8 的 Model::save() 内部靠 getLastInsID() 回写自增 id，
     *    但本环境模型 initDb() 走 Db::connect(null) 路径，getLastInsID()
     *    返回 int(0)，`if ($key && $val)` 判假 → setData('id') 被跳过 →
     *    $order['id'] 为 NULL → (string) 后是空串，order_id 静默失真。
     *    （同一 facade 连接上直连 PDO::lastInsertId / insertGetId 均正常，
     *     分歧在连接实例；已逐层排除 PDO/驱动/模型绑定假设后确认）
     *    insertGetId 由 Db 门面直插直返，绕开 save() 的回写路径。
     *
     * @return self 已持久化的订单（id 为真实自增主键）
     */
    public static function createForUser(int $userId): self
    {
        $data = [
            'user_id'    => $userId,
            'order_sn'   => self::generateOrderSn(),
            'status'     => self::STATUS_UNPAID,
            'createtime' => time(),
        ];
        $data['id'] = (int) Db::name((new self())->getTable())->insertGetId($data);
        return new self($data);
    }

    /**
     * 生成订单号 —— 实测形态：YmdHis(14) + 14 位随机
     *
     * ⚠️ uk_order_sn 唯一索引兜底；理论上随机碰撞会被 DB 拒绝，
     *    14 位随机空间下概率可忽略，不做重试循环（重试会掩盖真问题）
     */
    public static function generateOrderSn(): string
    {
        return date('YmdHis') . (string) random_int(10000000000000, 99999999999999);
    }
}
