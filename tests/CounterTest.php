<?php

use Bredala\Utils\Counter;
use PHPUnit\Framework\TestCase;

class CounterTest extends TestCase
{
    /**
     * Counter is global static state with no flush, so every test gets its own name.
     */
    private function name(): string
    {
        return $this->getName();
    }

    public function testGetIsZeroForAnUnknownCounter()
    {
        self::assertSame(0, Counter::get($this->name()));
    }

    public function testSetReturnsTheValue()
    {
        self::assertSame(5, Counter::set($this->name(), 5));
        self::assertSame(5, Counter::get($this->name()));
    }

    public function testSetDefaultsToZero()
    {
        self::assertSame(0, Counter::set($this->name()));
    }

    public function testSetAcceptsNegatives()
    {
        self::assertSame(-3, Counter::set($this->name(), -3));
    }

    public function testIncrementFromScratch()
    {
        self::assertSame(1, Counter::increment($this->name()));
    }

    public function testIncrementAccumulates()
    {
        Counter::increment($this->name());
        Counter::increment($this->name());

        self::assertSame(2, Counter::get($this->name()));
    }

    public function testIncrementByAStep()
    {
        self::assertSame(10, Counter::increment($this->name(), 10));
    }

    public function testDecrementFromScratchGoesNegative()
    {
        // There is no floor at zero.
        self::assertSame(-1, Counter::decrement($this->name()));
    }

    public function testDecrementByAStep()
    {
        Counter::set($this->name(), 10);

        self::assertSame(7, Counter::decrement($this->name(), 3));
    }

    public function testIncrementByANegativeStepDecrements()
    {
        self::assertSame(-5, Counter::increment($this->name(), -5));
    }

    public function testResetSetsBackToZeroAndReturnsIt()
    {
        Counter::set($this->name(), 42);

        self::assertSame(0, Counter::reset($this->name()));
        self::assertSame(0, Counter::get($this->name()));
    }

    public function testResetOnAnUnknownCounter()
    {
        self::assertSame(0, Counter::reset($this->name()));
    }

    public function testCountersAreIndependent()
    {
        Counter::set($this->name() . ':a', 1);
        Counter::set($this->name() . ':b', 2);

        self::assertSame(1, Counter::get($this->name() . ':a'));
        self::assertSame(2, Counter::get($this->name() . ':b'));
    }

    public function testStateIsGlobalAndSharedAcrossCallSites()
    {
        Counter::set($this->name(), 7);

        $read = fn(string $n) => Counter::get($n);

        self::assertSame(7, $read($this->name()));
    }

    public function testResetDoesNotRemoveTheCounter()
    {
        // reset() is set(0), not a delete -- there is no del()/flush().
        Counter::reset($this->name());

        self::assertSame(1, Counter::increment($this->name()));
    }
}
