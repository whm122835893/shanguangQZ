<?php
declare(strict_types=1);

namespace app\service\admin;

/**
 * Totp —— RFC 6238 TOTP（2FA），纯 PHP 实现
 *
 * @package app\service\admin
 */
final class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;

    /** 生成 Base32 密钥（160 bit） */
    public static function generateSecret(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret   = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    /** 当前（或指定窗口）的验证码 */
    public static function code(string $base32Secret, ?int $at = null): string
    {
        $key    = self::base32Decode($base32Secret);
        $counter = intdiv($at ?? time(), self::PERIOD);
        $binCounter = pack('N2', 0, $counter);
        $hash   = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value  = (
            (ord($hash[$offset]) & 0x7F) << 24
            | (ord($hash[$offset + 1]) & 0xFF) << 16
            | (ord($hash[$offset + 2]) & 0xFF) << 8
            | (ord($hash[$offset + 3]) & 0xFF)
        );
        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** 校验：允许 ±1 窗口时钟漂移 */
    public static function verify(string $base32Secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $now = time();
        foreach ([-1, 0, 1] as $drift) {
            if (hash_equals(self::code($base32Secret, $now + $drift * self::PERIOD), $code)) {
                return true;
            }
        }
        return false;
    }

    /** otpauth URI，前端转二维码 */
    public static function otpauthUrl(string $secret, string $account): string
    {
        return 'otpauth://totp/' . rawurlencode('司南后台:' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode('司南后台') . '&period=30&digits=6';
    }

    private static function base32Decode(string $str): string
    {
        $str  = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $str) ?? '');
        $map  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        for ($i = 0, $n = strlen($str); $i < $n; $i++) {
            $bits .= str_pad(decbin(strpos($map, $str[$i])), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
