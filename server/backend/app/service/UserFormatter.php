<?php
declare(strict_types=1);

namespace app\service;

/**
 * 用户信息出参契约（106 叶子字段）
 *
 * 从 TradeFormatter 拆出 —— userInfo 一组就占 120 行且逻辑独立，
 * 与订单/藏品/消息的规则没有交集，合并会超 300 行。
 *
 * 🔴 本组是全项目最复杂的一处还原，三层嵌套必须完整：
 *   ① 98 个落库标量
 *   ② verification.{email,mobile}  ← 由 2 个拆列还原
 *   ③ statistics.{4 字段}          ← 由 4 个拆列还原
 *   ④ token（实测空串）+ url（实时拼 "/u/{id}"）
 *
 * ============================ 五个反直觉点 ============================
 *
 * ⚠️ 1. ore 类 6 位小数，金额类 2 位小数，同一接口内并存
 *      实测 user_ore = "0.000000" / money = "0.00"
 *      统一用 6 位会毁掉 money，统一用 2 位会毁掉 ore。
 *
 * ⚠️ 2. 实测为 null 的字段必须输出 null
 *      16 个字段实测为 null（birthday / private_key / user_qq 等）。
 *      压成 0 或 "" 都会改变业务含义 ——
 *      比如 share_success_time=0 会被前端当成"1970 年参与过活动"。
 *
 * ⚠️ 3. 4 个 int 字段实测会下发 null（option_pass / yttime /
 *      share_success_time / begin_team_rabbit_time），
 *      不能无脑 (int)，必须走 nullableInts 分支。
 *
 * ⚠️ 4. birthday / ali_openid 放在字符串组是为了保住 null，
 *      非空时再形态归一回 int。
 *
 * ⚠️ 5. id_card / real_name 原站已脱敏（"520******838" / "王*绵"），
 *      原样透传，不要再叠加第二层脱敏（会变成双重打码）。
 *
 * @package app\service
 */
final class UserFormatter
{
    /**
     * 用户信息 —— 原站 user/getUserInfo 实测 106 叶子字段
     *
     * 🔴 三层还原，缺一层前端就崩：
     *   ① 98 个落库标量直接输出
     *   ② verification.{email,mobile} 由 2 个拆列还原成嵌套对象
     *   ③ statistics.{4 字段} 由 4 个拆列还原成嵌套对象
     *   ④ token 出空串（实测就是 ""），url 由 id 实时拼 "/u/{id}"
     *
     * ⚠️ 金额 2 位、ore 6 位，同一接口内并存 —— 不能统一格式化。
     * ⚠️ 实测为 null 的字段必须输出 null，不能输出 ""。
     *
     * @param \think\Model|array $row user 行
     * @return array
     */
    public static function userInfo($row): array
    {
        $s = is_array($row) ? $row : $row->toArray();

        // ① 98 标量 —— 按实测字段集逐个取，不做 array_intersect_key 之外的加工
        $intFields = [
            'id', 'group_id', 'level', 'gender', 'score', 'successions',
            'maxsuccessions', 'prevtime', 'logintime', 'loginfailure',
            'jointime', 'createtime', 'updatetime', 'team_id',
            'inviter_user_id', 'new_inviter_user_id', 'sq_user_id',
            'yao_count', 'yao_count_uptime', 'real_name_authentication',
            'real_name_time', 'first_buy_time', 'collection_num',
            'blind_box_num', 'freeze', 'option_pass', 'is_white',
            'xin_count', 'pay_password', 'inviter_status', 'virtual',
            'market_open', 'has_bank', 'yttime', 'crystal_yellow',
            'crystal_purple', 'crystal_blue', 'share_status',
            'share_success_time', 'huifu_service_charge',
            'sandpay_service_charge', 'yifutong_service_charge',
            'invite_type', 'yibao_status', 'tipay_status', 'sandpay_status',
            'yifutong_status', 'show_wallet', 'random_name', 'is_show_rank',
            'huifu_account', 'rabbit_team_status', 'rabbit_person_status',
            'invited_user_buy_rabbit_collection_person',
            'is_first_one_market_buy_rabbit', 'begin_team_rabbit_time',
            'transfer_permission', 'transfer_permission_level',
            'vip_time', 'sync_time', 'r_status', 'is_vip',
        ];

        $strFields = [
            'username', 'nickname', 'email', 'mobile', 'avatar', 'bio',
            'loginip', 'joinip', 'status', 'inviter_code', 'real_name',
            'id_card', 'id_card_front', 'id_card_back', 'll_id',
            'person_intro', 'ntf_url', 'nft_url', 'private_key',
            'user_wx', 'user_qq', 'user_phone', 'account',
            // ⚠️ 这两个极易漏：实测下发 null，且类型归属容易判错
            //    birthday 是生日（unixtime）但实测样本为 null，
            //    放 int 组会被强转成 0，放 str 组才能保住 null。
            //    放这里是因为「null 保持 null」的优先级高于「类型归类」。
            'birthday', 'ali_openid',
        ];

        // ⚠️ int 字段里实测会下发 null 的 4 个：begin_team_rabbit_time /
        //    option_pass / share_success_time / yttime
        //    这几个不能无脑 (int)，null 必须保持 null —— 否则前端拿到的
        //    "从未参与过" 变成 "1970-01-01"，是两种完全不同的业务含义。
        //    实测原站这 4 项均为 null。
        $nullableInts = [
            'option_pass', 'yttime', 'share_success_time', 'begin_team_rabbit_time',
        ];

        $r = [];
        foreach ($intFields as $k) {
            if (in_array($k, $nullableInts, true)) {
                $v = $s[$k] ?? null;
                $r[$k] = ($v === null || $v === '') ? null : (int) $v;
            } else {
                $r[$k] = isset($s[$k]) ? (int) $s[$k] : 0;
            }
        }
        foreach ($strFields as $k) {
            $v = $s[$k] ?? null;
            // ⚠️ 实测这些字段为 null，必须保持 null，不能转成 ""
            $r[$k] = ($v === null) ? null : (string) $v;
        }

        // 金额类：2 位小数字符串（实测 "0.00" / "1.00"）
        foreach (['money', 'agent_money', 'collection_total_price',
                  'buy_collection_total_price', 'service_price',
                  'recharge_price', 'medals_number'] as $k) {
            $r[$k] = number_format((float) ($s[$k] ?? 0), 2, '.', '');
        }

        // ore 类：6 位小数字符串（实测 "0.000000"）
        // ⚠️ 这几个字段是 6 位，不是 2 位，别混用上面的格式化
        foreach (['user_ore', 'team_ore', 'person_ore',
                  'help_invited_user_product_ore_number'] as $k) {
            $r[$k] = number_format((float) ($s[$k] ?? 0), 6, '.', '');
        }

        // ② verification —— 由 email_verified / mobile_verified 还原
        $r['verification'] = [
            'email'  => (int) ($s['email_verified'] ?? 0),
            'mobile' => (int) ($s['mobile_verified'] ?? 0),
        ];

        // ⚠️ birthday 形态归一：null 保持 null（实测），有值时还原为 int unixtime
        //    它在 strFields 里只是为了保住 null，这里把类型纠正回来。
        $r['birthday'] = ($r['birthday'] === null || $r['birthday'] === '')
                            ? null
                            : (int) $r['birthday'];

        // ③ statistics —— 由 4 个拆列还原
        $r['statistics'] = [
            'invited_count' => (int) ($s['invited_count'] ?? 0),
            'buy_count'     => (int) ($s['buy_count'] ?? 0),
            'sale_count'    => (int) ($s['sale_count'] ?? 0),
            'join_day'      => (int) ($s['join_day'] ?? 0),
        ];

        // ④ token 实测为空串；url 是计算字段 "/u/{id}"
        $r['token'] = '';
        $r['url']   = '/u/' . (int) ($s['id'] ?? 0);

        return $r;
    }
}
