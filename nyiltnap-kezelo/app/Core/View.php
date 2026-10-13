<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Plain-PHP templates in app/Views. Templates receive their data as local
 * variables and must escape every dynamic value with e().
 */
final class View
{
    /** @param array<string, mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }

        return self::renderFile('layouts/' . $layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        return self::renderFile('partials/' . $template, $data);
    }

    /** @param array<string, mixed> $__data */
    private static function renderFile(string $__template, array $__data): string
    {
        if (preg_match('#^[a-z0-9_\-/]+$#', $__template) !== 1) {
            throw new RuntimeException('Invalid view name.');
        }
        $__path = BASE_PATH . '/app/Views/' . $__template . '.php';
        if (!is_file($__path)) {
            throw new RuntimeException('View not found: ' . $__template);
        }

        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__path;

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
