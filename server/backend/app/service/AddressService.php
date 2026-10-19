<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 收货地址服务（v3.0 从内存 MOCK 切 DB）
 *
 * 前端 AddressList.vue 只读列表（无增删改端点），故仅提供 list。
 * 默认地址排最前。契约见 tools/contract/user_extra_test.php。
 *
 * @package app\service
 */
final class AddressService
{
    public static function list(int $userId): array
    {
        $rows = Db::name('user_address')
            ->where('user_id', $userId)
            ->order('is_default', 'desc')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'         => (int) $r['id'],
                'name'       => (string) $r['name'],
                'mobile'     => (string) $r['mobile'],
                'province'   => (string) $r['province'],
                'city'       => (string) $r['city'],
                'district'   => (string) $r['district'],
                'address'    => (string) $r['address'],
                'is_default' => (int) $r['is_default'],
            ];
        }
        return $out;
    }
}
