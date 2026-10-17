<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 站点 Banner —— 对齐 banner/show/getBanner 实测 5 字段
 *
 * 实测样本（2 条）：
 *   {"id":2,"image":"/uploads/...","url":"","url_type":1,"is_show":1}
 *
 * 🔴 陷阱 1：实测只有 5 字段，DB 有 8 列
 *    title / sort / createtime / updatetime 实测**不下发**。
 *    出参必须是 5 字段白名单，多下发一个前端就可能踩未定义分支。
 *
 * 🔴 陷阱 2：url 实测是空串 ""，不是 null
 *    出参保持 ""，不要压成 null 也不要留NULL 被序列化成 null。
 *
 * @property int    $id
 * @property string $image      图片地址
 * @property string $url        跳转地址，实测空串
 * @property int    $url_type   跳转类型，实测 1
 * @property int    $is_show    是否展示，实测 1
 *
 * @package app\model
 */
class Banner extends Model
{
    protected $name = 'banner';
    protected $pk   = 'id';

    protected $schema = [
        'id'         => 'int',
        'title'      => 'string',
        'image'      => 'string',
        'url'        => 'string',
        'url_type'   => 'int',
        'sort'       => 'int',
        'is_show'    => 'int',
        'createtime' => 'int',
        'updatetime' => 'int',
    ];

    /** 实测出参字段白名单 —— 严格 5 个，多一个都不行 */
    public const LIST_FIELDS = ['id', 'image', 'url', 'url_type', 'is_show'];
}