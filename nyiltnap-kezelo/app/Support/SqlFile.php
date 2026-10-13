<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use RuntimeException;

/** Executes the project's own .sql files (schema, seed) statement by statement. */
final class SqlFile
{
    public static function run(PDO $pdo, string $path): int
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new RuntimeException('Cannot read SQL file: ' . $path);
        }

        // Drop full-line "--" comments; our files never put ";" inside strings
        // at the end of a line, so splitting on ";<newline>" is safe for them.
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--'),
        );
        $statements = preg_split('/;\s*(?:\R|$)/', implode("\n", $lines)) ?: [];

        $count = 0;
        foreach ($statements as $statement) {
            if (trim($statement) === '') {
                continue;
            }
            $pdo->exec($statement);
            $count++;
        }

        return $count;
    }
}
