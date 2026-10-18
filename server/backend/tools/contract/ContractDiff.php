<?php
declare(strict_types=1);

namespace tools\contract;

/**
 * 契约比对器
 *
 * 职责：把「本地 DB 驱动的出参」与「原站实测样本」做三方比对——
 *   1. 字段集：缺字段 / 多字段（多下发一个字段前端就可能踩未定义分支）
 *   2. 值：同名字段的值是否一致
 *   3. 信封：信封键集合与顺序是否一致
 *
 * 判定标准只看「契约层」——字段名、类型形态、null 语义。
 * 业务语义（如费率算法）不在比对范围，那是我们的设计自由度。
 *
 * @package tools\contract
 */
class ContractDiff
{
    /** @var array 缺失字段（本地有、原站无） */
    public array $extra = [];

    /** @var array 缺失字段（原站有、本地无） */
    public array $missing = [];

    /** @var array 值差异 field => [原站值, 本地值] */
    public array $valueDiff = [];

    /** @var array 信封键差异 */
    public array $envelopeDiff = [];

    /**
     * 扁平化嵌套数组，取所有叶子字段
     *
     * 原站出参有 `verification.email` 这类嵌套结构，
     * 而 DB 侧是 `email_verified` 拆列 —— 所以比对前先按路径扁平。
     *
     * 注意：标量数组（如 uc_ids = ["121926"]）**不能**按 0 拆成
     * `uc_ids.0` —— 那会让「标量」与「一维数组」在字段名上就对不上，
     * 制造假差异。规则：列表若首元素是标量，视为**标量**，保持原路径。
     */
    public static function flatten(array $data, string $prefix = ''): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : $prefix . '.' . $k;
            if (is_array($v)) {
                // 空数组无叶子可取，用 __empty__ 标记「存在但为空」
                if ($v === []) {
                    $out[$key] = '__empty__';
                    continue;
                }
                // 标量列表：保持原路径，不拆下标
                if (!is_array(reset($v))) {
                    $out[$key] = implode(',', array_map('strval', $v));
                    continue;
                }
                $out += self::flatten($v, $key);
                continue;
            }
            $out[$key] = $v;
        }
        return $out;
    }

    /**
     * 归一化值，让「类型相同但表示不同」不算差异
     *
     * 例：原站 "1" vs 本地 1 —— 契约上都是「ID 为 1」，不应报差异。
     * 但 null 与 0 是**真差异**（语义完全不同），必须保留。
     */
    public static function normalize($v)
    {
        if ($v === '__empty__') {
            return '__empty__';
        }
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return (int) $v;
        }
        if (is_numeric($v)) {
            return (string) $v;
        }
        return (string) $v;
    }

    /**
     * 比对两条记录
     *
     * @param array $raw      原站实测（已剥离信封的 data 部分）
     * @param array $local    本地 DB 驱动出参（同上）
     * @param array $ignore   要排除的运行时字段（如 logintime/loginip）
     */
    public function compare(array $raw, array $local, array $ignore = []): void
    {
        $r = self::flatten($raw);
        $l = self::flatten($local);
        if ($ignore) {
            foreach ($ignore as $f) {
                unset($r[$f], $l[$f]);
            }
        }

        $this->missing = array_values(array_diff(array_keys($r), array_keys($l)));
        $this->extra   = array_values(array_diff(array_keys($l), array_keys($r)));

        foreach (array_intersect(array_keys($r), array_keys($l)) as $k) {
            $rv = self::normalize($r[$k]);
            $lv = self::normalize($l[$k]);
            if ($rv !== $lv) {
                $this->valueDiff[$k] = [$r[$k], $l[$k]];
            }
        }
    }

    /**
     * 比对信封键
     *
     * @param array $keys 原站信封键
     * @param array $keys2 本地信封键
     */
    public function compareEnvelope(array $keys, array $keys2): void
    {
        $missing = array_values(array_diff($keys, $keys2));
        $extra   = array_values(array_diff($keys2, $keys));
        $order   = (array_values($keys) === array_values($keys2)) ? null : '顺序不同';
        if ($missing || $extra || $order) {
            $this->envelopeDiff = [
                'missing' => $missing,
                'extra'   => $extra,
                'order'   => $order,
            ];
        }
    }

    public function passed(): bool
    {
        return !$this->missing
            && !$this->extra
            && !$this->valueDiff
            && !$this->envelopeDiff;
    }

    /**
     * 渲染成可读报告
     */
    public function report(string $endpoint, int $fieldCount): string
    {
        $lines = [];
        $lines[] = sprintf('%-18s 字段 %3d/%-3d 缺%d 多%d 差%d 信封%s',
            $endpoint,
            $fieldCount,
            $fieldCount - count($this->missing),
            count($this->missing),
            count($this->extra),
            count($this->valueDiff),
            $this->envelopeDiff ? '差异' : '同'
        );

        foreach ($this->missing as $f) {
            $lines[] = "    [缺失] 原站有本地无：{$f}";
        }
        foreach ($this->extra as $f) {
            $lines[] = "    [多余] 本地有原站无：{$f}";
        }
        foreach ($this->valueDiff as $f => [$a, $b]) {
            $lines[] = sprintf('    [值差] %-24s 原站=%s 本地=%s',
                $f,
                json_encode($a, JSON_UNESCAPED_UNICODE),
                json_encode($b, JSON_UNESCAPED_UNICODE)
            );
        }
        if ($this->envelopeDiff) {
            $lines[] = '    [信封] ' . json_encode($this->envelopeDiff, JSON_UNESCAPED_UNICODE);
        }
        return implode("\n", $lines);
    }
}
