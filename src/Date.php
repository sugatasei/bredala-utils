<?php

namespace Bredala\Utils;

use InvalidArgumentException;
use JsonSerializable;

/**
 * @property int $seconds Numeric representation of seconds [0,59]
 * @property int $minutes Numeric representation of minutes	[0,59]
 * @property int $hours Numeric representation of hours	[0,23]
 * @property int $mday Numeric representation of the day of the month [1,31]
 * @property int $wday Numeric representation of the day of the week [0=Sunday,6=Saturday]
 * @property int $mon Numeric representation of a month	[1,12]
 * @property int $year A full numeric representation of a year, 4 digits
 * @property int $yday Numeric representation of the day of the year [0,365]
 */
class Date implements JsonSerializable
{
    /**
     * The day constants
     */
    public const int SUNDAY    = 0;
    public const int MONDAY    = 1;
    public const int TUESDAY   = 2;
    public const int WEDNESDAY = 3;
    public const int THURSDAY  = 4;
    public const int FRIDAY    = 5;
    public const int SATURDAY  = 6;

    public const array DAYS = [
        self::SUNDAY    => 'Dimanche',
        self::MONDAY    => 'Lundi',
        self::TUESDAY   => 'Mardi',
        self::WEDNESDAY => 'Mercredi',
        self::THURSDAY  => 'Jeudi',
        self::FRIDAY    => 'Vendredi',
        self::SATURDAY  => 'Samedi'
    ];

    /**
     * The month constants
     */
    public const int JANUARY   = 1;
    public const int FEBRUARY  = 2;
    public const int MARCH     = 3;
    public const int APRIL     = 4;
    public const int MAY       = 5;
    public const int JUNE      = 6;
    public const int JULY      = 7;
    public const int AUGUST    = 8;
    public const int SEPTEMBER = 9;
    public const int OCTOBER   = 10;
    public const int NOVEMBER  = 11;
    public const int DECEMBER  = 12;

    public const array MONTHS = [
        self::JANUARY   => 'Janvier',
        self::FEBRUARY  => 'Février',
        self::MARCH     => 'Mars',
        self::APRIL     => 'Avril',
        self::MAY       => 'Mai',
        self::JUNE      => 'Juin',
        self::JULY      => 'Juillet',
        self::AUGUST    => 'Août',
        self::SEPTEMBER => 'Septembre',
        self::OCTOBER   => 'Octobre',
        self::NOVEMBER  => 'Novembre',
        self::DECEMBER  => 'Décembre'
    ];

    public const array SHORT_MONTHS = [
        self::JANUARY   => 'Janv.',
        self::FEBRUARY  => 'Févr.',
        self::MARCH     => 'Mars',
        self::APRIL     => 'Avril',
        self::MAY       => 'Mai',
        self::JUNE      => 'Juin',
        self::JULY      => 'Juil.',
        self::AUGUST    => 'Août',
        self::SEPTEMBER => 'Sept.',
        self::OCTOBER   => 'Oct.',
        self::NOVEMBER  => 'Nov.',
        self::DECEMBER  => 'Déc.'
    ];

    /**
     * The seasons constants
     */
    public const int SPRING = 0;
    public const int SUMMER = 1;
    public const int AUTUMN = 2;
    public const int WINTER = 3;

    /**
     *  Formats
     */
    public const string DATETIME_SQL = 'Y-m-d H:i:s';
    public const string DATE_SQL     = 'Y-m-d';
    public const string TIME_SQL     = 'H:i:s';

    // -------------------------------------------------------------------------

    /**
     * Properties exposed by __get(), in jsonSerialize() order
     */
    private const array PROPERTIES = ['year', 'mon', 'mday', 'hours', 'minutes', 'seconds', 'wday', 'yday'];

    public private(set) int $timestamp;
    private ?array $date = null;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    /**
     * @throws InvalidArgumentException if the string is not a valid date
     */
    public function __construct(int|string|null $timestamp = null)
    {
        $this->timestamp = self::toTimestamp($timestamp);
    }

    public static function create(int|string|null $timestamp = null): static
    {
        return new static($timestamp);
    }

    /**
     * @throws InvalidArgumentException if the value is not a valid Ymd date
     */
    public static function fromYmd(int $ymd): static
    {
        $y = intdiv($ymd, 10000);
        $m = intdiv($ymd, 100) % 100;
        $d = $ymd % 100;

        if ($ymd < 0 || !checkdate($m, $d, $y)) {
            throw new InvalidArgumentException("Invalid Ymd date: {$ymd}");
        }

        return new static(mktime(0, 0, 0, $m, $d, $y));
    }

    // -------------------------------------------------------------------------
    // Instance
    // -------------------------------------------------------------------------

    public function format(string $format): string
    {
        return self::fr($format, $this->timestamp);
    }

    public function ymd(): int
    {
        return (int) $this->format('Ymd');
    }

    public function toSql(): string
    {
        return self::timestampToSql($this->timestamp);
    }

    public function toIso(): string
    {
        return self::timestampToIso($this->timestamp);
    }

    public function isLeap(): bool
    {
        return self::isLeapYear($this->year);
    }

    public function days(): int
    {
        return self::daysInMonth($this->mon, $this->year);
    }

    /**
     * Modifies the date in place
     *
     * @throws InvalidArgumentException if the modifier is not valid
     */
    public function modify(string $str): static
    {
        $this->timestamp = self::parse($str, $this->timestamp);
        $this->date = null;

        return $this;
    }

    // -------------------------------------------------------------------------
    // Magic methods
    // -------------------------------------------------------------------------

    /**
     * @throws InvalidArgumentException if the property does not exist
     */
    public function __get(string $name): int
    {
        if (!in_array($name, self::PROPERTIES, true)) {
            throw new InvalidArgumentException("Undefined property: " . static::class . "::\${$name}");
        }

        return $this->date()[$name];
    }

    public function __isset(string $name): bool
    {
        return in_array($name, self::PROPERTIES, true);
    }

    public function jsonSerialize(): array
    {
        $json = ['timestamp' => $this->timestamp];

        foreach (self::PROPERTIES as $name) {
            $json[$name] = $this->date()[$name];
        }

        return $json;
    }

    // -------------------------------------------------------------------------
    // Calendar
    // -------------------------------------------------------------------------

    /**
     * Returns if a year is a leap year
     */
    public static function isLeapYear(int $y): bool
    {
        return $y % 400 === 0 || ($y % 100 != 0 && $y % 4 === 0);
    }

    /**
     * Returns the number of days in a month
     */
    public static function daysInMonth(int $m, int $y): int
    {
        return $m === 2 ? 28 + (int)self::isLeapYear($y) : 31 - ($m - 1) % 7 % 2;
    }

    /**
     * Get the beggining date of a season for a given year
     * Returns a UTC timestamp, accurate to a few minutes
     *
     * @throws InvalidArgumentException if the season is not one of the season constants
     */
    public static function seasonDate(int $year, int $season): int
    {
        $y1 = $year / 1000.0;

        $jd = match ($season) {
            self::SPRING => 1721139.2855 + 365.2421376 * $year + 0.067919 * pow($y1, 2) - 0.0027879 * pow($y1, 3),
            self::SUMMER => 1721233.2486 + 365.2417284 * $year - 0.053018 * pow($y1, 2) + 0.009332 * pow($y1, 3),
            self::AUTUMN => 1721325.6978 + 365.2425055 * $year - 0.126689 * pow($y1, 2) + 0.0019401 * pow($y1, 3),
            self::WINTER => 1721414.3920 + 365.2428898 * $year - 0.010965 * pow($y1, 2) - 0.0084885 * pow($y1, 3),
            default => throw new InvalidArgumentException("Invalid season: {$season}")
        };

        $rad = M_PI / 180;
        $continue = true;

        while ($continue) {
            $t = ($jd - 2415020) / 36525;

            $l = 279.69668 + (36000.76892 * $t) + 0.0003025 * pow($t, 2);

            $m = (358.47583 + (35999.04975 * $t) - 0.00015 * pow($t, 2) - 0.0000033 * pow($t, 3)) / 360;
            $m = ($m - floor($m)) * 360;

            $c = (1.91946 - 0.004789 * $t - 0.000014 * pow($t, 2)) * sin($m * $rad) + (0.020094 - 0.0001 * $t) * sin($m * 2 * $rad) + 0.000293 * sin($m * 3 * $rad);

            $ome = (259.18 - 1934.142 * $t) / 360;
            $ome = ($ome - floor($ome)) * 360 * $rad;

            $ap = ($l + $c - 0.00569 - 0.00479 * sin($ome)) / 360;
            $ap = ($ap - floor($ap)) * 360;

            $test = $jd;
            $cor = 58 * sin(($season * 90 - $ap) * $rad);
            $jd = $jd + $cor;

            $continue = abs($jd - $test) > 0.00001;
        }

        $jd = $jd + 0.5;
        $z = floor($jd);
        if ($z < 2299161) {
            $a = $z;
        } else {
            $x = floor(($z - 1867216.25) / 36524.25);
            $a = $z + 1 + $x - floor($x / 4);
        }

        $b = $a + 1524;
        $c = floor(($b - 122.1) / 365.25);
        $d = floor(365.25 * $c);
        $e = floor(($b - $d) / 30.6001);
        $f = $jd - $z;
        $daydec = $b - $d - floor(30.6001 * $e) + $f;

        $month  = $e < 13.5 ? $e - 1 : $e - 13;
        $frac   = $daydec - floor($daydec);
        $day    = floor($daydec);
        $hour   = floor($frac * 24);
        $minute = floor(($frac * 24 - $hour) * 60);
        $second = floor((($frac * 24 - $hour) * 60 - $minute) * 60);

        return gmmktime((int) $hour, (int) $minute, (int) $second, (int) $month, (int) $day, $year);
    }

    // -------------------------------------------------------------------------
    // Conversions
    // -------------------------------------------------------------------------

    /**
     * @throws InvalidArgumentException if the value is not a valid date
     */
    public static function sqlToTimestamp(string $value): int
    {
        return self::parse($value);
    }

    /**
     * @throws InvalidArgumentException if the value is not a valid date
     */
    public static function isoToTimestamp(string $value): int
    {
        return self::sqlToTimestamp($value);
    }

    public static function timestampToIso(int $value): string
    {
        return date('c', $value);
    }

    public static function timestampToSql(int $value): string
    {
        return date(self::DATETIME_SQL, $value);
    }

    /**
     * @throws InvalidArgumentException if the value is not a valid date
     */
    public static function sqlToIso(string $value): string
    {
        return self::timestampToIso(self::sqlToTimestamp($value));
    }

    /**
     * @throws InvalidArgumentException if the value is not a valid date
     */
    public static function isoToSql(string $value): string
    {
        return self::timestampToSql(self::isoToTimestamp($value));
    }

    public static function timestampToYmd(?int $ts = null): int
    {
        return (int) self::fr('Ymd', $ts ?? time());
    }

    // -------------------------------------------------------------------------
    // French
    // -------------------------------------------------------------------------

    /**
     * date french version
     *
     * @throws InvalidArgumentException if the string is not a valid date
     */
    public static function fr(string $format, int|string|null $timestamp = null): string
    {
        $dt = date($format, self::toTimestamp($timestamp));

        if (preg_match("/[^\\\][DlFM]/", ' ' . $format)) {
            $dt = self::toFr($dt);
        }

        return $dt;
    }

    public static function toFr(string $date): string
    {
        return strtr($date, [
            'Wednesday' => 'Mercredi',
            'September' => 'Septembre',
            'December'  => 'Décembre',
            'February'  => 'Février',
            'Thursday'  => 'Jeudi',
            'November'  => 'Novembre',
            'Saturday'  => 'Samedi',
            'January'   => 'Janvier',
            'Tuesday'   => 'Mardi',
            'October'   => 'Octobre',
            'August'    => 'Août',
            'Sunday'    => 'Dimanche',
            'Monday'    => 'Lundi',
            'Friday'    => 'Vendredi',
            'April'     => 'Avril',
            'March'     => 'Mars',
            'July'      => 'Juillet',
            'June'      => 'Juin',
            'Aug'       => 'Août',
            'Apr'       => 'Avril',
            'Sun'       => 'Dim.',
            'Dec'       => 'Déc.',
            'Feb'       => 'Févr.',
            'Jan'       => 'Janv.',
            'Thu'       => 'Jeu.',
            'Jul'       => 'Juil.',
            'Jun'       => 'Juin',
            'Mon'       => 'Lun.',
            'May'       => 'Mai',
            'Tue'       => 'Mar.',
            'Mar'       => 'Mars',
            'Wed'       => 'Mer.',
            'Nov'       => 'Nov.',
            'Oct'       => 'Oct.',
            'Sat'       => 'Sam.',
            'Sep'       => 'Sept.',
            'Fri'       => 'Ven.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    private function date(): array
    {
        if ($this->date === null) {
            $this->date = getdate($this->timestamp);
        }

        return $this->date;
    }

    private static function toTimestamp(int|string|null $value): int
    {
        return match (true) {
            $value === null  => time(),
            is_int($value)   => $value,
            default          => self::parse($value),
        };
    }

    /**
     * strtotime() that rejects unparsable and out-of-range dates (e.g. 2026-02-31)
     *
     * @throws InvalidArgumentException
     */
    private static function parse(string $value, ?int $base = null): int
    {
        $timestamp = strtotime($value, $base ?? time());

        if ($timestamp === false || date_parse($value)['warning_count'] > 0) {
            throw new InvalidArgumentException("Invalid date: \"{$value}\"");
        }

        return $timestamp;
    }
}
