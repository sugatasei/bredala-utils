---
name: bredala-utils
description: How to correctly use the sugatasei/bredala-utils PHP helper collection (namespace Bredala\Utils — TextHelper, ArrayHelper, Date, Crypto, IP, Counter, Bench, File, Image) for slugs/case conversion/accent stripping/HTML escaping, array comparison and deduplication, date objects, French date formatting, SQL/ISO date conversion and season/leap-year maths, hashing and random tokens, bcrypt password hashing, symmetric encryption, IPv4/IPv6 conversion and storage, named counters, and timing marks. Use this whenever the project's composer.json requires sugatasei/bredala-utils, code imports from Bredala\Utils\*, or you're asked to slugify a string, strip accents, convert to camel/snake/kebab case, escape output, deduplicate or compare arrays, hash a password, generate a token/UUID/OTP, encrypt a value, store or convert an IP address, format a date in French, or time a code path in a PHP project that has this library available — even if the request is phrased generically without naming the library. Also check this before hand-rolling those helpers, since several have non-obvious or outright broken behavior (DecimalField-style type slips, IP binary/hex forms are not zero-padded, ArrayHelper::equal ignores keys) that plain PHP code would not share.
---

# bredala-utils

`sugatasei/bredala-utils` is a grab-bag of independent static helper classes for PHP 8.5+. There is no shared abstraction: each class stands alone, so use exactly the one you need. Several carry real defects — this skill says which, and what to do instead.

Namespace: `Bredala\Utils\*`. Source lives in `vendor/sugatasei/bredala-utils/src/`; read it directly when you need an exact signature — this skill focuses on which helper to reach for and the behavior that isn't obvious from the method names.

## Orientation

| Class | Use it for | Health |
| ----- | ---------- | ------ |
| `TextHelper` | slugs, case conversion, accent stripping, HTML escaping, splitting | good, two sharp edges |
| `ArrayHelper` | object→array, deduplication, associative merge, random pick | `equal()` is unreliable |
| `Date` | date object, French formatting, SQL/ISO conversion, leap years, seasons | good; throws on invalid dates, `modify()` mutates |
| `Crypto` | hashes, random tokens, UUID, OTP, bcrypt passwords, AES-256-GCM encryption | good; `uuid()` is v7, `otp()` is 1–18 digits, `decrypt()` gives `''` on any failure |
| `IP` | IPv4/IPv6 parsing, conversion, storage | good, **not zero-padded** |
| `Counter` | named integer counters for one script run | good |
| `Bench` | timing marks and intervals | good |
| `File` | file read/write/stream/CSV wrapper | untested here; `deleteDirRecursive()` is destructive |
| `Image` | Imagick pipeline, resizing, watermarks, perceptual hash | requires `ext-imagick`; untested here |

For a full method cheat-sheet see `references/api-reference.md`. For the complete list of easy-to-miss behaviors, see `references/gotchas.md` — read it before trusting any of these with correctness-critical work.

## Core recipes

### Slugs and case conversion

```php
use Bredala\Utils\TextHelper;

TextHelper::toKebabCase('Éléphant Bleu');   // 'elephant-bleu'
TextHelper::toSnakeCase('Éléphant Bleu');   // 'elephant_bleu'
TextHelper::toPascalCase('hello world');    // 'HelloWorld'
TextHelper::toCamelCase('hello world');     // 'helloWorld'
TextHelper::alias('Éléphant Bleu');         // 'Elephant-Bleu' — case preserved
```

All four go through `alias()`, so they strip accents **and** every non-alphanumeric character first. They are slug builders, not pure case converters: `API/v2 @users` becomes `api-v2-users`.

Only pass `-` or `_` as a custom separator. `alias($s, '.')` is broken — the separator is interpolated into a regex unescaped, so a metacharacter swallows the string.

### Escaping

```php
TextHelper::xss($value);          // null→'', numeric→cast, string→escaped, array→escaped JSON
TextHelper::htmlEncode($string);  // htmlspecialchars, ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML5
TextHelper::htmlDecode($string);  // the inverse
```

`ENT_HTML5` means `'` becomes `&apos;`, not `&#039;`.

### Passwords

Passwords, tokens and encryption all live on the static `Crypto` class, which replaced the former `Hash`, `Password` and `Crypto` classes. If a project still calls `Hash::make()`, `new Password()` or `Crypto::make()`, it targets an older version of the library.

```php
use Bredala\Utils\Crypto;

$hash = Crypto::passwordHash($plaintext);       // bcrypt, 60 chars; optional cost: passwordHash($p, 12)
Crypto::passwordVerify($plaintext, $hash);      // bool
```

bcrypt generates a random salt per hash, so two hashes of the same password differ: always compare through `passwordVerify()`.

`passwordVerify()` returns `false` (rather than throwing) for an empty password, an empty hash, or a malformed hash.

### Tokens

```php
Crypto::hash($string);         // sha1 hex; length picks the algo: 32/40/64/128
Crypto::hash($string, 64);     // sha256
Crypto::random(40);            // cryptographically secure random hex, exact length
Crypto::salt();                // sha1 of random_bytes — a random hex string
Crypto::otp(6);                // secure 6-digit code (random_int), zero-padded
Crypto::uuid();                // RFC 9562 v7 UUID, time-ordered
```

`hash()` silently falls back to sha1 for any length that isn't 32, 40, 64 or 128 — there's no error for `hash($s, 50)`. Don't confuse it with `passwordHash()`: `hash()` is a fast unsalted digest and must never be used for passwords.

`Crypto::otp()` uses `random_int`, so it is safe for login, payment or reset codes. It throws `ValueError` for a length outside 1–18. `Crypto::uuid()` is a v7 UUID: it embeds the creation time in milliseconds, so use `Crypto::random(32)` when that must not leak.

### Encryption

```php
$cipher = Crypto::encrypt($plaintext, $key);  // base64(iv . tag . ciphertext), aes-256-gcm
$plain = Crypto::decrypt($cipher, $key);      // '' on any failure
```

Each `encrypt()` draws a random 12-byte IV, so encrypting the same value twice gives different output — don't use the ciphertext as a lookup key (store a separate HMAC for that). The GCM tag means a tampered or truncated ciphertext is rejected.

The key is run through `sha256`, which accepts any length but is not a password KDF: pass a long random secret, or derive it first with `hash_pbkdf2()` / `sodium_crypto_pwhash()`.

`decrypt()` returns `''` for invalid base64, a too-short payload, the wrong key and tampering alike — and for a legitimately encrypted empty string. Data encrypted by the old CBC-based `Crypto` class cannot be decrypted by the current one.

### IP addresses

```php
use Bredala\Utils\IP;

$ip = IP::fromString('192.168.1.1');
$ip->isIPv6();   // false
$ip->toHex();    // 'c0a80101'
$ip->toBin();    // '11000000101010000000000100000001'

IP::fromHex($hex)->toString();
IP::isIPStr($input);   // validate BEFORE constructing
```

Two things to get right:

- **Validate first.** Invalid input silently becomes `0.0.0.0`, indistinguishable from a real zero address. Guard with `IP::isIPStr()`.
- **The binary and hex forms are not zero-padded.** `10.0.0.1` gives 7 hex characters, `192.168.1.1` gives 8, `0.0.0.0` gives `'0'`. Storing them raw breaks fixed-width columns and lexical sorting — pad with `str_pad($hex, 32, '0', STR_PAD_LEFT)`, and store the version alongside, because the static converters crash (`TypeError`) when handed v6 data without `$v6 = true`.

### Arrays

```php
use Bredala\Utils\ArrayHelper;

ArrayHelper::toArray($object);          // recursive, public properties only
ArrayHelper::unique($rows, 'author_id'); // distinct column values, nulls/'' dropped
ArrayHelper::mergeAssoc($a, $b);        // last wins, numeric keys preserved
ArrayHelper::rand($array);              // one element, null when empty
```

**Avoid `ArrayHelper::equal()`.** It compares only counts and `array_diff`, so it ignores keys, ignores order, compares as strings, and is fooled by duplicates: `equal(['a' => 1], ['b' => 1])` and `equal([1, 1], [1, 2])` both return `true`. Use `==` for loose key-aware comparison or `===` for strict.

### Dates

```php
use Bredala\Utils\Date;

$date = Date::create('2026-09-23 14:30');  // or an int timestamp, or null for now
$date->format('l j F Y');                  // 'Mercredi 23 Septembre 2026'
$date->year; $date->mon; $date->mday;      // read-only parts from getdate()
$date->modify('+1 day');                   // mutates $date and returns it
$date->toSql(); $date->toIso(); $date->ymd();
Date::fromYmd(20260923);

Date::fr('D j M Y', $timestamp);           // 'Mer. 23 Sept. 2026' — also accepts a date string
Date::toFr(date('l', $ts));                // translate an existing date() output
Date::isLeapYear(2024);                    // true
Date::daysInMonth(2, 2024);                // 29
Date::seasonDate(2026, Date::SPRING);      // equinox, UTC timestamp
Date::sqlToIso($sql); Date::isoToSql($iso);
```

`Date` replaced `DateHelper`; `dateSeason()` is now `seasonDate()` and `DATETIME_ISO` is gone.

**Invalid dates throw `InvalidArgumentException`** — in the constructor, `fromYmd()`, `modify()`, `fr()` and every string converter — including overflowing dates such as `2026-02-31` that `strtotime()` alone would roll over. Wrap user input in `try`.

**`modify()` mutates in place.** Clone first when the original must survive: `(clone $date)->modify('+1 day')`. `$timestamp` is `private(set)`, and an unknown property like `$date->foo` throws; `isset()` works on the eight date parts.

`fr()` only translates when the format contains an unescaped `D`, `l`, `F` or `M`; it relies on `date()` emitting English names, so the ambient `setlocale()` is irrelevant. `toFr()` is a plain `strtr()` with no word boundaries — run it over `date()` output only, never over prose (`'Marching'` becomes `'Marsing'`).

`seasonDate()` returns the real UTC instant (accurate to a few minutes) regardless of the ambient timezone; format it with `gmdate()` for the UTC day.

### Counters and timing

```php
use Bredala\Utils\Bench;
use Bredala\Utils\Counter;

Counter::increment('rows');    // returns the new value; no floor at zero
Counter::get('rows');
Counter::reset('rows');        // set to 0, not deleted

Bench::mark('import');
// … work …
Bench::mark('import');
Bench::time('import', 1000);   // [elapsed_ms] — intervals between consecutive marks
```

Both are global static state with no flush. Namespace your keys, and expect them to survive across requests in a long-lived worker.

## Behavior to keep in mind while writing code

- **`Crypto::encrypt()` is non-deterministic.** Same input, different ciphertext each call; `decrypt()` gives `''` on every failure, and old CBC-era ciphertexts no longer decrypt.
- **`Crypto::hash()` is not for passwords** — use `passwordHash()`.
- **`Crypto::otp()` throws `ValueError` outside 1–18 digits.**
- **`ArrayHelper::equal()` ignores keys and is fooled by duplicates.** Use `==`/`===`.
- **`IP`'s bin/hex forms are unpadded**, and the static converters raise `TypeError` on v6 data without `$v6 = true`.
- **`Date` throws on invalid dates and `Date::modify()` mutates the object.**
- **Invalid input is usually silent elsewhere.** `IP` falls back to `0.0.0.0`, `TextHelper::getAccents('typo')` returns `[]` (so accents just aren't stripped), `Crypto::hash($s, 50)` falls back to sha1, `StringField`-style filters return `null`. None of them raise.
- **`TextHelper::split()` does not trim the ends of the whole string** despite its docblock — `split(',', ' a , b ')` gives `[' a', 'b ']` — but it does escape regex metacharacter separators correctly (unlike `alias()`).
- **`TextHelper::remove_emoji()` covers five hardcoded Unicode ranges**, so flags, skin-tone modifiers and anything recent survive.
- **`ArrayHelper::unique()` compares strictly** (`1` and `'1'` both survive), drops `null`/`''`/`[]` but keeps `0` and `false`.
- **`Counter`, `Bench` and `TextHelper`'s accent cache are static with no reset.**
- **`Image` needs `ext-imagick`** (it extends `Imagick`, so it can't be mocked) and **`File::deleteDirRecursive()` deletes directories** — treat both with care; neither is covered by this package's test suite.

Read `references/gotchas.md` for the rest before relying on any of these.
