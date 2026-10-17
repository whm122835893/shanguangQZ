<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 用户消息模型
 *
 * 字段依据：原站 user/newList 实测行 6 字段
 * 样本：recon/raw2/user_newlist.json（uid 16797 实测 1 条）
 *
 * 实测样本（完整）：
 *   {
 *     "id": 55616,
 *     "content": "恭喜你成功购买了物品思维粒子",
 *     "title": "购买物品",
 *     "createtime": "2026-10-15 15:38",   ← ⚠️ 字符串！不是 unixtime
 *     "user_id": 16797,
 *     "read_status": 0
 *   }
 *
 * 🔴 陷阱 1（项目级不一致，必须知道）：createtime 在本项目里**大部分表是 int unixtime**，
 *    但消息接口下发的是格式化字符串 "Y-m-d H:i"。
 *    → DB 存 int unixtime（与其他表统一，且便于排序/区间查询），
 *      出参时用 date('Y-m-d H:i', $ts) 还原成字符串。
 *    ⚠️ 绝不可在出参时直接透传 int，前端会拿到 1792049907 显示成乱码。
 *    ⚠️ 也绝不可在 DB 里存字符串，那样无法按时间排序、无法做区间查询。
 *
 * ⚠️ 陷阱 2：信封不是标准分页
 *    实测 data = { "count": 1, "list": [...] }
 *    没有 total / per_page / current_page / last_page，用的是 count + list。
 *    与其他端点（如 orderList 用 {total,per_page,current_page,last_page,data}）不同。
 *    → 这是「同一项目内多套信封」的又一实例，切端点时必须逐端点核对，
 *      不要图省事统一用 Response::page()。
 *
 * ⚠️ 陷阱 3：content 是明文，可含用户昵称/商品名
 *    实测 "恭喜你成功购买了物品思维粒子"。属用户可见内容，
 *    出参前无需脱敏（是给他自己看的消息），但**入库时不可做 HTML 转义**
 *    —— 原站存的是纯文本，前端会自己渲染。入库转义会导致二次转义出现 &amp;。
 *    渲染侧防 XSS 是前端的责任（Vue 默认转义），后端只负责原样透传。
 *
 * @property int    $id
 * @property int    $user_id
 * @property string $title
 * @property string $content
 * @property int    $read_status  0 未读 1 已读（实测仅见 0）
 * @property int    $createtime   DB 存 int，出参转 "Y-m-d H:i" 字符串
 */
class Message extends Model
{
    protected $table = 'message';

    protected $autoWriteTimestamp = false;

    /** 出参时间格式 —— 实测 "2026-10-15 15:38"，即 Y-m-d H:i，无秒 */
    public const TIME_FORMAT = 'Y-m-d H:i';

    protected $schema = [
        'id'          => 'int',
        'user_id'     => 'int',
        'title'       => 'string',
        'content'     => 'string',
        'read_status' => 'int',
        'createtime'  => 'int',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 某用户的未读数
     *
     * ⚠️ 该值用于消息角标。原站是否下发未读总数未实测到（newList 只给 count=总数），
     *    若前端有未读角标需求，此方法为本地补充，需确认与原站角标口径一致。
     */
    public static function unreadCount(int $userId): int
    {
        return (int) self::where('user_id', $userId)
            ->where('read_status', 0)
            ->count();
    }

    /**
     * 按 ID 查消息并校验归属 —— IDOR 防护
     *
     * 🔐 归属不符返回 null（调用方转 404，不返回 403 泄露存在性）。
     *
     * @return Message|null
     */
    public static function findOwned(int $id, int $userId): ?Message
    {
        /** @var Message|null */
        return self::where('id', $id)
            ->where('user_id', $userId)   // 🔴 IDOR 防护
            ->find();
    }
}
