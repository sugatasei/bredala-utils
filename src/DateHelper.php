<?php

namespace Bredala\Utils;

class DateHelper
{
    /**
     * Days
     */
    const SUNDAY    = 0;
    const MONDAY    = 1;
    const TUESDAY   = 2;
    const WEDNESDAY = 3;
    const THURSDAY  = 4;
    const FRIDAY    = 5;
    const SATURDAY  = 6;

    /**
     * Months
     */
    const JANUARY   = 1;
    const FEBRUARY  = 2;
    const MARCH     = 3;
    const APRIL     = 4;
    const MAY       = 5;
    const JUNE      = 6;
    const JULY      = 7;
    const AUGUST    = 8;
    const SEPTEMBER = 9;
    const OCTOBER   = 10;
    const NOVEMBER  = 11;
    const DECEMBER  = 12;

    /**
     * Seasons
     */
    const SPRING = 0;
    const SUMMER = 1;
    const AUTUMN = 2;
    const WINTER = 3;

    /**
     * Formats
     */
    const DATETIME_ISO = 'c';
    const DATETIME_SQL = 'Y-m-d H:i:s';
    const DATE_SQL     = 'Y-m-d';
    const TIME_SQL     = 'H:i:s';

    // -------------------------------------------------------------------------

    /**
     * Returns if a year is a leap year
     *
     * @param int $y
     * @return boolean
     */
    public static function isLeapYear($y)
    {
        return $y % 400 === 0 || ($y % 100 !== 0 && $y % 4 === 0);
    }

    // -------------------------------------------------------------------------

    /**
     * Returns the number of days in a month
     *
     * @param int $m
     * @param int $y
     * @return int
     */
    public static function daysInMonth($m, $y)
    {
        return $m === 2 ? 28 + (int)self::isLeapYear($y) : 31 - ($m - 1) % 7 % 2;
    }


    /**
     * Get the beggining date of a season for a given year
     *
     * @param integer $annee
     * @param integer $saison
     * @return integer timestamp
     */
    public static function dateSeason(int $annee, int $saison): int
    {
        $TEST = (float)0;
        $M = (float)0;
        $Y1 = (float)($annee / 1000);

        switch ($saison) {
            case 0:
                $JD = (float)(1721139.2855 + 365.2421376 * $annee + 0.067919 * pow($Y1, 2) - 0.0027879 * pow($Y1, 3));
                break;
            case 1:
                $JD = (float)(1721233.2486 + 365.2417284 * $annee - 0.053018 * pow($Y1, 2) + 0.009332 * pow($Y1, 3));
                break;
            case 2:
                $JD = (float)(1721325.6978 + 365.2425055 * $annee - 0.126689 * pow($Y1, 2) + 0.0019401 * pow($Y1, 3));
                break;
            case 3:
                $JD = (float)(1721414.392 + 365.2428898 * $annee - 0.010965 * pow($Y1, 2) - 0.0084885 * pow($Y1, 3));
                break;
        }

        $RAD = (float)(M_PI / 180);

        $encore = true;

        while ($encore) {
            $T = ($JD - 2415020) / 36525;


            $L = 279.69668 + (36000.76892 * $T) + 0.0003025 * pow($T, 2);


            $M = (358.47583 + (35999.04975 * $T) - 0.00015 * pow($T, 2) - 0.0000033 * pow($T, 3)) / 360;
            $M = ($M - floor($M)) * 360;


            $C = (1.91946 - 0.004789 * $T - 0.000014 * pow($T, 2)) * sin($M * $RAD) + (0.020094 - 0.0001 * $T) * sin($M * 2) + (0.000293 * sin($M * 3));

            $OME = (259.18 - 1934.142 * $T) / 360;
            $OME = ($OME - floor($OME)) * 360 * $RAD;

            $AP = ($L + $C - 0.00569 - 0.00479 * sin($OME)) / 360;
            $AP = ($AP - floor($AP)) * 360;


            $TEST = $JD;
            $COR = 58 * sin(($saison * 90 - $AP) * $RAD);
            $JD = $JD + $COR;

            $encore = ($JD - $TEST) > 0.001;
        }

        $JD = $JD + 0.5;
        $Z = floor($JD);
        if ($Z < 2299161) {
            $A = $Z;
        } else {
            $X = floor(($Z - 1867216.25) / 36524.25);
            $A = $Z + 1 + $X - floor($X / 4);
        }

        $B = $A + 1524;
        $C = floor(($B - 122.1) / 365.25);
        $D = floor(365.25 * $C);
        $E = floor(($B - $D) / 30.6001);
        $F = $JD - $Z;
        $DayDec = $B - $D - floor(30.6001 * $E) + $F;

        $MN = $E < 13.5 ? $E - 1 : $E - 13;
        $Day = floor($DayDec);

        return mktime(0, 0, 0, $MN, $Day, $annee);
    }

    /**
     * date french version
     *
     * @param string $format
     * @param integer|null $timestamp
     * @return string
     */
    public static function fr(string $format, ?int $timestamp = null): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $dt = date($format, $timestamp);

        if (preg_match("/[^\\\][DlFM]/", ' ' . $format)) {
            $dt = self::toFr($dt);
        }

        return $dt;
    }

    /**
     * @param string $date
     * @return string
     */
    public static function toFr(string $date): string
    {
        return strtr($date, [
            'Wednesday' => 'Mercredi',
            'September' => 'Septembre',
            'December' => 'Décembre',
            'February' => 'Février',
            'Thursday' => 'Jeudi',
            'November' => 'Novembre',
            'Saturday' => 'Samedi',
            'January' => 'Janvier',
            'Tuesday' => 'Mardi',
            'October' => 'Octobre',
            'August' => 'Août',
            'Sunday' => 'Dimanche',
            'Monday' => 'Lundi',
            'Friday' => 'Vendredi',
            'April' => 'Avril',
            'March' => 'Mars',
            'July' => 'Juillet',
            'June' => 'Juin',
            'Aug' => 'Août',
            'Apr' => 'Avril',
            'Sun' => 'Dim.',
            'Dec' => 'Déc.',
            'Feb' => 'Févr.',
            'Jan' => 'Janv.',
            'Thu' => 'Jeu.',
            'Jul' => 'Juil.',
            'Jun' => 'Juin',
            'Mon' => 'Lun.',
            'May' => 'Mai',
            'Tue' => 'Mar.',
            'Mar' => 'Mars',
            'Wed' => 'Mer.',
            'Nov' => 'Nov.',
            'Oct' => 'Oct.',
            'Sat' => 'Sam.',
            'Sep' => 'Sept.',
            'Fri' => 'Ven.',
        ]);
    }

    // -------------------------------------------------------------------------
}
