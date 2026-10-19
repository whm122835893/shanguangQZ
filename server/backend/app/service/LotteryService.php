<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 抽奖域服务（v3.0 从内存 MOCK 切 DB）
 *
 * 契约来源：前端 Lottery.vue / Record.vue 实际消费字段（1:1 复刻原站）。
 * 行为红线见 tools/contract/lottery_test.php 头注释，七条全在测试里锁定。
 *
 * 消耗货币与原站对齐：lottery.crystal '10'/'20'/'30' → user.crystal_yellow/purple/blue，
 * 扣减用带条件的原子 UPDATE，永不透支。奖池存 lottery.collection_json（JSON），
 * weight 加权随机（0 永不中），collection_id=0 为「谢谢参与」。
 *
 * @package app\service
 */
final class LotteryService
{
    /** 单抽允许的次数（前端两个按钮：抽一次 / 抽五次） */
    private const ALLOWED_NUMBERS = [1, 5];

    /** crystal 类型 → user 字段映射 */
    private const CRYSTAL_FIELDS = ['10' => 'crystal_yellow', '20' => 'crystal_purple', '30' => 'crystal_blue'];

    public static function info(int $userId, int $lotteryId): array
    {
        $lot = self::activeLottery($lotteryId);
        if ($lot === null) {
            throw new LotteryException('暂无抽奖活动');
        }

        $field     = self::crystalField((string) $lot['crystal']);
        $own       = (int) Db::name('user')->where('id', $userId)->value($field);
        $cost      = max(1, (int) $lot['rule']);
        $remaining = intdiv(max(0, $own), $cost);

        return [
            'id'              => (int) $lot['id'],
            'name'            => (string) $lot['name'],
            'content'         => (string) ($lot['content'] ?? ''),
            'image1'          => (string) $lot['image1'],
            'image2'          => (string) $lot['image2'],
            'crystal'         => (string) $lot['crystal'],
            'rule'            => (string) $lot['rule'],
            'lottery_max'     => (string) $lot['lottery_max'],
            'grand_total'     => (int) $lot['grand_total'],
            'is_limit'        => (int) $lot['is_limit'],
            'display_number'  => (int) $lot['display_number'],
            // 前端 activeState 用 Date.parse(String(start_time)) 判断进行中，必须 'Y-m-d H:i:s'
            'start_time'      => $lot['start_time'] > 0 ? date('Y-m-d H:i:s', (int) $lot['start_time']) : '',
            'end_time'        => $lot['end_time'] > 0 ? date('Y-m-d H:i:s', (int) $lot['end_time']) : '',
            // 拥有量（前端 crystal() 直接展示）
            'crystal_yellow'  => (int) Db::name('user')->where('id', $userId)->value('crystal_yellow'),
            'crystal_purple'  => (int) Db::name('user')->where('id', $userId)->value('crystal_purple'),
            'crystal_blue'    => (int) Db::name('user')->where('id', $userId)->value('crystal_blue'),
            'remaining'       => $remaining,
            'collection_json' => self::decodePool((string) ($lot['collection_json'] ?? '')),
            // 公告：最近中奖滚动（原站 msg 形如「obNY*** 2小时前抽中了XX」）
            'lotteryList'     => self::notices((int) $lot['id']),
        ];
    }

    /** 当前用户抽奖记录（Lottery.vue 奖品区 + Record.vue 卡② 消费：数组） */
    public static function statistics(int $userId): array
    {
        $rows = Db::name('lottery_record')
            ->alias('r')
            ->leftJoin('collection c', 'c.id = r.collection_id')
            ->where('r.user_id', $userId)
            ->field('r.lottery_lv, r.collection_id, r.createtime, c.name, c.listimg')
            ->order('r.id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $prizeName = (string) $r['lottery_lv'];
            $out[] = [
                'prize_name'    => $prizeName,
                'name'          => $prizeName,
                'image'         => (string) ($r['listimg'] ?? ''),
                'lottery_image' => (string) ($r['listimg'] ?? ''),
                'create_time'   => date('Y-m-d H:i:s', (int) $r['createtime']),
            ];
        }
        return $out;
    }

    /** 中奖记录（Record.vue 卡① 消费：{list, total}） */
    public static function records(int $userId, int $page, int $limit): array
    {
        $page  = max(1, $page);
        $limit = min(50, max(1, $limit));

        $q = Db::name('lottery_record')
            ->alias('r')
            ->leftJoin('collection c', 'c.id = r.collection_id')
            ->where('r.user_id', $userId)
            ->where('r.collection_id', '>', 0);

        $total = (int) (clone $q)->count();
        $rows  = $q->field('r.lottery_lv, r.createtime, c.name')
            ->order('r.id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $list = [];
        foreach ($rows as $r) {
            $list[] = [
                'prize_name'  => (string) $r['lottery_lv'],
                'name'        => (string) ($r['name'] ?? $r['lottery_lv']),
                'create_time' => date('Y-m-d H:i:s', (int) $r['createtime']),
            ];
        }
        return ['list' => $list, 'total' => $total];
    }

    /**
     * 抽奖。number ∈ {1,5}；逐次加权随机；事务内原子扣水晶，永不透支、永不部分执行。
     *
     * @return array 中奖项数组，项含 name/collection_name/image/lottery_image/level/collection_id
     */
    public static function draw(int $userId, int $lotteryId, int $number): array
    {
        if (!in_array($number, self::ALLOWED_NUMBERS, true)) {
            throw new LotteryException('抽奖次数不合法');
        }

        $lot = self::lotteryForDraw($lotteryId);
        if ($lot === null) {
            throw new LotteryException('暂无抽奖活动');
        }

        $field   = self::crystalField((string) $lot['crystal']);
        $cost    = max(1, (int) $lot['rule']);
        $consume = $cost * $number;
        $pool    = self::decodePool((string) ($lot['collection_json'] ?? ''));
        if (!$pool) {
            throw new LotteryException('奖池未配置');
        }

        Db::startTrans();
        try {
            // 行锁扣水晶：条件不满足即抛，事务回滚，零部分执行
            $hit = Db::name('user')
                ->where('id', $userId)
                ->where($field, '>=', $consume)
                ->dec($field, $consume)
                ->update();
            if (!$hit) {
                throw new LotteryException('次数不足');
            }

            $wins = [];
            for ($i = 0; $i < $number; $i++) {
                $prize = self::weightedPick($pool);
                self::deliver($userId, (int) $lot['id'], $prize);
                $wins[] = [
                    'name'            => (string) $prize['name'],
                    'collection_name' => (string) $prize['name'],
                    'level'           => (string) $prize['level'],
                    'image'           => (string) $prize['image'],
                    'lottery_image'   => (string) $prize['lottery_image'],
                    'collection_id'   => (int) $prize['collection_id'],
                ];
            }

            Db::name('lottery')->where('id', $lot['id'])->inc('grand_total', $number)->update();
            Db::commit();
            return $wins;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /** 管理端：建/改活动。返回 id。 */
    public static function adminSave(array $payload, ?int $id = null): int
    {
        $data = [
            'name'            => trim((string) ($payload['name'] ?? '')),
            'content'         => (string) ($payload['content'] ?? ''),
            'image1'          => (string) ($payload['image1'] ?? ''),
            'image2'          => (string) ($payload['image2'] ?? ''),
            'crystal'         => in_array((string) ($payload['crystal'] ?? '10'), array_keys(self::CRYSTAL_FIELDS), true)
                ? (string) $payload['crystal'] : '10',
            'rule'            => (string) max(1, (int) ($payload['rule'] ?? 10)),
            'lottery_max'     => (string) max(0, (int) ($payload['lottery_max'] ?? 0)),
            'collection_json' => self::encodePool($payload['collection_json'] ?? []),
            'start_time'      => (int) ($payload['start_time'] ?? 0),
            'end_time'        => (int) ($payload['end_time'] ?? 0),
            'switch'          => !empty($payload['switch']) ? 1 : 0,
            'updatetime'      => time(),
        ];
        if ($data['name'] === '') {
            throw new LotteryException('活动名称不能为空');
        }
        if ($data['end_time'] <= $data['start_time']) {
            throw new LotteryException('活动时间无效');
        }
        if ($id !== null) {
            Db::name('lottery')->where('id', $id)->update($data);
            return $id;
        }
        $data['createtime'] = time();
        return (int) Db::name('lottery')->insertGetId($data);
    }

    /* ==================== 内部 ==================== */

    /** 取进行中的活动：switch=1 且 start<=now<=end；id>0 时必须精确匹配 */
    private static function activeLottery(int $lotteryId): ?array
    {
        $now = time();
        $q   = Db::name('lottery')
            ->where('switch', 1)
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now);
        if ($lotteryId > 0) {
            $q->where('id', $lotteryId);
            $row = $q->find();
            return $row ? (array) $row : null;
        }
        $row = $q->order('id', 'desc')->find();
        return $row ? (array) $row : null;
    }

    /**
     * draw 专用：先查活动存在且未关闭，再按时间窗给出精确文案
     * （info 的时间窗外一律「暂无抽奖活动」——原站实测语义；
     *   draw 则明确告知「暂未开始/活动已结束」——前端 toast 需要区分）。
     */
    private static function lotteryForDraw(int $lotteryId): ?array
    {
        $row = Db::name('lottery')->where('switch', 1)->where('id', $lotteryId)->find();
        if (!$row) {
            return null;
        }
        $lot = (array) $row;
        $now = time();
        if ((int) $lot['start_time'] > $now) {
            throw new LotteryException('暂未开始');
        }
        if ((int) $lot['end_time'] < $now) {
            throw new LotteryException('活动已结束');
        }
        return $lot;
    }

    private static function crystalField(string $crystal): string
    {
        return self::CRYSTAL_FIELDS[$crystal] ?? 'crystal_yellow';
    }

    private static function decodePool(string $json): array
    {
        $arr = json_decode($json, true);
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
                'lottery_image' => (string) ($it['lottery_image'] ?? $it['image'] ?? ''),
                'level'         => (string) ($it['level'] ?? $it['name']),
                'weight'        => max(0, (int) ($it['weight'] ?? 0)),
            ];
        }
        return $out;
    }

    private static function encodePool($pool): string
    {
        // 管理端/测试传 JSON 字符串，服务内部统一转数组再校验
        if (is_string($pool)) {
            $pool = json_decode($pool, true);
        }
        if (!is_array($pool)) {
            return '[]';
        }
        $out = [];
        foreach ($pool as $it) {
            if (!is_array($it) || !isset($it['name'])) {
                continue;
            }
            $out[] = [
                'collection_id' => (int) ($it['collection_id'] ?? 0),
                'name'          => (string) $it['name'],
                'image'         => (string) ($it['image'] ?? ''),
                'lottery_image' => (string) ($it['lottery_image'] ?? ''),
                'level'         => (string) ($it['level'] ?? $it['name']),
                'weight'        => max(0, (int) ($it['weight'] ?? 0)),
            ];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /** 加权随机：weight=0 永不中；全零奖池返回「谢谢参与」兜底 */
    private static function weightedPick(array $pool): array
    {
        $total = 0;
        foreach ($pool as $p) {
            $total += $p['weight'];
        }
        if ($total <= 0) {
            return ['collection_id' => 0, 'name' => '谢谢参与', 'image' => '', 'lottery_image' => '', 'level' => '谢谢参与', 'weight' => 0];
        }
        $roll = random_int(1, $total);
        foreach ($pool as $p) {
            if ($p['weight'] <= 0) {
                continue;
            }
            $roll -= $p['weight'];
            if ($roll <= 0) {
                return $p;
            }
        }
        return $pool[0];
    }

    /** 发放：中藏品 upsert user_collection；全部落 lottery_record */
    private static function deliver(int $userId, int $lotteryId, array $prize): void
    {
        $now        = time();
        $collId     = (int) $prize['collection_id'];
        $prizeName  = (string) $prize['level'];

        if ($collId > 0) {
            $held = Db::name('user_collection')
                ->where('user_id', $userId)
                ->where('collection_id', $collId)
                ->find();
            if ($held) {
                Db::name('user_collection')->where('id', $held['id'])->inc('num', 1)->update(['utime' => $now]);
            } else {
                Db::name('user_collection')->insert([
                    'user_id'      => $userId,
                    'collection_id'=> $collId,
                    'num'          => 1,
                    'sell_num'     => 0,
                    'lock_num'     => 0,
                    'is_collection'=> 1,
                    'can_compound' => 0,
                    'utime'        => $now,
                    'createtime'   => $now,
                ]);
            }
        }

        Db::name('lottery_record')->insert([
            'lottery_id'   => $lotteryId,
            'user_id'      => $userId,
            'collection_id'=> $collId,
            'lottery_lv'   => $prizeName,
            'score'        => 0,
            'createtime'   => $now,
        ]);
    }

    /** 公告：最近藏品中奖 → [{msg: '昵*** N前抽中了XX'}]，昵称脱敏 */
    private static function notices(int $lotteryId): array
    {
        $rows = Db::name('lottery_record')
            ->alias('r')
            ->leftJoin('user u', 'u.id = r.user_id')
            ->where('r.lottery_id', $lotteryId)
            ->where('r.collection_id', '>', 0)
            ->field('r.lottery_lv, r.createtime, u.nickname')
            ->order('r.id', 'desc')
            ->limit(10)
            ->select()
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $nick = (string) ($r['nickname'] ?? '');
            $mask = mb_strlen($nick) > 3
                ? mb_substr($nick, 0, 3) . '***'
                : ($nick !== '' ? $nick . '***' : '用户***');
            $out[] = ['msg' => $mask . ' ' . self::ago((int) $r['createtime']) . '抽中了' . (string) $r['lottery_lv']];
        }
        return $out;
    }

    private static function ago(int $ts): string
    {
        $diff = time() - $ts;
        if ($diff < 60) {
            return $diff . '秒前 ';
        }
        if ($diff < 3600) {
            return intdiv($diff, 60) . '分钟前 ';
        }
        if ($diff < 86400) {
            return intdiv($diff, 3600) . '小时前 ';
        }
        return date('m-d ', $ts);
    }
}
