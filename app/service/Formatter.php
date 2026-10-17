<?php
declare(strict_types=1);

namespace app\service;

/**
 * 原站契约格式化层
 *
 * 为什么单独一层：数据库是规整的二维表，原站 API 却有几个反直觉约定，
 * 直接把 Model 的 toArray() 丢出去会全部踩中。此层是唯一的契约出口。
 *
 * 红线（docs/api/BACKEND-API-SPEC.md §1.5）：
 *   1. 金额 → string（不是 float，PHP浮点会丢精度）
 *   2. 布尔 → int 0/1（不是 true/false）
 *   3.「字符串包数字」字段 → 保持字符串（goods_type/batch_id/issuer_id/affiche.type）
 *   4. 时间 → int unixtime（不是 datetime 字符串）
 *   5. 嵌套 dict 必须完整还原（batch 4 字段 / publisher 2 字段）
 *
 * @package app\service
 */
final class Formatter
{
    /**
     * 盲盒顶层字段白名单 —— 严格对齐原站实测的 26 字段（含 2 个嵌套对象）
     *
     * ⚠️ 为什么必须白名单而不是直接 toArray()：
     *    库里还有 batch_ref / publisher_ref / is_show / image 等多余字段，
     *    多下发一个字段前端就可能踩到未定义分支，白名单是唯一可靠的护栏。
     *
     * ⚠️ image 字段已从白名单移除——原站实测 blind_box/list 并不下发该字段，
     *    之前是 Mock 阶段凭猜测加的，被 verify-contract.py 抓出。
     *
     * 实测校验：本地输出与原站比对「值差异 0，缺失 0，多余 0」。
     */
    private const BLIND_BOX_FIELDS = [
        'id', 'name', 'listimg', 'main_image',
        'price', 'total_num', 'release_time', 'end_time', 'total_num_batch',
        'release_date_time_text', 'createtime', 'type', 'goods_type', 'batch_id',
        'soldout', 'fictitious_stock', 'open_draw', 'issuer_id', 'flux',
        'total_num_show', 'sale_status_text', 'diysort', 'release_time_text',
        'release_time_date_text',
    ];

    /**
     * 公告列表项字段白名单 —— 原站实测 7 字段
     * ⚠️ 列表不下发 content（原站实测如此），详情才下发。
     */
    private const AFFICHE_LIST_FIELDS = [
        'id', 'image', 'title', 'createtime', 'type', 'url', 'affiche_type',
    ];

    /**
     * 公告详情字段白名单
     */
    private const AFFICHE_DETAIL_FIELDS = [
        'id', 'image', 'title', 'createtime', 'type', 'url', 'affiche_type',
        'content', 'publish_time',
    ];

    /**
     * 盲盒：还原嵌套 batch / publisher
     *
     * @param \think\Model|array $row   blind_box 行
     * @param array|null   $batch 已关联的批次行
     * @param array|null   $pub   已关联的发行方行
     * @return array
     */
    public static function blindBox($row, ?array $batch = null, ?array $pub = null): array
    {
        $src = is_array($row) ? $row : $row->toArray();

        // 先按白名单取，避免把 batch_ref/publisher_ref/is_show 等内部字段泄漏给前端
        $r = [];
        foreach (self::BLIND_BOX_FIELDS as $k) {
            $r[$k] = $src[$k] ?? null;
        }

        // 金额：decimal 字符串化，避免浮点误差（原站实测 "4.88"）
        $r['price'] = number_format((float) $r['price'], 2, '.', '');

        // 布尔：强制 int（原站实测 0，不是 false）
        foreach (['soldout', 'fictitious_stock', 'open_draw'] as $k) {
            $r[$k] = (int) $r[$k];
        }

        // 时间戳与计数：强制 int
        foreach (['id', 'createtime', 'release_time', 'end_time', 'total_num',
                  'total_num_batch', 'flux', 'total_num_show', 'diysort',
                  'sale_status_text', 'release_time_text'] as $k) {
            $r[$k] = (int) $r[$k];
        }

        // ⚠️ 以下字段原站虽是 string 类型，但实测值是数字（如 "2"），
        //    保持 varchar 原样输出，绝不可转 int。
        foreach (['goods_type', 'batch_id', 'issuer_id'] as $k) {
            $r[$k] = (string) $r[$k];
        }
        $r['type'] = (string) $r['type'];

        // 嵌套对象：batch（实测 4 字段，缺一前端会报错）
        if ($batch) {
            $r['batch'] = [
                'start_time' => (int) $batch['start_time'],
                'end_time'   => (int) $batch['end_time'],
                'total_num'  => (int) $batch['total_num'],
                'has_num'    => (int) $batch['has_num'],
            ];
        } else {
            $r['batch'] = [
                'start_time' => (int) $r['release_time'],
                'end_time'   => (int) $r['end_time'],
                'total_num'  => (int) $r['total_num_batch'],
                'has_num'    => (int) $r['sale_status_text'],
            ];
        }

        // 嵌套对象：publisher（实测 2 字段）
        $r['publisher'] = $pub ? [
            'name'         => (string) $pub['name'],
            'company_logo' => (string) $pub['company_logo'],
        ] : ['name' => '', 'company_logo' => ''];

        // 时间格式化字段：原站直接下发文本，若库中为空则实时生成
        if ((string) $r['release_date_time_text'] === '' && $r['release_time'] > 0) {
            $r['release_date_time_text'] = date('Y-m-d H:i', $r['release_time']);
        }
        if ((string) $r['release_time_date_text'] === '' && $r['release_time'] > 0) {
            $r['release_time_date_text'] = date('Y-m-d H:i:s', $r['release_time']);
        }

        return $r;
    }

    /**
     * 公告
     *
     * @param mixed $row
     * @return array
     */
    public static function affiche($row): array
    {
        $src = is_array($row) ? $row : $row->toArray();

        $r = [];
        foreach (self::AFFICHE_DETAIL_FIELDS as $k) {
            if (array_key_exists($k, $src)) {
                $r[$k] = $src[$k];
            }
        }
        // type 实测是 "1"~"5" 字符串，不可转 int
        $r['type'] = (string) ($r['type'] ?? '');
        $r['id']         = (int) $r['id'];
        $r['createtime'] = (int) $r['createtime'];

        return $r;
    }

    /**
     * 公告列表项（详情接口才带 content，列表不带）
     *
     * @param array $rows
     * @return array
     */
    public static function afficheList(array $rows): array
    {
        return array_map(static function ($row) {
            $src = is_array($row) ? $row : $row->toArray();
            $r = [];
            foreach (self::AFFICHE_LIST_FIELDS as $k) {
                $r[$k] = $src[$k] ?? null;
            }
            $r['id']         = (int) $r['id'];
            $r['createtime'] = (int) $r['createtime'];
            // 列表不下发 content，与原站实测字段集一致
            $r['type'] = (string) $r['type'];
            return $r;
        }, $rows);
    }

    /**
     * Banner 列表项 —— 原站实测严格 5 字段
     *
     * 🔴 为什么白名单在 Model 上再抄一份：
     *    Banner::LIST_FIELDS 是「模型自述的契约」，这里是「出参的实际执行点」。
     *    两处都写死5 个，是刻意的冗余——任一处被人放宽，契约回归会立刻红。
     *
     * 🔴 url 实测是空串""，不是 null：
     *    DB 里url 存了NULL 的话这里会下发 null，前端 `banner.url ? ... : ...`
     *    分支就走错。统一 (string) 兜底成""。
     *
     * @param array $rows
     * @return array
     */
    public static function bannerList(array $rows): array
    {
        return array_map(static function ($row) {
            $src = is_array($row) ? $row : $row->toArray();
            return [
                'id'       => (int) ($src['id'] ?? 0),
                'image'    => (string) ($src['image'] ?? ''),
                'url'      => (string) ($src['url'] ?? ''),
                'url_type' => (int) ($src['url_type'] ?? 1),
                'is_show'  => (int) ($src['is_show'] ?? 0),
            ];
        }, $rows);
    }

}
