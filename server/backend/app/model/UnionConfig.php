<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 公会配置 —— 对齐 union/user/myunion 实测 config 层 6 字段
 *
 * 🔴 陷阱 1：have_rate 是**倍数**不是百分比
 *    实测 have_rate=1.88、user_rate=2、have_be_invite_back_rate=0.88。
 *    看着像「188% 返佣」，但它不是 88%——如果按百分比算会差一倍。
 *    契约层照抄数值；语义层按倍数处理，不要乘100。
 *
 * 🔴 陷阱 2：单行配置表
 *    实测 config 恒为单个对象（不是列表）。id=1 是固定那行。
 *
 * @property int $id
 * @property int $user_num会员上限，实测 20
 * @property string $have_rate             实测 1.88（decimal 2 位）
 * @property string $be_invite_back_rate   实测 1
 * @property string $have_be_invite_back_rate 实测 0.88
 * @property string $manager_user_rate     实测 1
 * @property string $user_rate             实测 2
 *
 * @package app\model
 */
class UnionConfig extends Model
{
    protected $name = 'union_config';
    protected $pk   = 'id';

    protected $schema = [
        'id'                       => 'int',
        'user_num'                 => 'int',
        'have_rate'                => 'string',   // ⚠️ 出参保持 1.88 而非 1
        'be_invite_back_rate'      => 'string',
        'have_be_invite_back_rate' => 'string',
        'manager_user_rate'        => 'string',
        'user_rate'                => 'string',
    ];

    /** 实测 config 层字段白名单 */
    public const CONFIG_FIELDS = [
        'user_num', 'have_rate', 'be_invite_back_rate',
        'have_be_invite_back_rate', 'manager_user_rate', 'user_rate',
    ];

    /** 配置表固定行 id */
    public const SINGLETON_ID = 1;

    /** 取配置（单行） */
    public static function singleton(): ?self
    {
        return self::find(self::SINGLETON_ID);
    }
}