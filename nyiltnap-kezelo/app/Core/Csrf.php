<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Synchronizer-token CSRF protection: one random token per session,
 * compared in constant time on every state-changing POST.
 */
final class Csrf
{
    public const FIELD = '_csrf';

    public static function token(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || strlen($_SESSION['_csrf']) !== 64) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function regenerate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function isValid(mixed $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? null;

        return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
    }

    public static function isValidRequest(): bool
    {
        return self::isValid($_POST[self::FIELD] ?? null);
    }

    /** @throws HttpException 403 when the token is missing or wrong */
    public static function verify(): void
    {
        if (!self::isValidRequest()) {
            throw new HttpException(403, 'A biztonsági token hiányzik vagy lejárt. Töltsd újra az oldalt, és próbáld meg ismét.');
        }
    }
}
