<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\AdminUser;

/**
 * Session-based admin authentication. Every protected controller action
 * calls Auth::requireAdmin() — hiding links in the UI is never relied on.
 */
final class Auth
{
    /** @var array<string, mixed>|null */
    private static ?array $user = null;
    private static bool $resolved = false;

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }
        self::$resolved = true;

        $id = $_SESSION['admin_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }

        $lastSeen = (int) ($_SESSION['admin_last_seen'] ?? 0);
        if (time() - $lastSeen > (int) Config::get('session.idle_timeout', 7200)) {
            self::logout();
            Session::flash('info', 'Biztonsági okokból kijelentkeztettünk, mert egy ideje nem volt aktivitás. Kérjük, jelentkezz be újra.');

            return null;
        }

        // Re-check the account on every request so a removed admin loses access immediately.
        $user = AdminUser::findById($id);
        if ($user === null) {
            self::logout();

            return null;
        }

        $_SESSION['admin_last_seen'] = time();

        return self::$user = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** @param array<string, mixed> $user */
    public static function login(array $user): void
    {
        // New session ID on privilege change prevents session fixation.
        Session::regenerate();
        Csrf::regenerate();
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_last_seen'] = time();
        self::$user = $user;
        self::$resolved = true;
    }

    public static function logout(): void
    {
        Session::reset();
        self::$user = null;
        self::$resolved = true;
    }

    /**
     * Returns the signed-in admin or stops the request: anonymous GETs are
     * sent to the login page, anything else gets a 403.
     *
     * @return array<string, mixed>
     */
    public static function requireAdmin(): array
    {
        $user = self::user();
        if ($user !== null) {
            return $user;
        }

        if (Request::isReadOnly()) {
            Response::redirect('/admin/login', ['next' => Request::pathWithQuery()], 302);
        }

        throw new HttpException(403, 'Ehhez a művelethez be kell jelentkezned.');
    }
}
