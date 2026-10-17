<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 藏品发行方模型（嵌套对象 publisher 拆表）
 *
 * 字段依据：原站 blind_box/list 实测 publisher 字典
 *   {"name":"闪光","company_logo":"/uploads/20240118/xxx.jpg"}
 */
class Publisher extends Model
{
    protected $table = 'publisher';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'           => 'int',
        'name'         => 'string',
        'company_logo' => 'string',
        'createtime'   => 'int',
    ];
}