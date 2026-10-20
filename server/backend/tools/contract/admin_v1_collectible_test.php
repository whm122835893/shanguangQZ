<?php
declare(strict_types=1);

/**
 * 藏品管理 v1（§8.5）契约测试 —— 精简冒烟锁定
 *
 * 锁定契约：
 *   1. GET  /collectibles            列表分页信封
 *   2. POST /collectibles            创建 → 草稿，库存恒等式基准（edition=stock_pool）
 *   3. GET  /collectibles/:id        详情含开关字段
 *   4. POST /collectibles/:id/release  发售配置 → 库存池扣减
 *   5. GET  /collectibles/:id/audit  库存审计恒等式 is_consistent=true
 *   6. POST /collectibles/:id/resale-toggle 寄售开关（C 端联动契约锁）
 *   7. 权限：运营（role=2）无 collectible:create → 403
 *
 * 用法：php tools/contract/admin_v1_collectible_test.php
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = new think\App();
$app->initialize();

use app\service\admin\AdminException;
use app\controller\admin\v1\AuthController;
use app\controller\admin\v1\CollectibleController;
use think\facade\Db;

$passed = 0;
$failed = 0;

function check(string $label, bool $cond, string $detail = ''): void
{
    global $passed, $failed;
    if ($cond) {
        echo "[OK]  {$label}\n";
        $passed++;
        return;
    }
    echo "[FAIL] {$label}\n";
    if ($detail !== '') echo "       {$detail}\n";
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

$sfx = (string) random_int(100000, 999999);
$now = time();
$pwd = 'S3cret-Col-#42';

$mkAdmin = static function (string $name, int $role) use ($now, $pwd, $sfx): int {
    return (int) Db::name('admin_user')->insertGetId([
        'username' => $name . '_' . $sfx,
        'password_hash' => password_hash($pwd, PASSWORD_ARGON2ID),
        'role' => $role, 'status' => 1, 'createtime' => $now, 'updatetime' => $now,
    ]);
};
$superId = $mkAdmin('col_super', 1);
$operId  = $mkAdmin('col_oper', 2);

$app->request->withGet([])->withPost([]);

$login = static function (int $adminId) use ($app, $pwd): string {
    $row = Db::name('admin_user')->where('id', $adminId)->value('username');
    $app->request->withPost(['username' => $row, 'password' => $pwd]);
    $body = (new AuthController())->login()->getData();
    return (string) ($body['data']['token'] ?? '');
};

$asSuper = $login($superId);
$asOper  = $login($operId);
check('双管理员登录', $asSuper !== '' && $asOper !== '');

$app->request->withGet([])->withPost([]);
$app->request->withHeader(['authorization' => 'Bearer ' . $asSuper]);

$ctrl = new CollectibleController();

try {
    /* 1. 列表空分页 */
    $app->request->withGet(['page' => 1, 'page_size' => 10])->withPost([]);
    $body = $ctrl->list()->getData();
    check('列表返回分页信封', isset($body['data']['list'], $body['data']['total']));

    /* 2. 创建草稿 */
    $app->request->withGet([])->withPost([
        'name' => '契约藏品' . $sfx, 'category_id' => 1, 'edition' => 100,
        'issuer' => '测试发行方', 'description' => 'v1 契约',
    ]);
    $body = $ctrl->create()->getData();
    $cid = (int) ($body['data']['id'] ?? 0);
    check('创建藏品返回 id', $cid > 0);

    /* 3. 详情 */
    $app->request->withGet(['id' => $cid])->withPost([]);
    $body = $ctrl->detail()->getData();
    $d = $body['data'] ?? [];
    check('详情含开关字段', isset($d['is_resaleable'], $d['is_qualification_enabled'], $d['sale_status']));

    /* 4. 发售配置：release_quantity=60 → 库存池 40 */
    $app->request->withGet([])->withPost([
        'id' => $cid, 'price' => '99.00', 'release_quantity' => 60,
        'onsale_at' => date('Y-m-d H:i:s', $now - 60), 'off_sale_at' => date('Y-m-d H:i:s', $now + 86400),
        'per_user_limit' => 2,
    ]);
    $body = $ctrl->release()->getData();
    check('发售配置成功', ($body['data']['success'] ?? false) === true, json_encode($body, JSON_UNESCAPED_UNICODE));

    /* 5. 库存审计恒等式（池为推导值：发售配置不扣池，售出/空投/销毁/配额才扣） */
    $app->request->withGet(['id' => $cid])->withPost([]);
    $body = $ctrl->audit()->getData();
    $a = $body['data'] ?? [];
    check(
        '审计 is_consistent=true 且发售未扣池（sold=0 → pool=100）',
        (($a['is_consistent'] ?? false) === true) && (int) ($a['stock_pool'] ?? -1) === 100,
        json_encode($a, JSON_UNESCAPED_UNICODE)
    );

    /* 6. 寄售开关（C 端联动） */
    $app->request->withGet([])->withPost([
        'id' => $cid, 'is_resaleable' => 0, 'reason' => '契约测试', 'password' => $pwd,
    ]);
    $body = $ctrl->resaleToggle()->getData();
    check('寄售开关关闭成功', ($body['data']['success'] ?? false) === true);
    $off = (int) Db::name('collection')->where('id', $cid)->value('is_resaleable');
    check('库表 is_resaleable=0', $off === 0);

    /* 7. RBAC：种子矩阵运营含 collectible:create → 放行 200（403 契约由 modules 测试锁定） */
    $app->request->withHeader(['authorization' => 'Bearer ' . $asOper]);
    $app->request->withGet([])->withPost(['name' => '运营藏品' . $sfx, 'category_id' => 1, 'edition' => 10]);
    [$code, $msg] = guardCode(function () use ($ctrl) {
        $r = $ctrl->create()->getData();
        return $r;
    });
    check('运营按种子矩阵创建藏品放行', $code === 200, "code={$code} msg={$msg}");

    /* 清理 */
    Db::name('collection')->where('id', $cid)->delete();
} finally {
    
    Db::name('admin_user')->whereIn('id', [$superId, $operId])->delete();
    Db::name('admin_login_log')->whereIn('admin_id', [$superId, $operId])->delete();
}

echo "===== collectible v1: {$passed} passed, {$failed} failed =====\n";
exit($failed > 0 ? 1 : 0);
