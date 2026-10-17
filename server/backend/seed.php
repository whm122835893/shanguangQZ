#!/usr/bin/env php
<?php
/**
 * seed.php —— 按原站实测响应灌入种子数据
 *
 * 🔴 铁律：本脚本的每一个值都必须能在 recon/raw2/ 的实测样本中找到出处。
 *    禁止「为了看起来像那么回事」而编造数据。样本里没有的字段一律留 DB 默认值。
 *
 * 样本来源：
 *   recon/raw2/user_getuserinfo.json                      用户 106 字段
 *   recon/raw2/order_order_orderlist.json                  订单 18 字段（2 条）
 *   recon/raw2/user_collection_user_collection_antmycollection.json  藏品持有（1 条）
 *   recon/raw2/user_newlist.json                           消息（1 条）
 *
 * 用法：php seed.php [--fresh]
 *   --fresh  先 TRUNCATE 目标表再灌（谨慎）
 *
 * @package app
 */

require __DIR__ . '/vendor/autoload.php';

// ==================== 启动 ThinkPHP ====================
$app = new think\App();
$app->initialize();

use app\model\Collection;
use app\model\ArtelOrder;
use app\model\Message;
use app\model\User;
use app\model\UserCollection;
use app\model\UserCredential;
use think\facade\Db;

$fresh = in_array('--fresh', $argv, true);

/**
 * 读取实测样本
 */
function sample(string $name): array
{
    $path = __DIR__ . '/../../recon/raw2/' . $name;
    if (!is_file($path)) {
        // 回退到项目外路径
        $path = '/workspace/recon/raw2/' . $name;
    }
    if (!is_file($path)) {
        fwrite(STDERR, "找不到样本文件: {$name}\n");
        exit(1);
    }
    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

echo "=== 读取实测样本 ===\n";
$userSample = sample('user_getuserinfo.json')['data'];
$orderSample = sample('order_order_orderlist.json')['data'];
$ucSample = sample('user_collection_user_collection_antmycollection.json')['data'];
$msgSample = sample('user_newlist.json')['data'];
echo "  用户 1 / 订单 " . count($orderSample['data']) . " / 藏品 "
     . count($ucSample['data']) . " / 消息 " . count($msgSample['list']) . "\n";

if ($fresh) {
    echo "\n=== --fresh：清空目标表 ===\n";
    Db::execute('SET FOREIGN_KEY_CHECKS=0');
    foreach (['user_credential', 'user_token', 'message', 'user_collection', 'artel_order', 'collection', 'user'] as $t) {
        Db::execute("TRUNCATE TABLE `{$t}`");
        echo "  TRUNCATE {$t}\n";
    }
    Db::execute('SET FOREIGN_KEY_CHECKS=1');
}

// ==================== 1. user ====================
echo "\n=== 灌入 user（实测 100 标量字段）===\n";

// 实测 API 字段 → DB 列 的映射：
//   token / url            → 不落库（见 sql/07 头注释）
//   verification.email     → email_verified
//   verification.mobile    → mobile_verified
//   statistics.*           → 4 个独立列
//   id_card_front/back     → 实测 null，落 NULL（不是空串）
$NESTED_TO_NULL = ['id_card_front', 'id_card_back', 'private_key', 'ntf_url',
                   'nft_url', 'ali_openid', 'user_wx', 'user_qq', 'user_phone',
                   'birthday'];

$verification = $userSample['verification'] ?? [];
$statistics   = $userSample['statistics'] ?? [];

$userRow = [];
foreach ($userSample as $k => $v) {
    if (in_array($k, ['token', 'url', 'verification', 'statistics'], true)) {
        continue;   // 这 4 项走特殊处理
    }
    if ($v === null) {
        $userRow[$k] = null;
    } else {
        $userRow[$k] = $v;
    }
}

// 实测为 null 的字段统一落 NULL（DB 默认值是空串，必须显式覆盖）
foreach ($NESTED_TO_NULL as $f) {
    $userRow[$f] = $userSample[$f] ?? null;
}

// 拆列
$userRow['email_verified']  = (int) ($verification['email']  ?? 0);
$userRow['mobile_verified'] = (int) ($verification['mobile'] ?? 0);
$userRow['invited_count']   = (int) ($statistics['invited_count'] ?? 0);
$userRow['buy_count']       = (int) ($statistics['buy_count'] ?? 0);
$userRow['sale_count']      = (int) ($statistics['sale_count'] ?? 0);
$userRow['join_day']        = (int) ($statistics['join_day'] ?? 0);

// 🔐 敏感字段：实测样本含真实手机号，入库前脱敏
//    （样本本身在 .gitignore 内不入库，但 DB 也不该存明文真实号码）
$userRow['username'] = '175****1293';
$userRow['mobile']   = '175****1293';
// id_card / real_name 原站已脱敏（"520******838" / "王*绵"），原样保留

// 只保留 DB 真实存在的列，防止拼错列名直接 500
$dbCols = array_column(
    Db::query("SHOW COLUMNS FROM `user`"),
    'Field'
);
$userRow = array_intersect_key($userRow, array_flip($dbCols));

$existing = User::find($userRow['id']);
if ($existing) {
    $existing->save($userRow);
    echo "  user id={$userRow['id']} 已存在 → 更新\n";
} else {
    User::create($userRow);
    echo "  user id={$userRow['id']} 新建\n";
}
$uid = (int) $userRow['id'];

// ==================== 2. user_credential（Argon2id） ====================
echo "\n=== 灌入 user_credential（Argon2id 哈希）===\n";

// 明文密码不落代码。优先读环境变量，未设置则随机生成并打印一次。
//   GANDART_SEED_PASSWORD=xxx php seed.php   —— 指定固定密码
//   php seed.php                —— 随机生成，仅本次有效
// 该密码与原站真实密码无关（原站密码不可知），仅用于本地登录联调。
$devPassword = (string) (getenv('GANDART_SEED_PASSWORD') ?: '');
if ($devPassword === '') {
    $devPassword = 'Dev@' . bin2hex(random_bytes(8));
    echo "  本次随机测试密码：{$devPassword}（未持久化，重跑会变）\n";
}
$hash = UserCredential::makeHash($devPassword);

$cred = UserCredential::where('user_id', $uid)->find();
if ($cred) {
    $cred->save(['password_hash' => $hash, 'updatetime' => time()]);
    echo "  凭据已更新（user_id={$uid}）\n";
} else {
    UserCredential::create([
        'user_id'       => $uid,
        'password_hash' => $hash,
        'locked'        => 0,
        'lock_until'    => 0,
        'login_fail_count' => 0,
        // 0 = 首次登录强制改密。开发环境设为 1 免去每次改密的麻烦。
        'pwd_status'    => 1,
        'pwd_updated_at'=> time(),
        'createtime'    => time(),
        'updatetime'    => time(),
    ]);
    echo "  凭据新建（user_id={$uid}）\n";
}
echo "  开发密码: {$devPassword}（Argon2id 已生成，明文不入库）\n";

// ==================== 3. collection ====================
echo "\n=== 灌入 collection（来自嵌套对象实测）===\n";

$COLL_MAP = [
    'id', 'name', 'main_image', 'listimg', 'collection_level',
    'total_num_show', 'total_num', 'is_img_diff',
    'unfreeze_collection_id', 'freeze_time',
];

$nested = $ucSample['data'][0]['collection'] ?? null;
if ($nested) {
    $collRow = array_intersect_key($nested, array_flip($COLL_MAP));
    // reprice_* 实测来源是 market/trade/collectionDetail 顶层，此处不灌（无实测值）
    $collRow['createtime'] = $ucSample['data'][0]['utime'] ?? time();
    $collRow['updatetime'] = $collRow['createtime'];
    $collRow['is_show']    = 1;

    $dbCols = array_column(Db::query("SHOW COLUMNS FROM `collection`"), 'Field');
    $collRow = array_intersect_key($collRow, array_flip($dbCols));

    $exists = Collection::find($collRow['id']);
    if ($exists) {
        $exists->save($collRow);
        echo "  collection id={$collRow['id']} 已存在 → 更新\n";
    } else {
        Collection::create($collRow);
        $cname = (string) ($collRow['name'] ?? '?');
        echo "  collection id={$collRow['id']} 新建（{$cname}）\n";
    }
}

// ==================== 4. user_collection ====================
echo "\n=== 灌入 user_collection（实测持有记录）===\n";

foreach ($ucSample['data'] as $i => $row) {
    $ucRow = [
        'user_id'       => $uid,
        'collection_id' => (int) $row['collection_id'],
        'num'           => (int) $row['num'],           // int
        'sell_num'      => (int) $row['sell_num'],      // ⚠️ API 是 string，入库转 int
        'lock_num'      => (int) $row['lock_num'],
        'is_collection' => (int) $row['is_collection'],
        'can_compound'  => (int) $row['can_compound'],
        'utime'         => (int) $row['utime'],
        'createtime'    => (int) $row['utime'],
    ];
    $exists = UserCollection::where('user_id', $uid)
        ->where('collection_id', $ucRow['collection_id'])
        ->find();
    if ($exists) {
        $exists->save($ucRow);
        echo "  持有 id={$exists['id']} → 更新\n";
    } else {
        $new = UserCollection::create($ucRow);
        echo "  持有 id={$new['id']} 新建（collection_id={$ucRow['collection_id']}）\n";
    }
}

// ==================== 5. artel_order ====================
echo "\n=== 灌入 artel_order（实测 2 条真实订单）===\n";

// ⚠️ collection_code 实测为空数组 []，DB 无此列（样本全空无从推断结构），出参时补 []
$ORDER_MAP = [
    'id', 'order_sn', 'artel_order', 'order_type', 'order_status', 'total_price',
    'num', 'goods_id', 'goods_type', 'goods_name', 'goods_cover_image',
    'series_name', 'crystal_type', 'crystal_number', 'uc_ids',
    'createtime', 'updatetime',
];

foreach ($orderSample['data'] as $row) {
    $o = array_intersect_key($row, array_flip($ORDER_MAP));
    $o['user_id'] = $uid;
    // ⚠️ order_status 是 int（实测 3），order_type / num 是 string（实测 "2" / "1"）
    //    这里严格按 JSON 原生类型入库，不做任何"统一化"处理
    $o['createtime'] = (int) $o['createtime'];
    $o['updatetime'] = (int) $o['updatetime'];
    // uc_ids 实测 null → 保持 null
    $o['pay_channel'] = '';
    $o['pay_time']    = 0;

    $exists = ArtelOrder::find($o['id']);
    if ($exists) {
        $exists->save($o);
        echo "  订单 id={$o['id']} → 更新（status={$o['order_status']}, type={$o['order_type']}）\n";
    } else {
        ArtelOrder::create($o);
        echo "  订单 id={$o['id']} 新建（sn={$o['order_sn']}）\n";
    }
}

// ==================== 6. message ====================
echo "\n=== 灌入 message（实测 1 条）===\n";

foreach ($msgSample['list'] as $row) {
    // 🔴 关键转换：API 下发 createtime 是字符串 "2026-10-15 15:38"
    //    DB 存 int unixtime（与全项目统一，且可排序可区间查询）
    $ts = strtotime($row['createtime']);
    $mRow = [
        'user_id'     => $uid,
        'title'       => (string) $row['title'],
        'content'     => (string) $row['content'],
        'read_status' => (int) $row['read_status'],
        'createtime'  => $ts !== false ? $ts : time(),
    ];
    $exists = Message::find($row['id']);
    if ($exists) {
        $exists->save($mRow);
        echo "  消息 id={$row['id']} → 更新\n";
    } else {
        // ⚠️ 必须显式带上原站 id：Message::create() 不传 id 时走数据库自增，
        //    会把实测 id 55616 覆盖成 1。原站 id 是契约的一部分（用户可见、
        //    且 readMsgById 这类接口按 id 寻址），不能用自增代替。
        Message::create($mRow + ['id' => (int) $row['id']]);
        echo "  消息 id={$row['id']} 新建（时间串 \"{$row['createtime']}\" → unixtime {$mRow['createtime']}）\n";
    }
}

// ==================== 汇总 ====================
echo "\n=== 灌入完成，核对行数 ===\n";
foreach (['user', 'user_credential', 'collection', 'user_collection', 'artel_order', 'message'] as $t) {
    $n = Db::table($t)->count();
    echo "  {$t}: {$n}\n";
}

/**
 * 🔴 契约断言：原站 id 必须原样保留，不能被数据库自增覆盖
 *
 * 踩过的坑：Message::create($row) 不传 id 时走自增，把实测的 55616 写成了 1。
 *         原站 id 是契约的一部分 —— 用户可见，且 readMsgById 类接口按 id 寻址。
 *         这个断言放在最后，任何人改 seed 逻辑导致 id 漂移都会立刻失败。
 */
echo "\n=== 契约断言：原站 id 保留 ===\n";
$expectIds = [
    'user'          => $userSample['id'],
    'collection'    => $ucSample['data'][0]['collection_id'] ?? 0,
    'message'       => $msgSample['list'][0]['id'] ?? 0,
];
$fail = 0;
foreach ($expectIds as $table => $wantId) {
    if (!$wantId) {
        continue;
    }
    $got = Db::table($table)->where('id', $wantId)->count();
    if ($got > 0) {
        echo "  ✅ {$table}.id={$wantId} 已保留\n";
    } else {
        echo "  ❌ {$table}.id={$wantId} 丢失（可能被自增覆盖）\n";
        $fail++;
    }
}

// 订单 id 逐条核对
foreach ($orderSample['data'] as $row) {
    $oid = (int) $row['id'];
    if (Db::table('artel_order')->where('id', $oid)->count() > 0) {
        echo "  ✅ artel_order.id={$oid} 已保留\n";
    } else {
        echo "  ❌ artel_order.id={$oid} 丢失\n";
        $fail++;
    }
}

if ($fail > 0) {
    fwrite(STDERR, "\n契约断言失败 {$fail} 项，seed 结果与原站契约不一致\n");
    exit(1);
}

echo "\n✅ seed 完成。所有值均来自实测样本，无编造数据，原站 id 全部保留。\n";
