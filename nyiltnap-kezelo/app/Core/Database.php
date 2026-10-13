<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use PDO;
use PDOException;
use Throwable;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection === null) {
            self::$connection = self::connect((array) Config::get('db'));
        }

        return self::$connection;
    }

    /** Lets tests and CLI scripts swap in a specific connection. */
    public static function setConnection(?PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    /**
     * @param array<string, mixed> $config
     * @throws DatabaseUnavailableException when the server cannot be reached or rejects the credentials
     */
    public static function connect(array $config, bool $selectDatabase = true): PDO
    {
        $socket = (string) ($config['socket'] ?? '');
        $dsn = $socket !== ''
            ? 'mysql:unix_socket=' . $socket
            : sprintf('mysql:host=%s;port=%d', (string) ($config['host'] ?? '127.0.0.1'), (int) ($config['port'] ?? 3306));
        if ($selectDatabase) {
            $dsn .= ';dbname=' . (string) ($config['name'] ?? '');
        }
        $dsn .= ';charset=utf8mb4';

        try {
            $pdo = new PDO($dsn, (string) ($config['user'] ?? ''), (string) ($config['pass'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);

            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            // Align NOW()/CURRENT_TIMESTAMP with the app timezone without needing
            // the server's named time zone tables.
            $offset = (new DateTimeImmutable('now'))->format('P');
            $pdo->exec('SET time_zone = ' . $pdo->quote($offset));
            // Strict mode: overlong or invalid values fail loudly instead of being truncated.
            $pdo->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (PDOException $e) {
            throw new DatabaseUnavailableException('Database connection failed.', 0, $e);
        }

        return $pdo;
    }

    /**
     * Runs $callback inside a transaction; rolls back on any exception.
     *
     * @template T
     * @param callable(PDO): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Current time in the app timezone, formatted for DATETIME columns. */
    public static function now(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }
}
