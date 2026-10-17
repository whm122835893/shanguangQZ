<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 盲盒批次模型（嵌套对象 batch 拆表）
 *
 * 字段依据：原站 blind_box/list 实测 batch 字典
 *   {"start_time":1792224000,"end_time":1792231200,"total_num":100000,"has_num":1}
 */
class Batch extends Model
{
    protected $table = 'blind_box_batch';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'         => 'int',
        'start_time' => 'int',
        'end_time'   => 'int',
        'total_num'  => 'int',
        'has_num'    => 'int',
    ];
}