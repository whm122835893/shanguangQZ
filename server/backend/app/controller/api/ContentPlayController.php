<?php
declare(strict_types=1);

namespace app\controller\api;

use app\controller\api\Concerns\HasAuth;
use app\common\Request;
use app\common\Response;
use app\service\MockData;

/**
 * 玩法域控制器（v2.4 从 ContentController 拆分）
 *
 * 合成、抽签 / 抽奖、活动 / 社区、水晶兑换
 *
 * 🔴 拆分原因：ContentController 一度涨到 379 行，触发单文件 ≤300 行的 P0 红线。
 *    这四块业务相互独立，且与公告 / Banner / 盲盒无耦合，拆开互不干扰。
 *
 * 🔴 本域端点目前全部是【MOCK】：
 *    有实测样本的端点（synthesis/recordList、lottery/statistics 等）
 *    尚未完成逐字段核验，故先不切 DB。样本清单见 tools/contract/run.php。
 *
 * ⚠️ 改动本文件后必须跑契约回归：php tools/contract/run.php
 *
 * @package app\controller\api
 */
class ContentPlayController
{
    use HasAuth;

    // ==================== 合成 ====================

    /** POST /user/login 账号密码登录【DB + Argon2id】 */

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
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
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
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
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
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
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
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }
        return Response::ok(['prize_id' => 1, 'prize_name' => '藏品A']);
    }

    /** POST /lottery/record */

    public function lotteryRecord()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
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
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
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
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
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
}
