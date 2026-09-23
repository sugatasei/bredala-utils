<?php

use Bredala\Utils\IP;
use PHPUnit\Framework\TestCase;

class IPTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Round trips
    // -------------------------------------------------------------------------

    /**
     * @dataProvider v4Provider
     */
    public function testIPv4FromString(string $address, string $hex)
    {
        $ip = IP::fromString($address);

        self::assertTrue($ip->isIPv4());
        self::assertFalse($ip->isIPv6());
        self::assertSame($address, $ip->toString());
        self::assertSame($hex, $ip->toHex());
    }

    public static function v4Provider(): array
    {
        return [
            'private' => ['192.168.1.1', 'c0a80101'],
            'zero' => ['0.0.0.0', '0'],
            'broadcast' => ['255.255.255.255', 'ffffffff'],
            'loopback' => ['127.0.0.1', '7f000001'],
            'leading zeros stripped' => ['10.0.0.1', 'a000001'],
        ];
    }

    /**
     * @dataProvider v6Provider
     */
    public function testIPv6FromString(string $address, string $hex)
    {
        $ip = IP::fromString($address);

        self::assertTrue($ip->isIPv6());
        self::assertFalse($ip->isIPv4());
        self::assertSame($address, $ip->toString());
        self::assertSame($hex, $ip->toHex());
    }

    public static function v6Provider(): array
    {
        return [
            'loopback' => ['::1', '00000000000000000000000000000001'],
            'documentation' => ['2001:db8::1', '20010db8000000000000000000000001'],
            'link local' => ['fe80::1', 'fe800000000000000000000000000001'],
        ];
    }

    /**
     * @dataProvider allAddressProvider
     */
    public function testEveryRepresentationRoundTrips(string $address)
    {
        $ip = IP::fromString($address);

        self::assertSame($address, IP::fromBin($ip->toBin())->toString(), 'via bin');
        self::assertSame($address, IP::fromHex($ip->toHex())->toString(), 'via hex');
        self::assertSame($address, IP::fromString($ip->toString())->toString(), 'via str');
    }

    public static function allAddressProvider(): array
    {
        return [
            'v4 private' => ['192.168.1.1'],
            'v4 loopback' => ['127.0.0.1'],
            'v4 broadcast' => ['255.255.255.255'],
            'v4 leading zeros' => ['10.0.0.1'],
            'v6 loopback' => ['::1'],
            'v6 documentation' => ['2001:db8::1'],
            'v6 link local' => ['fe80::1'],
        ];
    }

    public function testTheVersionIsPreservedAcrossRoundTrips()
    {
        self::assertTrue(IP::fromHex(IP::fromString('::1')->toHex())->isIPv6());
        self::assertTrue(IP::fromHex(IP::fromString('127.0.0.1')->toHex())->isIPv4());
    }

    public function testTheConstructorTakesTheSourceFormatExplicitly()
    {
        self::assertSame('192.168.1.1', (new IP('c0a80101', 'hex'))->toString());
        self::assertSame('192.168.1.1', (new IP('192.168.1.1', 'str'))->toString());
    }

    public function testAnUnknownSourceFormatLeavesTheDefaults()
    {
        $ip = new IP('192.168.1.1', 'nope');

        self::assertSame('0.0.0.0', $ip->toString());
        self::assertSame('0', $ip->toBin());
    }

    // -------------------------------------------------------------------------
    // Padding
    // -------------------------------------------------------------------------

    public function testBinAndHexAreNotZeroPadded()
    {
        // Leading zeros are stripped, so the string length varies with the value.
        // Do not use these forms for fixed-width storage or lexical sorting --
        // pad them yourself with str_pad(..., STR_PAD_LEFT).
        self::assertSame(32, mb_strlen(IP::fromString('192.168.1.1')->toBin()));
        self::assertSame(28, mb_strlen(IP::fromString('10.0.0.1')->toBin()));
        self::assertSame('0', IP::fromString('0.0.0.0')->toBin());

        self::assertSame(8, mb_strlen(IP::fromString('192.168.1.1')->toHex()));
        self::assertSame(7, mb_strlen(IP::fromString('10.0.0.1')->toHex()));
    }

    public function testIPv6HexIsAlwaysFullWidth()
    {
        // Unlike v4, the v6 hex form keeps its 32 characters even for '::1'.
        self::assertSame(32, mb_strlen(IP::fromString('::1')->toHex()));
    }

    public function testSortingTheUnpaddedFormsIsWrong()
    {
        // 0.255.0.1 is numerically the smaller address, but its hex form is one
        // character shorter, so a lexical sort puts it after 1.0.0.1.
        $low = IP::fromString('0.255.0.1')->toHex();
        $high = IP::fromString('1.0.0.1')->toHex();

        self::assertSame('ff0001', $low);
        self::assertSame('1000001', $high);
        self::assertLessThan(hexdec($high), hexdec($low), 'numerically ordered');
        self::assertGreaterThan(0, strcmp($low, $high), 'but lexically reversed');
    }

    // -------------------------------------------------------------------------
    // Invalid input
    // -------------------------------------------------------------------------

    /**
     * @dataProvider invalidProvider
     */
    public function testInvalidInputFallsBackToTheZeroAddress(string $address)
    {
        // No exception: an unparseable address silently becomes 0.0.0.0.
        $ip = IP::fromString($address);

        self::assertSame('0.0.0.0', $ip->toString());
        self::assertSame('0', $ip->toBin());
        self::assertSame('0', $ip->toHex());
        self::assertTrue($ip->isIPv4());
    }

    public static function invalidProvider(): array
    {
        return [
            'word' => ['nope'],
            'empty' => [''],
            'out of range octet' => ['999.1.1.1'],
            'incomplete' => ['192.168.1'],
            'too many octets' => ['1.2.3.4.5'],
            'trailing dot' => ['192.168.1.1.'],
            'cidr' => ['192.168.1.0/24'],
        ];
    }

    public function testTheZeroAddressIsIndistinguishableFromAFailure()
    {
        // A legitimate 0.0.0.0 and a parse failure produce the same object, so
        // validate with isIPStr() before constructing if you need to tell them apart.
        self::assertSame(
            IP::fromString('0.0.0.0')->toString(),
            IP::fromString('garbage')->toString()
        );
        self::assertTrue(IP::isIPStr('0.0.0.0'));
        self::assertFalse(IP::isIPStr('garbage'));
    }

    // -------------------------------------------------------------------------
    // Predicates
    // -------------------------------------------------------------------------

    public function testIsIPStr()
    {
        self::assertTrue(IP::isIPStr('192.168.1.1'));
        self::assertTrue(IP::isIPStr('::1'));
        self::assertFalse(IP::isIPStr('nope'));
        self::assertFalse(IP::isIPStr(''));
    }

    public function testIsIPv6Str()
    {
        self::assertTrue(IP::isIPv6Str('::1'));
        self::assertTrue(IP::isIPv6Str('2001:db8::1'));
        self::assertFalse(IP::isIPv6Str('192.168.1.1'));
    }

    public function testIsIPHex()
    {
        self::assertTrue(IP::isIPHex('c0a80101'));
        self::assertTrue(IP::isIPHex('00000000000000000000000000000001'));
        self::assertFalse(IP::isIPHex('zzzz'));
    }

    public function testIsIPv6Hex()
    {
        self::assertTrue(IP::isIPv6Hex('00000000000000000000000000000001'));
        self::assertFalse(IP::isIPv6Hex('c0a80101'));
    }

    public function testIsIPBin()
    {
        self::assertTrue(IP::isIPBin('11000000101010000000000100000001'));
        self::assertFalse(IP::isIPBin('12'));
    }

    public function testIsIPv6Bin()
    {
        self::assertTrue(IP::isIPv6Bin(str_repeat('1', 128)));
        self::assertFalse(IP::isIPv6Bin('11000000101010000000000100000001'));
    }

    // -------------------------------------------------------------------------
    // Static converters
    // -------------------------------------------------------------------------

    public function testBinToHex()
    {
        self::assertSame('c0a80101', IP::binToHex('11000000101010000000000100000001'));
    }

    public function testBinToStr()
    {
        self::assertSame('192.168.1.1', IP::binToStr('11000000101010000000000100000001'));
    }

    public function testHexToBin()
    {
        self::assertSame('11000000101010000000000100000001', IP::hexToBin('c0a80101'));
    }

    public function testStrToBin()
    {
        self::assertSame('11000000101010000000000100000001', IP::strToBin('192.168.1.1'));
    }

    public function testTheConvertersNeedTheV6FlagForIPv6()
    {
        $hex = IP::fromString('2001:db8::1')->toHex();

        self::assertSame('2001:db8::1', IP::binToStr(IP::hexToBin($hex, true), true));
    }

    public function testTheConvertersDefaultToIPv4AndCrashOnV6Data()
    {
        // BUG: binToStr() without $v6 = true hands a 128-bit value to long2ip(),
        // which raises a TypeError. The $v6 flag is not optional in practice --
        // carry the version alongside the stored value.
        $bin = IP::hexToBin(IP::fromString('2001:db8::1')->toHex(), true);

        $this->expectException(TypeError::class);

        IP::binToStr($bin);
    }

    public function testStrToBinAndBinToStrAreInverses()
    {
        foreach (['192.168.1.1', '127.0.0.1', '255.255.255.255'] as $address) {
            self::assertSame($address, IP::binToStr(IP::strToBin($address)));
        }
    }

    public function testBinToHexAndHexToBinAreInverses()
    {
        $bin = IP::strToBin('192.168.1.1');

        self::assertSame($bin, IP::hexToBin(IP::binToHex($bin)));
    }

    public function testTheEmptyStringValidatesAsBinAndHex()
    {
        // The patterns are {0,128} and {0,32}, so '' matches both. Only isIPStr(),
        // which goes through filter_var(), rejects it.
        self::assertTrue(IP::isIPBin(''));
        self::assertTrue(IP::isIPHex(''));
        self::assertFalse(IP::isIPStr(''));
    }

    public function testVersionDetectionIsPurelyByLength()
    {
        // This collides with zero-padding for storage: once an IPv4 hex value is
        // padded to 32 characters, isIPv6Hex() calls it IPv6. Keep the version in
        // its own column instead of inferring it from the width.
        $v4 = IP::fromString('192.168.1.1')->toHex();

        self::assertFalse(IP::isIPv6Hex($v4));
        self::assertTrue(IP::isIPv6Hex(str_pad($v4, 32, '0', STR_PAD_LEFT)));
    }
}
