<?php

namespace Bredala\Utils;

/**
 * Counter
 */
class Counter
{
    private static $counters = [];

    // -------------------------------------------------------------------------

    /**
     * @param string $name
     * @param integer $value
     * @return integer
     */
    public static function set(string $name, int $value = 0): int
    {
        self::$counters[$name] = $value;
        return $value;
    }

    /**
     * @param string $name
     * @return integer
     */
    public static function get(string $name): int
    {
        return self::$counters[$name] ?? 0;
    }

    // -------------------------------------------------------------------------

    /**
     * @param string $name
     * @return integer
     */
    public static function reset(string $name): int
    {
        return self::set($name, 0);
    }

    /**
     * @param string $name
     * @return integer
     */
    public static function increment(string $name, int $value = 1): int
    {
        return self::set($name, self::get($name) + $value);
    }

    /**
     * @param string $name
     * @return integer
     */
    public static function decrement(string $name, int $value = 1): int
    {
        return self::set($name, self::get($name) - $value);
    }

    // -------------------------------------------------------------------------
}
