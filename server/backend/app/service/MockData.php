<?php
declare(strict_types=1);

namespace app\service;

/**
 * Mock 数据工厂
 *
 * 所有字段名/类型/取值均对齐原站实测样本（docs/api/API.md §4），
 * 不臆造字段。金额为string、布尔为 int、时间戳为 int —— 严格遵守 SPEC §1.5。
 *
 * @package app\service
 */
final class MockData
{
    /** 固定图片池：全部走 oss() 本地化路径，前端 strip query 后可命中 */
    private const IMAGES = [
        '/uploads/20261010/2bac5172345cf2ca30c08a6713a65af7.jpg',
        '/uploads/20261010/d86edf5a1795d65dc822e139a88e4c2a.jpg',
        '/uploads/20261010/f0094d1aba622b6aceb2c91896af3eb8.jpg',
        '/uploads/20261012/982cfb57d2d7bd4f66da6be37899e220.png',
        '/uploads/20261015/91b117ce94ef29dedd5298fa17a85a16.jpg',
    ];

    private static function img(int $i = 0): string
    {
        return self::IMAGES[$i % count(self::IMAGES)];
    }

    /**
     * 藏品本体（实测 10 字段，API.md §4.3）
     */
    public static function collection(int $id = 8): array
    {
        $names = ['思维粒子', '赤壁之战', '青花瓷', '唐三彩', '青铜饕餮'];
        return [
            'id'                    => $id,
            'name'                  => $names[$id % count($names)],
            'main_image'            => self::img($id),
            'listimg'               => self::img($id + 1),
            'collection_level'      => '',
            'total_num_show'        => 0,
            'total_num'             => 100000,
            'is_img_diff'           => 0,
            'unfreeze_collection_id'=> 0,
            'freeze_time'           => 0,
        ];
    }

    /**
     * 用户持仓（实测 6 字段 + 嵌套 collection，API.md §4.3）
     * 注意 sell_num 实测是字符串、num/lock_num 是 int —— 保留原站不一致
     */
    public static function userCollection(int $collectionId = 8): array
    {
        return [
            'collection_id' => $collectionId,
            'num'           => 1,
            'sell_num'      => '0',
            'lock_num'      => 0,
            'is_collection' => 1,
            'can_compound'  => 0,
            'utime'         => time() - 86400,
            'collection'    => self::collection($collectionId),
        ];
    }

    /**
     * 订单（实测 18 字段，API.md §4.2）
     * order_type=2 市场单、order_status=1 待付款 —— 组合语义来自逆向字典
     */
    public static function order(int $id = 18554, int $orderType = 2, int $orderStatus = 1): array
    {
        $ts = time() - 3600;
        return [
            'artel_order'           => 'mh_secondary_market_order',
            'id'                    => $id,
            'order_sn'              => date('YmdHis', $ts) . '57101499336911',
            'order_type'            => $orderType,
            'order_status'          => $orderStatus,
            'total_price'           => '100.00',
            'num'                   => '1',
            'goods_id'              => 155,
            'goods_type'            => 2,
            'goods_name'            => '赤壁之战',
            'goods_cover_image'     => self::img(1),
            'series_name'           => '历史区',
            'crystal_type'          => 'crystal_no',
            'crystal_number'        => '0',
            'uc_ids'                => null,
            'collection_code'       => [],
            'createtime'            => $ts,
            'updatetime'            => $ts + 1,
        ];
    }

    /**
     * 用户完整信息（实测 90+ 字段的关键子集，API.md §4.1）
     *
     * 🔴 三个易错点（前端已硬编码依赖，改名即崩）：
     *   inviter_code 而非 invite_code
     *   huifu_account / yibao_status / sandpay_status 三个独立字段
     *   实名信息必须是脱敏值
     */
    public static function userInfo(): array
    {
        return [
            'id'              => 16797,
            'username'        => '17587881293',
            'nickname'        => 'GqdusoI30',
            'mobile'          => '17587881293',
            'avatar'          => '/defautAvatar.png',
            'level'           => 1,
            'gender'          => 1,

            'money'           => '0.00',
            'agent_money'     => '0.00',
            'recharge_price'  => '0.00',
            'service_price'   => '0.00',
            'user_ore'        => '0.000000',
            'team_ore'        => '0.000000',
            'person_ore'      => '0.000000',

            'score'           => 0,
            'successions'     => 4,
            'maxsuccessions'  => 4,
            'prevtime'        => time() - 86400,

            // 🔴 邀请码字段名
            'inviter_code'        => 'Q28116797',
            'inviter_user_id'     => 0,
            'inviter_status'      => 0,
            'invite_type'         => 1,
            'new_inviter_user_id' => 0,
            'yao_count'           => 0,

            // 实名（已脱敏，后端脱敏而非前端处理）
            'real_name_authentication' => 1,
            'real_name'           => '王*绵',
            'id_card'             => '520******838',
            'id_card_front'       => '',
            'id_card_back'        => '',
            'real_name_time'      => time() - 2592000,

            // 🔴 支付通道三字段，前端 Number(x)===1 判定
            'huifu_account'       => 1,
            'yibao_status'        => 0,
            'sandpay_status'      => 0,
            'tipay_status'        => 0,
            'yifutong_status'     => 0,
            'pay_password'        => 0,

            'huifu_service_charge'    => 1,
            'sandpay_service_charge'  => 0,
            'yifutong_service_charge' => 0,

            'collection_num'              => 1,
            'collection_total_price'      => '1.00',
            'buy_collection_total_price'  => '1.00',
            'blind_box_num'               => 0,
            'crystal_yellow'              => 0,
            'crystal_purple'              => 0,
            'crystal_blue'                => 0,

            'freeze'                     => 0,
            'is_white'                   => 0,
            'market_open'                => 1,
            'transfer_permission'        => 0,
            'transfer_permission_level'  => 1,
            'is_vip'                     => 0,
            'is_show_rank'               => 1,
            'show_wallet'                => 1,

            'statistics' => [
                'invited_count' => 0,
                'buy_count'     => 1,
                'sale_count'    => 0,
                'join_day'      => 30,
            ],

            'createtime' => time() - 2592000,
            'updatetime' => time() - 60,
            'logintime'  => time() - 300,
            'joinip'     => '127.0.0.1',
            'loginip'    => '127.0.0.1',
            'status'     => 'normal',
            'url'        => '/u/16797',
        ];
    }

    /**
     * 登录返回（token 在 userinfo 下，API.md §4.4）
     */
    public static function loginResult(): array
    {
        $u = self::userInfo();
        return [
            'id'         => $u['id'],
            'nickname'   => $u['nickname'],
            'mobile'     => $u['mobile'],
            'token'      => 'mock-' . bin2hex(random_bytes(16)),
            'user_id'    => $u['id'],
            'expiretime' => time() + 86400,
            'expires_in' => 86400,
            'is_exist'   => 1,
        ];
    }

    /**
     * 首页轮播（实测字段 API.md §4.4）
     */
    public static function banner(int $i = 0): array
    {
        return [
            'id'       => $i + 2,
            'image'    => self::img($i),
            'url'      => '',
            'url_type' => 1,
            'is_show'  => 1,
        ];
    }

    /**
     * 公告（实测字段 API.md §4.4）
     */
    public static function affiche(int $id = 134): array
    {
        return [
            'id'           => $id,
            'image'        => self::img($id),
            'title'        => '关于闪光集市运营公告的说明',
            'createtime'   => time() - 3600,
            'type'         => '1',
            'url'          => '',
            'affiche_type' => '运营公告',
        ];
    }

    /**
     * 市场挂单（marketGoodsDetail 的 detail.key 是下单必需，见 SPEC §4.2）
     */
    public static function marketGoods(int $id = 155): array
    {
        return [
            'id'         => $id,
            'name'       => '赤壁之战',
            'listimg'    => self::img(1),
            'main_image' => self::img(1),
            'series_name'=> '历史区',
            'price'      => '100.00',
            'num'        => 1,
            'sale_number'=> 0,
            'collection_id' => 8,
        ];
    }

    /**
     * 生成一组列表项并按分页切片
     *
     * @param callable(int):array $factory
     * @return array{list:array,total:int}
     */
    public static function paginate(callable $factory, int $total, int $page, int $size): array
    {
        $offset = ($page - 1) * $size;
        $list   = [];
        for ($i = $offset; $i < min($offset + $size, $total); $i++) {
            $list[] = $factory($i);
        }
        return ['list' => $list, 'total' => $total];
    }
}
