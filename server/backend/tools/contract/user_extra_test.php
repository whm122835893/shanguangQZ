<?php
declare(strict_types=1);

/**
 * 用户扩展域 TDD 契约测试 —— RankService（市值排行）+ AddressService（收货地址）
 *
 * 背景：
 *   /user/ranking    原为内存假数据。市值 = 持有数 × 该藏品最低在售挂单价，
 *                    全部从 user_collection + market_goods 实时聚合，无快照表。
 *   /user/address_list 原为内存假数据。前端只读列表，建 user_address 表落真数据。
 *
 * 契约要点（前端消费字段）：
 *   ranking：{data: [{nickname, avatar, market_value(string), rank}], my: '', activityRule: ''}
 *            —— market_value 必须是 string（金额红线），rank 1 起按市值降序
 *   address_list：数组，项 {id, name, mobile, province, city, district, address, is_default}
 *            —— 只返回当前用户的，默认地址排最前
 *
 * 用法：php tools/contract/user_extra_test.php
 * 退出码：0 = 全通过，1 = 有用例失败（禁止提交）
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\RankService;
use app\service\AddressService;
use think\facade\Db;

const MARK_OK   = '[OK]  ';
const MARK_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): bool
{
    global $passed, $failed;
    if ($cond) {
        echo MARK_OK . $label . "\n";
        $passed++;
        return true;
    }
    echo MARK_FAIL . $label . "\n";
    if ($detail !== '') {
        echo '       ' . $detail . "\n";
    }
    $failed++;
    return false;
}

/* ==================== 测试准备 ==================== */

foreach (['RK_T_A', 'RK_T_B'] as $nick) {
    $uid = (int) Db::name('user')->where('nickname', $nick)->value('id');
    if ($uid > 0) {
        Db::name('user_collection')->where('user_id', $uid)->delete();
        Db::name('user_address')->where('user_id', $uid)->delete();
        Db::name('user')->where('id', $uid)->delete();
    }
}

// 测试藏品
Db::name('collection')->where('name', '测试排行藏品X')->delete();
$collX = (int) Db::name('collection')->insertGetId([
    'name' => '测试排行藏品X', 'listimg' => '/static/oss/test/rk.jpg', 'createtime' => time(),
]);

$mkUser = function (string $nick): int {
    return (int) Db::name('user')->insertGetId([
        'username'     => 'rk_' . strtolower($nick) . '_' . time(),
        'nickname'     => $nick,
        // user.uk_mobile 唯一键：空串会撞已有用户，必须生成唯一号
        'mobile'       => '199' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'inviter_code' => 'RK' . random_int(10000000, 99999999),
        'createtime'   => time(),
    ]);
};
$uidA = $mkUser('RK_T_A'); // 持 2 件 × 挂单 10 = 20
$uidB = $mkUser('RK_T_B'); // 持 1 件 × 挂单 100 = 100 → 第一

$mkHold = function (int $uid, int $num) use ($collX) {
    Db::name('user_collection')->insert([
        'user_id' => $uid, 'collection_id' => $collX, 'num' => $num,
        'sell_num' => 0, 'lock_num' => 0, 'is_collection' => 1, 'can_compound' => 0,
        'utime' => time(), 'createtime' => time(),
    ]);
};
$mkHold($uidA, 2);
$mkHold($uidB, 1);

// 藏品挂单：B 挂 100，A 挂 10（最低价 10 → A 市值 2×10）
Db::name('market_goods')->where('collection_code', 'like', 'RKT%')->delete();
Db::name('market_goods')->insert([
    'user_id' => $uidB, 'collection_id' => $collX, 'collection_code' => 'RKT-B1',
    'price' => '100.00', 'num' => 1, 'sold_num' => 0, 'status' => 1,
    'createtime' => time(), 'updatetime' => time(),
]);
Db::name('market_goods')->insert([
    'user_id' => $uidA, 'collection_id' => $collX, 'collection_code' => 'RKT-A1',
    'price' => '10.00', 'num' => 1, 'sold_num' => 0, 'status' => 1,
    'createtime' => time(), 'updatetime' => time(),
]);

/* ==================== 1. ranking 契约 ==================== */

$rank = RankService::ranking((int) $uidA);
check('ranking.data 是数组', is_array($rank['data'] ?? null));
check('ranking.my / activityRule 是字符串字段（原站实测结构）',
    array_key_exists('my', $rank) && array_key_exists('activityRule', $rank));

// 语义：市值 = 持有数 × 该藏品最低在售价（市场公允价，非用户自己的挂单价）
// A 持 2 × 10 = 20.00；B 持 1 × 10 = 10.00，A 名次必须高于 B。
// 注意：榜单可能混入演示数据等其他用户，断言只针对测试自建用户的相对次序与精确市值。
$byNick = [];
foreach (($rank['data'] ?? []) as $it) {
    $byNick[$it['nickname']] = $it;
}
$first = $byNick['RK_T_A'] ?? [];
$second = $byNick['RK_T_B'] ?? [];
check('公允计价后 A（20.00）上榜', isset($first['rank']),
    'got ' . var_export(array_keys($byNick), true));
check('A 市值 = 持有数 × 最低挂单价 = 20.00（string）',
    ($first['market_value'] ?? '') === '20.00',
    'got ' . var_export($first['market_value'] ?? null, true));
check('B 市值 10.00 上榜且名次低于 A',
    isset($second['rank']) && ($second['market_value'] ?? '') === '10.00'
    && (int) $second['rank'] > (int) $first['rank'],
    'got ' . var_export($second, true));
check('rank 从 1 开始', (int) (($rank['data'][0] ?? [])['rank'] ?? 0) === 1);
check('market_value 是 string（金额红线）', is_string($first['market_value'] ?? null));
check('项含 nickname/avatar 字段（前端模板消费）',
    isset($first['nickname'], $first['avatar']));

check('零市值用户不上榜（HAVING mv>0，而非显示 0 行）', (function () {
    // 持有无挂单藏品的用户不炸，且不进榜单
    $uid = (int) Db::name('user')->insertGetId([
        'username' => 'rk_t_c_' . time(), 'nickname' => 'RK_T_C',
        'mobile'       => '198' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
        'inviter_code' => 'RC' . random_int(10000000, 99999999), 'createtime' => time(),
    ]);
    Db::name('user_collection')->insert([
        'user_id' => $uid, 'collection_id' => 99999999, 'num' => 1,
        'sell_num' => 0, 'lock_num' => 0, 'is_collection' => 1, 'can_compound' => 0,
        'utime' => time(), 'createtime' => time(),
    ]);
    $r = RankService::ranking($uid);
    $hit = null;
    foreach (($r['data'] ?? []) as $it) {
        if (($it['nickname'] ?? '') === 'RK_T_C') {
            $hit = $it;
        }
    }
    Db::name('user_collection')->where('user_id', $uid)->delete();
    Db::name('user')->where('id', $uid)->delete();
    return $hit === null;
})());

/* ==================== 2. address 契约 ==================== */

Db::name('user_address')->insert([
    'user_id' => $uidA, 'name' => '张三', 'mobile' => '13800000001',
    'province' => '浙江省', 'city' => '杭州市', 'district' => '西湖区',
    'address' => '文三路 100 号', 'is_default' => 1, 'createtime' => time(),
]);
Db::name('user_address')->insert([
    'user_id' => $uidA, 'name' => '李四', 'mobile' => '13800000002',
    'province' => '浙江省', 'city' => '杭州市', 'district' => '滨江区',
    'address' => '江南大道 200 号', 'is_default' => 0, 'createtime' => time(),
]);
Db::name('user_address')->insert([
    'user_id' => $uidB, 'name' => '王五', 'mobile' => '13800000003',
    'address' => '上海市浦东新区', 'is_default' => 0, 'createtime' => time(),
]);

$list = AddressService::list($uidA);
check('address list 返回数组', is_array($list) && count($list) === 2, 'got ' . count($list ?: []));
check('默认地址排最前', ($list[0]['name'] ?? '') === '张三');
check('项含 name/mobile/address/is_default（前端消费字段）',
    isset($list[0]['name'], $list[0]['mobile'], $list[0]['address'], $list[0]['is_default']));
check('只返回当前用户地址（A 看不到 B 的）',
    (function () use ($list) {
        foreach ($list as $it) {
            if (($it['name'] ?? '') === '王五') {
                return false;
            }
        }
        return true;
    })());

/* ==================== 收尾 ==================== */

foreach ([$uidA, $uidB] as $uid) {
    Db::name('user_collection')->where('user_id', $uid)->delete();
    Db::name('user_address')->where('user_id', $uid)->delete();
    Db::name('user')->where('id', $uid)->delete();
}
Db::name('market_goods')->where('collection_code', 'like', 'RKT%')->delete();
Db::name('collection')->where('id', $collX)->delete();

echo "\n==============================\n";
echo "通过 {$passed} / " . ($passed + $failed) . " 项\n";
exit($failed > 0 ? 1 : 0);
