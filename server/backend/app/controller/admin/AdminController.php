<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\service\AdminAuthException;
use app\service\AdminAuthService;
use app\service\Audit;
use app\service\LotteryException;
use app\service\LotteryService;
use app\service\SynthesisException;
use app\service\SynthesisService;
use app\common\Request;
use app\common\Response;
use think\facade\Db;

/**
 * 管理端控制器（P0 最小集）
 *
 * 范围：登录/登出/会话 + banner/公告/藏品 上下架运营接口。
 * RBAC、操作员管理、审批流 —— P2，刻意不做。
 *
 * 🔐 鉴权模型：
 *   - 登录换 token（明文只下发一次，12 小时有效）
 *   - 后续请求带 header X-Admin-Token: <token>（兼容 query: admin_token）
 *   - 所有 mutation 写 audit_log（event='admin_*'），运营动作可审计
 *
 * ⚠️ 改动后必须跑：php tools/contract/admin_test.php
 *    并执行 bash tools/contract/admin_e2e.sh（起服务后 curl 全链路）
 *
 * @package app\controller\admin
 */
final class AdminController
{
    /** token 提取：header X-Admin-Token 优先，兼容 query admin_token */
    private static function bearer(): string
    {
        $header = request()->header('x-admin-token', '');
        if (!is_string($header) || $header === '') {
            return Request::str('admin_token');
        }
        return trim($header);
    }

    /** @return array{0:array|null, 1:\think\response\Json|null} [admin, errorResponse] */
    private static function requireAdmin(): array
    {
        $admin = AdminAuthService::verify(self::bearer());
        if ($admin === null) {
            return [null, Response::unauthorized('登录已失效，请重新登录')];
        }
        return [$admin, null];
    }

    private static function ip(): string
    {
        $ip = request()->ip();
        return is_string($ip) ? $ip : '0.0.0.0';
    }

    // ==================== 会话 ====================

    public function login()
    {
        try {
            $session = AdminAuthService::login(
                Request::str('username'),
                Request::str('password'),
                self::ip()
            );
        } catch (AdminAuthException $e) {
            return Response::fail($e->getMessage());
        }
        return Response::ok([
            'token'      => $session['token'],
            'expiretime' => $session['expiretime'],
            'admin'      => $session['admin'],
        ]);
    }

    public function logout()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err; // 未登录的登出按未认证处理，不泄露会话状态
        }
        AdminAuthService::logout(self::bearer());
        Audit::write('admin_logout', (int) $admin['id'], [], true);
        return Response::ok(null, '已退出');
    }

    public function profile()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        return Response::ok(['admin' => $admin]);
    }

    // ==================== banner 运营 ====================

    public function bannerList()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $page     = max(1, Request::int('page', 1));
        $perPage  = min(100, max(1, Request::int('per_page', 10)));
        $q        = Db::name('banner')->order('sort', 'desc')->order('id', 'desc');
        $total    = (clone $q)->count();
        $list     = $q->page($page, $perPage)->select()->toArray();
        return Response::page($list, $total, $perPage, $page, 'banner列表');
    }

    /** 新建/编辑：带 id 更新，不带新建 */
    public function bannerSave()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $id    = Request::int('id');
        $title = Request::str('title');
        $image = Request::str('image');
        if ($title === '' || $image === '') {
            return Response::fail('标题与图片不能为空');
        }
        $now = time();
        $data = [
            'title'      => mb_substr($title, 0, 128),
            'image'      => $image,
            'url'        => Request::str('url'),
            'url_type'   => Request::int('url_type', 1) === 2 ? 2 : 1,
            'sort'       => Request::int('sort', 0),
            'updatetime' => $now,
        ];
        if ($id > 0) {
            $exists = Db::name('banner')->where('id', $id)->find();
            if ($exists === null) {
                return Response::fail('banner不存在');
            }
            Db::name('banner')->where('id', $id)->update($data);
            Audit::write('admin_banner_update', (int) $admin['id'], ['id' => $id], true);
        } else {
            $data['createtime'] = $now;
            $data['is_show']    = 1;
            $id = (int) Db::name('banner')->insertGetId($data);
            Audit::write('admin_banner_create', (int) $admin['id'], ['id' => $id], true);
        }
        return Response::ok(['id' => $id]);
    }

    /** 上下架：is_show 0/1 */
    public function bannerToggle()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        return self::toggle('banner', Request::int('id'), Request::int('is_show'), 'admin_banner_toggle', (int) $admin['id']);
    }

    // ==================== 公告运营 ====================

    public function afficheList()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $page    = max(1, Request::int('page', 1));
        $perPage = min(100, max(1, Request::int('per_page', 10)));
        $q       = Db::name('affiche')->order('diysort', 'desc')->order('createtime', 'desc');
        $total   = (clone $q)->count();
        $list    = $q->page($page, $perPage)->select()->toArray();
        return Response::page($list, $total, $perPage, $page, '公告列表');
    }

    public function afficheSave()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $id    = Request::int('id');
        $title = Request::str('title');
        $type  = Request::str('type');
        if ($title === '' || $type === '') {
            return Response::fail('标题与公告类型不能为空');
        }
        $typeRow = Db::name('affiche_type')->where('id', (int) $type)->find();
        if ($typeRow === null) {
            return Response::fail('公告类型不存在');
        }
        $now  = time();
        $data = [
            'title'        => mb_substr($title, 0, 160),
            'type'         => $type,
            'affiche_type' => (string) $typeRow['name'],
            'image'        => Request::str('image'),
            'url'          => Request::str('url'),
            'content'      => Request::str('content'),
            'diysort'      => Request::int('diysort', 0),
            'publish_time' => $now,
        ];
        if ($id > 0) {
            $exists = Db::name('affiche')->where('id', $id)->find();
            if ($exists === null) {
                return Response::fail('公告不存在');
            }
            Db::name('affiche')->where('id', $id)->update($data);
            Audit::write('admin_affiche_update', (int) $admin['id'], ['id' => $id], true);
        } else {
            $data['createtime'] = $now;
            $data['is_show']    = 1;
            $id = (int) Db::name('affiche')->insertGetId($data);
            Audit::write('admin_affiche_create', (int) $admin['id'], ['id' => $id], true);
        }
        return Response::ok(['id' => $id]);
    }

    public function afficheToggle()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        return self::toggle('affiche', Request::int('id'), Request::int('is_show'), 'admin_affiche_toggle', (int) $admin['id']);
    }

    // ==================== 藏品上下架 ====================

    public function collectionList()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $page    = max(1, Request::int('page', 1));
        $perPage = min(100, max(1, Request::int('per_page', 10)));
        $q       = Db::name('collection')->order('diysort', 'desc')->order('id', 'desc');
        $total   = (clone $q)->count();
        $list    = $q->page($page, $perPage)->select()->toArray();
        return Response::page($list, $total, $perPage, $page, '藏品列表');
    }

    /** 上下架即 C 端 collection_list 的 is_show 过滤位 */
    public function collectionToggle()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        return self::toggle('collection', Request::int('id'), Request::int('is_show'), 'admin_collection_toggle', (int) $admin['id']);
    }

    // ==================== 抽奖运营 ====================

    public function lotteryList()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $page    = max(1, Request::int('page', 1));
        $perPage = min(100, max(1, Request::int('per_page', 10)));
        $q       = Db::name('lottery')->order('id', 'desc');
        $total   = (clone $q)->count();
        $list    = $q->page($page, $perPage)->select()->toArray();
        return Response::page($list, $total, $perPage, $page, '抽奖活动列表');
    }

    /** 新建/编辑抽奖活动（奖池 collection_json 传 JSON 字符串，LotteryService 校验结构） */
    public function lotterySave()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $payload = [
            'name'            => Request::str('name'),
            'content'         => Request::str('content'),
            'image1'          => Request::str('image1'),
            'image2'          => Request::str('image2'),
            'crystal'         => Request::str('crystal', '10'),
            'rule'            => Request::str('rule', '10'),
            'lottery_max'     => Request::int('lottery_max', 0),
            'collection_json' => Request::str('collection_json', '[]'),
            'start_time'      => Request::int('start_time'),
            'end_time'        => Request::int('end_time'),
            'switch'          => Request::int('switch', 1),
        ];
        $id = Request::int('id');
        try {
            $id = LotteryService::adminSave($payload, $id > 0 ? $id : null);
        } catch (LotteryException $e) {
            return Response::fail($e->getMessage());
        }
        Audit::write($id > 0 ? 'admin_lottery_update' : 'admin_lottery_create', (int) $admin['id'], ['id' => $id], true);
        return Response::ok(['id' => $id]);
    }

    /** 活动开关（lottery.switch 位，与 banner 的 is_show 语义不同故单独落点） */
    public function lotteryToggle()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $id     = Request::int('id');
        $switch = Request::int('switch') === 1 ? 1 : 0;
        if ($id <= 0) {
            return Response::fail('id不能为空');
        }
        $row = Db::name('lottery')->where('id', $id)->find();
        if ($row === null) {
            return Response::fail('活动不存在');
        }
        Db::name('lottery')->where('id', $id)->update(['switch' => $switch, 'updatetime' => time()]);
        Audit::write('admin_lottery_toggle', (int) $admin['id'], ['id' => $id, 'switch' => $switch], true);
        return Response::ok(['id' => $id, 'switch' => $switch]);
    }

    // ==================== 合成运营 ====================

    public function synthesisThemeList()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $list = Db::name('synthesis_theme')->order('sort', 'desc')->order('id', 'desc')->select()->toArray();
        return Response::ok($list);
    }

    /** 新建/编辑合成主题 */
    public function synthesisThemeSave()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $payload = [
            'name'      => Request::str('name'),
            'image'     => Request::str('image'),
            'types'     => Request::int('types', 1),
            'rule_text' => Request::str('rule_text'),
            'sort'      => Request::int('sort', 0),
            'status'    => Request::int('status', 1),
        ];
        $id = Request::int('id');
        try {
            $id = SynthesisService::adminSaveTheme($payload, $id > 0 ? $id : null);
        } catch (SynthesisException $e) {
            return Response::fail($e->getMessage());
        }
        Audit::write($id > 0 ? 'admin_synth_theme_update' : 'admin_synth_theme_create', (int) $admin['id'], ['id' => $id], true);
        return Response::ok(['id' => $id]);
    }

    /** 新建/编辑合成方案（need_items 传 JSON 字符串） */
    public function synthesisPlanSave()
    {
        [$admin, $err] = self::requireAdmin();
        if ($err !== null) {
            return $err;
        }
        $payload = [
            'theme_id'        => Request::int('theme_id'),
            'title'           => Request::str('title'),
            'image'           => Request::str('image'),
            'description'     => Request::str('description'),
            'start_time'      => Request::int('start_time'),
            'end_time'        => Request::int('end_time'),
            'real_start_time' => Request::int('real_start_time'),
            'classify'        => Request::str('classify', 'regular'),
            'type'            => Request::str('type', '10'),
            'stock'           => Request::int('stock', 0),
            'need_items'      => Request::str('need_items', '[]'),
            'status'          => Request::int('status', 1),
        ];
        $id = Request::int('id');
        try {
            $id = SynthesisService::adminSavePlan($payload, $id > 0 ? $id : null);
        } catch (SynthesisException $e) {
            return Response::fail($e->getMessage());
        }
        Audit::write($id > 0 ? 'admin_synth_plan_update' : 'admin_synth_plan_create', (int) $admin['id'], ['id' => $id], true);
        return Response::ok(['id' => $id]);
    }

    /** 统一的上下架落点：只允许 0/1，写审计，返回新状态 */
    private static function toggle(string $table, int $id, int $isShow, string $action, int $adminId)
    {
        if ($id <= 0) {
            return Response::fail('id不能为空');
        }
        $isShow = $isShow === 1 ? 1 : 0;
        $row    = Db::name($table)->where('id', $id)->find();
        if ($row === null) {
            return Response::fail('记录不存在');
        }
        // affiche 表无 updatetime 列（原站遗留结构），只有 banner/collection 带
        $data = ['is_show' => $isShow];
        if ($table !== 'affiche') {
            $data['updatetime'] = time();
        }
        Db::name($table)->where('id', $id)->update($data);
        Audit::write($action, $adminId, ['id' => $id, 'is_show' => $isShow], true);
        return Response::ok(['id' => $id, 'is_show' => $isShow]);
    }
}
