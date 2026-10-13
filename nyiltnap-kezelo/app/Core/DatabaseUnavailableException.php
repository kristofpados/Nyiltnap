<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Thrown when the database cannot be reached; rendered as a friendly 503 page. */
final class DatabaseUnavailableException extends RuntimeException
{
}
