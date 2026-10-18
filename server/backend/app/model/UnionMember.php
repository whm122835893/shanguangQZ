<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 公会成员 —— 对齐 union/user/myunion 实测 my 层 8 字段
 *
 * 🔴 陷阱 1：union_id 可为 NULL
 *    实测未加入公会时 is_join=0 且 union=null，说明「从未加入」是正常状态。
 *    建表时若设NOT NULL，插不进「未加入」的记录——这是建表时的建模错误。
 *
 * 🔴 陷阱 2：sort 是字符串不是数字
 *    实测 "暂无排名"。没加入公会时不是 null、不是 0，而是中文文案。
 *    推论：加入后大概率是 "1"/"2" 这类名次字符串。
 *
 * 🔴 陷阱 3：nickname / avatar / mobile 不在本表
 *    实测 my 层有这3 个字段，但它们属于 user 表，
 *    出参时需 JOIN user 取，**不能**在 union_member 里冗余存一份。
 *
 * @property int|null $union_id    所属公会；未加入为 NULL
 * @property int      $user_id
 * @property int      $power       权限，实测 0
 * @property int      $is_manage   是否管理员，实测 0
 * @property int      $is_join     是否已加入，实测 0
 * @property string   $sort        实测 "暂无排名"（字符串）
 * @property int      $createtime
 *
 * @package app\model
 */
class UnionMember extends Model
{
    protected $name = 'union_member';
    protected $pk   = 'id';

    protected $schema = [
        'id'         => 'int',
        'union_id'   => 'int',    // ⚠️ 可为 NULL
        'user_id'    => 'int',
        'power'      => 'int',
        'is_manage'  => 'int',
        'is_join'    => 'int',
        'sort'       => 'string', // ⚠️ 实测 "暂无排名"
        'createtime' => 'int',
    ];

    /** 实测 my 层字段白名单 —— 多下发字段前端会踩未定义分支 */
    public const MY_FIELDS = [
        'id', 'avatar', 'nickname', 'mobile', 'is_join', 'sort', 'power', 'is_manage',
    ];

    /** 关联用户：取 nickname / avatar / mobile */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 取某用户的成员关系
     *
     * @return static|null null 表示该用户根本没有成员记录
     *                     （区别于「有记录但 is_join=0」）
     */
    public static function ofUser(int $userId): ?self
    {
        return self::where('user_id', $userId)->find();
    }
}