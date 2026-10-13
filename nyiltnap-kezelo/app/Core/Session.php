<?php

declare(strict_types=1);

namespace App\Core;

/** Hardened PHP session plus a small flash-message store. */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $setting = strtolower((string) Config::get('session.secure', 'auto'));
        $secure = $setting === 'auto' ? Request::isHttps() : in_array($setting, ['1', 'true', 'yes', 'on'], true);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');

        $name = (string) Config::get('session.name', 'nyiltnap_session');
        // The __Host- prefix makes browsers refuse the cookie unless it is
        // Secure, host-only and scoped to "/" — only possible over HTTPS.
        session_name($secure ? '__Host-' . $name : $name);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Wipes all session data and issues a fresh session ID. */
    public static function reset(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** @param 'success'|'error'|'info' $type */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    public static function takeFlashes(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($messages) ? array_values($messages) : [];
    }
}
