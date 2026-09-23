<?php

use Bredala\Utils\Crypto;
use PHPUnit\Framework\TestCase;

class CryptoTest extends TestCase
{
    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            self::markTestSkipped('ext-openssl is required');
        }
    }

    // -------------------------------------------------------------------------
    // hash
    // -------------------------------------------------------------------------

    /**
     * @dataProvider algoProvider
     */
    public function testHashSelectsTheAlgorithmFromTheRequestedLength(int $length, string $algo)
    {
        self::assertSame(hash($algo, 'abc'), Crypto::hash('abc', $length));
        self::assertSame($length, mb_strlen(Crypto::hash('abc', $length)));
    }

    public static function algoProvider(): array
    {
        return [
            'md5' => [32, 'md5'],
            'sha1' => [40, 'sha1'],
            'sha256' => [64, 'sha256'],
            'sha512' => [128, 'sha512'],
        ];
    }

    public function testHashDefaultsToSha1()
    {
        self::assertSame(hash('sha1', 'abc'), Crypto::hash('abc'));
    }

    /**
     * @dataProvider unknownLengthProvider
     */
    public function testAnUnsupportedLengthSilentlyFallsBackToSha1(int $length)
    {
        // No exception: any length that is not 32/40/64/128 is replaced by 40.
        self::assertSame(hash('sha1', 'abc'), Crypto::hash('abc', $length));
        self::assertSame(40, mb_strlen(Crypto::hash('abc', $length)));
    }

    public static function unknownLengthProvider(): array
    {
        return [
            'too short' => [8],
            'between two algos' => [50],
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function testHashIsDeterministic()
    {
        self::assertSame(Crypto::hash('abc'), Crypto::hash('abc'));
    }

    public function testHashDistinguishesInputs()
    {
        self::assertNotSame(Crypto::hash('abc'), Crypto::hash('abd'));
    }

    public function testHashAcceptsAnEmptyString()
    {
        self::assertSame(hash('sha1', ''), Crypto::hash(''));
    }

    public function testHashIsLowercaseHex()
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]+$/', Crypto::hash('abc'));
    }

    // -------------------------------------------------------------------------
    // salt
    // -------------------------------------------------------------------------

    public function testSaltHasTheRequestedLengthForASupportedAlgorithm()
    {
        self::assertSame(40, mb_strlen(Crypto::salt()));
        self::assertSame(64, mb_strlen(Crypto::salt(64)));
    }

    public function testSaltFallsBackToFortyForAnUnsupportedLength()
    {
        self::assertSame(40, mb_strlen(Crypto::salt(7)));
    }

    public function testSaltIsRandom()
    {
        self::assertNotSame(Crypto::salt(), Crypto::salt());
    }

    public function testSaltIsHex()
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', Crypto::salt());
    }

    // -------------------------------------------------------------------------
    // random
    // -------------------------------------------------------------------------

    /**
     * @dataProvider randomLengthProvider
     */
    public function testRandomHonoursTheExactLength(int $length)
    {
        self::assertSame($length, mb_strlen(Crypto::random($length)));
    }

    public static function randomLengthProvider(): array
    {
        return [
            'one' => [1],
            'odd' => [19],
            'even' => [20],
            'default' => [40],
            'long' => [129],
        ];
    }

    public function testRandomDefaultsToForty()
    {
        self::assertSame(40, mb_strlen(Crypto::random()));
    }

    public function testRandomIsHex()
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', Crypto::random());
    }

    public function testRandomIsRandom()
    {
        self::assertNotSame(Crypto::random(), Crypto::random());
    }

    public function testRandomOfZeroOrLessThrows()
    {
        // ceil(0 / 2) is 0 and random_bytes() rejects it, so a computed length
        // that reaches 0 is a ValueError rather than an empty string.
        $this->expectException(ValueError::class);

        Crypto::random(0);
    }

    // -------------------------------------------------------------------------
    // uuid
    // -------------------------------------------------------------------------

    public function testUuidHasTheCanonicalShape()
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            Crypto::uuid()
        );
    }

    public function testUuidIs36Characters()
    {
        self::assertSame(36, mb_strlen(Crypto::uuid()));
    }

    public function testUuidIsAVersion7Uuid()
    {
        for ($i = 0; $i < 100; $i++) {
            self::assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
                Crypto::uuid()
            );
        }
    }

    public function testUuidStartsWithTheCurrentTimestampInMilliseconds()
    {
        $before = (int) (microtime(true) * 1000);
        $uuid = Crypto::uuid();
        $after = (int) (microtime(true) * 1000);

        $time = hexdec(str_replace('-', '', substr($uuid, 0, 13)));

        self::assertGreaterThanOrEqual($before, $time);
        self::assertLessThanOrEqual($after, $time);
    }

    public function testUuidsSortChronologically()
    {
        // Order within the same millisecond depends on the random bits, so the
        // values are generated in distinct milliseconds.
        $uuids = [];
        for ($i = 0; $i < 5; $i++) {
            $uuids[] = Crypto::uuid();
            usleep(2000);
        }

        $sorted = $uuids;
        sort($sorted, SORT_STRING);

        self::assertSame($uuids, $sorted);
    }

    public function testUuidsDiffer()
    {
        self::assertNotSame(Crypto::uuid(), Crypto::uuid());
    }

    // -------------------------------------------------------------------------
    // otp
    // -------------------------------------------------------------------------

    /**
     * @dataProvider otpLengthProvider
     */
    public function testOtpIsZeroPaddedToTheRequestedLength(int $length)
    {
        $otp = Crypto::otp($length);

        self::assertSame($length, mb_strlen($otp));
        self::assertMatchesRegularExpression('/^[0-9]+$/', $otp);
    }

    public static function otpLengthProvider(): array
    {
        return ['four' => [4], 'default' => [6], 'eight' => [8]];
    }

    public function testOtpDefaultsToSixDigits()
    {
        self::assertSame(6, mb_strlen(Crypto::otp()));
    }

    public function testOtpSupportsEighteenDigits()
    {
        self::assertMatchesRegularExpression('/^[0-9]{18}$/', Crypto::otp(18));
    }

    /**
     * @dataProvider otpInvalidLengthProvider
     */
    public function testOtpRejectsAnOutOfRangeLength(int $length)
    {
        $this->expectException(ValueError::class);
        Crypto::otp($length);
    }

    public static function otpInvalidLengthProvider(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'nineteen' => [19]];
    }

    public function testOtpStaysWithinItsDigitBudget()
    {
        for ($i = 0; $i < 50; $i++) {
            self::assertLessThanOrEqual(9999, (int) Crypto::otp(4));
        }
    }

    // -------------------------------------------------------------------------
    // passwordHash / passwordVerify
    // -------------------------------------------------------------------------

    /**
     * The lowest cost bcrypt accepts, to keep the suite fast.
     */
    private const COST = 4;

    public function testPasswordHashProducesABcryptHash()
    {
        $hash = Crypto::passwordHash('secret', self::COST);

        self::assertStringStartsWith('$2y$', $hash);
        self::assertSame(60, strlen($hash));
    }

    public function testPasswordHashIsSaltedSoTwoHashesDiffer()
    {
        self::assertNotSame(
            Crypto::passwordHash('secret', self::COST),
            Crypto::passwordHash('secret', self::COST)
        );
    }

    public function testPasswordVerifyAcceptsTheRightPassword()
    {
        self::assertTrue(Crypto::passwordVerify('secret', Crypto::passwordHash('secret', self::COST)));
    }

    public function testPasswordVerifyRejectsTheWrongPassword()
    {
        self::assertFalse(Crypto::passwordVerify('wrong', Crypto::passwordHash('secret', self::COST)));
    }

    public function testPasswordVerifyIsCaseSensitive()
    {
        self::assertFalse(Crypto::passwordVerify('Secret', Crypto::passwordHash('secret', self::COST)));
    }

    public function testPasswordVerifyRejectsAnEmptyPassword()
    {
        self::assertFalse(Crypto::passwordVerify('', 'whatever'));
    }

    public function testPasswordVerifyRejectsAnEmptyHash()
    {
        self::assertFalse(Crypto::passwordVerify('secret', ''));
    }

    public function testPasswordVerifyRejectsAMalformedHashWithoutThrowing()
    {
        self::assertFalse(Crypto::passwordVerify('secret', 'not-a-hash'));
    }

    public function testCostIsAppliedToTheHash()
    {
        $hash = Crypto::passwordHash('secret', self::COST);

        self::assertStringStartsWith('$2y$04$', $hash);
        self::assertSame(self::COST, password_get_info($hash)['options']['cost']);
    }

    public function testDefaultCostIsBcryptsOwnDefault()
    {
        self::assertSame(
            PASSWORD_BCRYPT_DEFAULT_COST,
            password_get_info(Crypto::passwordHash('secret'))['options']['cost']
        );
    }

    public function testAFalsyCostFallsBackToTheDefault()
    {
        // The cost is only applied when truthy, so 0 is treated as "not set".
        self::assertSame(
            PASSWORD_BCRYPT_DEFAULT_COST,
            password_get_info(Crypto::passwordHash('secret', 0))['options']['cost']
        );
    }

    // -------------------------------------------------------------------------
    // encrypt / decrypt
    // -------------------------------------------------------------------------

    public function testRoundTrip()
    {
        self::assertSame('secret', Crypto::decrypt(Crypto::encrypt('secret', 'key'), 'key'));
    }

    /**
     * @dataProvider payloadProvider
     */
    public function testRoundTripPreservesThePayload(string $payload)
    {
        self::assertSame($payload, Crypto::decrypt(Crypto::encrypt($payload, 'key'), 'key'));
    }

    public static function payloadProvider(): array
    {
        return [
            'empty' => [''],
            'short' => ['a'],
            'accented' => ['déjà vu'],
            'json' => ['{"a":1}'],
            'long' => [str_repeat('x', 5000)],
            'binary' => ["\x00\x01\x02\xff"],
            'newlines' => ["a\nb"],
        ];
    }

    public function testEncryptProducesBase64()
    {
        $encrypted = Crypto::encrypt('secret', 'key');

        self::assertMatchesRegularExpression('#^[A-Za-z0-9/+]+=*$#', $encrypted);
        self::assertNotFalse(base64_decode($encrypted, true));
    }

    public function testEncryptPrependsA12ByteIvAndA16ByteTag()
    {
        self::assertSame(12 + 16 + 6, strlen(base64_decode(Crypto::encrypt('secret', 'key'))));
    }

    public function testCiphertextIsNotDeterministic()
    {
        // A random IV is drawn on every call.
        self::assertNotSame(Crypto::encrypt('secret', 'key'), Crypto::encrypt('secret', 'key'));
    }

    public function testAnEmptyPlaintextStillEncryptsToANonEmptyString()
    {
        self::assertNotSame('', Crypto::encrypt('', 'key'));
    }

    public function testTheWrongKeyDoesNotDecrypt()
    {
        self::assertSame('', Crypto::decrypt(Crypto::encrypt('secret', 'key'), 'other-key'));
    }

    public function testATamperedCiphertextIsRejected()
    {
        $raw = base64_decode(Crypto::encrypt('secret', 'key'));
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);

        self::assertSame('', Crypto::decrypt(base64_encode($raw), 'key'));
    }

    public function testATamperedTagIsRejected()
    {
        $raw = base64_decode(Crypto::encrypt('secret', 'key'));
        $raw[12] = chr(ord($raw[12]) ^ 1);

        self::assertSame('', Crypto::decrypt(base64_encode($raw), 'key'));
    }

    public function testATruncatedPayloadIsRejected()
    {
        $raw = base64_decode(Crypto::encrypt('secret', 'key'));

        self::assertSame('', Crypto::decrypt(base64_encode(substr($raw, 0, 27)), 'key'));
    }

    public function testDecryptReturnsAnEmptyStringOnInvalidBase64()
    {
        self::assertSame('', Crypto::decrypt('not base64!!', 'key'));
    }

    public function testDecryptOfAnEmptyStringIsEmpty()
    {
        self::assertSame('', Crypto::decrypt('', 'key'));
    }

    public function testAnyKeyLengthIsAccepted()
    {
        foreach (['k', str_repeat('k', 32), str_repeat('k', 100)] as $key) {
            self::assertSame('secret', Crypto::decrypt(Crypto::encrypt('secret', $key), $key));
        }
    }
}
