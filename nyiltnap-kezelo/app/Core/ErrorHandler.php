<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Turns PHP errors into exceptions, logs unexpected failures to
 * storage/logs/app.log (never to the browser in production) and renders
 * designed, generic Hungarian error pages.
 */
final class ErrorHandler
{
    private const LOG_FILE = '/storage/logs/app.log';

    public static function register(): void
    {
        $debug = (bool) Config::get('app.debug', false);

        error_reporting(E_ALL);
        ini_set('display_errors', $debug && PHP_SAPI === 'cli' ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
        // Keep function arguments (which may contain form data) out of traces.
        ini_set('zend.exception_ignore_args', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            if (($severity & (E_DEPRECATED | E_USER_DEPRECATED)) !== 0) {
                self::writeLog('DEPRECATED', $message . ' in ' . $file . ':' . $line);

                return true;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                self::handle(new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']));
            }
        });
    }

    public static function handle(Throwable $e): void
    {
        if (PHP_SAPI === 'cli') {
            self::report($e);
            fwrite(STDERR, 'Hiba: ' . $e->getMessage() . PHP_EOL);
            exit(1);
        }

        $headers = [];
        if ($e instanceof HttpException) {
            $status = $e->status;
            $headers = $e->headers;
        } elseif ($e instanceof DatabaseUnavailableException) {
            self::report($e);
            $status = 503;
            $headers['Retry-After'] = '120';
        } else {
            self::report($e);
            $status = 500;
        }

        self::render($status, $e instanceof HttpException ? $e->getMessage() : '', $headers, $e);
    }

    /** @param array<string, string> $headers */
    public static function render(int $status, string $message = '', array $headers = [], ?Throwable $e = null): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }

        $debug = (bool) Config::get('app.debug', false);

        try {
            echo View::render('errors/error', [
                'title'   => 'Hiba (' . $status . ')',
                'status'  => $status,
                'message' => $message,
                'debug'   => $debug && $e !== null && !($e instanceof HttpException) ? $e : null,
            ], 'error');
        } catch (Throwable $renderFailure) {
            self::report($renderFailure);
            echo '<!doctype html><meta charset="utf-8"><title>Hiba</title>'
                . '<p>Váratlan hiba történt. Kérjük, próbáld újra később.</p>';
        }
    }

    public static function report(Throwable $e): void
    {
        $lines = [sprintf('%s(%s): %s in %s:%d', $e::class, (string) $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine())];

        // File/line/function only — never arguments, which could hold personal data.
        foreach (array_slice($e->getTrace(), 0, 12) as $i => $frame) {
            $lines[] = sprintf(
                '  #%d %s:%s %s%s%s()',
                $i,
                $frame['file'] ?? '[internal]',
                $frame['line'] ?? '-',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? '',
            );
        }
        if ($e->getPrevious() !== null) {
            $previous = $e->getPrevious();
            $lines[] = sprintf('  caused by %s(%s): %s', $previous::class, (string) $previous->getCode(), $previous->getMessage());
        }

        self::writeLog('ERROR', implode(PHP_EOL, $lines));
    }

    private static function writeLog(string $level, string $message): void
    {
        $line = sprintf('[%s] %s %s%s', date('Y-m-d H:i:s'), $level, $message, PHP_EOL);
        $dir = BASE_PATH . '/storage/logs';
        if (is_dir($dir) && is_writable($dir)) {
            file_put_contents(BASE_PATH . self::LOG_FILE, $line, FILE_APPEND | LOCK_EX);
        } else {
            error_log($line);
        }
    }
}
