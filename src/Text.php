<?php

namespace Bredala\Utils;

/**
 * Help to work with texts
 */
class Text
{
    private static $accents = [
        "æ|ǽ" => "ae",
        "œ" => "oe",
        "Ä|À|Á|Â|Ã|Ä|Å|Ǻ|Ā|Ă|Ą|Ǎ" => "A",
        "ä|à|á|â|ã|å|ǻ|ā|ă|ą|ǎ|ª" => "a",
        "Ç|Ć|Ĉ|Ċ|Č" => "C",
        "ç|ć|ĉ|ċ|č" => "c",
        "Ð|Ď|Đ" => "D",
        "ð|ď|đ" => "d",
        "È|É|Ê|Ë|Ē|Ĕ|Ė|Ę|Ě" => "E",
        "è|é|ê|ë|ē|ĕ|ė|ę|ě" => "e",
        "Ĝ|Ğ|Ġ|Ģ" => "G",
        "ĝ|ğ|ġ|ģ" => "g",
        "Ĥ|Ħ" => "H",
        "ĥ|ħ" => "h",
        "Ì|Í|Î|Ï|Ĩ|Ī|Ĭ|Ǐ|Į|İ" => "I",
        "ì|í|î|ï|ĩ|ī|ĭ|ǐ|į|ı" => "i",
        "Ĵ" => "J",
        "ĵ" => "j",
        "Ķ" => "K",
        "ķ" => "k",
        "Ĺ|Ļ|Ľ|Ŀ|Ł" => "L",
        "ĺ|ļ|ľ|ŀ|ł" => "l",
        "Ñ|Ń|Ņ|Ň" => "N",
        "ñ|ń|ņ|ň|ŉ" => "n",
        "Ö|Ò|Ó|Ô|Õ|Ō|Ŏ|Ǒ|Ő|Ơ|Ø|Ǿ" => "O",
        "ö|ò|ó|ô|õ|ō|ŏ|ǒ|ő|ơ|ø|ǿ|º" => "o",
        "Ŕ|Ŗ|Ř" => "R",
        "ŕ|ŗ|ř" => "r",
        "Ś|Ŝ|Ş|Š" => "S",
        "ś|ŝ|ş|š|ſ" => "s",
        "Ţ|Ť|Ŧ" => "T",
        "ţ|ť|ŧ" => "t",
        "Ü|Ù|Ú|Û|Ũ|Ū|Ŭ|Ů|Ű|Ų|Ư|Ǔ|Ǖ|Ǘ|Ǚ|Ǜ" => "U",
        "ü|ù|ú|û|ũ|ū|ŭ|ů|ű|ų|ư|ǔ|ǖ|ǘ|ǚ|ǜ" => "u",
        "Ý|Ÿ|Ŷ" => "Y",
        "ý|ÿ|ŷ" => "y",
        "Ŵ" => "W",
        "ŵ" => "w",
        "Ź|Ż|Ž" => "Z",
        "ź|ż|ž" => "z",
        "Æ|Ǽ" => "AE",
        "ß" => "ss",
        "Ĳ" => "IJ",
        "ĳ" => "ij",
        "Œ" => "OE",
        "ƒ" => "f",
        "’" => "'"
    ];

    private static $accentTable = null;

    // -------------------------------------------------------------------------

    /**
     * Convert Accented Characters to ASCII
     *
     * @param string $str
     * @return string
     */
    public static function convertAccents($str)
    {
        $accents = static::getAccentTable();
        return preg_replace($accents['search'], $accents['replace'], $str);
    }

    protected static function getAccentTable(): array
    {
        if (static::$accentTable === null) {

            static::$accentTable = [
                'search' => [],
                'replace' => [],
            ];

            foreach (static::$accents as $k => $v) {
                // Flag u => UTF-8
                static::$accentTable['search'][] = '#' . $k . '#u';
                static::$accentTable['replace'][] = $v;
            }
        }

        return static::$accentTable;
    }

    // -------------------------------------------------------------------------

    /**
     * @param string $str
     * @param string $char
     * @return string
     */
    public static function normalize(string $str, string $char = "-"): string
    {
        $str = self::convertAccents($str);
        $str = preg_replace("#[^a-z0-9]#i", $char, $str);
        $str = trim($str, $char);
        $str = preg_replace("#{$char}+#", $char, $str);
        return $str;
    }

    /**
     * @param string $str
     * @return string
     */
    public static function toKebabCase(string $str): string
    {
        $str = self::normalize($str, '-');
        $str = mb_strtolower($str);
        return $str;
    }

    /**
     * @param string $str
     * @return string
     */
    public static function toSnakeCase(string $str): string
    {
        $str = self::normalize($str, '_');
        $str = mb_strtolower($str);
        return $str;
    }

    /**
     * @param string $str
     * @return string
     */
    public static function toPascalCase(string $str): string
    {
        $char = '-';
        $str = self::normalize($str, $char);
        $str = ucwords($str, $char);
        return str_replace($char, '', $str);
    }

    /**
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
     * SQL Like operator in PHP.
     * Returns TRUE if match else FALSE.
     *
     * @param   string $pattern
     * @param   string $subject
     * @return  bool
     */
    public static function like($pattern, $subject)
    {
        $pattern = str_replace('%', '.*', preg_quote($pattern));
        return (bool) preg_match("/^{$pattern}$/i", $subject);
    }

    /**
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
     * @param string $str
     * @return string
     */
    public static function lcfirst(string $str): string
    {
        if ($str) {
            $str[0] = mb_strtolower($str[0]);
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

    // -------------------------------------------------------------------------
}

/* End of file */
