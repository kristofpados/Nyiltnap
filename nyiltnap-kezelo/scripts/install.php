<?php

declare(strict_types=1);

/*
 * Adatbázis telepítése / frissítése (idempotens).
 *
 *   php scripts/install.php
 *
 * 1. Létrehozza a DB_NAME adatbázist, ha még nincs (és van hozzá jogosultság).
 * 2. Lefuttatja a database/schema.sql és database/seed.sql fájlokat.
 * Meglévő táblát és adatot nem töröl, így többször is futtatható.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\DatabaseUnavailableException;
use App\Support\SqlFile;

$config = (array) Config::get('db');
$name = (string) $config['name'];

echo PHP_EOL . 'Nyílt nap kezelő — adatbázis telepítése' . PHP_EOL;
echo str_repeat('─', 44) . PHP_EOL;

if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1) {
    fwrite(STDERR, "Érvénytelen DB_NAME: csak betű, szám és aláhúzás engedélyezett.\n");
    exit(1);
}

try {
    $server = Database::connect($config, false);
} catch (DatabaseUnavailableException $e) {
    fwrite(STDERR, "Nem sikerült csatlakozni a MySQL/MariaDB szerverhez ({$config['host']}:{$config['port']}).\n");
    fwrite(STDERR, 'Részletek: ' . ($e->getPrevious()?->getMessage() ?? $e->getMessage()) . "\n");
    fwrite(STDERR, "Ellenőrizd, hogy fut-e az adatbázis-szerver, és helyesek-e a .env beállításai.\n");
    exit(1);
}

try {
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "  ✓ Adatbázis rendben: {$name}" . PHP_EOL;
} catch (PDOException $e) {
    echo "  ! Az adatbázist nem tudtam létrehozni (valószínűleg nincs CREATE jogosultság)." . PHP_EOL;
    echo "    Feltételezem, hogy a tárhelyen már létezik: {$name}" . PHP_EOL;
}
$server = null;

$pdo = Database::connect($config, true);
Database::setConnection($pdo);

foreach (['schema.sql', 'seed.sql'] as $file) {
    $statements = SqlFile::run($pdo, BASE_PATH . '/database/' . $file);
    echo "  ✓ database/{$file} ({$statements} utasítás)" . PHP_EOL;
}

$programs = (int) $pdo->query('SELECT COUNT(*) FROM programs WHERE is_active = 1')->fetchColumn();
$registrations = (int) $pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$admins = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();

echo PHP_EOL;
echo "  Aktív szakmák:   {$programs}" . PHP_EOL;
echo "  Jelentkezések:   {$registrations}" . PHP_EOL;
echo "  Admin fiókok:    {$admins}" . PHP_EOL . PHP_EOL;

if ($admins === 0) {
    echo 'Következő lépés: hozd létre az első admin fiókot:' . PHP_EOL;
    echo '  php scripts/create_admin.php' . PHP_EOL . PHP_EOL;
}
