<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Config;
use App\Core\Database;
use PDO;

/**
 * Sliding-window login throttle per client IP. After N failures within the
 * window further attempts are refused until the oldest counted failure
 * expires — a temporary pause, never a permanent lockout, so an attacker
 * cannot lock the real admin out for good. IPs are stored only as hashes.
 *
 * Limitation (documented in README): attackers rotating IP addresses are
 * not stopped by this; strong passwords remain the main defence.
 */
final class LoginThrottle
{
    /** Seconds the client must wait before trying again (0 = allowed now). */
    public static function secondsUntilAllowed(string $ip): int
    {
        $max = max(1, (int) Config::get('security.login_max_attempts', 5));
        $window = max(60, (int) Config::get('security.login_window_minutes', 15) * 60);
        $pdo = Database::connection();

        // Housekeeping: nothing older than a day is ever needed.
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < :cutoff')
            ->execute(['cutoff' => date('Y-m-d H:i:s', time() - 86400)]);

        $stmt = $pdo->prepare(
            'SELECT attempted_at FROM login_attempts
              WHERE ip_hash = :ip AND attempted_at >= :since
              ORDER BY attempted_at DESC
              LIMIT :max'
        );
        $stmt->bindValue('ip', self::hash($ip));
        $stmt->bindValue('since', date('Y-m-d H:i:s', time() - $window));
        $stmt->bindValue('max', $max, PDO::PARAM_INT);
        $stmt->execute();
        $attempts = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($attempts) < $max) {
            return 0;
        }

        // The oldest of the last $max failures decides when a slot frees up.
        $unlockAt = (int) strtotime((string) end($attempts)) + $window;

        return max(1, $unlockAt - time());
    }

    public static function recordFailure(string $ip): void
    {
        Database::connection()
            ->prepare('INSERT INTO login_attempts (ip_hash, attempted_at) VALUES (:ip, :at)')
            ->execute(['ip' => self::hash($ip), 'at' => Database::now()]);
    }

    public static function clear(string $ip): void
    {
        Database::connection()
            ->prepare('DELETE FROM login_attempts WHERE ip_hash = :ip')
            ->execute(['ip' => self::hash($ip)]);
    }

    private static function hash(string $ip): string
    {
        return hash('sha256', 'nyiltnap-login|' . $ip);
    }
}
