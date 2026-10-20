<?php
declare(strict_types=1);

/**
 * 管理后台 v1（司南重建版）契约测试 —— 第八批：CMS 管理 §8.13（18 接口）+ 系统配置 §8.14（10 接口）
 *
 * 锁定契约：
 *   1. Banner：banner 表（link↔url / sort_order↔sort / status↔is_show）；
 *      show_time_start/end 可选时间窗；CRUD 全链路
 *   2. 公告：cms_announcement（type 1=系统 2=活动 3=维护；status 0=草稿 1=已发布 2=已下线；
 *      publish_time 可选，0=创建即发布）
 *   3. 协议：cms_agreement（每类型一份，uk_type）；GET /cms/agreements?type= → { list }
 *   4. 文物展览：cms_artifact 标准分页 CRUD
 *   5. 装修：site_config(cms.decoration)
 *   6. 系统配置 5 组：system.payment / system.security / system.cors / system.sms_oss /
 *      system.global —— GET 免密码，PUT 必须带管理员密码（高风险）+ 审计落库
 *   7. RBAC：财务（role=3）无 cms:* / system:* → 403
 *
 * 用法：php tools/contract/admin_v1_cms_system_test.php
 * 退出码：0 = 全通过
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\controller\admin\v1\CmsController;
use app\controller\admin\v1\SystemController;
use app\service\admin\AdminException;
use think\facade\Db;

const CS_OK   = '[OK]  ';
const CS_FAIL = '[FAIL]';

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        echo CS_OK . $label . "\n";
        $passed++;
        return;
    }
    echo CS_FAIL . $label . "\n";
    if ($detail !== '') {
        echo '       ' . $detail . "\n";
    }
    $failed++;
}

function guardCode(callable $fn): array
{
    try {
        $fn();
        return [200, ''];
    } catch (AdminException $e) {
        return [$e->httpCode(), $e->getMessage()];
    }
}

/* ==================== fixtures ==================== */

$sfx = (string) random_int(100000, 999999);
$now = time();
$pwd = 'S3cret-Cms-#8';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('cms_super', 1);
$finId   = $mkAdmin('cms_fin', 3);

$app->request->withGet([]);
$app->request->withPost([]);

$login = static function (int $adminId) use ($app, $pwd): string {
    $name = Db::name('admin_user')->where('id', $adminId)->value('username');
    $app->request->withPost(['username' => $name, 'password' => $pwd]);
    $body = (new \app\controller\admin\v1\AuthController())->login()->getData();
    return (string) ($body['data']['token'] ?? '');
};

try {
    $asSuper = $login($superId);
    $asFin   = $login($finId);
    check('管理员登录成功', $asSuper !== '' && $asFin !== '');

    $cms = new CmsController();
    $sys = new SystemController();
    $superHeader = ['authorization' => 'Bearer ' . $asSuper];
    $finHeader   = ['authorization' => 'Bearer ' . $asFin];

    /* ==================== 8.13.1 Banner（134~138） ==================== */

    // RBAC：财务无 cms:*
    $app->request->withHeader($finHeader)->withPost(['image' => 'x.png', 'link' => '/a']);
    [$r0] = guardCode(fn () => $cms->bannerCreate());
    check('财务建 Banner → 403', $r0 === 403, "got $r0");

    $app->request->withHeader($superHeader)
        ->withPost(['image' => 'banner_' . $sfx . '.png', 'link' => '/activity/main', 'sort_order' => '10',
            'status' => '1', 'show_time_start' => (string) ($now - 100), 'show_time_end' => (string) ($now + 86400)]);
    $bc = $cms->bannerCreate()->getData();
    $bannerId = (int) ($bc['data']['id'] ?? 0);
    check('创建 Banner → id', (int) ($bc['code'] ?? 0) === 200 && $bannerId > 0, var_export($bc, true));
    check('Banner 落库映射（link→url/sort_order→sort/status→is_show）',
        (int) Db::name('banner')->where('id', $bannerId)->value('is_show') === 1
        && (string) Db::name('banner')->where('id', $bannerId)->value('url') === '/activity/main'
        && (int) Db::name('banner')->where('id', $bannerId)->value('sort') === 10
        && (int) Db::name('banner')->where('id', $bannerId)->value('show_time_start') === $now - 100);

    $app->request->withGet(['page' => '1', 'page_size' => '10', 'status' => '1'])->withPost([]);
    $bl = $cms->bannerList()->getData();
    $hit = false;
    foreach (($bl['data']['list'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $bannerId) {
            $hit = ((int) ($row['sort_order'] ?? -1) === 10 && ($row['link'] ?? '') === '/activity/main');
        }
    }
    check('Banner 列表 status 筛选 + 输出映射', (int) ($bl['code'] ?? 0) === 200
        && (int) ($bl['data']['total'] ?? 0) >= 1 && $hit, var_export($bl, true));

    $app->request->withGet(['id' => (string) $bannerId])->withPost([]);
    $bd = $cms->bannerDetail()->getData();
    check('Banner 详情 ISO 时间窗', (int) ($bd['code'] ?? 0) === 200
        && ($bd['data']['show_time_start'] ?? '') !== '' && ($bd['data']['show_time_end'] ?? '') !== '',
        var_export($bd['data'] ?? null, true));

    $app->request->withGet(['id' => (string) $bannerId])->withPost(['status' => '0', 'sort_order' => '5']);
    [$be] = guardCode(fn () => $cms->bannerEdit());
    check('Banner 编辑 → 200', $be === 200, "got $be");
    check('Banner 编辑落库', (int) Db::name('banner')->where('id', $bannerId)->value('is_show') === 0
        && (int) Db::name('banner')->where('id', $bannerId)->value('sort') === 5);

    $app->request->withPost([]);
    [$bdel] = guardCode(fn () => $cms->bannerDelete());
    check('Banner 删除 → 200', $bdel === 200, "got $bdel");
    check('Banner 删除落库', Db::name('banner')->where('id', $bannerId)->find() === null);

    /* ==================== 8.13.2 公告（139~143） ==================== */

    $app->request->withPost(['title' => '契约公告_' . $sfx, 'content' => '正文内容', 'type' => '2',
        'is_top' => '1', 'status' => '1', 'publish_time' => (string) $now]);
    $ac = $cms->announcementCreate()->getData();
    $annId = (int) ($ac['data']['id'] ?? 0);
    check('创建公告 → id', (int) ($ac['code'] ?? 0) === 200 && $annId > 0, var_export($ac, true));

    // 草稿公告（status 筛选依据）
    $app->request->withPost(['title' => '草稿公告_' . $sfx, 'content' => 'x', 'type' => '1', 'is_top' => '0', 'status' => '0']);
    $ac2 = $cms->announcementCreate()->getData();
    $annId2 = (int) ($ac2['data']['id'] ?? 0);

    $app->request->withGet(['page' => '1', 'page_size' => '10', 'status' => '1', 'type' => '2'])->withPost([]);
    $al = $cms->announcementList()->getData();
    $annHit = false;
    foreach (($al['data']['list'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $annId) {
            $annHit = ((int) ($row['is_top'] ?? 0) === 1 && ($row['title'] ?? '') === '契约公告_' . $sfx);
        }
    }
    check('公告列表 type+status 筛选', (int) ($al['code'] ?? 0) === 200
        && (int) ($al['data']['total'] ?? 0) >= 1 && $annHit, var_export($al, true));

    $app->request->withGet(['id' => (string) $annId])->withPost([]);
    $ad = $cms->announcementDetail()->getData();
    check('公告详情 publish_time ISO', (int) ($ad['code'] ?? 0) === 200
        && ($ad['data']['publish_time'] ?? '') !== '' && ($ad['data']['content'] ?? '') === '正文内容',
        var_export($ad['data'] ?? null, true));

    $app->request->withGet(['id' => (string) $annId])->withPost(['title' => '契约公告v2_' . $sfx, 'status' => '2']);
    [$ae] = guardCode(fn () => $cms->announcementEdit());
    check('公告编辑（下线）→ 200', $ae === 200, "got $ae");
    check('公告编辑落库', (string) Db::name('cms_announcement')->where('id', $annId)->value('title') === '契约公告v2_' . $sfx
        && (int) Db::name('cms_announcement')->where('id', $annId)->value('status') === 2);

    $app->request->withPost([]);
    [$adel] = guardCode(fn () => $cms->announcementDelete());
    check('公告删除 → 200', $adel === 200, "got $adel");
    check('公告删除落库', Db::name('cms_announcement')->where('id', $annId)->find() === null);
    Db::name('cms_announcement')->where('id', $annId2)->delete(); // 清理草稿

    /* ==================== 8.13.3 协议（144~145） ==================== */

    $agreeType = 'user_' . $sfx; // uk_type 每类型一份，避免撞历史数据
    Db::name('cms_agreement')->insert([
        'type' => $agreeType, 'title' => '用户协议V1', 'content' => 'V1 条款', 'version' => '1.0.0',
        'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
    $agreeId = (int) Db::name('cms_agreement')->where('type', $agreeType)->value('id');

    $app->request->withGet(['type' => $agreeType])->withPost([]);
    $ag = $cms->agreementList()->getData();
    check('协议列表 type 筛选', (int) ($ag['code'] ?? 0) === 200
        && count($ag['data']['list'] ?? []) === 1
        && ($ag['data']['list'][0]['version'] ?? '') === '1.0.0', var_export($ag, true));

    $app->request->withGet(['id' => (string) $agreeId])->withPost(['title' => '用户协议V2', 'content' => 'V2 条款', 'version' => '1.1.0']);
    [$age] = guardCode(fn () => $cms->agreementEdit());
    check('协议编辑 → 200', $age === 200, "got $age");
    check('协议编辑落库', (string) Db::name('cms_agreement')->where('id', $agreeId)->value('version') === '1.1.0'
        && (string) Db::name('cms_agreement')->where('id', $agreeId)->value('title') === '用户协议V2');
    Db::name('cms_agreement')->where('id', $agreeId)->delete();

    /* ==================== 8.13.4 文物展览（146~149） ==================== */

    $app->request->withPost(['name' => '契约文物_' . $sfx, 'dynasty' => '商代', 'material' => '青铜',
        'image' => 'artifact_' . $sfx . '.png', 'description' => '契约描述']);
    $fc = $cms->artifactCreate()->getData();
    $artId = (int) ($fc['data']['id'] ?? 0);
    check('创建文物 → id', (int) ($fc['code'] ?? 0) === 200 && $artId > 0, var_export($fc, true));

    $app->request->withGet(['page' => '1', 'page_size' => '10'])->withPost([]);
    $fl = $cms->artifactList()->getData();
    $artHit = false;
    foreach (($fl['data']['list'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $artId) {
            $artHit = (($row['dynasty'] ?? '') === '商代' && ($row['material'] ?? '') === '青铜');
        }
    }
    check('文物列表命中', (int) ($fl['code'] ?? 0) === 200 && (int) ($fl['data']['total'] ?? 0) >= 1 && $artHit,
        var_export($fl, true));

    $app->request->withGet(['id' => (string) $artId])->withPost(['dynasty' => '西周', 'status' => '0']);
    [$fe] = guardCode(fn () => $cms->artifactEdit());
    check('文物编辑 → 200', $fe === 200, "got $fe");
    check('文物编辑落库', (string) Db::name('cms_artifact')->where('id', $artId)->value('dynasty') === '西周'
        && (int) Db::name('cms_artifact')->where('id', $artId)->value('status') === 0);

    $app->request->withPost([]);
    [$fdel] = guardCode(fn () => $cms->artifactDelete());
    check('文物删除 → 200', $fdel === 200, "got $fdel");
    check('文物删除落库', Db::name('cms_artifact')->where('id', $artId)->find() === null);

    /* ==================== 8.13.5 装修配置（150~151） ==================== */

    // 装修 KV 为持久态，重跑前重置为种子值保证幂等
    Db::name('site_config')->where('key', 'cms.decoration')->update([
        'value' => '{"theme_color":"#C8102E","button_color":"#D4AF37","background_gradient":"linear-gradient(180deg,#1A0A0D 0%,#2B0F14 100%)","site_name":"司南数藏","site_logo":"","sections":[]}',
        'updatetime' => $now,
    ]);
    $app->request->withGet([])->withPost([]);
    $dc = $cms->decorationView()->getData();
    check('装修配置读种子', (int) ($dc['code'] ?? 0) === 200
        && ($dc['data']['theme_color'] ?? '') === '#C8102E'
        && isset($dc['data']['button_color'], $dc['data']['background_gradient'], $dc['data']['sections']),
        var_export($dc['data'] ?? null, true));

    $app->request->withPost(['theme_color' => '#000000', 'site_name' => '契约站点_' . $sfx,
        'sections' => [['key' => 'banner', 'enabled' => 1]]]);
    [$du] = guardCode(fn () => $cms->decorationEdit());
    check('装修配置更新 → 200', $du === 200, "got $du");
    $dcfg = json_decode((string) Db::name('site_config')->where('key', 'cms.decoration')->value('value'), true);
    check('装修配置落库（未传键保留）', ($dcfg['theme_color'] ?? '') === '#000000'
        && ($dcfg['button_color'] ?? '') === '#D4AF37'
        && ($dcfg['site_name'] ?? '') === '契约站点_' . $sfx);

    /* ==================== 8.14 系统配置（152~161） ==================== */

    // 通用断言器：GET 免密码读种子 → PUT 缺密码 400 → PUT 带密码成功落库
    $cfgFlow = static function (SystemController $sys, array $superHeader, string $pwd,
        string $getFn, string $putFn, array $putPayload, callable $dbValue,
        callable $expectGet, callable $expectDb) use ($app, &$passed, &$failed): void {
        $app->request->withHeader($superHeader)->withGet([])->withPost([]);
        $g = $sys->$getFn()->getData();
        check($getFn . ' 读种子', (int) ($g['code'] ?? 0) === 200 && $expectGet($g['data'] ?? []),
            var_export($g, true));

        $app->request->withPost($putPayload); // 无密码
        [$e0] = guardCode(fn () => $sys->$putFn());
        check($putFn . ' 缺密码 → 400', $e0 === 400, "got $e0");

        $app->request->withPost($putPayload + ['password' => $pwd]);
        [$e1] = guardCode(fn () => $sys->$putFn());
        check($putFn . ' 带密码 → 200', $e1 === 200, "got $e1");
        $raw = json_decode((string) $dbValue(), true);
        check($putFn . ' 落库', $expectDb(is_array($raw) ? $raw : []), json_encode($raw, JSON_UNESCAPED_UNICODE));
    };

    $cfgFlow($sys, $superHeader, $pwd,
        'paymentView', 'paymentUpdate',
        ['alipay' => ['app_id' => '2026000001', 'is_enabled' => '1']],
        fn () => Db::name('site_config')->where('key', 'system.payment')->value('value'),
        fn ($d) => isset($d['alipay'], $d['wechat']),
        fn ($raw) => ($raw['alipay']['app_id'] ?? '') === '2026000001' && (int) ($raw['alipay']['is_enabled'] ?? 0) === 1
            && isset($raw['wechat']['mch_id']));

    $cfgFlow($sys, $superHeader, $pwd,
        'securityView', 'securityUpdate',
        ['tx_password_max_fail' => '8', 'rate_limit_qps' => '60'],
        fn () => Db::name('site_config')->where('key', 'system.security')->value('value'),
        fn ($d) => isset($d['tx_password_max_fail'], $d['tx_password_lock_minutes'], $d['rate_limit_qps'], $d['rate_limit_ip'], $d['rate_limit_user']),
        fn ($raw) => (int) ($raw['tx_password_max_fail'] ?? 0) === 8 && (int) ($raw['rate_limit_qps'] ?? 0) === 60
            && (int) ($raw['tx_password_lock_minutes'] ?? 0) === 15);

    $cfgFlow($sys, $superHeader, $pwd,
        'corsView', 'corsUpdate',
        ['allowed_origins' => ['https://sinan.example.com'], 'api_whitelist' => ['/api/v1/notice']],
        fn () => Db::name('site_config')->where('key', 'system.cors')->value('value'),
        fn ($d) => isset($d['allowed_origins'], $d['api_whitelist']),
        fn ($raw) => ($raw['allowed_origins'][0] ?? '') === 'https://sinan.example.com'
            && ($raw['api_whitelist'][0] ?? '') === '/api/v1/notice');

    $cfgFlow($sys, $superHeader, $pwd,
        'smsOssView', 'smsOssUpdate',
        ['sms' => ['driver' => 'aliyun', 'is_enabled' => '1'], 'oss' => ['driver' => 'aliyun', 'bucket' => 'sinan']],
        fn () => Db::name('site_config')->where('key', 'system.sms_oss')->value('value'),
        fn ($d) => isset($d['sms'], $d['oss']),
        fn ($raw) => (int) ($raw['sms']['is_enabled'] ?? 0) === 1 && ($raw['oss']['bucket'] ?? '') === 'sinan');

    $cfgFlow($sys, $superHeader, $pwd,
        'globalView', 'globalUpdate',
        ['site_name' => '司南数藏X', 'min_recharge' => '10.00', 'order_timeout_minutes' => '30'],
        fn () => Db::name('site_config')->where('key', 'system.global')->value('value'),
        fn ($d) => isset($d['site_name'], $d['contact_phone'], $d['min_recharge'], $d['order_timeout_minutes'], $d['max_upload_size']),
        fn ($raw) => ($raw['site_name'] ?? '') === '司南数藏X' && ($raw['min_recharge'] ?? '') === '10.00'
            && (int) ($raw['order_timeout_minutes'] ?? 0) === 30);

    // 系统配置写必须落审计
    $auditHit = Db::name('audit_log')->where('action', 'system_config_update')
        ->where('createtime', '>=', $now - 60)->count();
    check('系统配置写审计落库', (int) $auditHit >= 1, 'audit_log hits=' . $auditHit);
} catch (\Throwable $e) {
    echo '[EXCEPTION] ' . get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    $failed++;
}

echo "\n===== v1 cms+system: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
