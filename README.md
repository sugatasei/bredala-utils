# Bredala/Utils

Collection de classes utilitaires pour PHP.

Pas d'abstraction commune : chaque classe est indépendante, on n'utilise que celle dont on a besoin.

## Installation

```bash
composer require sugatasei/bredala-utils
```

`Bredala\Utils\Image` étend `Imagick` et nécessite donc l'extension `imagick`, déclarée en `suggest` :

```bash
apt install php-imagick
```

## Claude Code

Ce package fournit un skill Claude Code dans [`skills/bredala-utils/`](skills/bredala-utils/) qui documente les patterns d'usage et les pièges de la librairie (formes binaires d'`IP` non zéro-remplies, etc.).

Dans un projet qui dépend de `sugatasei/bredala-utils`, copie-le une fois dans `.claude/skills/` après `composer install` pour que Claude Code le charge automatiquement (le nom du dossier doit correspondre au `name` déclaré dans `SKILL.md`) :

```bash
cp -r vendor/sugatasei/bredala-utils/skills/bredala-utils .claude/skills/bredala-utils
```

## Vue d'ensemble

| Classe | Usage |
| ------ | ----- |
| `TextHelper` | alias, conversions de casse, suppression d'accents, échappement HTML, découpage |
| `ArrayHelper` | objet vers tableau, déduplication, fusion associative, tirage aléatoire |
| `Date` | objet date, format français, conversions SQL/ISO, années bissextiles, saisons astronomiques |
| `Crypto` | hachages, jetons aléatoires, UUID, OTP, mots de passe bcrypt, chiffrement AES-256-GCM |
| `IP` | analyse, conversion et stockage d'adresses IPv4 et IPv6 |
| `Counter` | compteurs entiers nommés, le temps d'une exécution |
| `Bench` | marques et intervalles de temps |
| `File` | lecture, écriture, flux et CSV sur un fichier |
| `Image` | pipeline Imagick : redimensionnement, filigrane, empreinte perceptuelle |

## TextHelper

`Bredala\Utils\TextHelper` Manipulation de chaînes.

### Accents

- `removeAccents(string $str, string $table = 'all'): string` Supprime les accents.
- `getAccents(string $table): array` Retourne une table d'accents, mise en cache.

Deux tables existent : `all` et `fr`. La table `fr` est bien plus étroite : `removeAccents('ñ', 'fr')` laisse `ñ` intact car ce n'est pas un accent français.

Un nom de table inconnu résout silencieusement vers une table vide — la chaîne est alors retournée inchangée, ce qui ressemble à « cette chaîne n'avait pas d'accent ».

### Alias et casses

- `alias(string $str, string $char = "-"): string` Alias conservant la casse.
- `toKebabCase(string $str): string`
- `toSnakeCase(string $str): string`
- `toPascalCase(string $str): string`
- `toCamelCase(string $str): string`

```php
TextHelper::alias('Éléphant Bleu');        // 'Elephant-Bleu'
TextHelper::toKebabCase('Éléphant Bleu');  // 'elephant-bleu'
TextHelper::toSnakeCase('Éléphant Bleu');  // 'elephant_bleu'
TextHelper::toPascalCase('hello world');   // 'HelloWorld'
TextHelper::toCamelCase('hello world');    // 'helloWorld'
```

Les quatre conversions passent par `alias()` : ce sont donc des **constructeurs d'alias**, pas des convertisseurs de casse. Elles suppriment les accents *et* tout caractère non alphanumérique : `'API/v2 @users'` devient `'api-v2-users'`.

`toPascalCase()` et `toCamelCase()` n'abaissent jamais la casse (elles utilisent `ucwords()`) : une entrée tout en majuscules le reste.

**Ne passer que `-` ou `_` comme séparateur.** L'étape finale d'`alias()` est un `preg_replace("#{$char}+#", …)` où le séparateur est interpolé sans échappement : `alias('a b c', '.')` compile « n'importe quel caractère, répété » et retourne `'.'`.

### Découpage

- `split($char, $str)` Découpe en rognant autour du séparateur, et écarte les éléments vides.

`split()` échappe correctement les métacaractères regex du séparateur (contrairement à `alias()`). En revanche, malgré sa documentation, elle **ne rogne pas les extrémités de la chaîne** : `split(',', ' a , b , c ')` retourne `[' a', 'b', 'c ']`.

### Divers

- `ucfirst(string $str): string` Met la première lettre en majuscule, compatible UTF-8.
- `lcfirst(string $str): string` Met la première lettre en minuscule, compatible UTF-8.
- `remove_emoji(string $string): string` Supprime les emoji.

`remove_emoji()` ne couvre que cinq plages Unicode figées : les drapeaux, les modificateurs de teinte et tout ajout récent à Unicode subsistent. Elle ne supprime que les points de code, laissant les espaces alentour.

### Échappement

- `xss(mixed $value): string` Échappe une valeur quelconque.
- `htmlEncode(string $value): string` Via `htmlspecialchars(ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8')`.
- `htmlDecode(string $value): string` L'inverse.

`ENT_HTML5` implique que `'` devient `&apos;`, pas `&#039;`. `xss()` court-circuite sur les valeurs numériques, qui sont converties sans échappement, et encode les non-scalaires en JSON avant de les échapper.

## ArrayHelper

`Bredala\Utils\ArrayHelper`

- `toArray($object): array` Convertit récursivement un objet en tableau.
- `equal(array $a, array $b): bool` Mêmes clés et mêmes valeurs, strictement, ordre des clés ignoré.
- `unique(array $rows, ?string $property = null): array` Valeurs distinctes, éventuellement d'une colonne.
- `rand(array $data)` Un élément au hasard, `null` si le tableau est vide.
- `mergeAssoc(array ...$arrays): array` Fusion associative, le dernier gagne.

`toArray()` ne voit que les propriétés publiques et hérite des limites de `json_encode` : une ressource ou un `NAN` fait échouer l'encodage, et le type de retour `: array` transforme cela en `TypeError`.

`equal()` se situe entre `==` et `===` : elle compare les valeurs **strictement** comme `===` (`equal([1], ['1'])` vaut `false`), mais **ignore l'ordre des clés** comme `==` (`equal(['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1])` vaut `true`). Les sous-tableaux sont comparés récursivement selon la même règle, les objets par identité. Dans une liste, la clé est la position : `equal([1, 2], [2, 1])` vaut `false`. Les clés suivent la normalisation de PHP (`'1'` et `1` sont la même clé).

`unique()` écarte `null`, `''` et `[]` mais conserve `0` et `false`. Elle compare **strictement** (`===`) : `1`, `'1'` et `1.0` restent trois valeurs distinctes, et deux tableaux ne sont fusionnés que s'ils sont identiques. Elle ignore silencieusement les lignes dépourvues de la colonne. Seul `null` signifie « pas de colonne » : `''` et `'0'` sont des noms de colonne valides. La comparaison est en O(n²), à éviter sur de très gros volumes.

`mergeAssoc()` **préserve les clés numériques**, contrairement à `array_merge()` qui les renumérote. La fusion est superficielle.

## Date

`Bredala\Utils\Date` Objet date autour d'un timestamp, et helpers statiques. Remplace l'ancienne classe `DateHelper`.

| Avant | Maintenant |
| ----- | ---------- |
| `DateHelper::fr()`, `toFr()`, `isLeapYear()`, `daysInMonth()` | `Date::fr()`, `toFr()`, `isLeapYear()`, `daysInMonth()` |
| `DateHelper::dateSeason()` | `Date::seasonDate()`, qui retourne maintenant l'instant UTC réel |
| `DateHelper::DATETIME_ISO` | supprimée : `Date::timestampToIso()` ou `->toIso()` |

Constantes :

- `SUNDAY` à `SATURDAY` (0 à 6, comme `date('w')`) ;
- `JANUARY` à `DECEMBER` (1 à 12) ;
- `SPRING`, `SUMMER`, `AUTUMN`, `WINTER` (0 à 3) ;
- `DAYS`, `MONTHS` et `SHORT_MONTHS` : noms français, indexés par les constantes ci-dessus ;
- `DATETIME_SQL`, `DATE_SQL`, `TIME_SQL`.

**Une date invalide lève une `InvalidArgumentException`**, partout où la classe reçoit une chaîne : constructeur, `modify()`, `fr()`, convertisseurs. Les dates qui débordent sont aussi rejetées, alors que `strtotime('2026-02-31')` retournerait le 3 mars sans erreur.

### L'objet

- `__construct(int|string|null $timestamp = null)` et `create(...)` Un timestamp, une chaîne lisible par `strtotime()`, ou `null` pour maintenant.
- `fromYmd(int $ymd)` Depuis un entier `20260923`, vérifié avec `checkdate()`.
- `format(string $format): string` Comme `date()`, avec les noms français.
- `ymd(): int`, `toSql(): string`, `toIso(): string`
- `isLeap(): bool`, `days(): int` Année bissextile, nombre de jours du mois.
- `modify(string $str): static` Applique un modificateur `strtotime()` (`'+1 day'`, `'next monday'`).
- `$timestamp` Lisible, non modifiable de l'extérieur (`private(set)`).
- `$year`, `$mon`, `$mday`, `$hours`, `$minutes`, `$seconds`, `$wday`, `$yday` Propriétés en lecture, issues de `getdate()`.

```php
$date = Date::create('2026-09-23 14:30:00');
$date->format('l j F Y');   // 'Mercredi 23 Septembre 2026'
$date->year;                // 2026
$date->modify('+1 day')->toSql();  // '2026-09-24 14:30:00'
json_encode($date);         // {"timestamp":…,"year":2026,"mon":9,…}
```

**`modify()` modifie l'objet lui-même** et le retourne : `$demain = $date->modify('+1 day')` change aussi `$date`. Cloner d'abord pour garder l'original : `(clone $date)->modify('+1 day')`. En cas de modificateur invalide, la date reste inchangée.

Une propriété inconnue (`$date->foo`) lève une `InvalidArgumentException`. `isset()` fonctionne sur les huit propriétés, donc `$date->foo ?? $defaut` ne lève rien.

### Helpers statiques

- `isLeapYear(int $y): bool` Année bissextile.
- `daysInMonth(int $m, int $y): int` Nombre de jours dans un mois.
- `seasonDate(int $year, int $season): int` Timestamp UTC de l'équinoxe ou du solstice, à quelques minutes près.
- `sqlToTimestamp(string)`, `isoToTimestamp(string)`, `timestampToSql(int)`, `timestampToIso(int)`, `sqlToIso(string)`, `isoToSql(string)`
- `timestampToYmd(?int $ts = null): int`
- `fr(string $format, int|string|null $timestamp = null): string` `date()` avec les noms français.
- `toFr(string $date): string` Traduit un texte de date anglais.

```php
Date::fr('l j F Y', $ts);           // 'Mercredi 23 Septembre 2026'
Date::fr('D j M Y', '2026-09-23');  // 'Mer. 23 Sept. 2026'
Date::daysInMonth(2, 2024);         // 29
Date::seasonDate(2026, Date::SPRING);
```

Les paramètres sont typés et le fichier n'a pas `strict_types` : `daysInMonth('2', 2024)` fonctionne, `daysInMonth('fev', 2024)` lève une `TypeError`.

`fr()` ne traduit que si le format contient un `D`, `l`, `F` ou `M` non échappé — la décision se prend sur le **format**, pas sur le résultat. Elle repose sur le fait que `date()` émet toujours des noms anglais, donc le `setlocale()` ambiant n'a aucune influence.

`toFr()` est un `strtr()` sans frontière de mot : `toFr('Marching')` retourne `'Marsing'`. À n'appliquer qu'à une sortie de `date()`, jamais à du texte libre.

`seasonDate()` retourne un instant UTC qui ne dépend pas du fuseau horaire ambiant. Pour afficher le jour local, passer par `date()` ; pour le jour UTC, par `gmdate()`. L'équinoxe d'automne 2026 tombe le 23 septembre à 00:05 UTC, soit le 22 au soir à l'ouest de l'UTC. Une saison inconnue lève une `InvalidArgumentException`.

## Crypto

`Bredala\Utils\Crypto` Hachage, aléatoire, mots de passe et chiffrement. Méthodes statiques.

Cette classe remplace les anciennes classes `Hash`, `Password` et `Crypto`.

| Avant | Maintenant |
| ----- | ---------- |
| `Hash::make()` | `Crypto::hash()` |
| `Hash::random()`, `salt()`, `uuid()`, `otp()` | `Crypto::random()`, `salt()`, `uuid()`, `otp()` |
| `(new Password())->setCost($c)->hash($pw)` | `Crypto::passwordHash($pw, $c)` |
| `(new Password())->verify($pw, $h)` | `Crypto::passwordVerify($pw, $h)` |
| `Crypto::make()->encode($s, $k)` | `Crypto::encrypt($s, $k)` |
| `Crypto::make()->decode($s, $k)` | `Crypto::decrypt($s, $k)` |

### Hachage et aléatoire

- `hash(string $string, int $length = 40): string` Hachage hexadécimal de longueur fixe.
- `random(int $length = 40): string` Chaîne hexadécimale aléatoire de longueur exacte.
- `salt(int $length = 40): string` Hachage d'octets aléatoires.
- `uuid(): string` UUID v7 (RFC 9562) : horodatage en millisecondes suivi de bits aléatoires, triable chronologiquement.
- `otp(int $len = 6): string` Code numérique cryptographiquement sûr (`random_int`), complété par des zéros. Longueur de 1 à 18.

L'algorithme de `hash()` et `salt()` est choisi par la longueur demandée : 32 pour md5, 40 pour sha1, 64 pour sha256, 128 pour sha512. **Toute autre longueur retombe silencieusement sur sha1** (40) : `hash($s, 50)` ne lève aucune erreur et retourne 40 caractères.

`random()` respecte exactement la longueur demandée, y compris impaire. `random(0)` lève une `ValueError`.

`uuid()` expose sa date de création dans ses 12 premiers caractères hexadécimaux. Deux UUID créés dans la même milliseconde ne sont pas ordonnés entre eux.

**`otp()` lève une `ValueError` hors de 1 à 18 chiffres** : au-delà, `10 ** $len` ne tient plus dans un entier PHP 64 bits. Une longueur calculée qui tombe à 0 fait donc planter l'appel.

### Mots de passe

- `passwordHash(string $password, ?int $cost = null): string` Retourne un hachage bcrypt de 60 caractères. Un coût falsy vaut le coût par défaut de bcrypt.
- `passwordVerify(string $password, string $hash): bool`

```php
$hash = Crypto::passwordHash($plaintext);
Crypto::passwordVerify($plaintext, $hash);
```

bcrypt génère un sel aléatoire propre à chaque hachage : c'est pourquoi deux hachages du même mot de passe diffèrent toujours, et pourquoi il faut systématiquement passer par `passwordVerify()`.

`passwordVerify()` retourne `false` sans lever d'exception pour un mot de passe vide, un hachage vide ou un hachage malformé.

### Chiffrement

- `encrypt(string $str, string $key): string` Chiffre en AES-256-GCM et encode en base64.
- `decrypt(string $str, string $key): string` Déchiffre. Retourne `''` en cas d'échec.

```php
$cipher = Crypto::encrypt($plaintext, $key);
$plain = Crypto::decrypt($cipher, $key);
```

Chaque appel à `encrypt()` tire un IV aléatoire de 12 octets : deux chiffrements du même texte avec la même clé donnent deux chiffrés différents. Le résultat est `base64(iv . tag . chiffré)`, avec une marque d'authentification GCM de 16 octets. Un chiffré modifié ou tronqué est rejeté au déchiffrement.

La clé passe par `hash('sha256', $key, true)`, donc toute longueur est acceptée. Ce n'est pas une dérivation adaptée à un mot de passe : utiliser une clé longue et aléatoire, ou dériver la clé en amont avec `hash_pbkdf2()` ou `sodium_crypto_pwhash()`.

`decrypt()` retourne `''` pour du base64 invalide, un chiffré trop court, une mauvaise clé ou un chiffré altéré. Ces cas sont indiscernables entre eux, et aussi d'un texte vide chiffré légitimement.

Le format n'est pas compatible avec l'ancienne classe `Crypto` (AES-256-CBC, IV dérivé de la clé) : les données chiffrées avec elle ne se déchiffrent plus et doivent être migrées avec l'ancien code.

## IP

`Bredala\Utils\IP` Adresses IPv4 et IPv6. Remplace `ip2long` et `long2ip`.

### Construction

- `__construct(string $ip, string $from)` `$from` valant `'str'`, `'hex'` ou `'bin'`.
- `fromString(string $ip)`
- `fromBin(string $ip)`
- `fromHex(string $ip)`

### Lecture

- `isIPv4()` / `isIPv6()`
- `toString()` / `toBin()` / `toHex()`

### Validation

- `isIPStr(string $ip)` / `isIPv6Str(string $ip)` Via `filter_var`.
- `isIPBin(string $ip)` / `isIPv6Bin(string $ip)`
- `isIPHex(string $ip)` / `isIPv6Hex(string $ip)`

### Conversions pures

- `binToHex(string $ip, bool $v6 = false)`
- `binToStr(string $ip, bool $v6 = false)`
- `hexToBin(string $ip, bool $v6 = false)`
- `strToBin(string $ip, bool $v6 = false)`

```php
$ip = IP::fromString('192.168.1.1');
$ip->isIPv6();  // false
$ip->toHex();   // 'c0a80101'
$ip->toBin();   // '11000000101010000000000100000001'
```

Trois points à connaître :

- **Valider avant de construire.** Une entrée invalide devient silencieusement `0.0.0.0`, indiscernable d'une véritable adresse nulle. Aucun chemin ne lève d'exception, y compris un `$from` inconnu. Passer par `IP::isIPStr()` en amont. À noter que `isIPBin('')` et `isIPHex('')` valent `true` (leurs motifs sont `{0,128}` et `{0,32}`) : tester la vacuité séparément.
- **Les formes binaire et hexadécimale IPv4 ne sont pas zéro-remplies.** Les zéros de tête sont supprimés, donc la largeur varie : `192.168.1.1` donne 8 caractères hexadécimaux, `10.0.0.1` en donne 7, `0.0.0.0` donne `'0'`. Le stockage en colonne de largeur fixe et le **tri lexical** en souffrent (`0.255.0.1` vaut `'ff0001'` et se trie *après* `'1000001'` pour `1.0.0.1`). Compléter avec `str_pad($hex, 32, '0', STR_PAD_LEFT)`. La forme hexadécimale IPv6, elle, fait toujours 32 caractères.
- **Conserver la version à part.** Les conversions statiques lèvent une `TypeError` sur des données IPv6 sans `$v6 = true`. Et comme `isIPv6Hex()` ne teste que la longueur, une valeur IPv4 complétée à 32 caractères pour le stockage sera déclarée IPv6 — la version doit vivre dans sa propre colonne, pas être déduite de la largeur.

## Counter

`Bredala\Utils\Counter` Compteurs entiers nommés. Chaque méthode retourne la valeur résultante.

- `set(string $name, int $value = 0): int`
- `get(string $name): int`
- `reset(string $name): int` Remet à zéro. Ce n'est pas une suppression.
- `increment(string $name, int $value = 1): int`
- `decrement(string $name, int $value = 1): int`

Il n'y a **pas de plancher à zéro** : `decrement()` sur un compteur inconnu retourne `-1`, et `increment($name, -5)` décrémente.

## Bench

`Bredala\Utils\Bench` Marques et intervalles de temps.

- `mark(string $label)` Ajoute une marque.
- `remove(string $label)` Supprime un libellé.
- `time(string $label, int $unit = 1): array` Intervalles entre marques consécutives.
- `times(int $unit = 1): array` Tous les libellés.

```php
Bench::mark('import');
// ... traitement ...
Bench::mark('import');
Bench::time('import', 1000);   // [durée_en_ms]
```

`time()` retourne des **intervalles**, pas des horodatages, et exige au moins deux marques : une marque seule donne `[]`, comme un libellé inconnu. `$unit` est un simple multiplicateur, donc `$unit = 0` aplatit tous les intervalles à `0.0`. `time()` ne consomme pas les marques.

`Counter` et `Bench` sont des états statiques globaux sans purge. Préfixer les clés par domaine, et s'attendre à ce qu'elles survivent d'une requète à l'autre en processus long.

## File

`Bredala\Utils\File` Enveloppe autour d'un fichier. Retourne `null` en cas d'échec plutôt que de lever une exception — il faut donc vérifier chaque valeur de retour, il n'y a pas de message d'erreur.

- `__construct(string $filename)`, `getFilename(): string`, `isFile(): bool`
- Fichier entier : `get(): ?string`, `json(): ?array`, `put(string $data): ?int`, `delete(): static`
- Flux : `isOpen(): bool`, `open(string $mode = 'r+'): bool`, `close(): bool`, `stream()`, `eof(): bool`
- Lecture : `read(): ?string`, `readCsv(string $delimiter = ',', string $enclosure = '"', string $escape_char = '\\'): ?array`
- Écriture : `add(string $text): ?int`, `line(string $text): ?int`, `csv(array $data, string $delimiter = ",", string $enclosure = '"', string $escape = "\\"): ?int`
- Statiques : `unlink(string $file)`, `deleteDirRecursive(string $dir): bool`

`deleteDirRecursive()` **supprime des répertoires** : ne jamais lui passer un chemin construit depuis une donnée non maîtrisée.

Cette classe n'est pas couverte par la suite de tests du package.

## Image

`Bredala\Utils\Image` Étend `Imagick`. Nécessite l'extension `imagick` et de vrais fichiers sur disque.

- Construction : `__construct(...$files)`, `create(...$files): static`
- Lecture : `getBgColor(): string`, `getQuality(): int`, `getName(): string`, `getFileSize(): int`, `getImageMimeType(): string`, `getImageLength(): int`
- Écriture : `setBgColor(string $color)`, `setQuality(int $quality)`
- Transformations fluides : `prepare()`, `cmynToRgb()`, `stripExif()`, `fitBounds(int $width, int $height, bool $strict_orientation = false)`, `crop(array $data)`, `watermark(Image $img, int $x, int $y)`, `toJpeg(bool $force = false)`, `toPng(bool $force = false)`, `toWebp(bool $force = false)`
- Tests : `isInside(int $width, int $height, bool $strict_orientation = false): bool`, `hasFormat(string $format): bool`
- Sortie : `save(?string $to = null): static` Retourne une **nouvelle** instance si le nom de fichier change.
- Empreinte perceptuelle : `getHash()`, `similarHash(string $hash1, string $hash2, float &$percent = 0): int`

La sortie WebP exige en plus le support WebP compilé dans ImageMagick. Comme la classe étend une classe native, elle n'est pas mockable. Elle n'est pas couverte par la suite de tests du package.

## Utilisation

```php
use Bredala\Utils\ArrayHelper;
use Bredala\Utils\Crypto;
use Bredala\Utils\Date;
use Bredala\Utils\IP;
use Bredala\Utils\TextHelper;

// Création d'un article
$title = 'Les Éléphants d\'Afrique !';

$article = [
    'title' => $title,
    'slug' => TextHelper::toKebabCase($title),
    'token' => Crypto::random(32),
    'published_at' => Date::create()->format('l j F Y'),
];

print_r($article);

// Inscription d'un utilisateur
$user = [
    'email' => 'tom@example.test',
    'password' => Crypto::passwordHash('correct horse battery staple'),
];

var_dump(Crypto::passwordVerify('correct horse battery staple', $user['password']));

// Journalisation d'une IP, en largeur fixe
$raw = '10.0.0.1';

if (IP::isIPStr($raw)) {
    $ip = IP::fromString($raw);
    $stored = [
        'ip' => str_pad($ip->toHex(), 32, '0', STR_PAD_LEFT),
        'is_v6' => $ip->isIPv6(),
    ];
    print_r($stored);
}

// Clés étrangères distinctes d'un jeu de résultats
$rows = [['author_id' => 1], ['author_id' => 2], ['author_id' => 1], ['author_id' => null]];
print_r(ArrayHelper::unique($rows, 'author_id'));
```

## Tests

```bash
composer install
vendor/bin/phpunit
```

`Image` et `File` ne sont pas couverts : la première exige `ext-imagick` et des fichiers images réels, la seconde des accès au système de fichiers.
