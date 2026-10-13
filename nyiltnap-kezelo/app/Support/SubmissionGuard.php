<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One-time token per rendered form. A double-click or a re-sent POST that
 * carries an already-used token is answered with the existing success page
 * instead of a second insert. A freshly loaded form always gets a new
 * token, so deliberately registering again (even with identical data)
 * is never blocked.
 */
final class SubmissionGuard
{
    private const ISSUED = '_form_tokens';
    private const USED = '_used_form_tokens';
    private const KEEP = 20;

    public static function issue(): string
    {
        $token = bin2hex(random_bytes(16));
        $issued = is_array($_SESSION[self::ISSUED] ?? null) ? $_SESSION[self::ISSUED] : [];
        $issued[$token] = time();
        $_SESSION[self::ISSUED] = array_slice($issued, -self::KEEP, null, true);

        return $token;
    }

    public static function wasUsed(string $token): bool
    {
        return $token !== '' && isset($_SESSION[self::USED][$token]);
    }

    public static function markUsed(string $token): void
    {
        if ($token === '' || preg_match('/^[a-f0-9]{32}$/', $token) !== 1) {
            return;
        }
        unset($_SESSION[self::ISSUED][$token]);
        $used = is_array($_SESSION[self::USED] ?? null) ? $_SESSION[self::USED] : [];
        $used[$token] = time();
        $_SESSION[self::USED] = array_slice($used, -self::KEEP, null, true);
    }
}
