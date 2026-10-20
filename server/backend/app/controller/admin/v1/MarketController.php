<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\AdminException;
use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * MarketController —— 市场管理（契约 §8.9，7 个接口）
 *
 * 载体说明（§3.5）：真实挂单载体为 market_goods（C 端 TradeController/OrderService 在用），
 * consignment 为旧版遗留表，v1 管理端不使用。
 *
 * market_goods.status：1=在售 2=已售 3=已下架 4=已锁定
 * 强制下架：仅在售单可下架；status→3 + is_system_delisted=1 + delist_reason + 审计落库。
 *   C 端购买/挂单链路读到 status!=1 自然不可见，无需通知机制。
 *
 * 价格预警（§8.9.83）：实时扫描而非定时任务——挂单价 < 发行价 1% → 低价，
 *   > 发行价 10 倍 → 高价。基准取 collection.sale_price（发行价）。
 *
 * 手续费配置（§8.9.84/85）：site_config KV（key=market.fee_config），JSON value；
 *   fee_rate ∈ (0,100]；修改需管理员密码 + 审计。RBAC：查看 market:list / 修改 market:config。
 *
 * @package app\controller\admin\v1
 */
final class MarketController
{
    use V1;

    public const FEE_CONFIG_KEY = 'market.fee_config';
    /** C 端二级市场订单的 artel_order 标识（订单模块实测形态） */
    private const MARKET_ORDER_TYPE = 'mh_secondary_market_order';

    /** GET /market/listings —— 挂单列表（80） */
    public function listings()
    {
        self::guard('market:list');
        [$page, $size] = self::paging();

        $q = Db::name('market_goods')->alias('mg')
            ->leftJoin('collection c', 'c.id = mg.collection_id')
            ->leftJoin('user u', 'u.id = mg.user_id')
            ->field('mg.id,mg.user_id,mg.collection_id,mg.collection_code,mg.price,mg.num,mg.sold_num,'
                . 'mg.status,mg.is_system_delisted,mg.delist_reason,mg.createtime,mg.updatetime,'
                . 'c.name as collection_name,u.nickname as seller_nickname,u.mobile as seller_mobile');
        if (self::str('status') !== '') {
            $q->where('mg.status', self::int('status'));
        }
        if ($cid = self::int('collectible_id')) {
            $q->where('mg.collection_id', $cid);
        }
        if ($uid = self::int('user_id')) {
            $q->where('mg.user_id', $uid);
        }
        if (self::str('price_min') !== '') {
            $q->where('mg.price', '>=', self::str('price_min'));
        }
        if (self::str('price_max') !== '') {
            $q->where('mg.price', '<=', self::str('price_max'));
        }
        $q->order('mg.id', 'desc');

        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(fn ($r) => $this->listingBrief($r), $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** POST /market/listings/delist —— 强制下架（81，高风险：密码 + 审计） */
    public function delist()
    {
        $admin = self::guard('market:manage');
        $id = self::int('id');
        $reason = self::str('reason');
        if ($id <= 0) {
            throw new AdminException('缺少必填参数: id');
        }
        if ($reason === '') {
            throw new AdminException('请填写下架原因');
        }
        self::verifyPassword($admin);

        $mg = Db::name('market_goods')->where('id', $id)->find();
        if ($mg === null) {
            throw new AdminException('挂单不存在', Resp::NOT_FOUND);
        }
        if ((int) $mg['status'] !== 1) {
            throw new AdminException('仅在售状态的挂单可强制下架', Resp::CONFLICT);
        }
        Db::name('market_goods')->where('id', $id)->update([
            'status' => 3,
            'is_system_delisted' => 1,
            'delist_reason' => mb_substr($reason, 0, 255),
            'updatetime' => time(),
        ]);
        Guard::audit('market_delist', (int) $admin['id'], $id, [
            'reason' => $reason, 'owner_user_id' => (int) $mg['user_id'],
            'collection_id' => (int) $mg['collection_id'], 'price' => (string) $mg['price'],
        ]);
        return Resp::ok(['id' => $id]);
    }

    /** GET /market/trades —— 成交记录（82） */
    public function trades()
    {
        self::guard('market:list');
        [$page, $size] = self::paging();

        $q = Db::name('artel_order')->alias('o')
            ->leftJoin('user u', 'u.id = o.user_id')
            ->field('o.id,o.order_sn,o.user_id,o.total_price,o.num,o.goods_id,o.goods_name,'
                . 'o.pay_channel,o.pay_time,o.createtime,u.nickname as buyer_nickname,u.mobile as buyer_mobile')
            ->where('o.artel_order', self::MARKET_ORDER_TYPE)
            ->where('o.pay_time', '>', 0);
        if ($cid = self::int('collectible_id')) {
            $q->where('o.goods_id', $cid);
        }
        if ($uid = self::int('user_id')) {
            $q->where('o.user_id', $uid);
        }
        $q->order('o.id', 'desc');

        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();
        $list = array_map(static fn ($r) => [
            'id'             => (int) $r['id'],
            'order_sn'       => (string) $r['order_sn'],
            'user_id'        => (int) $r['user_id'],
            'buyer_nickname' => (string) ($r['buyer_nickname'] ?? ''),
            'buyer_mobile'   => self::maskPhone((string) ($r['buyer_mobile'] ?? '')),
            'collectible_id' => (int) $r['goods_id'],
            'goods_name'     => (string) $r['goods_name'],
            'total_price'    => self::money($r['total_price']),
            'num'            => (int) $r['num'],
            'pay_channel'    => (string) $r['pay_channel'],
            'pay_time'       => self::iso((int) $r['pay_time']),
            'create_time'    => self::iso((int) $r['createtime']),
        ], $rows);
        return Resp::page($list, $total, $page, $size);
    }

    /** GET /market/price-alerts —— 异常价格预警（83，实时扫描） */
    public function priceAlerts()
    {
        self::guard('market:monitor');
        [$page, $size] = self::paging();

        $rows = Db::name('market_goods')->alias('mg')
            ->join('collection c', 'c.id = mg.collection_id')
            ->where('mg.status', 1)
            ->where('c.sale_price', '>', 0)
            ->field('mg.id,mg.user_id,mg.collection_id,mg.price,mg.updatetime,'
                . 'c.name as collection_name,c.sale_price')
            ->select()->toArray();

        $now = time();
        $alerts = [];
        foreach ($rows as $r) {
            $base = (float) $r['sale_price'];
            $price = (float) $r['price'];
            if ($base <= 0) {
                continue;
            }
            $kind = '';
            if ($price < $base * 0.01) {
                $kind = 'low';
            } elseif ($price > $base * 10) {
                $kind = 'high';
            }
            if ($kind === '') {
                continue;
            }
            $alerts[] = [
                'listing_id'      => (int) $r['id'],
                'user_id'         => (int) $r['user_id'],
                'collectible_id'  => (int) $r['collection_id'],
                'collection_name' => (string) $r['collection_name'],
                'kind'            => $kind,
                'price'           => self::money($price),
                'base_price'      => self::money($base),
                'ratio'           => round($price / $base, 4),
                'detected_at'     => self::iso($now),
            ];
        }

        $total = count($alerts);
        $alerts = array_slice($alerts, ($page - 1) * $size, $size);
        return Resp::page($alerts, $total, $page, $size);
    }

    /** GET /market/fee-config —— 手续费配置读取（84） */
    public function feeConfig()
    {
        self::guard('market:list');
        $raw = Db::name('site_config')->where('key', self::FEE_CONFIG_KEY)->value('value');
        $cfg = json_decode((string) $raw, true);
        if (!is_array($cfg)) {
            $cfg = [];
        }
        return Resp::ok([
            'fee_rate' => (string) ($cfg['fee_rate'] ?? '5'),
            'fee_min'  => (string) ($cfg['fee_min'] ?? '1.00'),
            'fee_max'  => (string) ($cfg['fee_max'] ?? '500.00'),
        ]);
    }

    /** PUT/POST /market/fee-config —— 手续费配置修改（85，高风险：密码 + 审计） */
    public function feeConfigUpdate()
    {
        $admin = self::guard('market:config');
        $rate = self::str('fee_rate');
        $min = self::str('fee_min');
        $max = self::str('fee_max');
        if ($rate === '' || $min === '' || $max === '') {
            throw new AdminException('缺少必填参数: fee_rate/fee_min/fee_max');
        }
        if (!is_numeric($rate) || (float) $rate <= 0 || (float) $rate > 100) {
            throw new AdminException('fee_rate 必须在 (0, 100] 区间');
        }
        if (!is_numeric($min) || !is_numeric($max) || (float) $min < 0 || (float) $max < (float) $min) {
            throw new AdminException('fee_min/fee_max 不合法（需 0 ≤ min ≤ max）');
        }
        self::verifyPassword($admin);

        $value = json_encode([
            'fee_rate' => $rate,
            'fee_min'  => self::money($min),
            'fee_max'  => self::money($max),
        ], JSON_UNESCAPED_UNICODE);
        Db::name('site_config')->where('key', self::FEE_CONFIG_KEY)->update([
            'value' => $value,
            'updatetime' => time(),
        ]);
        Guard::audit('market_fee_config', (int) $admin['id'], 0, [
            'fee_rate' => $rate, 'fee_min' => self::money($min), 'fee_max' => self::money($max),
        ]);
        return Resp::ok();
    }

    /** GET /market/collectibles/:id/listings —— 某藏品寄售监控（86） */
    public function collectibleListings()
    {
        self::guard('market:monitor');
        $cid = self::int('id');
        $col = Db::name('collection')->where('id', $cid)->find();
        if ($col === null) {
            throw new AdminException('藏品不存在', Resp::NOT_FOUND);
        }
        [$page, $size] = self::paging();

        $q = Db::name('market_goods')->alias('mg')
            ->leftJoin('user u', 'u.id = mg.user_id')
            ->field('mg.id,mg.user_id,mg.collection_id,mg.collection_code,mg.price,mg.num,mg.sold_num,'
                . 'mg.status,mg.is_system_delisted,mg.delist_reason,mg.createtime,mg.updatetime,'
                . 'u.nickname as seller_nickname,u.mobile as seller_mobile')
            ->where('mg.collection_id', $cid)
            ->order('mg.id', 'desc');
        $total = (clone $q)->count();
        $rows = $q->page($page, $size)->select()->toArray();

        // 近 7 日成交均价趋势（数据源：已支付二级市场订单，按应用时区聚合）
        $trendRows = Db::name('artel_order')
            ->where('artel_order', self::MARKET_ORDER_TYPE)
            ->where('goods_id', $cid)
            ->where('pay_time', '>', 0)
            ->where('pay_time', '>=', strtotime(date('Y-m-d')) - 6 * 86400)
            ->field("FROM_UNIXTIME(pay_time, '%Y-%m-%d') as d, COUNT(*) as cnt, AVG(total_price) as avg_price")
            ->group('d')->order('d', 'asc')->select()->toArray();
        $priceTrend = array_map(static fn ($t) => [
            'date'      => (string) $t['d'],
            'avg_price' => self::money($t['avg_price']),
            'count'     => (int) $t['cnt'],
        ], $trendRows);

        $list = array_map(fn ($r) => $this->listingBrief($r), $rows);
        return Resp::ok([
            'collectible' => [
                'id'         => (int) $col['id'],
                'name'       => (string) $col['name'],
                'sale_price' => self::money($col['sale_price'] ?? 0),
                'total_num'  => (int) $col['total_num'],
            ],
            'list'        => $list,
            'total'       => $total,
            'page'        => $page,
            'page_size'   => $size,
            'price_trend' => $priceTrend,
        ]);
    }

    // --------------------------------------------------------

    /** 挂单行 → 输出（金额 string、卖家手机号脱敏） */
    private function listingBrief(array $r): array
    {
        return [
            'id'                 => (int) $r['id'],
            'user_id'            => (int) $r['user_id'],
            'seller_nickname'    => (string) ($r['seller_nickname'] ?? ''),
            'seller_mobile'      => self::maskPhone((string) ($r['seller_mobile'] ?? '')),
            'collectible_id'     => (int) $r['collection_id'],
            'collection_name'    => (string) ($r['collection_name'] ?? ''),
            'collection_code'    => (string) ($r['collection_code'] ?? ''),
            'price'              => self::money($r['price']),
            'num'                => (int) $r['num'],
            'sold_num'           => (int) $r['sold_num'],
            'status'             => (int) $r['status'],
            'is_system_delisted' => (int) ($r['is_system_delisted'] ?? 0),
            'delist_reason'      => (string) ($r['delist_reason'] ?? ''),
            'create_time'        => self::iso((int) $r['createtime']),
            'update_time'        => self::iso((int) $r['updatetime']),
        ];
    }
}
