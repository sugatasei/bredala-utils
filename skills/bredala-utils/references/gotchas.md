# bredala-utils — gotchas

Things the method names don't tell you. Grouped by class. Every item is pinned by a test in `tests/`, except where it says the class is untested.

## Crypto

`Crypto` replaced the former `Hash`, `Password` and `Crypto` classes; everything is static.

### Hashing and randomness

- **`hash()` is a fast unsalted digest, not a password hash.** Its name sits next to `passwordHash()`; never store passwords with it.
- **`uuid()` is a v7 UUID (RFC 9562), not v4.** The first 48 bits are the creation time in milliseconds, so the value leaks when it was generated and is sortable; two UUIDs from the same millisecond are not ordered relative to each other. Use `random(32)` if the timestamp must not be exposed.
- **`otp()` throws `ValueError` for a length outside 1–18.** Beyond 18 digits `10 ** $len` no longer fits a 64-bit PHP int. A computed length that reaches 0 crashes rather than returning `''`. Within range it uses `random_int`, so every code from `'000000'` to `'999999'` is equally likely and safe for login, payment or reset codes.
- **`hash()` silently falls back to sha1 for an unsupported length.** Only 32, 40, 64 and 128 map to an algorithm; `hash($s, 50)` returns a 40-character sha1 with no error, so a mismatch with a fixed-width column shows up as truncation, not as an exception.
- **`salt($length)` inherits that fallback**, so `salt(7)` returns 40 characters, not 7.
- **`random(0)` throws `ValueError`** (`random_bytes()` rejects a zero length) rather than returning `''`. A computed length that reaches 0 crashes.
- **`random()` honours odd lengths exactly** (it over-generates then truncates), so `random(19)` is 19 characters.

### Passwords

- **`passwordHash($p, 0)` means "default cost", not "cost 0".** The cost is only applied when truthy, so any falsy argument falls back to bcrypt's own default.
- **`passwordVerify()` returns `false` rather than throwing** for an empty password, an empty hash, or a malformed hash. You cannot distinguish "wrong password" from "corrupt hash column".
- **Two hashes of the same password always differ** (random salt), so never compare hashes directly or cache them as a key — always go through `passwordVerify()`.

### Encryption

- **Ciphertext is not deterministic.** Each `encrypt()` draws a random IV, so the same plaintext and key give a different output every time. Never compare ciphertexts or index on them; store an HMAC of the plaintext alongside if you need equality lookups.
- **`decrypt()` returns `''` for every failure mode.** Invalid base64 (strict decoding), a payload shorter than IV + tag (28 bytes), the wrong key and a tampered ciphertext all give the same empty string. It never throws, so check for `''` when a failure matters.
- **`decrypt('')` is `''`**, indistinguishable from an empty plaintext that was legitimately encrypted (which itself encrypts to a non-empty string).
- **The key is hashed with sha256, not stretched.** Any length works, but a low-entropy key such as a user password stays brute-forceable. Pass a high-entropy secret, or derive it with `hash_pbkdf2()` / `sodium_crypto_pwhash()` first.
- **The format changed.** The former `Crypto` class used AES-256-CBC with an IV derived from the key and an instance API (`make()`, `encode()`/`decode()`). Its ciphertexts decrypt to `''` now — migrate them with the old code before upgrading.

## IP

- **Invalid input silently becomes `0.0.0.0`.** Every constructor path falls through to the defaults with no exception, so a typo, an empty string, a CIDR block or a hostname all produce a valid-looking object. A legitimate `0.0.0.0` is indistinguishable from a parse failure — validate with `IP::isIPStr()` *before* constructing.
- **An unknown `$from` in the constructor leaves the defaults too.** `new IP('192.168.1.1', 'string')` (instead of `'str'`) silently yields `0.0.0.0`.
- **The IPv4 binary and hex forms are not zero-padded.** Leading zeros are stripped, so the width varies with the value: `192.168.1.1` → 8 hex characters, `10.0.0.1` → 7, `0.0.0.0` → `'0'`. Storing them raw breaks `CHAR(8)`/`BINARY` columns, and **lexical sorting disagrees with numeric ordering** (`0.255.0.1` is `'ff0001'` and sorts *after* `1.0.0.1`'s `'1000001'`). Pad with `str_pad($hex, 32, '0', STR_PAD_LEFT)` before storing or sorting.
- **The IPv6 hex form *is* always 32 characters**, so the two families pad differently — another reason to normalize to a single width yourself.
- **The static converters crash on IPv6 data without `$v6 = true`.** `binToStr($v6Bin)` hands a 128-bit value to `long2ip()`, which raises `TypeError`. The flag is not optional in practice: store the version alongside the value, or always round-trip through the `IP` object, which tracks it for you.
- **`isIPv6Bin()`/`isIPv6Hex()` classify purely by length** (exactly 128 bits / 32 hex characters). This collides with the padding advice above: once you `str_pad()` an IPv4 hex value to 32 characters for storage, `isIPv6Hex()` reports it as IPv6. Keep the version in its own column rather than inferring it from width.
- **`isIPBin('')` and `isIPHex('')` both return `true`.** Their patterns are `{0,128}` and `{0,32}`, so the empty string validates as an IP. Guard for emptiness separately. (`isIPStr()` uses `filter_var` and correctly rejects `''`.)

## ArrayHelper

- **`equal()` is strict but ignores key order.** It requires the same keys with `===`-equal values, in any key order, recursing into nested arrays; objects compare by identity. `equal([1], ['1'])` and `equal([0], [false])` are `false`, `equal(['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1])` is `true`. On a list the key is the position, so `equal([1, 2], [2, 1])` is `false` — sort both sides first for multiset equality. Keys go through PHP's own normalization, so `['1' => x]` and `[1 => x]` are equal.
- **`unique()` compares strictly (`===`).** `1`, `'1'` and `1.0` are three distinct values, as are `0`, `false` and `'0'`; arrays are deduplicated only when identical. If you relied on `1` and `'1'` collapsing, normalize the types first.
- **`unique()` drops `null`, `''` and `[]` but keeps `0` and `false`.** The filter is a strict comparison against those three values only, so a column of `0` sentinels survives while a column of empty strings is silently emptied.
- **`unique()` is O(n²)** (`in_array` against the values kept so far). Fine for typical lists; avoid it on very large arrays.
- **Only `null` means "no column".** `unique($rows, '')` and `unique($rows, '0')` extract the column named `''` or `'0'`.
- **`unique()` silently skips rows missing the column** — the result can be shorter than the input with no indication which rows were dropped.
- **`toArray()` only sees public properties** (it round-trips through `json_encode`), and inherits every `json_encode` limitation. A resource or `NAN` anywhere makes `json_encode` return `false`, `json_decode(false, true)` return `null`, and the `: array` return type turn that into `TypeError: toArray(): Return value must be of type array, null returned` — a type error about the method's own signature rather than a `JsonException` naming the offending property.
- **`mergeAssoc()` preserves numeric keys**, unlike `array_merge()` which renumbers them. It is also shallow — a nested array is replaced, not merged.
- **`rand()` returns `null` for an empty array**, which is indistinguishable from an array whose chosen element is `null`.

## TextHelper

- **`alias()` is broken for regex metacharacter separators.** The final collapse step is `preg_replace("#{$char}+#", $char, $str)` with `$char` interpolated **unescaped**, so `alias('a b c', '.')` compiles to "any character, repeated" and returns `'.'`. Only `-` and `_` (and other literal characters) are safe. Note `split()` *does* escape its separator — the two are inconsistent.
- **The case converters are slug builders, not case converters.** `toKebabCase`/`toSnakeCase`/`toPascalCase`/`toCamelCase` all run `alias()` first, so they strip accents **and** every non-alphanumeric character: `'API/v2 @users'` becomes `'api-v2-users'`. Don't use them to rename a PHP identifier or a database column that contains anything else.
- **`toPascalCase()`/`toCamelCase()` never lowercase.** They use `ucwords()`, so an all-caps input stays all-caps: `'ÀÉÎÕÜ'` becomes `'AEIOU'` and `'aEIOU'` respectively.
- **`split()` does not trim the ends of the whole string**, despite the docblock's "trim all elements". The pattern only consumes whitespace adjacent to a separator, so `split(',', ' a , b , c ')` returns `[' a', 'b', 'c ']` — the first and last elements keep their outer padding. Wrap it in `array_map('trim', …)` if you need real trimming.
- **An unknown accent table silently resolves to an empty map.** `getAccents('typo')` returns `[]` with no error, so `removeAccents($s, 'typo')` returns the string unchanged — a misspelled table name looks like "this string had no accents". Only `all` and `fr` exist.
- **The `fr` table is much narrower than `all`.** `removeAccents('ñ', 'fr')` leaves `ñ` intact because it isn't a French accent.
- **The accent tables are cached in a private static with no reset.**
- **`remove_emoji()` covers five hardcoded Unicode ranges** (emoticons, misc symbols & pictographs, transport & map, misc symbols, dingbats). Regional-indicator flags, skin-tone modifiers, ZWJ sequences and anything added to Unicode since are left in place — `'🇫🇷'` survives untouched. It also only deletes codepoints, leaving the surrounding spaces behind (`'a 🚀 b'` → `'a  b'`).
- **`lcfirst()`'s docblock says "last character uppercase"** — it lowercases the *first* character. The code is right, the comment is wrong.
- **`xss()` short-circuits on `is_numeric()`**, so numeric strings are cast rather than escaped, and non-scalars are json-encoded then escaped. `ENT_HTML5` means `'` becomes `&apos;`, not `&#039;`.

## Date

- **Invalid dates throw `InvalidArgumentException`.** The constructor, `create()`, `fromYmd()`, `modify()`, `fr()` with a string and the string converters all reject unparsable input *and* overflowing dates: `strtotime('2026-02-31')` returns 3 March, but `new Date('2026-02-31')` throws. Catch it around user input.
- **`modify()` mutates the object** and returns `$this`, so `$tomorrow = $today->modify('+1 day')` moves `$today` too. Clone first. A rejected modifier leaves the date unchanged.
- **Unknown properties throw.** `$date->foo` raises `InvalidArgumentException` rather than PHP's usual warning. `__isset()` is defined, so `isset($date->year)` is `true` and `$date->foo ?? $default` stays safe.
- **`$timestamp` is `private(set)`.** Assigning it from outside raises `Error`; use `modify()`, which also refreshes the cached `getdate()` parts.
- **`seasonDate()` returns a UTC instant.** It no longer depends on `date.timezone`, but the *calendar day* still does when you format it with `date()`: the 2026 autumn equinox is 23 September 00:05 UTC, which is still 22 September west of UTC. Use `gmdate()` for the UTC day. Accuracy is a few minutes.
- **Parameters are typed without `strict_types`.** Numeric strings are coerced (`daysInMonth('2', 2024)` is 29); non-numeric ones raise `TypeError`.
- **`fr()` decides from the *format*, not the output.** The trigger is a regex looking for an unescaped `D`, `l`, `F` or `M` in the format string, so a format without one is returned untranslated even if the result contains a month name from elsewhere.
- **`fr()` relies on `date()` emitting English names**, which it always does regardless of `setlocale()`. That's why the translation works — but it also means switching to `strftime`-style localization breaks it.
- **`toFr()` is an unbounded `strtr()`.** No word boundaries, so any occurrence of a name inside a larger word is replaced: `toFr('Marching')` returns `'Marsing'`. Run it over `date()` output only, never over user prose. (`strtr()` does prefer the longest matching key, so `'March'` correctly becomes `'Mars'` rather than being mangled by the `'Mar'` entry.)
- **Weekday constants are 0-based on Sunday** (matching `date('w')`) while month constants are 1-based and season constants are 0-based. Three different bases in one class.

## Counter / Bench

- **Both are global static state with no flush.** `Counter::reset()` sets a counter to `0`, it does not delete it, and `Bench::remove()` is the only way to drop a label. In tests, namespace keys per test case; in a long-lived worker (RoadRunner, Swoole), values survive across requests.
- **`Counter` has no floor at zero.** `decrement()` on an unknown counter returns `-1`, and `increment($name, -5)` decrements.
- **`Bench::time()` returns intervals, not timestamps**, and needs at least two marks — a single mark yields `[]`, which is also what an unknown label returns. You cannot tell "never marked" from "marked once".
- **`Bench`'s `$unit` is a plain multiplier**, so `$unit = 0` flattens every interval to `0.0` rather than meaning "seconds".
- **`Bench::time()` does not consume the marks**, so repeated calls return the same intervals and marks keep accumulating until `remove()`.

## File / Image (not covered by this package's tests)

- **`File::deleteDirRecursive()` deletes directories.** The most destructive API in the package set. Never pass it a path assembled from input you don't fully control.
- **`File` returns `null` on failure rather than throwing** — for a missing file, a closed stream, a failed write. Check every return value; there is no error message.
- **`Image extends \Imagick`**, so it requires `ext-imagick` (declared only as a `suggest`), cannot be mocked, and needs real image files on disk. WebP output additionally needs WebP support compiled into ImageMagick. `save()` returns a *new* instance when the filename changes.
