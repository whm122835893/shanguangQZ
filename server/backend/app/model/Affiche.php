<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 公告模型
 *
 * 字段依据：原站 index/afficheList 实测响应
 *   id / image / title / createtime(int) / type(str "1"~"5") / url / affiche_type
 *
 * ⚠️ type 是字符串型数字（实测 "1"），不可转 int。
 *
 * @property int    $id
 * @property string $title
 * @property string $image
 * @property string $type
 * @property string $affiche_type
 * @property string $url
 * @property string $content
 * @property int    $publish_time
 * @property int    $createtime
 * @property int    $diysort
 * @property int    $is_show
 */
class Affiche extends Model
{
    protected $table = 'affiche';

    protected $autoWriteTimestamp = false;

    // 可批量写入
    protected $schema = [
        'id'           => 'int',
        'title'        => 'string',
        'image'        => 'string',
        'type'         => 'string',
        'affiche_type' => 'string',
        'url'          => 'string',
        'content'      => 'string',
        'publish_time' => 'int',
        'createtime'   => 'int',
        'diysort'      => 'int',
        'is_show'      => 'int',
    ];
}