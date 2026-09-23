<?php

use Bredala\Utils\Date;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
    /**
     * Wednesday 23 September 2026, 14:30. mktime() is timezone-dependent, and
     * tests/bootstrap.php pins Europe/Paris.
     */
    private function timestamp(): int
    {
        return mktime(14, 30, 0, 9, 23, 2026);
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testWeekdayConstantsMatchPhpsNumbering()
    {
        // 0 = Sunday, like date('w').
        self::assertSame(0, Date::SUNDAY);
        self::assertSame(6, Date::SATURDAY);
        self::assertSame((int) date('w', $this->timestamp()), Date::WEDNESDAY);
    }

    public function testMonthConstantsAreOneBased()
    {
        self::assertSame(1, Date::JANUARY);
        self::assertSame(12, Date::DECEMBER);
        self::assertSame((int) date('n', $this->timestamp()), Date::SEPTEMBER);
    }

    public function testNameConstantsAreIndexedLikeTheNumericConstants()
    {
        self::assertSame('Mercredi', Date::DAYS[Date::WEDNESDAY]);
        self::assertSame('Dimanche', Date::DAYS[Date::SUNDAY]);
        self::assertSame('Septembre', Date::MONTHS[Date::SEPTEMBER]);
        self::assertSame('Sept.', Date::SHORT_MONTHS[Date::SEPTEMBER]);
        self::assertCount(7, Date::DAYS);
        self::assertCount(12, Date::MONTHS);
        self::assertCount(12, Date::SHORT_MONTHS);
    }

    public function testSeasonConstantsAreZeroBased()
    {
        self::assertSame(0, Date::SPRING);
        self::assertSame(3, Date::WINTER);
    }

    public function testFormatConstants()
    {
        self::assertSame('Y-m-d H:i:s', Date::DATETIME_SQL);
        self::assertSame('Y-m-d', Date::DATE_SQL);
        self::assertSame('H:i:s', Date::TIME_SQL);
    }

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function testConstructFromATimestamp()
    {
        self::assertSame($this->timestamp(), (new Date($this->timestamp()))->timestamp);
    }

    public function testConstructFromAString()
    {
        self::assertSame($this->timestamp(), (new Date('2026-09-23 14:30:00'))->timestamp);
    }

    public function testConstructDefaultsToNow()
    {
        $before = time();
        $timestamp = (new Date())->timestamp;

        self::assertGreaterThanOrEqual($before, $timestamp);
        self::assertLessThanOrEqual(time(), $timestamp);
    }

    public function testCreateIsTheStaticEquivalent()
    {
        self::assertInstanceOf(Date::class, Date::create());
        self::assertSame($this->timestamp(), Date::create('2026-09-23 14:30:00')->timestamp);
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testConstructRejectsAnInvalidString(string $value)
    {
        $this->expectException(InvalidArgumentException::class);

        new Date($value);
    }

    public static function invalidDateProvider(): array
    {
        return [
            'garbage' => ['not a date'],
            'empty' => [''],
            'month 13' => ['2026-13-01'],
            'hour 25' => ['2026-09-23 25:00:00'],
            // strtotime() alone would silently roll this over to 3 March.
            'february 31' => ['2026-02-31'],
        ];
    }

    public function testFromYmd()
    {
        self::assertSame('2024-02-29 00:00:00', Date::fromYmd(20240229)->toSql());
    }

    /**
     * @dataProvider invalidYmdProvider
     */
    public function testFromYmdRejectsAnInvalidDate(int $ymd)
    {
        $this->expectException(InvalidArgumentException::class);

        Date::fromYmd($ymd);
    }

    public static function invalidYmdProvider(): array
    {
        return [
            'month 13' => [20261301],
            'day 99' => [20260999],
            'not a leap year' => [20230229],
            'negative' => [-20260101],
            'too short' => [2026],
        ];
    }

    // -------------------------------------------------------------------------
    // Instance
    // -------------------------------------------------------------------------

    public function testFormatIsFrench()
    {
        self::assertSame('Mercredi 23 Septembre 2026', Date::create($this->timestamp())->format('l j F Y'));
    }

    public function testYmd()
    {
        self::assertSame(20260923, Date::create($this->timestamp())->ymd());
        self::assertSame(20260923, Date::fromYmd(20260923)->ymd());
    }

    public function testToSqlAndToIso()
    {
        $date = Date::create($this->timestamp());

        self::assertSame('2026-09-23 14:30:00', $date->toSql());
        self::assertSame('2026-09-23T14:30:00+02:00', $date->toIso());
    }

    public function testIsLeapAndDays()
    {
        self::assertTrue(Date::create('2024-02-10')->isLeap());
        self::assertSame(29, Date::create('2024-02-10')->days());
        self::assertFalse(Date::create('2025-02-10')->isLeap());
        self::assertSame(30, Date::create('2025-04-10')->days());
    }

    public function testModifyChangesTheDateInPlace()
    {
        $date = Date::create('2026-09-23 14:30:00');

        self::assertSame($date, $date->modify('+1 day'));
        self::assertSame('2026-09-24 14:30:00', $date->toSql());
    }

    public function testModifyRefreshesTheDateParts()
    {
        $date = Date::create('2026-12-31');
        self::assertSame(2026, $date->year);

        $date->modify('+1 day');

        self::assertSame(2027, $date->year);
        self::assertSame(1, $date->mon);
    }

    public function testModifyRejectsAnInvalidModifierAndKeepsTheDate()
    {
        $date = Date::create('2026-09-23 14:30:00');

        try {
            $date->modify('whenever');
            self::fail('No exception thrown');
        } catch (InvalidArgumentException) {
            self::assertSame('2026-09-23 14:30:00', $date->toSql());
        }
    }

    public function testTimestampIsReadOnlyFromOutside()
    {
        $date = Date::create($this->timestamp());

        $this->expectException(Error::class);

        $date->timestamp = 0;
    }

    // -------------------------------------------------------------------------
    // Magic methods
    // -------------------------------------------------------------------------

    public function testDatePartsAreExposedAsProperties()
    {
        $date = Date::create('2026-09-23 14:30:15');

        self::assertSame(2026, $date->year);
        self::assertSame(9, $date->mon);
        self::assertSame(23, $date->mday);
        self::assertSame(14, $date->hours);
        self::assertSame(30, $date->minutes);
        self::assertSame(15, $date->seconds);
        self::assertSame(Date::WEDNESDAY, $date->wday);
        self::assertSame(265, $date->yday);
    }

    public function testAnUnknownPropertyThrows()
    {
        $this->expectException(InvalidArgumentException::class);

        Date::create()->foo;
    }

    public function testTheCacheIsNotAPublicProperty()
    {
        $this->expectException(InvalidArgumentException::class);

        Date::create()->date;
    }

    public function testIsset()
    {
        $date = Date::create();

        self::assertTrue(isset($date->year));
        self::assertFalse(isset($date->foo));
        self::assertSame('default', $date->foo ?? 'default');
    }

    public function testJsonSerialize()
    {
        self::assertSame(
            '{"timestamp":' . $this->timestamp() . ',"year":2026,"mon":9,"mday":23,"hours":14,"minutes":30,"seconds":0,"wday":3,"yday":265}',
            json_encode(Date::create($this->timestamp()))
        );
    }

    // -------------------------------------------------------------------------
    // isLeapYear
    // -------------------------------------------------------------------------

    /**
     * @dataProvider leapYearProvider
     */
    public function testIsLeapYear(int $year, bool $expected)
    {
        self::assertSame($expected, Date::isLeapYear($year));
    }

    public static function leapYearProvider(): array
    {
        return [
            'divisible by 4' => [2024, true],
            'not divisible by 4' => [2025, false],
            'divisible by 100 only' => [1900, false],
            'divisible by 400' => [2000, true],
            'another 400' => [2400, true],
            'another 100' => [2100, false],
            'year zero' => [0, true],
        ];
    }

    public function testIsLeapYearAgreesWithPhp()
    {
        foreach ([1900, 2000, 2020, 2023, 2024, 2100] as $year) {
            self::assertSame(
                (bool) date('L', mktime(0, 0, 0, 1, 1, $year)),
                Date::isLeapYear($year),
                "year {$year}"
            );
        }
    }

    // -------------------------------------------------------------------------
    // daysInMonth
    // -------------------------------------------------------------------------

    /**
     * @dataProvider daysInMonthProvider
     */
    public function testDaysInMonth(int $month, int $year, int $expected)
    {
        self::assertSame($expected, Date::daysInMonth($month, $year));
    }

    public static function daysInMonthProvider(): array
    {
        return [
            'january' => [1, 2025, 31],
            'february common' => [2, 2025, 28],
            'february leap' => [2, 2024, 29],
            'february 1900' => [2, 1900, 28],
            'february 2000' => [2, 2000, 29],
            'april' => [4, 2025, 30],
            'december' => [12, 2025, 31],
        ];
    }

    public function testDaysInMonthAgreesWithPhpForEveryMonth()
    {
        foreach ([2024, 2025] as $year) {
            for ($month = 1; $month <= 12; $month++) {
                self::assertSame(
                    (int) date('t', mktime(0, 0, 0, $month, 1, $year)),
                    Date::daysInMonth($month, $year),
                    "{$year}-{$month}"
                );
            }
        }
    }

    public function testDaysInMonthCoercesANumericString()
    {
        // Typed int parameters without strict_types: '2' becomes 2.
        self::assertSame(29, Date::daysInMonth('2', 2024));
    }

    public function testDaysInMonthRejectsANonNumericString()
    {
        $this->expectException(TypeError::class);

        Date::daysInMonth('feb', 2024);
    }

    // -------------------------------------------------------------------------
    // seasonDate
    // -------------------------------------------------------------------------

    /**
     * @dataProvider seasonProvider
     */
    public function testSeasonDateReturnsTheAstronomicalBoundary(int $season, string $expected)
    {
        self::assertSame($expected, gmdate('Y-m-d', Date::seasonDate(2026, $season)));
    }

    public static function seasonProvider(): array
    {
        return [
            'spring equinox' => [Date::SPRING, '2026-03-20'],
            'summer solstice' => [Date::SUMMER, '2026-06-21'],
            'autumn equinox' => [Date::AUTUMN, '2026-09-23'],
            'winter solstice' => [Date::WINTER, '2026-12-21'],
        ];
    }

    /**
     * @dataProvider ephemerisProvider
     */
    public function testSeasonDateIsAccurateToTenMinutes(int $year, int $season, string $utc)
    {
        $expected = (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->getTimestamp();

        self::assertEqualsWithDelta($expected, Date::seasonDate($year, $season), 600);
    }

    public static function ephemerisProvider(): array
    {
        // Published instants, UTC.
        return [
            '2024 spring' => [2024, Date::SPRING, '2024-03-20 03:06'],
            '2024 summer' => [2024, Date::SUMMER, '2024-06-20 20:51'],
            '2024 autumn' => [2024, Date::AUTUMN, '2024-09-22 12:44'],
            '2024 winter' => [2024, Date::WINTER, '2024-12-21 09:20'],
            '2026 spring' => [2026, Date::SPRING, '2026-03-20 14:46'],
            '2026 summer' => [2026, Date::SUMMER, '2026-06-21 08:24'],
            '2026 autumn' => [2026, Date::AUTUMN, '2026-09-23 00:05'],
            '2026 winter' => [2026, Date::WINTER, '2026-12-21 20:50'],
        ];
    }

    public function testSeasonDateDoesNotDependOnTheTimezone()
    {
        $previous = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $utc = Date::seasonDate(2026, Date::SPRING);
            date_default_timezone_set('Pacific/Auckland');
            $auckland = Date::seasonDate(2026, Date::SPRING);
        } finally {
            date_default_timezone_set($previous);
        }

        self::assertSame($utc, $auckland);
    }

    public function testSeasonDateRejectsAnUnknownSeason()
    {
        $this->expectException(InvalidArgumentException::class);

        Date::seasonDate(2026, 4);
    }

    public function testSeasonsAreOrderedWithinAYear()
    {
        $previous = 0;

        foreach ([Date::SPRING, Date::SUMMER, Date::AUTUMN, Date::WINTER] as $season) {
            $timestamp = Date::seasonDate(2026, $season);
            self::assertGreaterThan($previous, $timestamp);
            $previous = $timestamp;
        }
    }

    public function testSeasonDateWorksAcrossYears()
    {
        self::assertSame('2030-03-20', gmdate('Y-m-d', Date::seasonDate(2030, Date::SPRING)));
    }

    // -------------------------------------------------------------------------
    // Conversions
    // -------------------------------------------------------------------------

    public function testTimestampConversions()
    {
        self::assertSame($this->timestamp(), Date::sqlToTimestamp('2026-09-23 14:30:00'));
        self::assertSame($this->timestamp(), Date::isoToTimestamp('2026-09-23T14:30:00+02:00'));
        self::assertSame('2026-09-23 14:30:00', Date::timestampToSql($this->timestamp()));
        self::assertSame('2026-09-23T14:30:00+02:00', Date::timestampToIso($this->timestamp()));
    }

    public function testSqlIsoConversions()
    {
        self::assertSame('2026-09-23T14:30:00+02:00', Date::sqlToIso('2026-09-23 14:30:00'));
        self::assertSame('2026-09-23 14:30:00', Date::isoToSql('2026-09-23T12:30:00+00:00'));
    }

    /**
     * @dataProvider stringConverterProvider
     */
    public function testStringConvertersRejectAnInvalidDate(string $method)
    {
        $this->expectException(InvalidArgumentException::class);

        Date::$method('2026-02-31');
    }

    public static function stringConverterProvider(): array
    {
        return [
            'sqlToTimestamp' => ['sqlToTimestamp'],
            'isoToTimestamp' => ['isoToTimestamp'],
            'sqlToIso' => ['sqlToIso'],
            'isoToSql' => ['isoToSql'],
        ];
    }

    public function testTimestampToYmd()
    {
        self::assertSame(20260923, Date::timestampToYmd($this->timestamp()));
        self::assertSame((int) date('Ymd'), Date::timestampToYmd());
    }

    // -------------------------------------------------------------------------
    // fr
    // -------------------------------------------------------------------------

    public function testFrTranslatesLongDayAndMonthNames()
    {
        self::assertSame('Mercredi 23 Septembre 2026', Date::fr('l j F Y', $this->timestamp()));
    }

    public function testFrTranslatesAbbreviatedNames()
    {
        self::assertSame('Mer. 23 Sept. 2026', Date::fr('D j M Y', $this->timestamp()));
    }

    public function testFrLeavesAFormatWithoutNamesAlone()
    {
        self::assertSame('2026-09-23', Date::fr('Y-m-d', $this->timestamp()));
    }

    public function testFrMatchesDateForANonTranslatedFormat()
    {
        $format = Date::DATETIME_SQL;

        self::assertSame(
            date($format, $this->timestamp()),
            Date::fr($format, $this->timestamp())
        );
    }

    public function testFrDefaultsToNow()
    {
        self::assertSame(date('Y'), Date::fr('Y'));
    }

    public function testFrAcceptsADateString()
    {
        self::assertSame('Mercredi 23 Septembre 2026', Date::fr('l j F Y', '2026-09-23'));
    }

    public function testFrRejectsAnInvalidDateString()
    {
        $this->expectException(InvalidArgumentException::class);

        Date::fr('Y', 'not a date');
    }

    public function testFrTranslationIsTriggeredByTheFormatNotTheOutput()
    {
        // The check is a regex on the FORMAT for an unescaped D, l, F or M. A
        // format without one is returned untouched even if it could contain a
        // month name -- and a literal 'M' in an escaped sequence still triggers it.
        self::assertSame('2026', Date::fr('Y', $this->timestamp()));
    }

    public function testFrIsUnaffectedByTheAmbientLocale()
    {
        // date() always emits English names, so the setlocale() in the bootstrap
        // does not change what toFr() has to match.
        $previous = setlocale(LC_TIME, '0');
        setlocale(LC_TIME, 'C');

        try {
            self::assertSame('Mercredi', Date::fr('l', $this->timestamp()));
        } finally {
            setlocale(LC_TIME, $previous);
        }
    }

    // -------------------------------------------------------------------------
    // toFr
    // -------------------------------------------------------------------------

    public function testToFrTranslatesAFullDate()
    {
        self::assertSame(
            'Mercredi 23 Septembre 2026',
            Date::toFr('Wednesday 23 September 2026')
        );
    }

    /**
     * @dataProvider longNameProvider
     */
    public function testToFrTranslatesEveryLongName(string $english, string $french)
    {
        self::assertSame($french, Date::toFr($english));
    }

    public static function longNameProvider(): array
    {
        return [
            'Monday' => ['Monday', 'Lundi'],
            'Tuesday' => ['Tuesday', 'Mardi'],
            'Wednesday' => ['Wednesday', 'Mercredi'],
            'Thursday' => ['Thursday', 'Jeudi'],
            'Friday' => ['Friday', 'Vendredi'],
            'Saturday' => ['Saturday', 'Samedi'],
            'Sunday' => ['Sunday', 'Dimanche'],
            'January' => ['January', 'Janvier'],
            'February' => ['February', 'Février'],
            'March' => ['March', 'Mars'],
            'April' => ['April', 'Avril'],
            'May' => ['May', 'Mai'],
            'June' => ['June', 'Juin'],
            'July' => ['July', 'Juillet'],
            'August' => ['August', 'Août'],
            'September' => ['September', 'Septembre'],
            'October' => ['October', 'Octobre'],
            'November' => ['November', 'Novembre'],
            'December' => ['December', 'Décembre'],
        ];
    }

    /**
     * @dataProvider shortNameProvider
     */
    public function testToFrTranslatesAbbreviations(string $english, string $french)
    {
        self::assertSame($french, Date::toFr($english));
    }

    public static function shortNameProvider(): array
    {
        return [
            'Mon' => ['Mon', 'Lun.'],
            'Wed' => ['Wed', 'Mer.'],
            'Sun' => ['Sun', 'Dim.'],
            'Jan' => ['Jan', 'Janv.'],
            'Feb' => ['Feb', 'Févr.'],
            'Aug' => ['Aug', 'Août'],
            'Sep' => ['Sep', 'Sept.'],
            'Dec' => ['Dec', 'Déc.'],
        ];
    }

    public function testToFrLeavesUnknownTextAlone()
    {
        self::assertSame('2026-09-23', Date::toFr('2026-09-23'));
    }

    public function testToFrTranslatesEveryOccurrence()
    {
        self::assertSame('Lundi et Lundi', Date::toFr('Monday et Monday'));
    }

    public function testToFrMatchesLongNamesBeforeTheirAbbreviations()
    {
        // 'March' must not be turned into 'Mars' + leftover 'ch' by the 'Mar'
        // entry: strtr() prefers the longest key.
        self::assertSame('Mars', Date::toFr('March'));
        self::assertSame('Mai', Date::toFr('May'));
    }

    public function testToFrIsNotWordBounded()
    {
        // strtr() is a plain substring replacement with no word boundaries, so a
        // name embedded in another word is translated too -- don't run it over
        // arbitrary prose, only over date() output.
        self::assertSame('Marsing', Date::toFr('Marching'));
        self::assertSame('Août ust', Date::toFr('Aug ust'));
    }
}
