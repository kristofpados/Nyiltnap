<?php

declare(strict_types=1);

namespace App\Core;

/** Read-only helpers around the current HTTP request. */
final class Request
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isReadOnly(): bool
    {
        return in_array(self::method(), ['GET', 'HEAD'], true);
    }

    /**
     * URL prefix of the application, e.g. "" (own vhost / built-in server)
     * or "/nyiltnap-kezelo/public" (XAMPP sub-folder).
     */
    public static function basePath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/.');
        if ($script === '') {
            return '';
        }

        $uriPath = self::rawPath();
        if ($dir !== '' && !self::pathHasPrefix($uriPath, $dir) && str_ends_with($dir, '/public')) {
            // Project root used as document root: the root .htaccess rewrites
            // into public/ while visible URLs omit the "/public" segment.
            $withoutPublic = substr($dir, 0, -strlen('/public'));
            if ($withoutPublic === '' || self::pathHasPrefix($uriPath, $withoutPublic)) {
                $dir = $withoutPublic;
            }
        }

        return $dir;
    }

    /** Application-relative path, always starting with "/" and without a trailing slash. */
    public static function path(): string
    {
        $path = rawurldecode(self::rawPath());
        $base = self::basePath();
        if ($base !== '' && self::pathHasPrefix($path, $base)) {
            $path = substr($path, strlen($base));
        }
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }

        $path = '/' . trim($path, '/');

        return $path;
    }

    /** Path plus query string, used for the post-login "next" target. */
    public static function pathWithQuery(): string
    {
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');

        return self::path() . ($query !== '' ? '?' . $query : '');
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /** A query/body value guaranteed to be a string (arrays and missing values become $default). */
    public static function string(array $source, string $key, string $default = ''): string
    {
        $value = $source[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public static function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        if ((bool) Config::get('app.trust_proxy', false)) {
            return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        }

        return false;
    }

    public static function ip(): string
    {
        if ((bool) Config::get('app.trust_proxy', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private static function rawPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = explode('?', $uri, 2)[0];

        return $path === '' ? '/' : $path;
    }

    private static function pathHasPrefix(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }
}
