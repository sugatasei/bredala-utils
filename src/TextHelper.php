<?php

namespace Bredala\Utils;

/**
 * Help to work with texts
 */
class TextHelper
{
    private static array $accents = [];

    // -------------------------------------------------------------------------

    /**
     * Remove accents
     *
     * @param string $str
     * @return string
     */
    public static function removeAccents(string $str): string
    {
        return strtr($str, self::getAccents());
    }

    /**
     * Get accents table
     *
     * @return array
     */
    public static function getAccents(): array
    {
        if (!static::$accents) {
            static::$accents = require __DIR__ . '/accents.php';
        }

        return static::$accents;
    }

    // -------------------------------------------------------------------------

    /**
     * Get an alias from a string that keeps only alphanumeric chars
     *
     * @param string $str Input string
     * @param string $char character used to replace all non alphanumeric chars
     * @return string
     */
    public static function alias(string $str, string $char = "-"): string
    {
        $str = self::removeAccents($str);
        $str = preg_replace("#[^a-z0-9]#i", $char, $str);
        $str = trim($str, $char);
        $str = preg_replace("#{$char}+#", $char, $str);
        return $str;
    }

    /**
     * Transform a string into kebab-case
     *
     * @param string $str
     * @return string
     */
    public static function toKebabCase(string $str): string
    {
        $str = self::alias($str, '-');
        $str = mb_strtolower($str);
        return $str;
    }

    /**
     * Transform a string into snake_case
     *
     * @param string $str
     * @return string
     */
    public static function toSnakeCase(string $str): string
    {
        $str = self::alias($str, '_');
        $str = mb_strtolower($str);
        return $str;
    }

    /**
     * Transform a string into PascalCase
     *
     * @param string $str
     * @return string
     */
    public static function toPascalCase(string $str): string
    {
        $char = '-';
        $str = self::alias($str, $char);
        $str = ucwords($str, $char);
        return str_replace($char, '', $str);
    }

    /**
     * Transform a string into camelCase
     *
     * @param string $str
     * @return string
     */
    public static function toCamelCase(string $str): string
    {
        return lcfirst(self::toPascalCase($str));
    }

    // -------------------------------------------------------------------------

    /**
     * Explode a string and trim all elements
     *
     * @param string $char
     * @param string $str
     * @return array
     */
    public static function split($char, $str)
    {
        if ($char && mb_strpos('#!^$()[]{}|?+*.\\', $char) !== false) {
            $char = '\\' . $char;
        }

        return preg_split("#\s*{$char}\s*#", $str, -1, PREG_SPLIT_NO_EMPTY);
    }

    // -------------------------------------------------------------------------

    /**
     * Make a string's first character uppercase. UTF-8 compatible.
     *
     * @param string $str
     * @return string
     */
    public static function ucfirst(string $str): string
    {
        if ($str) {
            $str = mb_strtoupper(mb_substr($str, 0, 1)) . mb_substr($str, 1);
        }
        return $str;
    }

    /**
     * Make a string's last character uppercase. UTF-8 compatible.
     *
     * @param string $str
     * @return string
     */
    public static function lcfirst(string $str): string
    {
        if ($str) {
            $str = mb_strtolower(mb_substr($str, 0, 1)) . mb_substr($str, 1);
        }
        return $str;
    }

    /**
     * Remove emoji
     *
     * @param string $string
     * @return string
     */
    public static function remove_emoji(string $string): string
    {
        // Match Emoticons
        $regex_emoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clear_string = preg_replace($regex_emoticons, '', $string);

        // Match Miscellaneous Symbols and Pictographs
        $regex_symbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clear_string = preg_replace($regex_symbols, '', $clear_string);

        // Match Transport And Map Symbols
        $regex_transport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clear_string = preg_replace($regex_transport, '', $clear_string);

        // Match Miscellaneous Symbols
        $regex_misc = '/[\x{2600}-\x{26FF}]/u';
        $clear_string = preg_replace($regex_misc, '', $clear_string);

        // Match Dingbats
        $regex_dingbats = '/[\x{2700}-\x{27BF}]/u';
        $clear_string = preg_replace($regex_dingbats, '', $clear_string);

        return $clear_string;
    }

    /**
     * Protect HTML attribute values
     *
     * @param mixed $value
     * @return string
     */
    public static function xss(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return self::htmlEncode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return self::htmlEncode(json_encode($value));
    }

    /**
     * Convert special characters to HTML entities
     *
     * @param string $value
     * @return string
     */
    public static function htmlEncode(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    /**
     * Convert HTML entities to their corresponding characters
     *
     * @param string $value
     * @return string
     */
    public static function htmlDecode(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    // -------------------------------------------------------------------------
}
