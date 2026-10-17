<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 用户持有藏品模型
 *
 * 字段依据：原站 user_collection/user_collection/antMycollection 实测行 7 字段
 *          + 嵌套 collection 对象 10 字段 = 17
 * 样本：recon/raw2/user_collection_user_collection_antmycollection.json（uid 16797 实测 1 条）
 *
 * 实测样本（完整）：
 *   {
 *     "collection_id": 8,
 *     "num": 1,                ← int
 *     "sell_num": "0",         ← ⚠️ string！其余计数是 int
 *     "utime": 1792049907,
 *     "collection": { ...10 字段嵌套... },
 *     "lock_num": 0,           ← int
 *     "is_collection": 1,
 *     "can_compound": 0
 *   }
 *
 * ⚠️ 陷阱 1：sell_num 是 string，num / lock_num / is_collection / can_compound 是 int
 *    同一条记录里混用。这是原站既有约定，不是我们的选择。
 *    DB 侧 sell_num 用 int 存储（纯数字），出参时转 string 还原形态。
 *
 * ⚠️ 陷阱 2：嵌套 collection 与本表是「N:1 拆表」，出参必须完整还原 10 字段
 *    少一个字段前端就可能踩未定义分支。
 *
 * ⚠️ 陷阱 3：is_collection 语义未确认
 *    实测值为 1。字面像「是否已集齐/已合成」，但**未抓到 0 值样本**，
 *    不可断言语义。实现前须补抓 is_collection=0 的样本。
 *
 * ⚠️ 陷阱 4：分页信封上挂了两个额外字段
 *    实测 data 除标准 {total,per_page,current_page,last_page,data} 外，
 *    还有 current_count 与 current_consignment（同为 int）。
 *    这两个字段**不在行数据里**，属于分页级汇总，切端点时极易漏掉。
 *
 * @property int $id
 * @property int $user_id
 * @property int $collection_id
 * @property int $num         持有数（int）
 * @property int $sell_num    挂售数（⚠️ API 输出 string）
 * @property int $lock_num    锁定数
 * @property int $is_collection  语义未确认，实测仅见 1
 * @property int $can_compound   是否可合成，实测 0
 * @property int $utime       更新时间 unixtime
 */
class UserCollection extends Model
{
    protected $table = 'user_collection';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'            => 'int',
        'user_id'       => 'int',
        'collection_id' => 'int',
        'num'           => 'int',
        'sell_num'      => 'int',   // ⚠️ DB 用 int 存，API 输出转 string
        'lock_num'      => 'int',
        'is_collection' => 'int',
        'can_compound'  => 'int',
        'utime'         => 'int',
        'createtime'    => 'int',
    ];

    /**
     * 嵌套对象 collection 的字段白名单（实测 10 字段，缺一前端会报错）
     *
     * ⚠️ 这是 user_collection 场景下的 collection 字段集，
     *    与 market/trade/collectionDetail 场景的字段集**不同**（后者含 reprice_* 等）。
     *    同一张 collection 表在不同端点下发字段不同 —— 白名单必须分场景。
     */
    public const NESTED_FIELDS = [
        'id', 'name', 'main_image', 'listimg', 'collection_level',
        'total_num_show', 'total_num', 'is_img_diff',
        'unfreeze_collection_id', 'freeze_time',
    ];

    /**
     * 关联藏品定义（还原嵌套 collection 用）
     */
    public function collection()
    {
        return $this->belongsTo(Collection::class, 'collection_id', 'id');
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 可售数量 = 持有数 − 挂售数 − 锁定数
     *
     * ⚠️ 实测样本 num=1 / sell_num="0" / lock_num=0 → 可售 1。
     *    这个减法是**推断**：原站未见该字段，DB 也未设该列。
     *    若原站另有可售字段，以原站为准，不要用本方法顶替。
     */
    public function availableNum(): int
    {
        return max(0, (int) $this['num'] - (int) $this['sell_num'] - (int) $this['lock_num']);
    }
}
