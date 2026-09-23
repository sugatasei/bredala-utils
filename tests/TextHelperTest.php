<?php

use Bredala\Utils\TextHelper;
use PHPUnit\Framework\TestCase;

class TextHelperTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Accents
    // -------------------------------------------------------------------------

    public function testRemoveAccents()
    {
        self::assertSame('Elephant', TextHelper::removeAccents('Éléphant'));
    }

    public function testRemoveAccentsKeepsCase()
    {
        self::assertSame('AEIOU', TextHelper::removeAccents('ÀÉÎÕÜ'));
    }

    public function testRemoveAccentsLeavesPlainAsciiAlone()
    {
        self::assertSame('Hello', TextHelper::removeAccents('Hello'));
    }

    public function testTheDefaultTableIsAll()
    {
        self::assertSame(
            TextHelper::removeAccents('ñ', 'all'),
            TextHelper::removeAccents('ñ')
        );
    }

    public function testTheFrenchTableIsNarrowerThanAll()
    {
        // 'ñ' is not a French accent, so the 'fr' table leaves it untouched.
        self::assertSame('n', TextHelper::removeAccents('ñ', 'all'));
        self::assertSame('ñ', TextHelper::removeAccents('ñ', 'fr'));
    }

    public function testGetAccentsLoadsTheRequestedTable()
    {
        self::assertNotEmpty(TextHelper::getAccents('all'));
        self::assertNotEmpty(TextHelper::getAccents('fr'));
        self::assertGreaterThan(
            count(TextHelper::getAccents('fr')),
            count(TextHelper::getAccents('all'))
        );
    }

    public function testAnUnknownTableSilentlyResolvesToAnEmptyMap()
    {
        // No exception: a typo'd table name just means no substitution happens.
        self::assertSame([], TextHelper::getAccents('nope'));
        self::assertSame('Éléphant', TextHelper::removeAccents('Éléphant', 'nope'));
    }

    public function testGetAccentsIsMemoized()
    {
        self::assertSame(TextHelper::getAccents('fr'), TextHelper::getAccents('fr'));
    }

    // -------------------------------------------------------------------------
    // alias
    // -------------------------------------------------------------------------

    /**
     * @dataProvider aliasProvider
     */
    public function testAlias(string $input, string $expected)
    {
        self::assertSame($expected, TextHelper::alias($input));
    }

    public static function aliasProvider(): array
    {
        return [
            'accents removed' => ['Éléphant Bleu', 'Elephant-Bleu'],
            'case preserved' => ['Hello World', 'Hello-World'],
            'outer whitespace trimmed' => ['  Hello   World  ', 'Hello-World'],
            'punctuation replaced' => ["C'est déjà l'été !", 'C-est-deja-l-ete'],
            'runs collapsed' => ['a--b', 'a-b'],
            'digits kept' => ['Article 42', 'Article-42'],
            'already an alias' => ['hello-world', 'hello-world'],
            'empty' => ['', ''],
            'only separators' => ['---', ''],
        ];
    }

    public function testAliasAcceptsACustomSeparator()
    {
        self::assertSame('Hello_World', TextHelper::alias('Hello World', '_'));
    }

    public function testAliasEscapesRegexMetacharacterSeparators()
    {
        self::assertSame('a.b.c', TextHelper::alias(' a  b c ', '.'));
        self::assertSame('a+b', TextHelper::alias('a b', '+'));
        self::assertSame('a#b', TextHelper::alias('a  b', '#'));
    }

    public function testAliasAcceptsAMultiCharSeparator()
    {
        self::assertSame('a..b', TextHelper::alias('..a  b..', '..'));
    }

    public function testAliasAcceptsAnEmptySeparator()
    {
        self::assertSame('HelloWorld', TextHelper::alias('Hello World!', ''));
    }

    // -------------------------------------------------------------------------
    // Case conversions
    // -------------------------------------------------------------------------

    /**
     * @dataProvider caseProvider
     */
    public function testCaseConversions(string $input, array $expected)
    {
        self::assertSame($expected['kebab'], TextHelper::toKebabCase($input));
        self::assertSame($expected['snake'], TextHelper::toSnakeCase($input));
        self::assertSame($expected['pascal'], TextHelper::toPascalCase($input));
        self::assertSame($expected['camel'], TextHelper::toCamelCase($input));
    }

    public static function caseProvider(): array
    {
        return [
            'two words' => ['Hello World', [
                'kebab' => 'hello-world',
                'snake' => 'hello_world',
                'pascal' => 'HelloWorld',
                'camel' => 'helloWorld',
            ]],
            'accented' => ['Éléphant Bleu', [
                'kebab' => 'elephant-bleu',
                'snake' => 'elephant_bleu',
                'pascal' => 'ElephantBleu',
                'camel' => 'elephantBleu',
            ]],
            'messy punctuation' => ["C'est déjà l'été !", [
                'kebab' => 'c-est-deja-l-ete',
                'snake' => 'c_est_deja_l_ete',
                'pascal' => 'CEstDejaLEte',
                'camel' => 'cEstDejaLEte',
            ]],
            'empty' => ['', [
                'kebab' => '',
                'snake' => '',
                'pascal' => '',
                'camel' => '',
            ]],
        ];
    }

    public function testCaseConversionsGoThroughAliasSoTheyStripEverything()
    {
        // These are not pure case converters: they drop accents and every
        // non-alphanumeric character first.
        self::assertSame('api-v2-users', TextHelper::toKebabCase('API/v2 @users'));
    }

    public function testPascalCaseOfAnAllCapsInputIsUnchanged()
    {
        // ucwords() only uppercases; it never lowercases the rest.
        self::assertSame('AEIOU', TextHelper::toPascalCase('ÀÉÎÕÜ'));
        self::assertSame('aEIOU', TextHelper::toCamelCase('ÀÉÎÕÜ'));
    }

    // -------------------------------------------------------------------------
    // split
    // -------------------------------------------------------------------------

    public function testSplitTrimsAroundTheSeparator()
    {
        self::assertSame(['a', 'b', 'c'], TextHelper::split(',', 'a , b ,c'));
    }

    public function testSplitDropsEmptyElements()
    {
        self::assertSame(['a', 'b'], TextHelper::split(',', 'a,,b'));
    }

    public function testSplitDoesNotTrimTheEndsOfTheWholeString()
    {
        // Despite the docblock's "trim all elements", the pattern only eats
        // whitespace adjacent to a separator -- the first and last element keep
        // their outer padding.
        self::assertSame([' a', 'b', 'c '], TextHelper::split(',', ' a , b , c '));
    }

    /**
     * @dataProvider metacharProvider
     */
    public function testSplitEscapesRegexMetacharacterSeparators(string $separator)
    {
        self::assertSame(['a', 'b'], TextHelper::split($separator, "a{$separator}b"));
    }

    public static function metacharProvider(): array
    {
        return array_map(
            fn(string $char) => [$char],
            ['|' => '|', '.' => '.', '+' => '+', '*' => '*', '?' => '?', '(' => '(', '[' => '[', '^' => '^', '$' => '$']
        );
    }

    public function testSplitOnAnEmptyStringReturnsAnEmptyArray()
    {
        self::assertSame([], TextHelper::split(',', ''));
    }

    public function testSplitWithNoSeparatorFoundReturnsOneElement()
    {
        self::assertSame(['abc'], TextHelper::split(',', 'abc'));
    }

    public function testSplitAcceptsAMultiCharacterSeparator()
    {
        self::assertSame(['a', 'b'], TextHelper::split('--', 'a--b'));
    }

    // -------------------------------------------------------------------------
    // ucfirst / lcfirst
    // -------------------------------------------------------------------------

    public function testUcfirstIsUtf8Aware()
    {
        self::assertSame('École', TextHelper::ucfirst('école'));
    }

    public function testLcfirstIsUtf8Aware()
    {
        // Note the docblock says "last character uppercase" -- it lowercases the
        // first one.
        self::assertSame('école', TextHelper::lcfirst('École'));
    }

    public function testUcfirstAndLcfirstLeaveTheRestAlone()
    {
        self::assertSame('ABC', TextHelper::ucfirst('ABC'));
        self::assertSame('aBC', TextHelper::lcfirst('ABC'));
    }

    public function testUcfirstAndLcfirstOnAnEmptyString()
    {
        self::assertSame('', TextHelper::ucfirst(''));
        self::assertSame('', TextHelper::lcfirst(''));
    }

    // -------------------------------------------------------------------------
    // remove_emoji
    // -------------------------------------------------------------------------

    public function testRemoveEmojiStripsCommonRanges()
    {
        self::assertSame('hi  there', TextHelper::remove_emoji('hi 👋 there'));
    }

    public function testRemoveEmojiLeavesTheSurroundingSpacing()
    {
        // It only deletes the codepoints; it does not tidy up the whitespace.
        self::assertSame('a  b', TextHelper::remove_emoji('a 🚀 b'));
    }

    public function testRemoveEmojiKeepsAccentedText()
    {
        self::assertSame('café', TextHelper::remove_emoji('café'));
    }

    /**
     * @dataProvider emojiProvider
     */
    public function testRemoveEmojiCoversTheDeclaredRanges(string $emoji)
    {
        self::assertSame('', TextHelper::remove_emoji($emoji));
    }

    public static function emojiProvider(): array
    {
        return [
            'emoticons' => ['😀'],
            'symbols and pictographs' => ['🌍'],
            'transport and map' => ['🚀'],
            'miscellaneous symbols' => ['☀'],
            'dingbats' => ['✂'],
        ];
    }

    public function testRemoveEmojiDoesNotCoverEveryEmoji()
    {
        // The five hardcoded ranges miss flags, skin-tone modifiers, supplemental
        // symbols and anything added to Unicode since.
        self::assertSame('🇫🇷', TextHelper::remove_emoji('🇫🇷'));
    }

    // -------------------------------------------------------------------------
    // xss / htmlEncode / htmlDecode
    // -------------------------------------------------------------------------

    /**
     * @dataProvider xssProvider
     */
    public function testXss(mixed $input, string $expected)
    {
        self::assertSame($expected, TextHelper::xss($input));
    }

    public static function xssProvider(): array
    {
        return [
            'null' => [null, ''],
            'plain' => ['abc', 'abc'],
            'tags' => ['<b>x</b>', '&lt;b&gt;x&lt;/b&gt;'],
            'quotes' => ['"\'', '&quot;&apos;'],
            'ampersand' => ['&', '&amp;'],
            'int' => [42, '42'],
            'float' => [1.5, '1.5'],
            'true' => [true, '1'],
            'false' => [false, '0'],
            'array' => [['a' => 1], '{&quot;a&quot;:1}'],
        ];
    }

    public function testHtmlEncodeUsesHtml5Entities()
    {
        self::assertSame('&apos;', TextHelper::htmlEncode("'"));
    }

    public function testHtmlDecodeReversesHtmlEncode()
    {
        $value = '<a href="x">\'&\'</a>';

        self::assertSame($value, TextHelper::htmlDecode(TextHelper::htmlEncode($value)));
    }

    public function testHtmlDecodeHandlesNumericEntities()
    {
        self::assertSame("'", TextHelper::htmlDecode('&#039;'));
    }

    public function testHtmlEncodeSubstitutesInvalidUtf8()
    {
        // ENT_SUBSTITUTE replaces malformed bytes instead of returning ''.
        self::assertSame("\u{FFFD}", TextHelper::htmlEncode("\xC3"));
    }
}
