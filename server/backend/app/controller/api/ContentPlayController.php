<?php
declare(strict_types=1);

namespace app\controller\api;

use app\controller\api\Concerns\HasAuth;
use app\common\Request;
use app\common\Response;
use app\service\LotteryException;
use app\service\LotteryService;
use app\service\SynthesisException;
use app\service\SynthesisService;

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

    /** POST /synthesis/themeinfo 合成主题详情（v3.0 切 DB） */

    public function synthesisTheme()
    {
        // 原站实测：主题未开启 code:0，前端 catch 后 1.5s 自动回首页
        try {
            return Response::ok(SynthesisService::themeinfo(Request::int('thm')));
        } catch (SynthesisException $e) {
            return Response::fail($e->getMessage());
        }
    }

    /** POST /synthesis/material 合成材料（v3.0 切 DB：need_items 真实解析，空数组合法） */

    public function synthesisMaterial()
    {
        try {
            return Response::ok(SynthesisService::material(Request::int('play_id')));
        } catch (SynthesisException $e) {
            return Response::fail($e->getMessage());
        }
    }

    /** GET /synthesis/recordList 合成记录（本域无执行端点，恒真实空分页） */

    public function synthesisRecord()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        $r = SynthesisService::records((int) $user['id'], $p, $s);
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

        // v3.0 切 DB。原站实测：无进行中活动时 code:0「暂无抽奖活动」，前端 catch 后渲染空态。
        try {
            return Response::ok(LotteryService::info((int) $user['id'], Request::int('id')));
        } catch (LotteryException $e) {
            return Response::fail($e->getMessage());
        }
    }

    /** POST /lottery/statistics （两种入参形态） */

    public function lotteryStatistics()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        // v3.0 切 DB：当前用户抽奖记录（Lottery.vue 奖品区 + Record.vue 卡② 消费：数组）
        return Response::ok(LotteryService::statistics((int) $user['id']));
    }

    /** POST /lottery/start （写操作，Mock 仅返回结构） */

    public function lotteryStart()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        // v3.0 切 DB。前端 Lottery.vue 发 {lotteryId, number}；失败 toast 直接消费 msg。
        try {
            return Response::ok(LotteryService::draw(
                (int) $user['id'],
                Request::int('lotteryId'),
                Request::int('number', 1)
            ));
        } catch (LotteryException $e) {
            return Response::fail($e->getMessage());
        }
    }

    /** POST /lottery/record */

    public function lotteryRecord()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        // v3.0 切 DB：中奖记录 {list, total}（Record.vue 卡① 消费）
        ['page' => $p, 'size' => $s] = Request::page();
        $r = LotteryService::records((int) $user['id'], $p, $s);
        return Response::page($r['list'], $r['total'], $s, $p);
    }

    // ==================== 活动 / 社区 ====================

    /**
     * POST /activity/index 活动列表【空态】
     * 前端无页面调用（api/index.js 死导出已删）；本域无活动表、无运营入口，
     * 返回真实空分页——绝不编造活动。
     */

    public function activityIndex()
    {
        ['page' => $p, 'size' => $s] = Request::page();
        return Response::page([], 0, $s, $p);
    }

    /** GET /Activity/category 活动分类【空态】（⚠️ 首字母大写） */

    public function activityCategory()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        return Response::ok([]);
    }

    /**
     * GET /crystal_goods/list 水晶列表【空态】
     * 原站实测（Shop.vue 注释）即返回 total:0 data:[]（无上架商品），按原样返回。
     */

    public function crystalGoods()
    {
        [$user, $authErr] = $this->currentUser();
        if ($authErr !== null) {
            return $authErr;
        }

        ['page' => $p, 'size' => $s] = Request::page();
        return Response::page([], 0, $s, $p);
    }
}
