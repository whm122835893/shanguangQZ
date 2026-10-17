<?php
declare(strict_types=1);

namespace app\service;

/**
 * 交易域出参契约（P1）
 *
 * 从 Formatter 拆分而来 —— 原因：Formatter 同时承载「内容域」（公告/盲盒）与
 * 「交易域」（用户/订单/藏品/消息）两组契约，加起来超 300 行违反架构约束。
 * 用户信息因体量最大又拆为 UserFormatter。
 *
 * 🔴 本层是交易域唯一的契约出口。数据库是规整二维表，
 *    原站 API 却有若干反直觉约定，直接把 Model::toArray() 丢出去会全部踩中。
 *
 * ============================ 三套信封写法 ============================
 * 同一项目内信封不统一，切端点时必须逐端点核对，不要图省事统一用 Response::page()：
 *
 *   A. 标准五字段  {total,per_page,current_page,last_page,data}   —— orderList
 *   B. 七字段      A + current_count + current_consignment        —— antMycollection
 *   C. count+list  {count,list}                                    —— newList
 *
 * ============================ 核心红线 ============================
 *   1. 金额 → string（不是 float，PHP 浮点会丢精度）
 *   2. 布尔 → int 0/1（不是 true/false）
 *   3. 字符串包数字 → 保持字符串（order_type / num / sell_num）
 *   4. int 与 string 混用必须逐字段对照，不做「统一化」
 *   5. 实测为 null 的字段保持 null，不能压成 0 或 ""
 *   6. 嵌套对象必须完整还原，少一个字段前端就可能踩未定义分支
 *
 * @package app\service
 */
final class TradeFormatter
{
    /**
     * 用户信息（委托给 UserFormatter）
     *
     * 保留这个代理方法是为了让调用方只记一个入口；
     * 实际实现见 UserFormatter::userInfo()（106 叶子字段，三层嵌套还原）。
     */
    public static function userInfo($row): array
    {
        return UserFormatter::userInfo($row);
    }

    /**
     * 订单行 —— 原站 order/order/orderList 实测 18 字段
     *
     * 🔴 最容易写错的一组：同一条记录里 int 与 string 混用
     *    实测："order_status": 3 (int) / "order_type": "2" (str) / "num": "1" (str)
     *
     * ⚠️ collection_code 实测是空数组 []，DB 无对应列，出参固定补 []
     *    一旦原站开始下发真实内容，此处必须重新建模。
     *
     * @param \think\Model|array $row artel_order 行
     * @return array
     */
    public static function order($row): array
    {
        $s = is_array($row) ? $row : $row->toArray();

        return [
            'id'               => (int) $s['id'],
            // ⚠️ int，不是 string
            'order_status'     => (int) $s['order_status'],
            // ⚠️ string，不是 int
            'order_type'       => (string) $s['order_type'],
            'num'              => (string) $s['num'],
            'order_sn'         => (string) $s['order_sn'],
            'artel_order'      => (string) $s['artel_order'],
            'total_price'      => number_format((float) $s['total_price'], 2, '.', ''),
            'goods_id'         => (int) $s['goods_id'],
            // ⚠️ int（与 blind_box.goods_type 的 string 相反）
            'goods_type'       => (int) $s['goods_type'],
            'goods_name'       => (string) $s['goods_name'],
            'goods_cover_image'=> (string) $s['goods_cover_image'],
            'series_name'      => (string) $s['series_name'],
            'crystal_type'     => (string) $s['crystal_type'],
            // ⚠️ 实测 "0" 是 string
            'crystal_number'   => (string) $s['crystal_number'],
            // ⚠️ 双形态：null 或 "121926"
            'uc_ids'           => ($s['uc_ids'] === null || $s['uc_ids'] === '')
                                    ? null
                                    : explode(',', (string) $s['uc_ids']),
            // ⚠️ 实测恒为空数组
            'collection_code'  => [],
            'createtime'       => (int) $s['createtime'],
            'updatetime'       => (int) $s['updatetime'],
        ];
    }

    /**
     * 我的藏品行 —— 原站 antMycollection 实测 17 字段（7 顶层 + 10 嵌套）
     *
     * ⚠️ sell_num 出参必须是 string（实测 "0"），而 num / lock_num 是 int。
     * ⚠️ 嵌套 collection 只出 10 字段，**不含 reprice_*** ——
     *    那是藏品详情场景的字段，多下发会让前端走进错分支。
     *
     * @param \think\Model|array $row       user_collection 行
     * @param \think\Model|array|null $coll 已关联的 collection 行
     * @return array
     */
    public static function userCollection($row, $coll = null): array
    {
        $s = is_array($row) ? $row : $row->toArray();
        $c = $coll === null ? [] : (is_array($coll) ? $coll : $coll->toArray());

        // 嵌套 collection：实测 10 字段白名单
        $nested = [];
        foreach (\app\model\UserCollection::NESTED_FIELDS as $k) {
            $nested[$k] = $c[$k] ?? null;
        }
        foreach (['id', 'total_num_show', 'total_num', 'is_img_diff',
                  'unfreeze_collection_id', 'freeze_time'] as $k) {
            $nested[$k] = (int) ($nested[$k] ?? 0);
        }
        foreach (['name', 'main_image', 'listimg', 'collection_level'] as $k) {
            $nested[$k] = (string) ($nested[$k] ?? '');
        }

        return [
            'collection_id' => (int) $s['collection_id'],
            'num'           => (int) $s['num'],           // int
            'sell_num'      => (string) $s['sell_num'],  // ⚠️ string
            'lock_num'      => (int) $s['lock_num'],
            'is_collection' => (int) $s['is_collection'],
            'can_compound'  => (int) $s['can_compound'],
            'utime'         => (int) $s['utime'],
            'collection'    => $nested,
        ];
    }

    /**
     * 我的藏品分页信封 —— 🔴 不是标准分页
     *
     * 实测 data 的键：total / per_page / current_page / last_page / data
     *              / current_count / current_consignment
     *
     * ⚠️ 最后两个是**分页级汇总**，不在行数据里，极易漏掉。
     *    current_count     实测 5 —— 与 total 数值相同，语义疑似"当前持有数"
     *    current_consignment 实测 0 —— 疑似"当前挂售数"
     *    ⚠️ 二者真实语义未确认（样本只有一个用户一条数据），
     *    实现时先按 total 近似，不可当成确定语义硬编码。
     *
     * @param array $list 已格式化的行数据
     * @return array
     */
    public static function userCollectionPage(
        array $list,
        int $total,
        int $perPage,
        int $currentPage,
        ?int $currentCount = null,
        ?int $currentConsignment = null
    ): array {
        $perPage  = max(1, $perPage);
        $lastPage = (int) max(1, ceil($total / $perPage));

        return [
            'total'               => $total,
            'per_page'            => $perPage,
            'current_page'        => max(1, $currentPage),
            'last_page'           => $lastPage,
            'data'                => $list,
            // 实测样本中这两个与 total/0 同值，语义待确认
            'current_count'       => $currentCount ?? $total,
            'current_consignment' => $currentConsignment ?? 0,
        ];
    }

    /**
     * 消息行 —— 原站 user/newList 实测 6 字段
     *
     * 🔴 createtime 出参是**格式化字符串** "2026-10-15 15:38"，
     *    不是全项目通行的 unixtime！DB 存 int，出参时格式化还原。
     *
     * ⚠️ 若出参时直接透传 int，前端会显示 1792049880。
     *
     * @param \think\Model|array $row message 行
     * @return array
     */
    public static function message($row): array
    {
        $s = is_array($row) ? $row : $row->toArray();

        return [
            'id'          => (int) $s['id'],
            'title'       => (string) $s['title'],
            'content'     => (string) $s['content'],
            'user_id'     => (int) $s['user_id'],
            'read_status' => (int) $s['read_status'],
            // ⚠️ 格式化字符串，不是 unixtime
            'createtime'  => date(\app\model\Message::TIME_FORMAT, (int) $s['createtime']),
        ];
    }

    /**
     * 消息列表信封 —— 🔴 也不是标准分页
     *
     * 实测 data = { "count": 1, "list": [...] }
     * 没有 total / per_page / current_page / last_page，用 count + list。
     *
     * ⚠️ 这是项目内第三套信封写法（另两套：标准五字段、我的藏品七字段）。
     *    切端点时必须逐端点核对，不要图省事统一用 Response::page()。
     *
     * @param array $list
     * @return array
     */
    public static function messageList(array $list, int $count): array
    {
        return [
            'count' => $count,
            'list'  => $list,
        ];
    }
}
