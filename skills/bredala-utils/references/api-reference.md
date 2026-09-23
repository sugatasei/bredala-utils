# bredala-utils — API cheat sheet

Quick lookup by intent. This is not exhaustive — read the source in `vendor/sugatasei/bredala-utils/src/` for exact signatures/edge cases not covered here. Everything is static unless noted.

## TextHelper (`Bredala\Utils\TextHelper`)

| Intent | Method |
| ------ | ------ |
| Strip accents (tables: `all`, `fr`) | `removeAccents(string $str, string $table = 'all'): string` |
| Read an accent table (memoized) | `getAccents(string $table): array` |
| Slug, case preserved | `alias(string $str, string $char = "-"): string` |
| `kebab-case` / `snake_case` | `toKebabCase(string $str): string` / `toSnakeCase(string $str): string` |
| `PascalCase` / `camelCase` | `toPascalCase(string $str): string` / `toCamelCase(string $str): string` |
| Split and trim around a separator | `split($char, $str)` |
| UTF-8 aware first-character case | `ucfirst(string $str): string` / `lcfirst(string $str): string` |
| Strip emoji (five ranges) | `remove_emoji(string $string): string` |
| Escape any value for HTML | `xss(mixed $value): string` |
| Encode / decode HTML entities | `htmlEncode(string $value): string` / `htmlDecode(string $value): string` |

The four case converters all call `alias()` first, so they also strip accents and non-alphanumerics. Only `-` and `_` are safe separators for `alias()`. `split()` escapes regex metacharacters in `$char` and drops empty elements.

## ArrayHelper (`Bredala\Utils\ArrayHelper`)

| Intent | Method |
| ------ | ------ |
| Object → nested array (json round trip) | `toArray($object): array` |
| Same values, ignoring keys and order (**unreliable**) | `equal(array $a, array $b): bool` |
| Distinct values, optionally of one column | `unique(array $rows, ?string $property = null): array` |
| One random element, `null` if empty | `rand(array $data)` |
| Associative merge, last wins, keys preserved | `mergeAssoc(array ...$arrays): array` |

`unique()` compares strictly (`===`) and drops `null`, `''` and `[]`; `0` and `false` survive. `mergeAssoc()` differs from `array_merge()` in keeping numeric keys instead of renumbering.

## Date (`Bredala\Utils\Date`)

Instance wrapper around a timestamp, plus static helpers. Replaces the former `DateHelper`.

Typed constants: `SUNDAY`..`SATURDAY` (0–6, like `date('w')`), `JANUARY`..`DECEMBER` (1–12), `SPRING`/`SUMMER`/`AUTUMN`/`WINTER` (0–3), `DAYS`/`MONTHS`/`SHORT_MONTHS` (French names keyed by those), `DATETIME_SQL`, `DATE_SQL`, `TIME_SQL`.

| Intent | Method |
| ------ | ------ |
| Build (int timestamp, date string, or `null` for now) | `__construct(int\|string\|null $timestamp = null)` / `static create(...): static` |
| Build from `20260923` | `static fromYmd(int $ymd): static` |
| French `date()` | `format(string $format): string` |
| Export | `ymd(): int`, `toSql(): string`, `toIso(): string` |
| Leap year / days in this month | `isLeap(): bool`, `days(): int` |
| Apply a `strtotime()` modifier, in place | `modify(string $str): static` |
| Read the parts | `$timestamp` (`private(set)`), `$year`, `$mon`, `$mday`, `$hours`, `$minutes`, `$seconds`, `$wday`, `$yday` |
| JSON | `jsonSerialize(): array` — timestamp + the eight parts |
| Leap year test | `static isLeapYear(int $y): bool` |
| Days in a month | `static daysInMonth(int $m, int $y): int` |
| Equinox/solstice, UTC timestamp | `static seasonDate(int $year, int $season): int` |
| Conversions | `static sqlToTimestamp(string): int`, `isoToTimestamp(string): int`, `timestampToSql(int): string`, `timestampToIso(int): string`, `sqlToIso(string): string`, `isoToSql(string): string`, `timestampToYmd(?int $ts = null): int` |
| `date()` with French day/month names | `static fr(string $format, int\|string\|null $timestamp = null): string` |
| Translate existing English date text | `static toFr(string $date): string` |

Every method taking a date string, plus `fromYmd()`, `modify()`, `seasonDate()` with an unknown season and `__get()` with an unknown property, throws `InvalidArgumentException`.

## Crypto (`Bredala\Utils\Crypto`)

All static. Replaces the former `Hash`, `Password` and `Crypto` classes.

| Intent | Method |
| ------ | ------ |
| Fixed-length hex hash | `hash(string $string, int $length = 40): string` |
| Random hex of exactly `$length` | `random(int $length = 40): string` |
| Hash of random bytes | `salt(int $length = 40): string` |
| UUID v7 (RFC 9562), time-ordered | `uuid(): string` |
| Secure zero-padded numeric code (`random_int`, 1–18 digits) | `otp(int $len = 6): string` |
| Hash a password (bcrypt, 60-char `$2y$`, falsy cost = default) | `passwordHash(string $password, ?int $cost = null): string` |
| Verify a password, `false` on empty/malformed | `passwordVerify(string $password, string $hash): bool` |
| Encrypt to `base64(iv . tag . ciphertext)` | `encrypt(string $str, string $key): string` |
| Decrypt, `''` on any failure | `decrypt(string $str, string $key): string` |

`hash()`/`salt()` pick the algorithm from the length — 32→md5, 40→sha1, 64→sha256, 128→sha512 — and silently fall back to sha1 (40) for anything else. `random(0)` throws `ValueError`. Encryption is AES-256-GCM: random 12-byte IV per call, 16-byte tag, key hashed with `sha256`.

## IP (`Bredala\Utils\IP`)

Instance value object plus static helpers. Supports IPv4 and IPv6.

| Intent | Method |
| ------ | ------ |
| Build from a representation | `static fromString(string $ip)` / `fromBin(string $ip)` / `fromHex(string $ip)`, or `__construct(string $ip, string $from)` with `$from` in `str`/`hex`/`bin` |
| Version test | `isIPv6()` / `isIPv4()` |
| Read a representation | `toString()` / `toBin()` / `toHex()` |
| Validate before building | `static isIPStr(string $ip)` / `isIPv6Str(string $ip)` |
| Validate other forms | `static isIPBin`, `isIPv6Bin`, `isIPHex`, `isIPv6Hex` |
| Pure conversions | `static binToHex(string $ip, bool $v6 = false)`, `binToStr(...)`, `hexToBin(...)`, `strToBin(...)` |

Invalid input silently yields `0.0.0.0` / `'0'`. The IPv4 bin and hex forms are **not zero-padded**; the IPv6 hex form is always 32 characters. The static converters need `$v6 = true` for IPv6 data or they raise `TypeError`.

## Counter (`Bredala\Utils\Counter`)

Named integer counters, global static, one script run. Every method returns the resulting value.

`set(string $name, int $value = 0): int`, `get(string $name): int`, `reset(string $name): int` (sets 0, does not delete), `increment(string $name, int $value = 1): int`, `decrement(string $name, int $value = 1): int`. No floor at zero, no flush.

## Bench (`Bredala\Utils\Bench`)

Timing marks, global static.

`mark(string $label)` appends `microtime(true)`; `remove(string $label)` drops a label; `time(string $label, int $unit = 1): array` returns the intervals between consecutive marks (`[]` for an unknown label or a single mark); `times(int $unit = 1): array` returns `[label => intervals]`. `$unit` is a plain multiplier (1000 for milliseconds). `time()` does not consume the marks.

## File (`Bredala\Utils\File`)

Instance wrapper around one path. Returns `null` on failure rather than throwing. **Not covered by this package's tests.**

`__construct(string $filename)`, `getFilename(): string`, `isFile(): bool`; whole-file `get(): ?string`, `json(): ?array`, `put(string $data): ?int`, `delete(): static`; stream `isOpen()`, `open(string $mode = 'r+')`, `close()`, `stream()`, `eof()`; read `read(): ?string`, `readCsv(string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\'): ?array`; write `add(string $text): ?int`, `line(string $text): ?int`, `csv(array $data, ...): ?int`; statics `unlink(string $file)`, **`deleteDirRecursive(string $dir): bool`** (destructive).

## Image (`Bredala\Utils\Image`)

`class Image extends \Imagick` — requires `ext-imagick` and real files on disk. **Not covered by this package's tests**, and not mockable (it extends a native class).

Build `__construct(...$files)` / `static create(...$files)`; getters `getBgColor()`, `getQuality()`, `getName()`, `getFileSize()`, `getImageMimeType()`, `getImageLength()`; setters `setBgColor(string $color)`, `setQuality(int $quality)`; fluent transforms `prepare()`, `cmynToRgb()`, `stripExif()`, `fitBounds(int $width, int $height, bool $strict_orientation = false)`, `crop(array $data)`, `watermark(Image $img, int $x, int $y)`, `toJpeg(bool $force = false)`, `toPng(...)`, `toWebp(...)`; queries `isInside(...)`, `hasFormat(string $format)`; output `save(?string $to = null): static`; perceptual hashing `getHash()`, `static similarHash(string $hash1, string $hash2, float &$percent = 0): int`.
