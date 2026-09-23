<?php

namespace Bredala\Utils;

/**
 * Crypto
 */
class Crypto
{
    private const ENCODER = 'aes-256-gcm';

    /**
     * Available algorythm
     *
     * @var string
     */
    private static $algos = [
        32  => 'md5',
        40  => 'sha1',
        64  => 'sha256',
        128 => 'sha512',
    ];

    // -------------------------------------------------------------------------

    /**
     * Hash a string to a specific length
     *
     * @param string $string
     * @param int $length
     * @return string
     */
    public static function hash(string $string, int $length = 40): string
    {
        if (!isset(self::$algos[$length])) {
            $length = 40;
        }

        return hash(self::$algos[$length], $string, FALSE) ?: '';
    }

    /**
     * Generate a salt string
     *
     * @param int $length
     * @return string
     */
    public static function salt(int $length = 40): string
    {
        return self::hash(random_bytes($length), $length);
    }

    // -------------------------------------------------------------------------

    /**
     * Generates cryptographically secure pseudo-random string
     *
     * @param integer $length
     * @return string
     */
    public static function random(int $length = 40): string
    {
        $bytes = random_bytes(ceil($length / 2));
        return mb_substr(bin2hex($bytes), 0, $length);
    }

    /**
     * Generate a UUID (v7)
     * 36 characters : 32 hexadecimal numbers and 4 dashes
     * 48-bit Unix timestamp (ms) + 74 random bits, lexicographically sortable
     * Exemple : 0192a3b4-5c6d-7e8f-9a0b-1c2d3e4f5a6b
     * https://www.rfc-editor.org/rfc/rfc9562
     *
     * @return string 36 characters
     */
    public static function uuid(): string
    {
        $time = str_pad(dechex((int) (microtime(true) * 1000)), 12, '0', STR_PAD_LEFT);
        $rand = random_bytes(10);
        $rand[0] = chr((ord($rand[0]) & 0x0f) | 0x70); // version 7
        $rand[2] = chr((ord($rand[2]) & 0x3f) | 0x80); // variant RFC 9562

        $hex = $time . bin2hex($rand);

        return vsprintf('%s-%s-%s-%s-%s', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20),
        ]);
    }

    /**
     * Generate a cryptographically secure numeric OTP
     *
     * @param integer $len 1 to 18 digits
     * @return string
     */
    public static function otp(int $len = 6): string
    {
        if ($len < 1 || $len > 18) {
            throw new \ValueError('OTP length must be between 1 and 18');
        }

        return str_pad((string) random_int(0, 10 ** $len - 1), $len, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------

    /**
     * Return a password hash
     */
    public static function passwordHash(string $password, ?int $cost = null): string
    {
        $options = [];

        if ($cost) {
            $options['cost'] = $cost;
        }

        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    /**
     * Verify if a password and a hash corresponds
     */
    public static function passwordVerify(string $password, string $hash): bool
    {
        if (!$password || !$hash) {
            return false;
        }

        return password_verify($password, $hash);
    }

    // -------------------------------------------------------------------------

    public static function encrypt(string $str, string $key): string
    {
        $key = hash('sha256', $key, true);            // clé de 32 octets
        $iv  = random_bytes(openssl_cipher_iv_length(self::ENCODER));
        $ssl = openssl_encrypt($str, self::ENCODER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ssl);
    }

    public static function decrypt(string $str, string $key): string
    {
        $raw = base64_decode($str, true);
        $ivLen = openssl_cipher_iv_length(self::ENCODER);
        if ($raw === false || strlen($raw) < $ivLen + 16) {
            return '';
        }
        $key = hash('sha256', $key, true);
        $iv  = substr($raw, 0, $ivLen);
        $tag = substr($raw, $ivLen, 16);
        $dec = openssl_decrypt(substr($raw, $ivLen + 16), self::ENCODER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $dec === false ? '' : $dec;
    }

    // -------------------------------------------------------------------------
}
