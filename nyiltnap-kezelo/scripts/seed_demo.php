<?php

declare(strict_types=1);

/*
 * ⚠ CSAK FEJLESZTÉSHEZ / BEMUTATÓHOZ ⚠
 *
 * Kitalált (nem valós) jelentkezőket szúr be, hogy az adminfelület
 * bemutatható legyen. Éles környezetben (APP_ENV=production) nem fut le.
 *
 *   php scripts/seed_demo.php --yes            # 48 demó jelentkezés
 *   php scripts/seed_demo.php --yes --count=120
 *
 * Az iskolanevek szándékosan „Minta/Példa” jellegűek, a nevek véletlenszerű
 * kombinációk — egyik sem valós személy vagy intézmény adata.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Models\Program;
use App\Models\Registration;

$args = array_slice($argv, 1);
$env = (string) config('app.env');

if ($env === 'production') {
    fwrite(STDERR, "✗ APP_ENV=production: a demó adatok feltöltése éles környezetben tiltott.\n");
    exit(1);
}
if (!in_array('--yes', $args, true)) {
    fwrite(STDERR, "Ez a parancs KITALÁLT demó jelentkezőket ad hozzá a(z) „" . config('db.name') . "” adatbázishoz.\n");
    fwrite(STDERR, "Ha biztosan ezt szeretnéd, futtasd így: php scripts/seed_demo.php --yes\n");
    exit(1);
}

$count = 48;
foreach ($args as $arg) {
    if (preg_match('/^--count=(\d{1,4})$/', $arg, $m) === 1) {
        $count = max(1, min(2000, (int) $m[1]));
    }
}

$programs = array_values(Program::active());
if ($programs === []) {
    fwrite(STDERR, "✗ Nincs aktív szakma. Előbb futtasd: php scripts/install.php\n");
    exit(1);
}

$surnames = ['Nagy', 'Kovács', 'Tóth', 'Szabó', 'Horváth', 'Varga', 'Kiss', 'Molnár', 'Németh', 'Farkas', 'Balogh', 'Papp', 'Takács', 'Juhász', 'Mészáros', 'Simon', 'Rácz', 'Fekete', 'Szilágyi', 'Török', 'Fehér', 'Gál', 'Szűcs', 'Kocsis', 'Pintér', 'Fodor', 'Sipos', 'Lukács', 'Bíró', 'Katona', 'Fazekas', 'Kelemen', 'Hegedűs', 'Deák', 'Fülöp', 'Vörös', 'Bognár', 'Halász', 'Székely', 'Gáspár', 'Szőke-Nagy', 'Ősz'];
$given = ['Anna', 'Bence', 'Máté', 'Levente', 'Dominik', 'Dániel', 'Ádám', 'Olivér', 'Zsófia', 'Hanna', 'Luca', 'Lili', 'Boglárka', 'Jázmin', 'Emma', 'Réka', 'Eszter', 'Dóra', 'Kristóf', 'Botond', 'Marcell', 'Gergő', 'Balázs', 'Zalán', 'Benedek', 'Hunor', 'Áron', 'Nóra', 'Petra', 'Csenge', 'Gréta', 'Barnabás', 'Milán', 'Örs', 'Ödön', 'Ünige'];
$schools = ['Minta Általános Iskola', 'Példa Utcai Általános Iskola', 'Kertvárosi Mintaiskola', 'Napfény Minta Általános Iskola', 'Tölgyfa Utcai Példaiskola', 'Mintavárosi Általános Iskola', 'Folyóparti Példa Iskola', 'Hegyalja Mintaiskola'];

// Programs get different popularity so the charts look realistic.
$weights = [];
foreach ($programs as $i => $program) {
    $weights[$program['id']] = max(1, count($programs) - $i + random_int(0, 3));
}
$pickProgram = static function () use ($weights): int {
    $roll = random_int(1, array_sum($weights));
    foreach ($weights as $id => $weight) {
        $roll -= $weight;
        if ($roll <= 0) {
            return $id;
        }
    }

    return (int) array_key_first($weights);
};

$created = 0;
for ($i = 0; $i < $count; $i++) {
    // A few deliberately identical names show that duplicates are accepted.
    $name = ($i % 17 === 5) ? 'Kovács Anna' : $surnames[array_rand($surnames)] . ' ' . $given[array_rand($given)];
    $picked = [];
    $howMany = random_int(1, 100) <= 55 ? 1 : (random_int(1, 100) <= 75 ? 2 : 3);
    while (count($picked) < min($howMany, count($programs))) {
        $picked[$pickProgram()] = true;
    }

    $daysAgo = (int) floor(($count - $i) / max(1, $count) * 13);
    $createdAt = (new DateTimeImmutable('today'))
        ->modify("-{$daysAgo} days")
        ->setTime(random_int(8, 20), random_int(0, 59), random_int(0, 59));
    if ($createdAt > new DateTimeImmutable('now')) {
        $createdAt = new DateTimeImmutable('now');
    }

    Registration::create(
        $name,
        $schools[array_rand($schools)],
        random_int(1, 100) <= 42 ? Registration::BOARDING : Registration::COMMUTER,
        random_int(1, 100) <= 64,
        array_keys($picked),
        $createdAt->format('Y-m-d H:i:s'),
    );
    $created++;
}

echo "✓ {$created} kitalált demó jelentkezés rögzítve a(z) „" . config('db.name') . "” adatbázisban." . PHP_EOL;
echo "  Törlés: az adminfelületen soronként, vagy fejlesztői adatbázisnál: TRUNCATE-tel." . PHP_EOL;
