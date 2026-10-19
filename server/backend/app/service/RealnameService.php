<?php
declare(strict_types=1);

namespace app\service;

use think\db\exception\PDOException;
use think\facade\Db;

/**
 * RealnameService —— 实名认证唯一入口
 *
 * 🔴 为什么必须有这个类：
 *   此前实名信息只有 MockData 里的脱敏展示值，无提交端点、无加密、
 *   无一证一号约束。数字藏品平台实名是合规底线，明文/半明文存储不可接受。
 *
 * 三态分离设计（tools/contract/realname_test.php 逐条锁定）：
 *   展示态  user.real_name / user.id_card —— 脱敏值（"王*明"/"110**********02X"），
 *           UserFormatter 原样透传，getUserInfo 契约零改动
 *   密文态  user.real_name_enc / user.id_card_enc —— AES-256-GCM，
 *           base64(nonce 12B | tag 16B | cipher)，密钥来自 .env [realname] AES_KEY
 *   指纹态  user.id_card_hash —— HMAC-SHA256(大写证件号)，一证一号唯一键
 *           （指纹不可逆，可比对可索引，满足「一证一号」而无需解密）
 *
 * 五条红线：
 *   1. 证件必须过 GB11643 校验位 + 日期合法 + 年满 18 周岁
 *   2. 三态必须同时落库，缺一即为事故
 *   3. 一证一号：他人绑定同证拒绝（服务层检查 + uk_id_card_hash 竞态兜底）
 *   4. 已实名禁换证（防实名倒卖）；同用户同证重复提交 = 幂等成功
 *   5. 未实名不可 reveal；密钥缺失 fail-closed 拒绝服务，绝不降级明文
 */
final class RealnameService
{
    /** GB11643 校验位权重（i=1..17 对应系数） */
    private const WEIGHTS = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
    private const CHECK_MAP = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

    /**
     * 提交实名（幂等：同用户同证重复提交返回成功）
     *
     * @return array{real_name: string, id_card: string} 脱敏后的展示值
     */
    public static function submit(int $userId, string $realName, string $idCard): array
    {
        $realName = trim($realName);
        $idCard   = strtoupper(trim($idCard));

        $aesKey  = self::aesKey();
        $hmacKey = (string) env('REALNAME.HMAC_KEY');
        if ($hmacKey === '') {
            // fail-closed：缺指纹密钥等于裸奔，拒绝服务
            throw new RealnameException('实名密钥未配置');
        }

        self::validateName($realName);

        $user = Db::name('user')->where('id', $userId)->field('real_name_authentication,id_card_hash')->find();
        if ($user === null) {
            throw new RealnameException('用户不存在');
        }

        $hash = hash_hmac('sha256', $idCard, $hmacKey);

        // 已实名：同证 = 幂等；换证 = 拒绝（在证件格式校验**之前**判定，防探测已存证号）
        if ((int) ($user['real_name_authentication'] ?? 0) === 1) {
            if (($user['id_card_hash'] ?? '') === $hash) {
                $maskedName = self::maskName($realName);
                return ['real_name' => $maskedName, 'id_card' => (string) Db::name('user')->where('id', $userId)->value('id_card')];
            }
            throw new RealnameException('已完成实名，禁止变更');
        }

        self::validateIdCard($idCard);

        // 一证一号（服务层前置检查；竞态由 uk_id_card_hash 兜底）
        $dup = Db::name('user')->where('id_card_hash', $hash)->where('id', '<>', $userId)->count();
        if ($dup > 0) {
            throw new RealnameException('该身份证已被绑定');
        }

        $maskedName = self::maskName($realName);
        $maskedId   = self::maskIdCard($idCard);

        try {
            Db::name('user')->where('id', $userId)->update([
                'real_name'                => $maskedName,
                'id_card'                  => $maskedId,
                'real_name_enc'            => self::encrypt($realName, $aesKey),
                'id_card_enc'              => self::encrypt($idCard, $aesKey),
                'id_card_hash'             => $hash,
                'real_name_authentication' => 1,
                'real_name_time'           => time(),
            ]);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), '1062') || str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new RealnameException('该身份证已被绑定');
            }
            throw $e;
        }

        return ['real_name' => $maskedName, 'id_card' => $maskedId];
    }

    /** 解密回读（仅限已实名用户；用于内部核验，严禁进公开接口） */
    public static function reveal(int $userId): array
    {
        $row = Db::name('user')->where('id', $userId)->field(
            'real_name_authentication,real_name_enc,id_card_enc'
        )->find();
        if ($row === null || (int) ($row['real_name_authentication'] ?? 0) !== 1) {
            throw new RealnameException('用户未实名');
        }
        if (($row['real_name_enc'] ?? '') === '' || ($row['id_card_enc'] ?? '') === '') {
            throw new RealnameException('密文缺失，数据不完整');
        }

        return [
            'real_name' => self::decrypt((string) $row['real_name_enc']),
            'id_card'   => self::decrypt((string) $row['id_card_enc']),
        ];
    }

    // ==================== 校验 ====================

    private static function validateName(string $name): void
    {
        if ($name === '' || !preg_match('/^[\x{4e00}-\x{9fa5}·]{2,30}$/u', $name)) {
            throw new RealnameException('姓名格式不正确');
        }
    }

    private static function validateIdCard(string $id): void
    {
        if (!preg_match('/^\d{17}[\dX]$/', $id)) {
            throw new RealnameException('身份证号格式不正确');
        }

        // GB11643 校验位
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += (int) $id[$i] * self::WEIGHTS[$i];
        }
        if (self::CHECK_MAP[$sum % 11] !== $id[17]) {
            throw new RealnameException('身份证号校验位不正确');
        }

        // 出生日期合法
        $birth = substr($id, 6, 8);
        $y = (int) substr($birth, 0, 4);
        $m = (int) substr($birth, 4, 2);
        $d = (int) substr($birth, 6, 2);
        if (!checkdate($m, $d, $y) || $y < 1900) {
            throw new RealnameException('身份证出生日期不正确');
        }

        // 年满 18 周岁
        $age = (new \DateTimeImmutable("$y-$m-$d"))->diff(new \DateTimeImmutable('today'))->y;
        if ($age < 18) {
            throw new RealnameException('实名需年满 18 周岁');
        }
    }

    // ==================== 脱敏（契约格式对齐原站实测 "520******838" / "王*绵"） ====================
    // v3.1 改 public：管理端 RealnameController 复用同一契约格式

    public static function maskName(string $name): string
    {
        $len = mb_strlen($name);
        if ($len < 2) {
            return $name;
        }
        return $len === 2
            ? mb_substr($name, 0, 1) . '*'
            : mb_substr($name, 0, 1) . str_repeat('*', $len - 2) . mb_substr($name, -1);
    }

    public static function maskIdCard(string $id): string
    {
        return substr($id, 0, 3) . '******' . substr($id, -3);
    }

    // ==================== AES-256-GCM ====================

    private static function aesKey(): string
    {
        $hex = (string) env('REALNAME.AES_KEY');
        if ($hex === '' || !preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
            // fail-closed：密钥未配置/长度不对，宁可拒绝服务也不明文落库
            throw new RealnameException('实名 AES 密钥未配置或格式错误（需 64 位 hex）');
        }

        return hash('sha256', hex2bin($hex), true);
    }

    private static function encrypt(string $plain, string $key): string
    {
        $nonce = random_bytes(12);
        $tag   = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            throw new RealnameException('加密失败');
        }

        return base64_encode($nonce . $tag . $cipher);
    }

    private static function decrypt(string $blob): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 29) {
            throw new RealnameException('密文格式不正确');
        }
        $plain = openssl_decrypt(
            substr($raw, 28),
            'aes-256-gcm',
            self::aesKey(),
            OPENSSL_RAW_DATA,
            substr($raw, 0, 12),
            substr($raw, 12, 16)
        );
        if ($plain === false) {
            throw new RealnameException('解密失败（密钥不匹配或数据被篡改）');
        }

        return $plain;
    }
}
