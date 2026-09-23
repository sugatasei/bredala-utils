<?php

use Bredala\Utils\ArrayHelper;
use PHPUnit\Framework\TestCase;

class ArrayHelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // toArray
    // -------------------------------------------------------------------------

    public function testToArrayConvertsAnObject()
    {
        $object = new stdClass();
        $object->a = 1;
        $object->b = 'x';

        self::assertSame(['a' => 1, 'b' => 'x'], ArrayHelper::toArray($object));
    }

    public function testToArrayIsRecursive()
    {
        $child = new stdClass();
        $child->c = 1;
        $parent = new stdClass();
        $parent->child = $child;

        self::assertSame(['child' => ['c' => 1]], ArrayHelper::toArray($parent));
    }

    public function testToArrayOnlySeesPublicProperties()
    {
        $object = new class {
            public int $visible = 1;
            protected int $hidden = 2;
            private int $secret = 3;
        };

        self::assertSame(['visible' => 1], ArrayHelper::toArray($object));
    }

    public function testToArrayPassesAnArrayThrough()
    {
        self::assertSame(['a' => 1], ArrayHelper::toArray(['a' => 1]));
    }

    // -------------------------------------------------------------------------
    // equal
    // -------------------------------------------------------------------------

    public function testEqualOnIdenticalArrays()
    {
        self::assertTrue(ArrayHelper::equal([1, 2], [1, 2]));
    }

    public function testEqualOnEmptyArrays()
    {
        self::assertTrue(ArrayHelper::equal([], []));
    }

    public function testEqualIgnoresOrder()
    {
        self::assertTrue(ArrayHelper::equal([1, 2], [2, 1]));
    }

    public function testEqualIgnoresKeys()
    {
        // array_diff() compares values only, so two arrays with the same values
        // under different keys are reported equal.
        self::assertTrue(ArrayHelper::equal(['a' => 1], ['b' => 1]));
    }

    public function testEqualRejectsDifferentCounts()
    {
        self::assertFalse(ArrayHelper::equal([1, 2], [1, 2, 3]));
    }

    public function testEqualRejectsDifferentValues()
    {
        self::assertFalse(ArrayHelper::equal([1, 2], [2, 3]));
    }

    public function testEqualComparesValuesAsStrings()
    {
        // array_diff() casts to string, so 1 and '1' are the same value.
        self::assertTrue(ArrayHelper::equal([1], ['1']));
    }

    public function testEqualIsFooledByDuplicates()
    {
        // The check is 'same count AND no value of $a missing from $b'. Duplicates
        // defeat it in both directions: these pairs hold different multisets yet
        // are reported equal. Use == or a sorted comparison when that matters.
        self::assertTrue(ArrayHelper::equal([1, 1], [1, 2]));
        self::assertTrue(ArrayHelper::equal([1, 1, 2], [1, 2, 2]));
    }

    // -------------------------------------------------------------------------
    // unique
    // -------------------------------------------------------------------------

    public function testUniqueOnAFlatArray()
    {
        self::assertSame([1, 2], ArrayHelper::unique([1, 2, 1, 2]));
    }

    public function testUniqueReindexes()
    {
        self::assertSame([0, 1], array_keys(ArrayHelper::unique(['x' => 1, 'y' => 1, 'z' => 2])));
    }

    public function testUniqueExtractsAColumnWhenGivenAProperty()
    {
        $rows = [['g' => 'a'], ['g' => 'b'], ['g' => 'a']];

        self::assertSame(['a', 'b'], ArrayHelper::unique($rows, 'g'));
    }

    /**
     * @dataProvider droppedProvider
     */
    public function testUniqueDropsNullAndEmptyString(mixed $value)
    {
        self::assertSame(['keep'], ArrayHelper::unique(['keep', $value]));
    }

    public static function droppedProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
        ];
    }

    public function testUniqueDropsEmptyArrays()
    {
        self::assertSame(['keep'], ArrayHelper::unique(['keep', []]));
    }

    public function testUniqueDeduplicatesArraysByContent()
    {
        self::assertSame([[1], [2]], ArrayHelper::unique([[1], [1], [2]]));
    }

    public function testUniqueRaisesNoWarningOnArrayElements()
    {
        $warnings = [];
        set_error_handler(function (int $severity, string $message) use (&$warnings) {
            $warnings[] = $message;
            return true;
        });

        try {
            ArrayHelper::unique(['keep', [1], []]);
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $warnings);
    }

    /**
     * @dataProvider strictProvider
     */
    public function testUniqueComparesStrictly(array $rows, array $expected)
    {
        self::assertSame($expected, ArrayHelper::unique($rows));
    }

    public static function strictProvider(): array
    {
        return [
            'int and numeric string' => [[1, '1'], [1, '1']],
            'int and float' => [[1, 1.0], [1, 1.0]],
            'numeric strings' => [['1e1', '10'], ['1e1', '10']],
            'true and a string' => [[true, 'a', 'b'], [true, 'a', 'b']],
            'zero, false and "0"' => [[0, false, '0'], [0, false, '0']],
            // A loose comparison would drop 0 as a duplicate of null, then drop null.
            'null before zero' => [[null, 0], [0]],
            'null before false' => [[null, false], [false]],
        ];
    }

    public function testUniqueKeepsZeroAndFalse()
    {
        // The filter is a strict !== against null, '' and [] only.
        self::assertSame([0], ArrayHelper::unique([0, 0]));
        self::assertSame([false], ArrayHelper::unique([false]));
    }

    public function testUniqueOnAnEmptyArray()
    {
        self::assertSame([], ArrayHelper::unique([]));
    }

    public function testUniqueOnAMissingColumnReturnsAnEmptyArray()
    {
        self::assertSame([], ArrayHelper::unique([['a' => 1]], 'nope'));
    }

    public function testUniqueTreatsAnEmptyPropertyNameAsAColumn()
    {
        // Only null means "no property": '' and '0' are real column names.
        self::assertSame([], ArrayHelper::unique([1, 2], ''));
        self::assertSame(['a', 'b'], ArrayHelper::unique([['' => 'a'], ['' => 'b']], ''));
        self::assertSame(['x'], ArrayHelper::unique([['x'], ['x']], '0'));
    }

    // -------------------------------------------------------------------------
    // rand
    // -------------------------------------------------------------------------

    public function testRandReturnsAnElementOfTheArray()
    {
        $data = ['a', 'b', 'c'];

        self::assertContains(ArrayHelper::rand($data), $data);
    }

    public function testRandReturnsNullForAnEmptyArray()
    {
        self::assertNull(ArrayHelper::rand([]));
    }

    public function testRandOnASingleElementArray()
    {
        self::assertSame('only', ArrayHelper::rand(['only']));
    }

    public function testRandWorksOnAssociativeArrays()
    {
        $data = ['x' => 1, 'y' => 2];

        self::assertContains(ArrayHelper::rand($data), [1, 2]);
    }

    // -------------------------------------------------------------------------
    // mergeAssoc
    // -------------------------------------------------------------------------

    public function testMergeAssocLastWins()
    {
        self::assertSame(
            ['a' => 2, 'b' => 3],
            ArrayHelper::mergeAssoc(['a' => 1, 'b' => 3], ['a' => 2])
        );
    }

    public function testMergeAssocPreservesNumericKeys()
    {
        // Unlike array_merge(), numeric keys are kept and overwritten rather
        // than appended.
        self::assertSame([5 => 'b'], ArrayHelper::mergeAssoc([5 => 'a'], [5 => 'b']));
    }

    public function testMergeAssocWithNoArguments()
    {
        self::assertSame([], ArrayHelper::mergeAssoc());
    }

    public function testMergeAssocWithASingleArray()
    {
        self::assertSame(['a' => 1], ArrayHelper::mergeAssoc(['a' => 1]));
    }

    public function testMergeAssocIsShallow()
    {
        self::assertSame(
            ['a' => ['y' => 2]],
            ArrayHelper::mergeAssoc(['a' => ['x' => 1]], ['a' => ['y' => 2]])
        );
    }

    public function testMergeAssocKeepsTheFirstOccurrenceOrder()
    {
        self::assertSame(
            ['a', 'b', 'c'],
            array_keys(ArrayHelper::mergeAssoc(['a' => 1, 'b' => 1], ['c' => 1, 'a' => 2]))
        );
    }

    public function testToArrayOfUnencodableDataFailsOnTheReturnType()
    {
        // toArray() is a json_encode/json_decode round trip. json_encode returns
        // false, json_decode(false, true) returns null, and the ': array' return
        // type turns that into a TypeError about toArray() itself rather than a
        // JsonException naming the offending property.
        $object = new stdClass();
        $object->bad = NAN;

        $this->expectException(TypeError::class);
        $this->expectExceptionMessage('must be of type array, null returned');

        @ArrayHelper::toArray($object);
    }
}
