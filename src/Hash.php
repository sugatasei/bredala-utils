<?php

namespace Bredala\Utils;

/**
 * Hash
 *
 */
class Hash
{
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
    public static function make(string $string, int $length = 40): string
    {
        if (!isset(self::$algos[$length])) {
            $length = 40;
        }

        return hash(self::$algos[$length], $string, FALSE) ?: '';
    }

    // -------------------------------------------------------------------------

    /**
     * Generate a salt string
     *
     * @param int $length
     * @return string
     */
    public static function salt(int $length = 40)
    {
        return self::make(random_bytes($length), $length);
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
     * Generate a UUID (v4)
     * 36 characters : 32 hexadecimal numbers and 4 dashes
     * Exemple :  110e8400-e29b-11d4-a716-446655440000
     * http://www.ietf.org/rfc/rfc4122.txt
     *
     * @return string
     */
    public static function uuid(): string
    {
        $uid = uniqid();
        $rand = self::random(19);

        return vsprintf('%s-%s-%s%s-%s-%s', [
            mb_substr($uid, 0, 8),
            mb_substr($uid, 8, 4),
            mb_substr($uid, 12),
            mb_substr($rand, 0, 3),
            mb_substr($rand, 3, 4),
            mb_substr($rand, 7)
        ]);
    }

    /**
     * Generate an OTP number
     *
     * @param integer $len
     * @return string
     */
    public static function otp(int $len = 6): string
    {
        $rand = mt_rand(1, 10 ** $len - 1);
        return str_pad((string) $rand, $len, '0', STR_PAD_LEFT);
    }

    // -------------------------------------------------------------------------
}
