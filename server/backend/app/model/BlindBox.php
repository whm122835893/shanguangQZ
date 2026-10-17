<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 盲盒 / 藏品模型
 *
 * 字段依据：原站 box/blind_box/list 实测响应（顶层 22 字段 + 2 个嵌套对象）
 *
 * ⚠️ 四个「字符串包数字」字段（实测确认为字符串，改成 int 会导致前端 === '2' 判断失效）：
 *     goods_type = "2"  /  batch_id = "14"  /  issuer_id = "0"  /  type = "collection"
 *
 * ⚠️ 原站该端点 data 是「裸数组」，不是分页对象（实测 type=list，1 条），
 *    与其它端点的 {total,per_page,current_page,last_page,data} 结构不同。
 *
 * @property int    $id
 * @property string $name
 * @property string $listimg
 * @property string $main_image
 * @property string $image
 * @property string $price
 * @property int    $total_num
 * @property string $goods_type
 * @property string $batch_id
 * @property string $issuer_id
 */
class BlindBox extends Model
{
    protected $table = 'blind_box';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'                     => 'int',
        'name'                   => 'string',
        'listimg'                => 'string',
        'main_image'             => 'string',
        'image'                  => 'string',
        'price'                  => 'decimal',
        'total_num'              => 'int',
        'total_num_batch'        => 'int',
        'sale_number'            => 'int',
        'flux'                   => 'int',
        'total_num_show'         => 'int',
        'release_time'           => 'int',
        'end_time'               => 'int',
        'createtime'             => 'int',
        'type'                   => 'string',
        'goods_type'             => 'string',
        'batch_id'               => 'string',
        'soldout'                => 'int',
        'fictitious_stock'       => 'int',
        'open_draw'              => 'int',
        'issuer_id'              => 'string',
        'batch_ref'              => 'int',
        'publisher_ref'          => 'int',
        'release_date_time_text' => 'string',
        'release_time_date_text' => 'string',
        'sale_status_text'       => 'int',
        'release_time_text'      => 'int',
        'diysort'                => 'int',
        'is_show'                => 'int',
    ];

    /**
     * 关联批次（嵌套对象 batch 拆表后的还原）
     */
    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_ref', 'id');
    }

    /**
     * 关联发行方（嵌套对象 publisher 拆表后的还原）
     */
    public function publisher()
    {
        return $this->belongsTo(Publisher::class, 'publisher_ref', 'id');
    }
}