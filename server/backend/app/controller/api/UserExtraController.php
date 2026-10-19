<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;
use app\service\AddressService;
use app\service\RankService;

/**
 * 用户辅助域控制器（v2.4 从 UserController 拆分）
 *
 * 购买/流转记录、收货地址、用户排行榜
 *
 * 🔴 拆分原因：UserController 一度 313 行，触发单文件 ≤300 行的 P0 红线。
 *
 * v3.0：addressList（AddressService）/ userRank（RankService 实时聚合）已切 DB；
 *       getRecord 仍为【MOCK】→ 见第 3 类真空态改造。
 *    拿到recon/ 实测样本后逐个切 DB，并同步加入 tools/contract/Cases.php。
 *
 * ⚠️ 改动后必须跑：php tools/contract/run.php
 *
 * @package app\controller\api
 */
class UserExtraController
{
    use HasAuth;

    /**
     * POST /user_collection/user_collection/getRecord 购买/流转记录【空态】
     * 前端无页面调用（api/index.js 死导出已删）；无独立记录载体，
     * 真实空分页——不再编造订单。
     */

    public function collectionRecord()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        return Response::page([], 0, $s, $p);
    }

    /** POST /user/address_list 收货地址（v3.0 切 DB，AddressService） */

    public function addressList()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        // 前端兼容 d.list / d.data / 数组三种形态，直接回数组
        return Response::ok(AddressService::list((int) $user['id']));
    }

    // ==================== 市场 / 行情 ====================

    /**
     * POST /user/ranking 市值排行（v3.0 切实时聚合：持有数 × 藏品最低在售价）
     * 契约 {data:[{rank,id,nickname,avatar,market_value}], my, activityRule}
     */

    public function userRank()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok(RankService::ranking((int) $user['id']));
    }
}
