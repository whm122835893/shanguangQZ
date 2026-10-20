<?php
declare(strict_types=1);

namespace app\controller\admin\v1;

use app\service\admin\Guard;
use app\service\admin\Resp;
use think\facade\Db;

/**
 * SystemController —— 系统配置（契约 §8.14，10 个接口 / 5 组）
 *
 * 数据载体：site_config KV（system.payment / system.security / system.cors /
 * system.sms_oss / system.global），JSON value。
 *
 * 安全契约：GET 免密码（响应不含明文密钥语义由种子结构保证，密钥位仅存占位）；
 * PUT 全部为高风险操作 → 必须携带管理员密码（verifyPassword）+ 审计落库（action=system_config_update）。
 * 更新语义：顶层键覆盖、嵌套对象递归合并（未传子键保留原值）。
 *
 * @package app\controller\admin\v1
 */
final class SystemController
{
    use V1;

    private const KEYS = [
        'payment'  => 'system.payment',
        'security' => 'system.security',
        'cors'     => 'system.cors',
        'sms_oss'  => 'system.sms_oss',
        'global'   => 'system.global',
    ];

    // ========================================================
    // 8.14.1 支付配置（152~153）
    // ========================================================

    /** GET /system/payment（152） */
    public function paymentView()
    {
        self::guard('system:config');
        return Resp::ok($this->read('payment'));
    }

    /** PUT /system/payment（153，高风险） */
    public function paymentUpdate()
    {
        return $this->update('payment');
    }

    // ========================================================
    // 8.14.2 安全策略（154~155）
    // ========================================================

    /** GET /system/security（154） */
    public function securityView()
    {
        self::guard('system:security');
        return Resp::ok($this->read('security'));
    }

    /** PUT /system/security（155，高风险） */
    public function securityUpdate()
    {
        return $this->update('security');
    }

    // ========================================================
    // 8.14.3 CORS/域名（156~157）
    // ========================================================

    /** GET /system/cors（156） */
    public function corsView()
    {
        self::guard('system:config');
        return Resp::ok($this->read('cors'));
    }

    /** PUT /system/cors（157，高风险） */
    public function corsUpdate()
    {
        return $this->update('cors');
    }

    // ========================================================
    // 8.14.4 短信/OSS（158~159）
    // ========================================================

    /** GET /system/sms-oss（158） */
    public function smsOssView()
    {
        self::guard('system:config');
        return Resp::ok($this->read('sms_oss'));
    }

    /** PUT /system/sms-oss（159，高风险） */
    public function smsOssUpdate()
    {
        return $this->update('sms_oss');
    }

    // ========================================================
    // 8.14.5 全局参数（160~161）
    // ========================================================

    /** GET /system/global（160） */
    public function globalView()
    {
        self::guard('system:config');
        return Resp::ok($this->read('global'));
    }

    /** PUT /system/global（161，高风险） */
    public function globalUpdate()
    {
        return $this->update('global');
    }

    // --------------------------------------------------------

    /** 读配置（JSON 解析，非法 → []） */
    private function read(string $name): array
    {
        $raw = Db::name('site_config')->where('key', self::KEYS[$name])->value('value');
        $cfg = json_decode((string) $raw, true);
        return is_array($cfg) ? $cfg : [];
    }

    /** 统一写路径：密码校验 → 递归合并 → 落库 → 审计 */
    private function update(string $name)
    {
        $admin = self::guard('system:config');
        self::verifyPassword($admin);
        $current = $this->read($name);
        $incoming = [];
        foreach (request()->post() as $k => $v) {
            if ($k === 'password' || !is_scalar($v) && !is_array($v)) {
                continue;
            }
            $incoming[$k] = $v;
        }
        if ($incoming === []) {
            return self::fail('无可更新字段');
        }
        $merged = $this->merge($current, $incoming);
        $json = json_encode($merged, JSON_UNESCAPED_UNICODE);
        $now = time();
        $exists = Db::name('site_config')->where('key', self::KEYS[$name])->find();
        if ($exists === null) {
            Db::name('site_config')->insert([
                'key' => self::KEYS[$name], 'value' => $json,
                'group_name' => 'system', 'remark' => '', 'updatetime' => $now,
            ]);
        } else {
            Db::name('site_config')->where('key', self::KEYS[$name])->update(['value' => $json, 'updatetime' => $now]);
        }
        Guard::audit('system_config_update', (int) $admin['id'], 0, [
            'config' => self::KEYS[$name], 'fields' => array_keys($incoming),
        ]);
        return Resp::ok(['success' => true]);
    }

    /** 递归合并：标量覆盖，双方均为数组时下钻 */
    private function merge(array $base, array $incoming): array
    {
        foreach ($incoming as $k => $v) {
            if (is_array($v) && isset($base[$k]) && is_array($base[$k]) && !array_is_list($v)) {
                $base[$k] = $this->merge($base[$k], $v);
            } else {
                $base[$k] = $v;
            }
        }
        return $base;
    }
}
