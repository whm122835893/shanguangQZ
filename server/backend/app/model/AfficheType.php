<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 公告类型字典模型
 *
 * 字段依据：原站 index/affichetypelist 实测响应
 *   data 是裸数组 [{id, name}]，共 5 条 —— 不加分页信封
 */
class AfficheType extends Model
{
    protected $table = 'affiche_type';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'   => 'int',
        'name' => 'string',
    ];
}