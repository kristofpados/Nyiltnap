<?php

declare(strict_types=1);

namespace App\Core;

use Normalizer;

/** Reusable input normalisation and checks; field rules live in app/Forms. */
final class Validator
{
    /**
     * Normalises a single-line text field: rejects non-strings and invalid
     * UTF-8 (returns null), normalises to NFC when intl is available,
     * converts any Unicode whitespace run to one space and trims the ends.
     */
    public static function singleLine(mixed $value): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);
            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        $value = (string) preg_replace('/[\s\x{00A0}\x{1680}\x{2000}-\x{200B}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]+/u', ' ', $value);

        return trim($value);
    }

    public static function length(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    /** True for control, format, private-use or unassigned code points. */
    public static function hasInvisibleCharacters(string $value): bool
    {
        return preg_match('/[\p{Cc}\p{Cf}\p{Co}\p{Cn}]/u', $value) === 1;
    }

    public static function countLetters(string $value): int
    {
        return (int) preg_match_all('/\p{L}/u', $value);
    }

    /** Accepts "1".."9999999999" as a string, nothing else (no signs, spaces, decimals). */
    public static function isIdString(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[1-9][0-9]{0,9}$/', $value) === 1;
    }
}
