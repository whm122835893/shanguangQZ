<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 藏品系列模型
 *
 * 表结构见 sql/03-content.sql（原站 /index/getSeries 实测返回空数组，
 * 但端点存在；本表数据来源是 begBuyOrder 实测响应里的 series 快照：
 * series_id=9 / series_name=卷土重来，见 seed.php 灌入）。
 *
 * @property int    $id
 * @property string $name
 * @property int    $type
 * @property int    $sort
 * @property int    $is_show
 * @property int    $createtime
 */
class Series extends Model
{
    protected $table = 'series';

    protected $autoWriteTimestamp = false;

    protected $schema = [
        'id'         => 'int',
        'name'       => 'string',
        'type'       => 'int',
        'sort'       => 'int',
        'is_show'    => 'int',
        'createtime' => 'int',
    ];
}
