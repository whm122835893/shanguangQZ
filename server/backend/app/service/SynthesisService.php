<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 合成域服务（v3.0 从内存 MOCK 切 DB）
 *
 * 契约来源：前端 Compound.vue 实际消费字段（1:1 复刻原站）。
 * 行为红线见 tools/contract/synthesis_test.php 头注释。
 *
 * 关键点：
 *   - 方案时间戳一律毫秒（synthesis_plan 表注释「实测 13 位」，前端直接与 Date.now() 比对）
 *   - 本域只有「展示 + 材料查询」，无执行合成端点（前端 60 端点里没有），
 *     recordList 无记录载体，恒真实空分页
 *
 * @package app\service
 */
final class SynthesisService
{
    /** 主题详情（含 play 方案列表）。thm<=0 取排序最高主题 */
    public static function themeinfo(int $thm): array
    {
        $q = Db::name('synthesis_theme')->where('status', 1);
        $theme = $thm > 0
            ? (function () use ($q, $thm) {
                $row = (clone $q)->where('id', $thm)->find();
                return $row ? (array) $row : null;
            })()
            : (function () use ($q) {
                $row = (clone $q)->order('sort', 'desc')->order('id', 'desc')->find();
                return $row ? (array) $row : null;
            })();

        if ($theme === null) {
            throw new SynthesisException('主题未开启');
        }

        $plans = Db::name('synthesis_plan')
            ->where('theme_id', (int) $theme['id'])
            ->where('status', 1)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $play = [];
        foreach ($plans as $p) {
            $play[] = [
                'id'              => (int) $p['id'],
                'title'           => (string) $p['title'],
                'image'           => (string) $p['image'],
                'description'     => (string) ($p['description'] ?? ''),
                // 毫秒时间戳（前端 Number(it.start_time) 与 Date.now() 直接比对）
                'start_time'      => (int) $p['start_time'],
                'end_time'        => (int) $p['end_time'],
                'real_start_time' => (int) $p['real_start_time'],
                'classify'        => (string) $p['classify'],
                'type'            => (string) $p['type'],
                'stock'           => (int) $p['stock'],
                'is_auto'         => (int) $p['is_auto'],
            ];
        }

        return [
            'id'        => (int) $theme['id'],
            'name'      => (string) $theme['name'],
            'image'     => (string) $theme['image'],
            'rule_text' => (string) ($theme['rule_text'] ?? ''),
            'types'     => (int) $theme['types'],
            'play'      => $play,
        ];
    }

    /** 方案材料清单（need_items JSON 解析；无材料返回空数组——原站实测合法） */
    public static function material(int $playId): array
    {
        $plan = Db::name('synthesis_plan')->where('id', $playId)->where('status', 1)->find();
        if ($plan === null) {
            throw new SynthesisException('活动不存在');
        }
        return self::decodeItems((string) ($plan['need_items'] ?? ''));
    }

    /** 合成记录：本域无执行端点、无记录载体，恒真实空分页 */
    public static function records(int $userId, int $page, int $limit): array
    {
        return ['list' => [], 'total' => 0];
    }

    /* ==================== 管理端 ==================== */

    public static function adminSaveTheme(array $payload, ?int $id = null): int
    {
        $data = [
            'name'       => trim((string) ($payload['name'] ?? '')),
            'image'      => (string) ($payload['image'] ?? ''),
            'types'      => max(1, (int) ($payload['types'] ?? 1)),
            'rule_text'  => (string) ($payload['rule_text'] ?? ''),
            'sort'       => (int) ($payload['sort'] ?? 0),
            'status'     => (int) ($payload['status'] ?? 1) === 0 ? 0 : 1,
            'updatetime' => time(),
        ];
        if ($data['name'] === '') {
            throw new SynthesisException('主题名称不能为空');
        }
        if ($id !== null) {
            Db::name('synthesis_theme')->where('id', $id)->update($data);
            return $id;
        }
        $data['createtime'] = time();
        return (int) Db::name('synthesis_theme')->insertGetId($data);
    }

    public static function adminSavePlan(array $payload, ?int $id = null): int
    {
        $data = [
            'theme_id'        => (int) ($payload['theme_id'] ?? 0),
            'title'           => trim((string) ($payload['title'] ?? '')),
            'image'           => (string) ($payload['image'] ?? ''),
            'description'     => (string) ($payload['description'] ?? ''),
            'start_time'      => (int) ($payload['start_time'] ?? 0),
            'end_time'        => (int) ($payload['end_time'] ?? 0),
            'real_start_time' => (int) ($payload['real_start_time'] ?? $payload['start_time'] ?? 0),
            'classify'        => in_array((string) ($payload['classify'] ?? 'regular'), ['regular', 'delay', 'advance'], true)
                ? (string) $payload['classify'] : 'regular',
            'type'            => (string) ($payload['type'] ?? '10'),
            'stock'           => (int) ($payload['stock'] ?? 0),
            'need_items'      => self::encodeItems($payload['need_items'] ?? []),
            'status'          => (int) ($payload['status'] ?? 1) === 0 ? 0 : 1,
            'updatetime'      => time(),
        ];
        if ($data['theme_id'] <= 0) {
            throw new SynthesisException('主题不能为空');
        }
        if ($data['title'] === '') {
            throw new SynthesisException('方案标题不能为空');
        }
        if (Db::name('synthesis_theme')->where('id', $data['theme_id'])->find() === null) {
            throw new SynthesisException('主题不存在');
        }
        if ($id !== null) {
            Db::name('synthesis_plan')->where('id', $id)->update($data);
            return $id;
        }
        $data['createtime'] = time();
        return (int) Db::name('synthesis_plan')->insertGetId($data);
    }

    /* ==================== 内部 ==================== */

    private static function decodeItems(string $json): array
    {
        $arr = is_string($json) ? json_decode($json, true) : $json;
        if (!is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $it) {
            if (!is_array($it) || !isset($it['name'])) {
                continue;
            }
            $out[] = [
                'collection_id' => (int) ($it['collection_id'] ?? 0),
                'name'          => (string) $it['name'],
                'image'         => (string) ($it['image'] ?? ''),
                'num'           => max(1, (int) ($it['num'] ?? 1)),
            ];
        }
        return $out;
    }

    private static function encodeItems($items): string
    {
        if (is_string($items)) {
            $items = json_decode($items, true);
        }
        if (!is_array($items)) {
            return '[]';
        }
        return json_encode(self::decodeItems(json_encode($items, JSON_UNESCAPED_UNICODE)), JSON_UNESCAPED_UNICODE);
    }
}
