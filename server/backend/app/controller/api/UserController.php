<?php
declare(strict_types=1);

namespace app\controller\api;

use app\service\Audit;
use app\model\Collection;
use app\model\Message;
use app\service\MockData;
use app\common\Msg;
use app\common\Request;
use app\common\Response;
use app\service\LoginFormatter;
use app\service\RealnameException;
use app\service\RealnameService;
use app\service\TradeFormatter;
use app\service\UnionFormatter;
use app\model\User;
use app\model\UserCollection;
use app\model\UserCredential;
use app\model\UserToken;
use app\controller\api\Concerns\HasAuth;

/**
 * 用户域控制器（v2.3 从 Core 拆分）—— 登录/用户信息/藏品持有/消息/地址/排行/公会
 *
 * 🔴【DB】已按实测建表灌数；【MOCK】原站需登录或已下线，暂用内存假数据。
 * 🔐 鉴权走 HasAuth::currentUser()，禁止只判断 token 非空。
 * ⚠️ 改动后必须跑 php tools/contract/run.php，退出码非 0 禁止提交。
 *
 * @package app\controller\api
 */
class UserController
{
    use HasAuth;

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

        // 原站契约：data.userinfo 实测 26 字段（含 token/expiretime/expires_in/is_exist）
        // 🔴 不能只回 {token, user_id} 两个字段 —— 前端拿不到 expiretime
        //    就无法在登录态过期前主动登出，会出现「token 失效但界面还显示已登录」。
        //    实测字段集见 LoginFormatter 白名单（recon/raw/_login.json）。
        return Response::ok(['userinfo' => LoginFormatter::login(
            $user,
            $issued['plain'],   // ⚠️ 明文只在这里下发一次
            $issued['expiretime'],
            UserToken::TTL
        )], Msg::LOGIN);
    }

    /** POST /user/getUserInfo 当前用户完整信息【DB】 */

    public function getUserInfo()
    {
        [$user, $deny] = $this->currentUser();
        if ($deny !== null) {
            return $deny;
        }
        return Response::ok(TradeFormatter::userInfo($user), Msg::USER_INFO);
    }

    /**
     * POST /user/realname/submit 实名认证提交【新增】
     *
     * 原站无此端点（实名在原 App 内完成），本项目新增的补齐端点：
     *   - 三态分离落库：脱敏值（展示契约不变）+ AES-256-GCM 密文 + HMAC 指纹
     *   - 一证一号 / 已实名禁换证 / 年满 18 周岁，红线全部在 RealnameService
     * 响应仅回脱敏值，明文与密文绝不出接口。
     */
    public function realname()
    {
        [$user, $deny] = $this->currentUser();
        if ($deny !== null) {
            return $deny;
        }

        $realName = Request::str('real_name');
        $idCard   = Request::str('id_card');

        try {
            $masked = RealnameService::submit((int) $user['id'], $realName, $idCard);
        } catch (RealnameException $e) {
            Audit::write('realname_submit', (int) $user['id'], ['result' => 'reject', 'reason' => $e->getMessage()], false);
            return Response::fail($e->getMessage());
        }

        Audit::write('realname_submit', (int) $user['id'], ['result' => 'ok'], true);
        return Response::ok($masked, '实名提交成功');
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
        ), Msg::MY_COLLECTION);
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
        ), Msg::MESSAGE_LIST);
    }

    /**
     * POST /union/user/myunion —— 我的公会【DB 驱动】
     *
     * 实测（2026-10-18抓取）：data = {config, my, union, join}
     *   config 6 字段公会规则 / my 8 字段成员关系 / union=null / join=[]
     *
     * 契约要点见 UnionFormatter 的类注释。
     */
    public function myUnion()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok(UnionFormatter::myUnion((int) $user['id']), Msg::UNION_MY);
    }

    /**
     * GET|POST /union/index/logs —— 公会战力流水【MOCK】
     *
     * v2.4 从 TradeController 迁入：union/index/logs 属公会域，
     * 与 myUnion 同域归位（TradeController 同时解除超 300 行）。
     *
     * ⚠️ 分页参数名是 pageSize 不是 limit——原站 API.md §2.3
     *    记录过三种分页写法，本端点用 page + pageSize。
     */
    public function unionLogs()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        $p     = max(1, Request::int('page', 1));
        $size  = max(1, min(100, Request::int('pageSize', 15)));
        $total = 20;
        $list  = [];
        for ($i = ($p - 1) * $size; $i < min($p * $size, $total); $i++) {
            $list[] = [
                'id'         => $i + 1,
                'type'       => $i % 2 === 0 ? 1 : 2,      // 1 获得 2 消耗（int）
                'title'      => ($i % 2 === 0 ? '联合战力+' : '联合战力-') . ($i + 1),
                'num'        => (string) (($i % 3) + 1),  // 原站此字段为 string
                'createtime' => time() - $i * 600,
            ];
        }
        return Response::page($list, $total, $size, $p);
    }
}
