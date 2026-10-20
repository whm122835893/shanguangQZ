<?php
declare(strict_types=1);

use app\controller\admin\v1\AuthController;
use app\controller\admin\v1\BlindboxController;
use app\controller\admin\v1\CmsController;
use app\controller\admin\v1\CollectibleController;
use app\controller\admin\v1\MarketingController;
use app\controller\admin\v1\DashboardController;
use app\controller\admin\v1\MarketController;
use app\controller\admin\v1\OrderController;
use app\controller\admin\v1\PlatformController;
use app\controller\admin\v1\RealnameController;
use app\controller\admin\v1\RefundController;
use app\controller\admin\v1\PermissionController;
use app\controller\admin\v1\ReportController;
use app\controller\admin\v1\SecurityController;
use app\controller\admin\v1\SystemController;
use app\controller\admin\v1\TicketController;
use app\controller\admin\v1\TransferController;
use app\controller\admin\v1\UserController;
use app\controller\admin\v1\WalletController;
use think\facade\Route;

/**
 * 管理后台 v1 API（契约：司南后台提示词 v3.1 §8）
 *
 * BaseURL: /admin/api/v1
 * 认证: Authorization: Bearer <admin_jwt_token>（登录/刷新除外）
 * 响应: { code, data, message }，code===200 成功
 *
 * 旧版 P0/P1 管理端点（/admin/*，X-Admin-Token）保留在 route_admin.php，
 * 前端 admin.html 继续可用；新前端只走本文件。
 */

// ---------- 8.1 认证 /auth（7） ----------
Route::group('admin/api/v1/auth', function () {
    Route::rule('login',    [AuthController::class, 'login'],        'POST');
    Route::rule('logout',   [AuthController::class, 'logout'],       'POST|PUT');
    Route::rule('refresh',  [AuthController::class, 'refresh'],      'POST');
    Route::rule('me',       [AuthController::class, 'me'],           'GET');
    Route::rule('password', [AuthController::class, 'password'],     'PUT|POST');
    Route::rule('2fa/setup', [AuthController::class, 'twofaSetup'],  'POST');
    Route::rule('2fa/verify', [AuthController::class, 'twofaVerify'], 'POST');
});

// ---------- 8.2 仪表盘 /dashboard（6） ----------
Route::group('admin/api/v1/dashboard', function () {
    Route::rule('metrics',        [DashboardController::class, 'metrics'],        'GET');
    Route::rule('finance',        [DashboardController::class, 'finance'],        'GET');
    Route::rule('alerts',         [DashboardController::class, 'alerts'],         'GET');
    Route::rule('activities',     [DashboardController::class, 'activities'],     'GET');
    Route::rule('trends',         [DashboardController::class, 'trends'],         'GET');
    Route::rule('priority-stats', [DashboardController::class, 'priorityStats'],  'GET');
});

// ---------- 8.3 用户管理 /users（15） ----------
Route::group('admin/api/v1/users', function () {
    Route::rule('',                          [UserController::class, 'list'],                  'GET');
    Route::rule(':id',                       [UserController::class, 'detail'],                'GET');
    Route::rule(':id/freeze',                [UserController::class, 'freeze'],                'PUT|POST');
    Route::rule(':id/unfreeze',              [UserController::class, 'unfreeze'],              'PUT|POST');
    Route::rule(':id/reset-tx-password',     [UserController::class, 'resetTxPassword'],       'PUT|POST');
    Route::rule(':id/force-logout',          [UserController::class, 'forceLogout'],           'PUT|POST');
    Route::rule(':id/blacklist',             [UserController::class, 'addBlacklist'],          'POST');
    Route::rule(':id/blacklist',             [UserController::class, 'removeBlacklist'],       'DELETE');
    Route::rule(':id/wallet',                [UserController::class, 'wallet'],                'GET');
    Route::rule(':id/collectibles',          [UserController::class, 'collectibles'],          'GET');
    Route::rule(':id/blindboxes',            [UserController::class, 'blindboxes'],            'GET');
    Route::rule(':id/priority-qualifications', [UserController::class, 'priorityQualifications'], 'GET');
    Route::rule(':id/invites',               [UserController::class, 'invites'],               'GET');
    Route::rule(':id/recover-collectible',   [UserController::class, 'recoverCollectible'],    'POST');
    Route::rule(':id/recover-blindbox',      [UserController::class, 'recoverBlindbox'],       'POST');
});

// ---------- 8.4 实名认证 /realnames（4） ----------
Route::group('admin/api/v1/realnames', function () {
    Route::rule('',             [RealnameController::class, 'list'],       'GET');
    Route::rule(':id',          [RealnameController::class, 'detail'],     'GET');
    Route::rule(':id/full',     [RealnameController::class, 'full'],       'POST');
    Route::rule(':id/audit-logs', [RealnameController::class, 'auditLogs'], 'GET');
});

// ---------- 8.5 藏品管理 /collectibles（18） ----------
Route::group('admin/api/v1/collectibles', function () {
    Route::rule('',                          [CollectibleController::class, 'list'],          'GET');
    Route::rule('',                          [CollectibleController::class, 'create'],        'POST');
    Route::rule(':id',                       [CollectibleController::class, 'detail'],        'GET');
    Route::rule(':id',                       [CollectibleController::class, 'edit'],          'PUT');
    Route::rule(':id',                       [CollectibleController::class, 'delete'],        'DELETE');
    Route::rule(':id/release',               [CollectibleController::class, 'release'],       'POST');
    Route::rule(':id/quotas',                [CollectibleController::class, 'quotas'],        'POST');
    Route::rule(':id/quotas/:quota_id',      [CollectibleController::class, 'quotaUpdate'],   'PUT');
    Route::rule(':id/relist',                [CollectibleController::class, 'relist'],        'POST');
    Route::rule(':id/force-soldout',         [CollectibleController::class, 'forceSoldout'],  'POST');
    Route::rule(':id/destroy',               [CollectibleController::class, 'destroy'],       'POST');
    Route::rule(':id/airdrop',               [CollectibleController::class, 'airdrop'],       'POST');
    Route::rule(':id/resale-toggle',         [CollectibleController::class, 'resaleToggle'],  'POST');
    Route::rule(':id/price-control',         [CollectibleController::class, 'priceControl'],  'POST');
    Route::rule(':id/qualification',         [CollectibleController::class, 'qualification'], 'POST');
    Route::rule(':id/audit',                 [CollectibleController::class, 'audit'],         'GET');
    Route::rule(':id/airdrop-records',       [CollectibleController::class, 'airdropRecords'], 'GET');
    Route::rule(':id/destroy-records',       [CollectibleController::class, 'destroyRecords'], 'GET');
});


// ---------- 8.12 钱包管理 /wallet（6） ----------
Route::group('admin/api/v1/wallet', function () {
    Route::rule('recharges',     [WalletController::class, 'recharges'],     'GET');
    Route::rule('transactions',  [WalletController::class, 'transactions'],  'GET');
    Route::rule('fee-stats',     [WalletController::class, 'feeStats'],      'GET');
    Route::rule('conservation',  [WalletController::class, 'conservation'],  'GET');
    Route::rule('abnormal',      [WalletController::class, 'abnormal'],      'GET');
    Route::rule('export',        [WalletController::class, 'export'],        'GET');
});

// ---------- 8.7 订单管理 /orders（8） ----------
Route::group('admin/api/v1/orders', function () {
    Route::rule('',             [OrderController::class, 'list'],     'GET');
    Route::rule('abnormal',     [OrderController::class, 'abnormal'], 'GET');
    Route::rule('export',       [OrderController::class, 'export'],   'GET');
    Route::rule(':id',          [OrderController::class, 'detail'],   'GET');
    Route::rule(':id/cancel',   [OrderController::class, 'cancel'],   'POST');
    Route::rule(':id/mark-paid', [OrderController::class, 'markPaid'], 'POST');
    Route::rule(':id/refund',   [OrderController::class, 'refund'],   'POST');
    Route::rule(':id/repair',   [OrderController::class, 'repair'],   'POST');
});

// ---------- 8.8 退款管理 /refunds（4） ----------
Route::group('admin/api/v1/refunds', function () {
    Route::rule('',            [RefundController::class, 'list'],    'GET');
    Route::rule(':id',         [RefundController::class, 'detail'],  'GET');
    Route::rule(':id/approve', [RefundController::class, 'approve'], 'POST');
    Route::rule(':id/reject',  [RefundController::class, 'reject'],  'POST');
});

// ---------- 8.6 盲盒管理 /blindboxes（17） ----------
Route::group('admin/api/v1/blindboxes', function () {
    Route::rule('',                          [BlindboxController::class, 'list'],          'GET');
    Route::rule('',                          [BlindboxController::class, 'create'],        'POST');
    Route::rule(':id',                       [BlindboxController::class, 'detail'],        'GET');
    Route::rule(':id',                       [BlindboxController::class, 'edit'],          'PUT');
    Route::rule(':id',                       [BlindboxController::class, 'delete'],        'DELETE');
    Route::rule(':id/items',                 [BlindboxController::class, 'items'],         'POST');
    Route::rule(':id/items/:item_id',        [BlindboxController::class, 'itemUpdate'],    'PUT');
    Route::rule(':id/items/:item_id',        [BlindboxController::class, 'itemDelete'],    'DELETE');
    Route::rule(':id/release',               [BlindboxController::class, 'release'],       'POST');
    Route::rule(':id/relist',                [BlindboxController::class, 'relist'],        'POST');
    Route::rule(':id/force-soldout',         [BlindboxController::class, 'forceSoldout'],  'POST');
    Route::rule(':id/destroy',               [BlindboxController::class, 'destroy'],       'POST');
    Route::rule(':id/airdrop',               [BlindboxController::class, 'airdrop'],       'POST');
    Route::rule(':id/recover',               [BlindboxController::class, 'recover'],       'POST');
    Route::rule(':id/open-records',          [BlindboxController::class, 'openRecords'],   'GET');
    Route::rule(':id/audit',                 [BlindboxController::class, 'audit'],         'GET');
    Route::rule(':id/destroy-records',       [BlindboxController::class, 'destroyRecords'], 'GET');
});

// ---------- 8.9 市场管理 /market（7） ----------
Route::group('admin/api/v1/market', function () {
    Route::rule('listings',                 [MarketController::class, 'listings'],            'GET');
    Route::rule('listings/delist',          [MarketController::class, 'delist'],              'POST');
    Route::rule('trades',                   [MarketController::class, 'trades'],              'GET');
    Route::rule('price-alerts',             [MarketController::class, 'priceAlerts'],         'GET');
    Route::rule('fee-config',               [MarketController::class, 'feeConfig'],           'GET');
    Route::rule('fee-config',               [MarketController::class, 'feeConfigUpdate'],     'PUT|POST');
    Route::rule('collectibles/:id/listings', [MarketController::class, 'collectibleListings'], 'GET');
});

// ---------- 8.10 转赠管理 /transfers（6） ----------
Route::group('admin/api/v1/transfers', function () {
    Route::rule('',            [TransferController::class, 'list'],    'GET');
    Route::rule('stats',       [TransferController::class, 'stats'],   'GET');
    Route::rule('abnormal',    [TransferController::class, 'abnormal'], 'GET');
    Route::rule(':id',         [TransferController::class, 'detail'],  'GET');
    Route::rule(':id/revoke',  [TransferController::class, 'revoke'],  'POST');
    Route::rule(':id/cancel',  [TransferController::class, 'cancel'],  'POST');
});

// ---------- 8.11 营销活动 /marketing（35） ----------
Route::group('admin/api/v1/marketing', function () {
    // 8.11.1 优先购（93~102）
    Route::rule('priority',                      [MarketingController::class, 'priorityList'],      'GET');
    Route::rule('priority',                      [MarketingController::class, 'priorityCreate'],    'POST');
    Route::rule('priority/cleanup',              [MarketingController::class, 'priorityCleanup'],   'POST');
    Route::rule('priority/:id',                  [MarketingController::class, 'priorityDetail'],    'GET');
    Route::rule('priority/:id',                  [MarketingController::class, 'priorityEdit'],      'PUT|POST');
    Route::rule('priority/:id/whitelist',        [MarketingController::class, 'whitelistList'],     'GET');
    Route::rule('priority/:id/whitelist',        [MarketingController::class, 'whitelistAdd'],      'POST');
    Route::rule('priority/:id/whitelist/batch',  [MarketingController::class, 'whitelistBatch'],    'POST');
    Route::rule('priority/:id/whitelist/:wid',   [MarketingController::class, 'whitelistEdit'],     'PUT|POST');
    Route::rule('priority/:id/whitelist/:wid',   [MarketingController::class, 'whitelistDelete'],   'DELETE');
    // 8.11.2 签到（103~105）
    Route::rule('checkin/config',                [MarketingController::class, 'checkinConfig'],       'GET');
    Route::rule('checkin/config',                [MarketingController::class, 'checkinConfigUpdate'], 'PUT|POST');
    Route::rule('checkin/records',               [MarketingController::class, 'checkinRecords'],      'GET');
    // 8.11.3 邀请（106~108）
    Route::rule('invite/config',                 [MarketingController::class, 'inviteConfig'],       'GET');
    Route::rule('invite/config',                 [MarketingController::class, 'inviteConfigUpdate'], 'PUT|POST');
    Route::rule('invite/records',                [MarketingController::class, 'inviteRecords'],      'GET');
    // 8.11.4 抽奖（109~117）
    Route::rule('lucky-draw',                    [MarketingController::class, 'luckyList'],   'GET');
    Route::rule('lucky-draw',                    [MarketingController::class, 'luckyCreate'], 'POST');
    Route::rule('lucky-draw/:id',                [MarketingController::class, 'luckyDetail'], 'GET');
    Route::rule('lucky-draw/:id',                [MarketingController::class, 'luckyEdit'],   'PUT|POST');
    Route::rule('lucky-draw/:id/prizes',         [MarketingController::class, 'prizeList'],   'GET');
    Route::rule('lucky-draw/:id/prizes',         [MarketingController::class, 'prizeAdd'],    'POST');
    Route::rule('lucky-draw/:id/prizes/:pid',    [MarketingController::class, 'prizeEdit'],   'PUT|POST');
    Route::rule('lucky-draw/:id/prizes/:pid',    [MarketingController::class, 'prizeDelete'], 'DELETE');
    Route::rule('lucky-draw/:id/records',        [MarketingController::class, 'luckyRecords'], 'GET');
    // 8.11.5 合成（118~122）
    Route::rule('synthesis',                     [MarketingController::class, 'synthesisList'],   'GET');
    Route::rule('synthesis',                     [MarketingController::class, 'synthesisCreate'], 'POST');
    Route::rule('synthesis/:id',                 [MarketingController::class, 'synthesisDetail'], 'GET');
    Route::rule('synthesis/:id',                 [MarketingController::class, 'synthesisEdit'],   'PUT|POST');
    Route::rule('synthesis/:id/records',         [MarketingController::class, 'synthesisRecords'], 'GET');
    // 8.11.6 活动空投（123~124）
    Route::rule('airdrop',                       [MarketingController::class, 'airdrop'],          'POST');
    Route::rule('airdrop/records',               [MarketingController::class, 'airdropRecords'],   'GET');
    // 8.11.7 注册福利（125~126）
    Route::rule('register/reward',               [MarketingController::class, 'registerConfig'],       'GET');
    Route::rule('register/reward',               [MarketingController::class, 'registerConfigUpdate'], 'PUT|POST');
    // 8.11.8 盲盒数据（127）
    Route::rule('blindbox/stats',                [MarketingController::class, 'blindboxStats'], 'GET');
});

// ---------- 8.13 CMS 管理 /cms（18） ----------
Route::group('admin/api/v1/cms', function () {
    // Banner（134~138）
    Route::rule('banners',            [CmsController::class, 'bannerList'],            'GET');
    Route::rule('banners',            [CmsController::class, 'bannerCreate'],          'POST');
    Route::rule('banners/:id',        [CmsController::class, 'bannerDetail'],          'GET');
    Route::rule('banners/:id',        [CmsController::class, 'bannerEdit'],            'PUT|POST');
    Route::rule('banners/:id',        [CmsController::class, 'bannerDelete'],          'DELETE');
    // 公告（139~143）
    Route::rule('announcements',      [CmsController::class, 'announcementList'],      'GET');
    Route::rule('announcements',      [CmsController::class, 'announcementCreate'],    'POST');
    Route::rule('announcements/:id',  [CmsController::class, 'announcementDetail'],    'GET');
    Route::rule('announcements/:id',  [CmsController::class, 'announcementEdit'],      'PUT|POST');
    Route::rule('announcements/:id',  [CmsController::class, 'announcementDelete'],    'DELETE');
    // 协议（144~145）
    Route::rule('agreements',         [CmsController::class, 'agreementList'],         'GET');
    Route::rule('agreements/:id',     [CmsController::class, 'agreementEdit'],         'PUT|POST');
    // 文物展览（146~149）
    Route::rule('artifacts',          [CmsController::class, 'artifactList'],          'GET');
    Route::rule('artifacts',          [CmsController::class, 'artifactCreate'],        'POST');
    Route::rule('artifacts/:id',      [CmsController::class, 'artifactEdit'],          'PUT|POST');
    Route::rule('artifacts/:id',      [CmsController::class, 'artifactDelete'],        'DELETE');
    // 网页装修（150~151）
    Route::rule('decoration',         [CmsController::class, 'decorationView'],        'GET');
    Route::rule('decoration',         [CmsController::class, 'decorationEdit'],        'PUT|POST');
});

// ---------- 8.14 系统配置 /system（10） ----------
Route::group('admin/api/v1/system', function () {
    // 支付配置（152~153）
    Route::rule('payment',  [SystemController::class, 'paymentView'],   'GET');
    Route::rule('payment',  [SystemController::class, 'paymentUpdate'], 'PUT|POST');
    // 安全策略（154~155）
    Route::rule('security', [SystemController::class, 'securityView'],   'GET');
    Route::rule('security', [SystemController::class, 'securityUpdate'], 'PUT|POST');
    // CORS/域名（156~157）
    Route::rule('cors',     [SystemController::class, 'corsView'],   'GET');
    Route::rule('cors',     [SystemController::class, 'corsUpdate'], 'PUT|POST');
    // 短信/OSS（158~159）
    Route::rule('sms-oss',  [SystemController::class, 'smsOssView'],   'GET');
    Route::rule('sms-oss',  [SystemController::class, 'smsOssUpdate'], 'PUT|POST');
    // 全局参数（160~161）
    Route::rule('global',   [SystemController::class, 'globalView'],   'GET');
    Route::rule('global',   [SystemController::class, 'globalUpdate'], 'PUT|POST');
});

// ---------- 8.15 权限审计 /permission（15） ----------
Route::group('admin/api/v1/permission', function () {
    // 管理员（162~167）
    Route::rule('admins',                      [PermissionController::class, 'adminList'],          'GET');
    Route::rule('admins',                      [PermissionController::class, 'adminCreate'],        'POST');
    Route::rule('admins/:id',                  [PermissionController::class, 'adminDetail'],        'GET');
    Route::rule('admins/:id',                  [PermissionController::class, 'adminEdit'],          'PUT|POST');
    Route::rule('admins/:id',                  [PermissionController::class, 'adminDelete'],        'DELETE');
    Route::rule('admins/:id/reset-password',   [PermissionController::class, 'adminResetPassword'], 'PUT|POST');
    // 角色（168~171）
    Route::rule('roles',                       [PermissionController::class, 'roleList'],   'GET');
    Route::rule('roles',                       [PermissionController::class, 'roleCreate'], 'POST');
    Route::rule('roles/:id',                   [PermissionController::class, 'roleEdit'],   'PUT|POST');
    Route::rule('roles/:id',                   [PermissionController::class, 'roleDelete'], 'DELETE');
    // 权限树（172）
    Route::rule('permissions',                 [PermissionController::class, 'permissionTree'], 'GET');
    // 操作日志（173~175）
    Route::rule('operation-logs/export',       [PermissionController::class, 'operationLogsExport'], 'GET');
    Route::rule('operation-logs/:id',          [PermissionController::class, 'operationLogDetail'],  'GET');
    Route::rule('operation-logs',              [PermissionController::class, 'operationLogs'],       'GET');
    // 登录日志（176）
    Route::rule('login-logs',                  [PermissionController::class, 'loginLogs'], 'GET');
});

// ---------- 8.16 风控安全 /security（15） ----------
Route::group('admin/api/v1/security', function () {
    // 黑名单（177~179）
    Route::rule('blacklist',            [SecurityController::class, 'blacklistList'],   'GET');
    Route::rule('blacklist',            [SecurityController::class, 'blacklistAdd'],    'POST');
    Route::rule('blacklist/:id',        [SecurityController::class, 'blacklistRemove'], 'DELETE|POST');
    // 风控告警（180~182）
    Route::rule('risk-alerts',          [SecurityController::class, 'riskAlerts'],      'GET');
    Route::rule('risk-alerts/:id',      [SecurityController::class, 'riskAlertDetail'], 'GET');
    Route::rule('risk-alerts/:id',      [SecurityController::class, 'riskAlertHandle'], 'PUT|POST');
    // 安全事件（183~185）
    Route::rule('events',               [SecurityController::class, 'eventList'],   'GET');
    Route::rule('events/:id',           [SecurityController::class, 'eventDetail'], 'GET');
    Route::rule('events/:id',           [SecurityController::class, 'eventHandle'], 'PUT|POST');
    // 交易密码锁定（186~187）
    Route::rule('tx-locks',             [SecurityController::class, 'txLocks'],      'GET');
    Route::rule('tx-locks/:id/unlock',  [SecurityController::class, 'txLockUnlock'], 'POST');
    // 敏感操作审批（188~191）
    Route::rule('approvals',            [SecurityController::class, 'approvals'],       'GET');
    Route::rule('approvals/:id',        [SecurityController::class, 'approvalDetail'],  'GET');
    Route::rule('approvals/:id/approve', [SecurityController::class, 'approvalApprove'], 'POST');
    Route::rule('approvals/:id/reject',  [SecurityController::class, 'approvalReject'],  'POST');
});

// ---------- 8.17 客服工单 /tickets（8） ----------
Route::group('admin/api/v1/tickets', function () {
    // 工单（192~196）—— feedbacks 是静态段，必须先于 :id 注册
    Route::rule('/',                [TicketController::class, 'list'],         'GET');
    Route::rule('feedbacks',        [TicketController::class, 'feedbacks'],      'GET');
    Route::rule('feedbacks/:id',    [TicketController::class, 'feedbackDetail'], 'GET');
    Route::rule(':id',              [TicketController::class, 'detail'],         'GET');
    Route::rule(':id/assign',       [TicketController::class, 'assign'],         'PUT|POST');
    Route::rule(':id/reply',        [TicketController::class, 'reply'],          'POST');
    Route::rule(':id/status',       [TicketController::class, 'updateStatus'],   'PUT|POST');
    Route::rule(':id/compensate',   [TicketController::class, 'compensate'],     'POST');
});

// ---------- 8.18 数据报表 /reports（6） ----------
Route::group('admin/api/v1/reports', function () {
    Route::rule('sales',         [ReportController::class, 'sales'],         'GET');
    Route::rule('users',         [ReportController::class, 'users'],         'GET');
    Route::rule('collectibles',  [ReportController::class, 'collectibles'],  'GET');
    Route::rule('blindboxes',    [ReportController::class, 'blindboxes'],    'GET');
    Route::rule('finance',       [ReportController::class, 'finance'],       'GET');
    Route::rule('custom-export', [ReportController::class, 'customExport'],  'POST');
});

// ---------- 8.19 平台运维 /platform（4） ----------
Route::group('admin/api/v1/platform', function () {
    Route::rule('cleanup',                   [PlatformController::class, 'cleanup'],      'POST');
    Route::rule('cleanup-logs',              [PlatformController::class, 'cleanupLogs'],  'GET');
    Route::rule('backups',                   [PlatformController::class, 'backups'],      'GET');
    Route::rule('backups/:id/restore',       [PlatformController::class, 'restore'],      'POST');
});
