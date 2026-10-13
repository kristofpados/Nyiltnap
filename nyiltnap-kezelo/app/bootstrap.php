<?php

declare(strict_types=1);

/*
 * Shared bootstrap for the web front controller, CLI scripts and tests.
 * No Composer needed: a tiny PSR-4 autoloader maps App\* to app/*.
 */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit("Nyílt nap kezelő: PHP 8.1 vagy újabb szükséges.\n");
}

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Support/helpers.php';

App\Core\Env::load(BASE_PATH . '/.env');
App\Core\Config::load(require BASE_PATH . '/app/Config/config.php');

date_default_timezone_set((string) config('app.timezone', 'Europe/Budapest'));
mb_internal_encoding('UTF-8');

App\Core\ErrorHandler::register();
