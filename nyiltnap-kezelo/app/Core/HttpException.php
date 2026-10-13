<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** An expected HTTP error (404, 403, 405 …) rendered with a designed error page. */
final class HttpException extends RuntimeException
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        string $message = '',
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }
}
