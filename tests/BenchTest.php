<?php

use Bredala\Utils\Bench;
use PHPUnit\Framework\TestCase;

class BenchTest extends TestCase
{
    /**
     * Bench is global static state, so each test uses its own label and cleans up.
     */
    private string $label;

    protected function setUp(): void
    {
        $this->label = $this->getName();
    }

    protected function tearDown(): void
    {
        Bench::remove($this->label);
    }

    public function testTimeIsEmptyForAnUnknownLabel()
    {
        self::assertSame([], Bench::time('no-such-label'));
    }

    public function testASingleMarkProducesNoInterval()
    {
        // Intervals are differences between consecutive marks, so one mark alone
        // yields nothing.
        Bench::mark($this->label);

        self::assertSame([], Bench::time($this->label));
    }

    public function testTwoMarksProduceOneInterval()
    {
        Bench::mark($this->label);
        Bench::mark($this->label);

        self::assertCount(1, Bench::time($this->label));
    }

    public function testNMarksProduceNMinusOneIntervals()
    {
        for ($i = 0; $i < 5; $i++) {
            Bench::mark($this->label);
        }

        self::assertCount(4, Bench::time($this->label));
    }

    public function testIntervalsAreNonNegativeFloats()
    {
        Bench::mark($this->label);
        Bench::mark($this->label);

        foreach (Bench::time($this->label) as $interval) {
            self::assertIsFloat($interval);
            self::assertGreaterThanOrEqual(0.0, $interval);
        }
    }

    public function testTheUnitIsAMultiplier()
    {
        Bench::mark($this->label);
        Bench::mark($this->label);

        $seconds = Bench::time($this->label);
        $milliseconds = Bench::time($this->label, 1000);

        self::assertSame($seconds[0] * 1000, $milliseconds[0]);
    }

    public function testTheDefaultUnitIsOne()
    {
        Bench::mark($this->label);
        Bench::mark($this->label);

        self::assertSame(Bench::time($this->label, 1), Bench::time($this->label));
    }

    public function testAZeroUnitFlattensEveryInterval()
    {
        Bench::mark($this->label);
        Bench::mark($this->label);

        self::assertSame([0.0], Bench::time($this->label, 0));
    }

    public function testRemoveDropsTheLabel()
    {
        Bench::mark($this->label);
        Bench::mark($this->label);
        Bench::remove($this->label);

        self::assertSame([], Bench::time($this->label));
    }

    public function testRemoveOnAnUnknownLabelIsANoop()
    {
        Bench::remove('no-such-label');

        self::assertSame([], Bench::time('no-such-label'));
    }

    public function testTimesCoversEveryLabel()
    {
        Bench::mark($this->label . ':a');
        Bench::mark($this->label . ':a');
        Bench::mark($this->label . ':b');
        Bench::mark($this->label . ':b');

        $times = Bench::times();

        try {
            self::assertArrayHasKey($this->label . ':a', $times);
            self::assertArrayHasKey($this->label . ':b', $times);
            self::assertCount(1, $times[$this->label . ':a']);
        } finally {
            Bench::remove($this->label . ':a');
            Bench::remove($this->label . ':b');
        }
    }

    public function testTimesIncludesLabelsWithASingleMarkAsEmptyLists()
    {
        Bench::mark($this->label);

        self::assertSame([], Bench::times()[$this->label]);
    }

    public function testMarksAccumulateAcrossCalls()
    {
        Bench::mark($this->label);
        Bench::mark($this->label);
        self::assertCount(1, Bench::time($this->label));

        Bench::mark($this->label);
        self::assertCount(2, Bench::time($this->label));
    }

    public function testTimeDoesNotConsumeTheMarks()
    {
        Bench::mark($this->label);
        Bench::mark($this->label);

        self::assertSame(Bench::time($this->label), Bench::time($this->label));
    }
}
