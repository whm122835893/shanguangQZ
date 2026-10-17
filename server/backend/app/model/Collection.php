<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 藏品定义模型
 *
 * 字段依据（三处实测交叉验证，取并集）：
 *   ① user_collection/antMycollection 的嵌套 collection 对象 —— 10 字段
 *   ② market/trade/collectionDetail 的顶层 —— 额外含 reprice_* / max_buy_price 4 字段
 *   ③ sql/03-content.sql 建表定义 —— 19 列
 *
 * ⚠️ 本表最重要的认知：**同一张藏品表在不同端点下发的字段集不同**
 *
 *   场景 A 我的藏品（嵌套 collection）：
 *       id, name, main_image, listimg, collection_level,
 *       total_num_show, total_num, is_img_diff,
 *       unfreeze_collection_id, freeze_time            → 10 字段
 *
 *   场景 B 藏品详情（顶层）：
 *       上述字段 + reprice_limit, reprice_min_limit,
 *       reprice_beg_max_limit, max_buy_price           → 额外 4 字段
 *
 *   → 出参白名单必须**按场景分开**，绝不能用一个大而全的白名单糊弄，
 *     否则场景 A 会多下发 4 个字段（前端可能因此走进错分支）。
 *     见 UserCollection::NESTED_FIELDS 与 Detail 白名单的区分。
 *
 * ⚠️ reprice_* 三兄弟类型混用（实测）：
 *     reprice_limit          = 999999999  → int，999999999 表示「不限」
 *     reprice_min_limit     = "0.00"      → ⚠️ string
 *     reprice_beg_max_limit = "0.00"      → ⚠️ string
 *     max_buy_price         = 999999999  → int，「不限」
 *   同一组语义字段里两个 int 两个 string，出参时逐个对照，勿统一处理。
 *
 * ⚠️ 999999999 不是魔法值随意定的，它是原站的「不限」哨兵值。
 *   前端会拿它和真实价格比较判断是否显示「不限价」，
 *   我们若「优化」成 NULL 或 0，前端判断会反向。
 *
 * @property int    $id
 * @property string $name
 * @property int    $reprice_limit    999999999 = 不限
 * @property string $reprice_min_limit  实测 "0.00"（string）
 * @property int    $max_buy_price    999999999 = 不限
 */
class Collection extends Model
{
    protected $table = 'collection';

    protected $autoWriteTimestamp = false;

    /**
     * 「不限价」哨兵值 —— 原站实测 999999999
     *
     * ⚠️ 不可改成 NULL / 0 / PHP_INT_MAX，前端按该值做等值判断。
     */
    public const UNLIMITED = 999999999;

    protected $schema = [
        'id'                   => 'int',
        'name'                 => 'string',
        'main_image'           => 'string',
        'listimg'              => 'string',
        'collection_level'     => 'string',
        'series_id'            => 'int',
        'issuer_id'            => 'int',
        'total_num_show'       => 'int',
        'total_num'            => 'int',
        'is_img_diff'          => 'int',
        'unfreeze_collection_id'=> 'int',
        'freeze_time'          => 'int',
        'reprice_limit'        => 'int',     // ⚠️ int
        'reprice_min_limit'    => 'string',  // ⚠️ string
        'reprice_beg_max_limit'=> 'string',  // ⚠️ string
        'max_buy_price'        => 'int',     // ⚠️ int
        'diysort'              => 'int',
        'is_show'              => 'int',
        'createtime'           => 'int',
        'updatetime'           => 'int',
    ];

    /**
     * 详情场景白名单（嵌套 10 字段 + reprice 四字段 + 顶层 id/name）
     *
     * ⚠️ 这里刻意包含 reprice_* —— 因为 collectionDetail 场景原站确实下发。
     *    与 UserCollection::NESTED_FIELDS 的区别就在这 4 个字段。
     */
    public const DETAIL_FIELDS = [
        'id', 'name', 'listimg', 'main_image',
        'reprice_limit', 'reprice_min_limit', 'reprice_beg_max_limit',
        'max_buy_price',
    ];

    /**
     * 是否「不限价」
     */
    public function isUnlimitedPrice(): bool
    {
        return (int) $this['reprice_limit'] === self::UNLIMITED;
    }

    /**
     * 关联用户持有记录
     */
    public function holders()
    {
        return $this->hasMany(UserCollection::class, 'collection_id', 'id');
    }
}
