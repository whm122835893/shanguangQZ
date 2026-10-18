<?php
declare(strict_types=1);

namespace app\controller\api;

use app\common\Request;
use app\common\Response;
use app\controller\api\Concerns\HasAuth;
use app\service\MockData;

/**
 * 用户辅助域控制器（v2.4 从 UserController 拆分）
 *
 * 购买/流转记录、收货地址、用户排行榜
 *
 * 🔴 拆分原因：UserController 一度 313 行，触发单文件 ≤300 行的 P0 红线。
 *    这三个端点目前全是【MOCK】，与登录/用户信息等【DB】端点关注点不同，
 *    混在一起会让「哪些端点已经切 DB」变得难以一眼看清。
 *
 * 🔴 本域端点全部为【MOCK】：尚无原站成功样本，禁止凭端点名猜字段。
 *    拿到recon/ 实测样本后逐个切 DB，并同步加入 tools/contract/Cases.php。
 *
 * ⚠️ 改动后必须跑：php tools/contract/run.php
 *
 * @package app\controller\api
 */
class UserExtraController
{
    use HasAuth;

    /** POST /user_collection/user_collection/getRecord 购买/流转记录 */

    public function collectionRecord()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
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
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
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
}
