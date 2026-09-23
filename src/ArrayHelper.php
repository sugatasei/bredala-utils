<?php

namespace Bredala\Utils;

class ArrayHelper
{
    /**
     * Convertit récursivement un objet en tableau
     *
     * @param object $object
     * @return array
     */
    public static function toArray($object): array
    {
        return json_decode(json_encode($object), true);
    }

    public static function equal(array $a, array $b): bool
    {
        return count($a) === count($b) && !array_diff($a, $b);
    }

    /**
     * Return the unique values, optionally from a single column, compared strictly
     * null, '' and [] are dropped
     */
    public static function unique(array $rows, ?string $property = null): array
    {
        if ($property !== null) {
            $rows = array_column($rows, $property);
        }

        $out = [];
        foreach ($rows as $value) {
            if ($value !== null && $value !== '' && $value !== [] && !in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }

    public static function rand(array $data)
    {
        if (!$data) {
            return null;
        }

        $key = array_rand($data, 1);
        return $data[$key];
    }

    public static function mergeAssoc(array ...$arrays): array
    {
        $out = [];
        foreach ($arrays as $array) {
            foreach ($array as $k => $v) {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}
