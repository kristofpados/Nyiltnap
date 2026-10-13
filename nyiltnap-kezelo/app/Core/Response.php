<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * Sends baseline security headers. The CSP allows only same-origin
     * scripts, styles, fonts and images — the app uses no inline scripts or
     * inline style attributes, so no 'unsafe-inline' is needed.
     */
    public static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
        // Pages contain CSRF tokens and (in the admin) personal data: never cache.
        header('Cache-Control: no-store, max-age=0');
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        echo $body;
    }

    /**
     * Redirects to an application path (base path is added automatically).
     * 303 after POST makes the browser follow up with GET (Post/Redirect/Get).
     *
     * @param array<string, scalar> $query
     */
    public static function redirect(string $path, array $query = [], int $status = 303): never
    {
        header('Location: ' . url($path, $query), true, $status);
        exit;
    }
}
