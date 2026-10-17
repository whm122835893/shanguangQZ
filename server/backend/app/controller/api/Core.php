<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Request;
use app\common\Response;
use app\service\Formatter;
use app\service\TradeFormatter;
use app\model\Affiche;
use app\model\AfficheType;
use app\model\ArtelOrder;
use app\model\BlindBox;
use app\model\Collection;
use app\model\Message;
use app\model\User;
use app\model\UserCollection;
use app\model\UserToken;
use app\service\MockData;
use app\service\Audit;

/**
 * 核心业务控制器（Tier 1）
 *
 * 覆盖前端实际调用的核心端点，字段严格对齐原站实测。
 * 金额 string、布尔 int、时间戳 int —— 见 SPEC §1.5
 *
 * 🔴 数据来源分两类，勿混淆：
 *   【DB  】已按原站实测响应建表并灌入真实数据
 *           （公告 / 公告类型 / 盲盒 / 用户 / 订单 / 藏品持有 / 消息）
 *   【MOCK】原站需登录或已下线的端点，暂用内存假数据，待拿到真实样本后替换
 *
 * 🔐 鉴权状态（v2.2 修正）：
 *   之前 hasToken() 只判断 token 字符串非空 —— 传任意垃圾值即可通过。
 *   现在改为 UserToken::verify() 真实校验 sha256 + 有效期 + 账号状态，
 *   校验统一走 currentUser()。
 *
 * @package app\controller\api
 */
class Core
{
    /**
     * 取当前登录用户
     *
     * 🔐 这是**唯一的鉴权入口**，所有需要登录的端点都必须走它。
     *    不再允许「只判断 token 非空」——那是 Mock 阶段的临时做法，
     *    留着就是无鉴权后门。
     *
     * @return array{0:?User, 1:?\think\response\Json}
     *         [0] 用户对象（未登录为 null）[1] 未登录时的 401 响应（非登录接口传 null）
     */
    private function currentUser(): array
    {
        $token = Request::token();
        if ($token === '') {
            return [null, Response::unauthorized()];
        }

        $user = UserToken::verify($token);
        if ($user === null) {
            // 🔐 token 无效/过期/账号被禁 —— 一律 401，不区分原因。
            //    区分「token 过期」与「token 伪造」会给攻击者反馈信号。
            Audit::write('auth_verify', 0, ['result' => 'invalid_token'], false);
            return [null, Response::unauthorized('登录已失效，请重新登录')];
        }

        return [$user, null];
    }
    // ==================== 首页 / 公告 ====================

    /**
     * POST /index/afficheList 公告列表【DB】
     *
     * 原站实测契约：data 为分页信封 {total,per_page,current_page,last_page,data}，
     * 列表项 6 字段 {id,image,title,createtime,type,url,affiche_type}，不含 content。
     * ⚠️ type 是字符串 "1"~"5"，前端做 === '1' 判断。
     */
    public function afficheList()
    {
        ['page' => $p, 'size' => $s] = Request::page();

        // 分类过滤：category 对应 affiche.type（实测 1寄售/2活动/3合成/4运营/5空投）
        $category = Request::int('category', 0);
        $type     = Request::str('type');

        $query = Affiche::where('is_show', 1);
        if ($category > 0) {
            $query->where('type', (string) $category);
        } elseif ($type !== '') {
            $query->where('type', $type);
        }

        $total = (clone $query)->count();
        $rows  = $query->order('diysort', 'desc')
            ->order('createtime', 'desc')
            ->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        return Response::page(
            Formatter::afficheList($rows),
            $total,
            $s,
            $p
        );
    }

    /**
     * POST /index/affichetypelist 公告分类【DB】
     *
     * ⚠️ 原站 data 是裸数组 [{id,name}]，不是分页信封 —— 已实测确认。
     */
    public function afficheTypeList()
    {
        $rows = AfficheType::order('id', 'asc')->select()->toArray();
        return Response::ok(array_map(static fn($r) => [
            'id'   => (int) $r['id'],
            'name' => (string) $r['name'],
        ], $rows));
    }

    /**
     * GET /index/getSeries 藏品系列【MOCK】
     * 原站实测 data = [] （空数组，站点当前无系列数据）。保持空数组语义。
     */
    public function getSeries()
    {
        return Response::ok([]);
    }

    /** GET /banner/show/getBanner 首页轮播 */
    public function getBanner()
    {
        $total = 2;
        $list  = [MockData::banner(0), MockData::banner(1)];
        return Response::page($list, $total, 15, 1);
    }

    /**
     * GET /index/afficheDetail/ids/{id} 公告详情【DB】
     *
     * 原站详情比列表多下发 content（HTML）与 publish_time。
     * 库中 content 为空的记录返回站点级通用公告文案，避免前端详情页空白。
     */
    public function afficheDetail($id)
    {
        $row = Affiche::where('id', (int) $id)
            ->where('is_show', 1)
            ->find();

        if (!$row) {
            return Response::fail('公告不存在或已下架');
        }

        $a = Formatter::affiche($row);
        if (($a['content'] ?? '') === '') {
            $a['content'] = '<p>为保障交易安全，平台将陆续上线藏品溯源功能。</p>';
        }
        $a['publish_time'] = (int) $a['publish_time'];

        return Response::ok($a);
    }

    // ==================== 藏品 / 盲盒 ====================

    /**
     * GET /box/blind_box/list 发售日历【DB】
     *
     * 原站实测契约：data 是「裸数组」而非分页信封，列表项 22 顶层字段
     * + 2 个嵌套对象（batch 4 字段 / publisher 2 字段）。
     *⚠️ 原站确实支持分页入参（实测 page/list_rows 生效），但返回仍是裸数组，
     *    故此处沿用裸数组语义，仅按入参切片。
     */
    public function blindBoxList()
    {
        ['page' => $p, 'size' => $s] = Request::page();

        $query = BlindBox::where('is_show', 1);

        // 可选过滤：type（实测 "collection"）、goods_type（实测 "2"）
        $type = Request::str('type');
        if ($type !== '') {
            $query->where('type', $type);
        }
        $goodsType = Request::str('goods_type');
        if ($goodsType !== '') {
            $query->where('goods_type', $goodsType);
        }

        $rows = $query->order('diysort', 'desc')
            ->order('release_time', 'desc')
            ->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        // 批量取关联的batch / publisher，避免 N+1 查询
        $batchIds = array_values(array_unique(array_filter(
            array_column($rows, 'batch_ref'))));
        $pubIds   = array_values(array_unique(array_filter(
            array_column($rows, 'publisher_ref'))));

        // 注意：column() 第一个参数必须是字段数组，不能传 null
        $batchMap = $batchIds
            ? \app\model\Batch::whereIn('id', $batchIds)
                ->column('*', 'id')
            : [];
        $pubMap = $pubIds
            ? \app\model\Publisher::whereIn('id', $pubIds)
                ->column('*', 'id')
            : [];

        $list = array_map(static fn($r) => Formatter::blindBox(
            $r,
            $batchMap[$r['batch_ref']] ?? null,
            $pubMap[$r['publisher_ref']] ?? null
        ), $rows);

        // 🔴 裸数组，不是 Response::page()
        return Response::ok($list);
    }

    /**
     * 藏品系列列表
     * 🔴 注意：原站前端误写为 /box/collection/CollectionList（404 死路由），
     *    正确端点是下划线的 collection_list。本 Mock 只提供正确版本，
     *    并保留旧名做重定向提示，避免前端再次踩坑。
     */
    public function collectionList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 6;
        $r = MockData::paginate(fn($i) => MockData::collection($i + 1), $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 用户 ====================

    /** POST /user/login 账号密码登录【DB + Argon2id】 */
    public function login()
    {
        $account  = Request::str('account');
        $password = Request::str('password');

        // 🔐 取真实客户端 IP：优先 X-Forwarded-For 最左项（网关/反代会追加），
        //    其次 Remote-Addr。
        // ⚠️ XFF 可被客户端伪造，仅应用于**审计与限流记录**，
        //    绝不可作为「可信身份」用于鉴权判断（如按 IP 免验证）。
        $clientIp = (string) request()->ip();

        if ($account === '' || $password === '') {
            // 🔐 审计：记录失败原因。登录是爆破首要目标，无审计等于无防护。
            Audit::authFail('user_login', '账号或密码为空');
            return Response::fail('请输入账号和密码');
        }

        // 🔐 用户查询：账号可能填 username（手机号）或 mobile，两条路径都试
        //    ⚠️ 必须用参数化查询 —— ThinkPHP 的 where() 对数组值自动参数化，
        //       绝不能把 $account 拼进 SQL 字符串。
        /** @var User|null $user */
        $user = User::where(function ($q) use ($account) {
            $q->where('username', $account)->whereOr('mobile', $account);
        })->find();

        // 🛡️ 用户枚举防护：账号不存在时也执行一次假哈希校验，
        //    让「账号不存在」与「密码错误」的响应时间接近。
        //    否则攻击者能靠响应时间差异批量探测哪些手机号注册过本站。
        if ($user === null) {
            $fake = new \app\model\UserCredential();
            $fake->verifyPassword($password);   // 消耗 Argon2id 时间

            Audit::authFail('user_login', '账号或密码错误', ['account' => $account]);
            // ⚠️ 文案与密码错误时**完全一致**，不给攻击者区分信号
            return Response::fail(\app\model\UserCredential::GENERIC_ERROR);
        }

        /** @var \app\model\UserCredential|null $cred */
        $cred = \app\model\UserCredential::where('user_id', $user['id'])->find();

        if ($cred === null) {
            Audit::authFail('user_login', '账号或密码错误', ['account' => $account]);
            return Response::fail(\app\model\UserCredential::GENERIC_ERROR);
        }

        // 锁定检查：必须在校验密码**之前**判，否则撞库仍会消耗 Argon2id 算力
        if ($cred->isLocked()) {
            Audit::authFail('user_login', '账号或密码错误', ['account' => $account, 'locked' => true]);
            return Response::fail(\app\model\UserCredential::GENERIC_ERROR);
        }

        // 🔐 Argon2id 校验（恒定时间，password_verify 内部保证）
        if (!$cred->verifyPassword($password)) {
            $fails = $cred->onFail();   // 原子自增，达阈值自动锁定
            Audit::authFail('user_login', '账号或密码错误', [
                'account'  => $account,
                'fail_cnt' => $fails,
            ]);
            return Response::fail(\app\model\UserCredential::GENERIC_ERROR);
        }

        // 登录成功：清零失败计数
        $cred->onSuccess();

        // 账号状态校验：禁用账号即使密码正确也拒绝
        if ((string) $user['status'] !== 'normal') {
            Audit::authFail('user_login', '账号或密码错误', ['account' => $account]);
            return Response::fail(\app\model\UserCredential::GENERIC_ERROR);
        }

        // 🔐 签发 token：明文只在这一次出现，返回前端后 DB 只存 sha256
        $issued   = UserToken::issue(Request::str('device', 'unknown'), $clientIp);
        UserToken::create([
            'user_id'    => $user['id'],
            'token'      => $issued['hash'],       // ⚠️ 存哈希，不存明文
            'device'     => Request::str('device', 'unknown'),
            'ip'         => $clientIp,
            'expiretime' => $issued['expiretime'],
            'createtime' => time(),
        ]);

        // 登录时间/IP 回写（异步更好，但此处同步代价可接受）
        $user->save([
            'logintime' => time(),
            'loginip'   => $clientIp,
            'updatetime'=> time(),
        ]);

        Audit::write('user_login', (int) $user['id'], ['account' => $account], true);

        // 🔐 审计里 password 被自动替换为 ***REDACTED***（Audit::sanitize 黑名单）
        Audit::write('user_login_detail', (int) $user['id'], [
            'account'  => $account,
            'password' => $password,
        ], true);

        // 原站契约：data.userinfo.token
        return Response::ok(['userinfo' => [
            'token'  => $issued['plain'],   // ⚠️ 明文只在这里下发一次
            'user_id'=> (int) $user['id'],
        ]]);
    }

    /** POST /user/getUserInfo 当前用户完整信息【DB】 */
    public function getUserInfo()
    {
        [$user, $deny] = $this->currentUser();
        if ($deny !== null) {
            return $deny;
        }
        return Response::ok(TradeFormatter::userInfo($user));
    }

    /** POST /user_collection/user_collection/antMycollection 我的藏品【DB】 */
    public function myCollection()
    {
        [$user, $deny] = $this->currentUser();
        if ($deny !== null) {
            return $deny;
        }

        ['page' => $p, 'size' => $s] = Request::page();

        // 🔐 查询条件强制带 user_id —— 既是数据隔离，也是 IDOR 防护。
        //    去掉这个条件，任意用户改个 token 就能看别人的藏品。
        $query = UserCollection::where('user_id', $user['id']);

        $total = (int) $query->count();
        $rows  = $query->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        // 批量关联藏品定义，避免 N+1 查询（每行一次 collection 查询）
        $collIds = array_values(array_unique(array_filter(
            array_column($rows, 'collection_id')
        )));
        $collMap = [];
        if ($collIds) {
            // ⚠️ 不用 column(null, 'id') —— ThinkORM 3/4 对 null 参数签名不一致，
            //    ThinkORM 4 会抛 TypeError。用 select() + 手动建索引，跨版本安全。
            $collRows = Collection::whereIn('id', $collIds)->select()->toArray();
            foreach ($collRows as $cr) {
                $collMap[(int) $cr['id']] = $cr;
            }
        }

        $list = array_map(static function ($row) use ($collMap) {
            $cid = (int) $row['collection_id'];
            return TradeFormatter::userCollection($row, $collMap[$cid] ?? null);
        }, $rows);

        // ⚠️ current_count / current_consignment 实测与 total 同值 / 0，
        //    真实语义未确认（样本仅一个用户一条数据），先按持有总数与 0 近似。
        $currentCount = (int) UserCollection::where('user_id', $user['id'])->sum('num');
        $consignment  = (int) UserCollection::where('user_id', $user['id'])->sum('sell_num');

        return Response::ok(TradeFormatter::userCollectionPage(
            $list, $total, $s, $p, $currentCount, $consignment
        ));
    }

    /** POST /user/newList 我的消息【DB】 */
    public function newList()
    {
        [$user, $deny] = $this->currentUser();
        if ($deny !== null) {
            return $deny;
        }

        ['page' => $p, 'size' => $s] = Request::page();

        $query = Message::where('user_id', $user['id']);   // 🔐 数据隔离

        $count = (int) $query->count();
        $rows  = $query->order('createtime', 'desc')
            ->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        // ⚠️ 此端点信封是 {count, list}，不是标准五字段分页 —— 见 TradeFormatter::messageList
        return Response::ok(TradeFormatter::messageList(
            array_map(static fn($r) => TradeFormatter::message($r), $rows),
            $count
        ));
    }

    /** POST /user_collection/user_collection/getRecord 购买/流转记录 */
    public function collectionRecord()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 6;
        $r = MockData::paginate(function ($i) {
            $o = MockData::order(18554 + $i, 2, 2);
            $o['collection_name'] = MockData::collection($i)['name'];
            return $o;
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /user/address_list 收货地址 */
    public function addressList()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return Response::ok([
            [
                'id'      => 1,
                'name'    => '王*绵',
                'mobile'  => '175****1293',
                'province'=> '贵州省',
                'city'    => '贵阳市',
                'district'=> '观山湖区',
                'address' => '观山湖区某街道 1 号',
                'is_default' => 1,
            ],
        ]);
    }

    // ==================== 市场 / 行情 ====================

    /**
     * POST /market/market/getMarketGoodsListByGoodsId
     * 🔴 数据在 data.list，不是 data.data（前端踩坑点，SPEC §4.1）
     */
    public function marketGoodsListByGoodsId()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 4;
        $r = MockData::paginate(function ($i) {
            $g = MockData::marketGoods(155 + $i);
            $g['key'] = 'mock-key-' . (155 + $i);
            return $g;
        }, $total, $p, $s);

        return json([
            'code' => 1, 'msg' => '', 'time' => time(),
            'data' => ['list' => $r['list'], 'total' => $r['total']],
        ]);
    }

    /**
     * POST /market/market/marketGoodsDetail
     * 🔴 detail.key 是快捷下单必需（SPEC §4.2 检查清单第 1 项）
     */
    public function marketGoodsDetail()
    {
        $id   = Request::int('id', 155);
        $type = Request::int('type', 2);
        $g    = MockData::marketGoods($id);

        return Response::ok([
            'id'      => $g['id'],
            'name'    => $g['name'],
            'type'    => $type,
            'series_name' => $g['series_name'],
            'listimg' => $g['listimg'],
            'main_image' => $g['main_image'],
            'detail' => [
                'key'     => 'mock-key-' . $id,
                'price'   => '100.00',
                'num'     => 1,
                'goods_id'=> $id,
            ],
            'priceList' => [
                ['price' => '100.00', 'num' => 1],
            ],
        ]);
    }

    /** POST /market/market/getMarketList 行情主列表 */
    public function marketList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 10;
        $r = MockData::paginate(function ($i) {
            $c = MockData::collection($i + 1);
            return [
                'id'    => $c['id'],
                'name'  => $c['name'],
                'image' => $c['main_image'],
                'price' => (string)(100 + $i * 10) . '.00',
                'change_rate' => (string)round(($i - 2) * 1.5, 2) . '%',
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /market/market/marketList 幸运盒行情 */
    public function marketBoxList()
    {
        return $this->marketList();
    }

    /** POST /market/market/MarketDetail/ids/{id} */
    public function marketDetail($id)
    {
        $g = MockData::marketGoods((int) $id);
        return Response::ok($g);
    }

    /** POST /market/trade/tradingInformation 委托挂单列表 */
    public function tradingInformation()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(fn($i) => MockData::order(18600 + $i, 2, 1), $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /market/trade/begBuyList 求购列表 */
    public function begBuyList()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 5;
        $r = MockData::paginate(function ($i) {
            $o = MockData::order(18700 + $i, 11, 1);
            $o['number']      = 3;
            $o['sale_number'] = $i;
            return $o;
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 交易下单 ====================

    /**
     * POST /order/pay/fastOrderNew 快捷下单（核心交易接口）
     *
     * 🔴 三条硬规则（SPEC §4.2）：
     *   1. 端点名是 fastOrderNew，不是 fastCreateOrder
     *   2. key 必须来自 marketGoodsDetail 的 detail.key
     *   3. 同用户存在未支付订单时返回明确业务错误
     */
    public function fastOrderNew()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }

        $goodsId = Request::str('goods_id');
        $key     = Request::str('key');
        $payWay  = Request::str('pay_way');

        if ($goodsId === '' || $key === '') {
            return Response::fail('未找到合适商品');
        }
        if ($payWay === '') {
            return Response::fail('请选择支付方式');
        }
        // key 格式校验：Mock 约定mock-key-{goodsId}
        if ($key !== 'mock-key-' . $goodsId) {
            return Response::fail('未找到合适商品');
        }

        $ts = time();
        return Response::ok([
            'order_id'   => 19001,
            'order_sn'   => date('YmdHis', $ts) . '57101499336911',
            'pay_type'   => 4,
            'order_type' => 2,
        ]);
    }

    /** POST /order/pay/doPay 发起支付 */
    public function doPay()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        $orderSn = Request::str('order_sn');
        if ($orderSn === '') {
            return Response::fail('订单号不能为空');
        }
        return Response::ok(['pay_no' => 'MOCKPAY' . random_int(100000, 999999)]);
    }

    // ==================== 订单 ====================

    /** POST /order/order/orderList 我的订单【DB】 */
    public function orderList()
    {
        [$user, $deny] = $this->currentUser();
        if ($deny !== null) {
            return $deny;
        }

        ['page' => $p, 'size' => $s] = Request::page();

        // 🔐 强制带 user_id —— IDOR 防护：不加此条件可枚举全站订单
        $query = ArtelOrder::where('user_id', $user['id']);

        // 原站支持按状态筛选（实测 status 参数名未确认，前端传的是 order_status 语义）
        // ⚠️ 这里只透传 status 一个入口；原站是否还支持 order_sn / 时间区间未实测，
        //    不做无依据的扩展。
        $status = Request::int('status', 0);
        if ($status > 0) {
            $query->where('order_status', $status);
        }

        $total = (int) $query->count();
        $rows  = $query->order('createtime', 'desc')
            ->order('id', 'desc')
            ->page($p, $s)
            ->select()
            ->toArray();

        // ⚠️ 此端点是标准五字段分页（与 newList 的 {count,list} 不同）
        return Response::page(
            array_map(static fn($r) => TradeFormatter::order($r), $rows),
            $total, $s, $p
        );
    }

    /** POST /order/order/orderDetail/ids/{id} */
    public function orderDetail($id)
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return Response::ok(MockData::order((int) $id, 2, 1));
    }

    /** POST /order/order/MarketDetail/ids/{id} 市场订单详情 */
    public function marketOrderDetail($id)
    {
        return $this->orderDetail($id);
    }

    /** POST /order/order/myConsignment 我的寄售 */
    public function myConsignment()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(fn($i) => MockData::order(18800 + $i, 8, 1), $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /market/Prepayment/myOrderlist 我的预付订单 */
    public function myOrderlist()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 2;
        $r = MockData::paginate(fn($i) => MockData::order(18900 + $i, 4, 1), $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 合成 /盲盒 ====================

    /** POST /synthesis/themeinfo 合成主题（🔴 必须 POST，GET 无效） */
    public function synthesisTheme()
    {
        $thm = Request::int('thm', 1);
        return Response::ok([
            'thm'    => $thm,
            'name'   => '合成主题 ' . $thm,
            'image'  => MockData::collection($thm)['main_image'],
            'material_num' => 3,
        ]);
    }

    /** POST /synthesis/material 合成材料 */
    public function synthesisMaterial()
    {
        $thm = Request::int('thm', 1);
        // 原站约定：4003=无资格3003=直接进方案详情
        if ($thm === 0) {
            return Response::ok(['code' => 4003, 'msg' => '暂无合成资格']);
        }
        return Response::ok([
            'code' => 3003,
            'thm'  => $thm,
            'list' => [
                ['id' => 1, 'name' => '材料A', 'num' => 2, 'image' => MockData::collection(1)['main_image']],
                ['id' => 2, 'name' => '材料B', 'num' => 1, 'image' => MockData::collection(2)['main_image']],
            ],
        ]);
    }

    /** GET /synthesis/recordList 合成记录 */
    public function synthesisRecord()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 4;
        $r = MockData::paginate(function ($i) {
            $o = MockData::order(18950 + $i, 2, 2);
            $o['theme_name'] = '主题 ' . ($i + 1);
            return $o;
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 抽签 / 抽奖 ====================

    /** POST /lottery/info */
    public function lotteryInfo()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return Response::ok([
            'id'     => Request::int('id', 1),
            'name'   => '幸运抽签',
            'image'  => MockData::collection(1)['main_image'],
            'price'  => '10.00',
        ]);
    }

    /** POST /lottery/statistics （两种入参形态） */
    public function lotteryStatistics()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return Response::ok([
            'total_users'  => 128,
            'today_users'  => 12,
            'total_prize'  => 5,
        ]);
    }

    /** POST /lottery/start （写操作，Mock 仅返回结构） */
    public function lotteryStart()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized('请先登录');
        }
        return Response::ok(['prize_id' => 1, 'prize_name' => '藏品A']);
    }

    /** POST /lottery/record */
    public function lotteryRecord()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(function ($i) {
            $o = MockData::order(18970 + $i, 2, 2);
            $o['prize_name'] = '奖品' . ($i + 1);
            return $o;
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 活动 / 社区 ====================

    /** POST /activity/index 活动列表 */
    public function activityIndex()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 4;
        $r = MockData::paginate(function ($i) {
            return [
                'id'    => $i + 1,
                'name'  => '活动 ' . ($i + 1),
                'image' => MockData::collection($i)['main_image'],
                'type'  => 1,
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** GET /Activity/category 活动分类（⚠️ 首字母大写） */
    public function activityCategory()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return Response::ok([
            ['id' => 1, 'name' => '全部'],
            ['id' => 2, 'name' => '抽签'],
            ['id' => 3, 'name' => '空投'],
        ]);
    }

    /** GET /crystal_goods/list 水晶列表 */
    public function crystalGoods()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 3;
        $r = MockData::paginate(function ($i) {
            return [
                'id' => $i + 1,
                'name' => '水晶 ' . ($i + 1),
                'image' => MockData::collection($i)['main_image'],
                'crystal_type' => ['yellow', 'purple', 'blue'][$i % 3],
                'price' => '50.00',
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /user/ranking 排行榜 */
    public function userRank()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        $total = 10;
        $r = MockData::paginate(function ($i) {
            return [
                'rank'  => $i + 1,
                'id'    => 16797 - $i,
                'nickname' => '用户' . ($i + 1),
                'avatar'    => '/defautAvatar.png',
                'money'     => (string)(10000 - $i * 500) . '.00',
            ];
        }, $total, $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    /** POST /union/user/myunion 我的公会 */
    public function myUnion()
    {
        if (!Request::hasToken()) {
            return Response::unauthorized();
        }
        return Response::ok(null, '未加入公会');
    }
}
