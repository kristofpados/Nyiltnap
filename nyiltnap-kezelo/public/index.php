<?php

declare(strict_types=1);

/*
 * Front controller — the only PHP file reachable from the web.
 * Document root must be this public/ folder (see README → Telepítés).
 */

// PHP built-in server: let it serve real static files directly.
if (PHP_SAPI === 'cli-server') {
    $requested = realpath(__DIR__ . rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)));
    if ($requested !== false && is_file($requested) && str_starts_with($requested, __DIR__ . DIRECTORY_SEPARATOR) && $requested !== __FILE__) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

App\Core\Response::sendSecurityHeaders();
App\Core\Session::start();

$router = new App\Core\Router();
(require BASE_PATH . '/app/Config/routes.php')($router);
$router->dispatch(App\Core\Request::method(), App\Core\Request::path());
