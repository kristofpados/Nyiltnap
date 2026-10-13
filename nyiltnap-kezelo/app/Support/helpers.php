<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;

/** Escapes any value for HTML text and attribute contexts. Use for ALL dynamic output. */
function e(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/**
 * Builds an application URL including the base path.
 *
 * @param array<string, scalar|null> $query empty/null values are dropped
 */
function url(string $path = '/', array $query = []): string
{
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
    $queryString = $query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $base = Request::basePath();

    return ($base === '' ? $path : $base . ($path === '/' ? '/' : $path)) . $queryString;
}

/** URL of a file in public/assets with a cache-busting version parameter. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return url('/assets/' . $path) . '?v=' . $version;
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . e(Csrf::token()) . '">';
}

/** Inline reference to a symbol of the SVG sprite (app/Views/partials/icons.php). */
function icon(string $name, string $class = ''): string
{
    $safe = preg_replace('/[^a-z0-9\-]/', '', $name);

    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false">'
        . '<use href="#i-' . $safe . '"></use></svg>';
}

/** Icon names a program may use (kept in sync with the sprite). */
function program_icon(?string $name): string
{
    $allowed = ['code', 'gear', 'chip', 'robot', 'pen', 'bolt', 'layers', 'school', 'users'];

    return icon(in_array($name, $allowed, true) ? (string) $name : 'layers');
}

/* ---------------------------------------------------------------------
 * Hungarian formatting
 * ------------------------------------------------------------------- */

function hu_month(int $month, bool $short = false): string
{
    $long = ['január', 'február', 'március', 'április', 'május', 'június', 'július', 'augusztus', 'szeptember', 'október', 'november', 'december'];
    $abbr = ['jan.', 'febr.', 'márc.', 'ápr.', 'máj.', 'jún.', 'júl.', 'aug.', 'szept.', 'okt.', 'nov.', 'dec.'];

    return ($short ? $abbr : $long)[$month - 1] ?? '';
}

function to_datetime(string|DateTimeInterface|null $value): ?DateTimeImmutable
{
    if ($value === null || $value === '') {
        return null;
    }
    if ($value instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($value);
    }
    try {
        return new DateTimeImmutable($value);
    } catch (Exception) {
        return null;
    }
}

/** "2026. október 13., 10:42" */
function hu_datetime(string|DateTimeInterface|null $value, bool $withTime = true): string
{
    $dt = to_datetime($value);
    if ($dt === null) {
        return '–';
    }
    $text = $dt->format('Y') . '. ' . hu_month((int) $dt->format('n')) . ' ' . $dt->format('j') . '.';

    return $withTime ? $text . ', ' . $dt->format('G:i') : $text;
}

/** "ma, 10:42" / "tegnap, 18:05" / "okt. 3., 9:15" / "2025. okt. 3." for compact tables. */
function hu_relative_datetime(string|DateTimeInterface|null $value): string
{
    $dt = to_datetime($value);
    if ($dt === null) {
        return '–';
    }
    $today = new DateTimeImmutable('today');
    $day = $dt->setTime(0, 0);
    $time = $dt->format('G:i');

    if ($day == $today) {
        return 'ma, ' . $time;
    }
    if ($day == $today->modify('-1 day')) {
        return 'tegnap, ' . $time;
    }
    $short = hu_month((int) $dt->format('n'), true) . ' ' . $dt->format('j') . '.';
    if ($dt->format('Y') === $today->format('Y')) {
        return $short . ', ' . $time;
    }

    return $dt->format('Y') . '. ' . $short;
}

/** "okt. 13." — used under chart columns. */
function hu_short_date(string|DateTimeInterface|null $value): string
{
    $dt = to_datetime($value);

    return $dt === null ? '–' : hu_month((int) $dt->format('n'), true) . ' ' . $dt->format('j') . '.';
}

/** Hungarian thousands separator (narrow no-break space). */
function hu_number(int|float $value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals, ',', "\u{202F}");
}

function percent_of(int $part, int $total): string
{
    if ($total <= 0) {
        return '0%';
    }

    return hu_number(round($part / $total * 100)) . '%';
}

/**
 * Two complementary percentages (A and the rest) that always add up to
 * 100%, so a split such as boarding/commuter never reads "38% + 63%".
 *
 * @return array{0: string, 1: string}
 */
function percent_pair(int $part, int $total): array
{
    if ($total <= 0) {
        return ['0%', '0%'];
    }
    $first = (int) round($part / $total * 100);

    return [$first . '%', (100 - $first) . '%'];
}

/** Share in the 0–100 range for chart geometry (SVG attributes, not text). */
function share(int $part, int $max): float
{
    return $max > 0 ? round($part / $max * 100, 2) : 0.0;
}

function residency_label(string $value): string
{
    return $value === 'boarding' ? 'Kollégista' : 'Bejárós';
}

function graduating_label(bool|int $value): string
{
    return (bool) $value ? 'Végzős' : 'Nem végzős';
}

/** Displays a stored 8-char reference as "ABCD-EFGH". */
function format_reference(string $code): string
{
    return strlen($code) === 8 ? substr($code, 0, 4) . '-' . substr($code, 4) : $code;
}

/** Initials for the admin avatar chip. */
function initials(string $name): string
{
    $parts = preg_split('/[\s._\-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $letters !== '' ? $letters : 'A';
}

/**
 * Validates a post-login / post-action redirect target so it can only
 * point inside the admin area (prevents open redirects).
 */
function safe_admin_path(mixed $target, string $fallback = '/admin'): string
{
    if (!is_string($target) || $target === '' || strlen($target) > 500) {
        return $fallback;
    }
    if (preg_match('#^/admin(?:/[A-Za-z0-9/_\-]*)?(?:\?[A-Za-z0-9_\-.~%&=+\[\]]*)?$#', $target) !== 1) {
        return $fallback;
    }
    if (str_contains($target, '//') || str_contains($target, '\\')) {
        return $fallback;
    }
    $path = explode('?', $target, 2)[0];
    if (in_array(rtrim($path, '/'), ['/admin/login', '/admin/logout'], true)) {
        return $fallback;
    }

    return $target;
}
