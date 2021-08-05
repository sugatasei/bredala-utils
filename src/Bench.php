<?php

namespace Bredala\Utils;

class Bench
{

    protected static $marks = [];

    /**
     * Mark
     *
     * @param string $label
     */
    public static function mark(string $label)
    {
        static::$marks[$label][] = microtime(true);
    }

    /**
     * @param string $label
     */
    public static function remove(string $label)
    {
        if (isset(static::$marks[$label])) {
            unset(static::$marks[$label]);
        }
    }

    /**
     * Return one
     *
     * @param string $label
     * @param int $unit
     * @return float
     */
    public static function time(string $label, int $unit = 1): array
    {
        if (empty(static::$marks[$label])) {
            return [];
        }

        return static::diff(static::$marks[$label], $unit);
    }

    /**
     * List all
     *
     * @param int $unit
     * @return array
     */
    public static function times(int $unit = 1): array
    {
        $res = [];
        foreach (self::$marks as $label => $times) {
            $res[$label] = static::diff($times, $unit);
        }

        return $res;
    }

    /**
     * @param float $start
     * @param float $stop
     * @param int $unit
     * @return float
     */
    protected static function diff(array $values, int $unit): array
    {
        $res =  [];
        $len = count($values);

        if ($len > 1) {
            for ($i = 1; $i < $len; $i++) {
                $res[] = ($values[$i] - $values[$i - 1]) * $unit;
            }
        }

        return $res;
    }
}
