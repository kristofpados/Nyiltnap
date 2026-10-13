<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use InvalidArgumentException;

final class AdminUser
{
    public const USERNAME_PATTERN = '/^[A-Za-z0-9._\-]{3,60}$/';
    public const PASSWORD_MIN_LENGTH = 10;
    /** bcrypt only uses the first 72 bytes — longer passwords are rejected instead of silently truncated. */
    public const PASSWORD_MAX_BYTES = 72;

    /** @return array{id: int, username: string, created_at: string, last_login_at: ?string}|null */
    public static function findById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, username, created_at, last_login_at FROM admin_users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : [
            'id'            => (int) $row['id'],
            'username'      => (string) $row['username'],
            'created_at'    => (string) $row['created_at'],
            'last_login_at' => $row['last_login_at'] !== null ? (string) $row['last_login_at'] : null,
        ];
    }

    /** @return array{id: int, username: string, password_hash: string}|null */
    public static function findForLogin(string $username): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        return $row === false ? null : [
            'id'            => (int) $row['id'],
            'username'      => (string) $row['username'],
            'password_hash' => (string) $row['password_hash'],
        ];
    }

    /** Returns a Hungarian error message, or null when the password is acceptable. */
    public static function passwordProblem(string $password): ?string
    {
        if (mb_strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return 'A jelszónak legalább ' . self::PASSWORD_MIN_LENGTH . ' karakterből kell állnia.';
        }
        if (strlen($password) > self::PASSWORD_MAX_BYTES) {
            return 'A jelszó legfeljebb ' . self::PASSWORD_MAX_BYTES . ' bájt hosszú lehet.';
        }

        return null;
    }

    public static function create(string $username, string $password): int
    {
        self::assertValid($username, $password);
        $stmt = Database::connection()->prepare(
            'INSERT INTO admin_users (username, password_hash, created_at) VALUES (:username, :hash, :created_at)'
        );
        $stmt->execute([
            'username'   => $username,
            'hash'       => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => Database::now(),
        ]);

        return (int) Database::connection()->lastInsertId();
    }

    public static function updatePassword(int $id, string $password): void
    {
        if (($problem = self::passwordProblem($password)) !== null) {
            throw new InvalidArgumentException($problem);
        }
        $stmt = Database::connection()->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function recordLogin(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE admin_users SET last_login_at = :now WHERE id = :id');
        $stmt->execute(['now' => Database::now(), 'id' => $id]);
    }

    private static function assertValid(string $username, string $password): void
    {
        if (preg_match(self::USERNAME_PATTERN, $username) !== 1) {
            throw new InvalidArgumentException('A felhasználónév 3–60 karakter lehet, és csak angol betűket, számokat, pontot, kötőjelet és aláhúzást tartalmazhat.');
        }
        if (($problem = self::passwordProblem($password)) !== null) {
            throw new InvalidArgumentException($problem);
        }
    }
}
