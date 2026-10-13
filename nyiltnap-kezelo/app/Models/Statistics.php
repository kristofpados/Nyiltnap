<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Every number shown in the admin comes from here, so the dashboard and
 * the statistics page can never disagree.
 *
 * Counting rules:
 *  - Overall metrics read the registrations table alone (no join), so a
 *    registration is counted exactly once no matter how many programs it
 *    selected.
 *  - Per-program metrics count a registration once for each program it
 *    selected. The (registration_id, program_id) primary key guarantees a
 *    registration appears at most once per program, so COUNT/SUM over the
 *    join cannot double-count within a program.
 */
final class Statistics
{
    /**
     * @return array{
     *   total: int, graduating: int, non_graduating: int,
     *   boarding: int, commuter: int,
     *   boarding_graduating: int, boarding_non_graduating: int,
     *   commuter_graduating: int, commuter_non_graduating: int,
     *   first_at: ?string, latest_at: ?string
     * }
     */
    public static function overview(): array
    {
        $row = Database::connection()->query(
            "SELECT COUNT(*)                                                                 AS total,
                    COALESCE(SUM(is_graduating = 1), 0)                                      AS graduating,
                    COALESCE(SUM(residency_preference = 'boarding'), 0)                      AS boarding,
                    COALESCE(SUM(residency_preference = 'boarding' AND is_graduating = 1), 0) AS boarding_graduating,
                    COALESCE(SUM(residency_preference = 'boarding' AND is_graduating = 0), 0) AS boarding_non_graduating,
                    COALESCE(SUM(residency_preference = 'commuter' AND is_graduating = 1), 0) AS commuter_graduating,
                    COALESCE(SUM(residency_preference = 'commuter' AND is_graduating = 0), 0) AS commuter_non_graduating,
                    MIN(created_at)                                                          AS first_at,
                    MAX(created_at)                                                          AS latest_at
               FROM registrations"
        )->fetch();

        $total = (int) $row['total'];
        $graduating = (int) $row['graduating'];
        $boarding = (int) $row['boarding'];

        return [
            'total'                   => $total,
            'graduating'              => $graduating,
            'non_graduating'          => $total - $graduating,
            'boarding'                => $boarding,
            'commuter'                => $total - $boarding,
            'boarding_graduating'     => (int) $row['boarding_graduating'],
            'boarding_non_graduating' => (int) $row['boarding_non_graduating'],
            'commuter_graduating'     => (int) $row['commuter_graduating'],
            'commuter_non_graduating' => (int) $row['commuter_non_graduating'],
            'first_at'                => $row['first_at'] !== null ? (string) $row['first_at'] : null,
            'latest_at'               => $row['latest_at'] !== null ? (string) $row['latest_at'] : null,
        ];
    }

    /**
     * Per-program breakdown. Active programs are always listed (even with
     * zero registrations); inactive ones only if registrations reference them.
     *
     * @return list<array{
     *   id: int, name: string, icon: ?string, is_active: bool,
     *   total: int, graduating: int, non_graduating: int,
     *   boarding: int, commuter: int,
     *   boarding_graduating: int, boarding_non_graduating: int
     * }>
     */
    public static function byProgram(): array
    {
        $rows = Database::connection()->query(
            "SELECT p.id, p.name, p.icon, p.is_active, p.sort_order,
                    COUNT(r.id)                                                                  AS total,
                    COALESCE(SUM(r.is_graduating = 1), 0)                                        AS graduating,
                    COALESCE(SUM(r.residency_preference = 'boarding'), 0)                        AS boarding,
                    COALESCE(SUM(r.residency_preference = 'boarding' AND r.is_graduating = 1), 0) AS boarding_graduating,
                    COALESCE(SUM(r.residency_preference = 'boarding' AND r.is_graduating = 0), 0) AS boarding_non_graduating
               FROM programs p
               LEFT JOIN registration_programs rp ON rp.program_id = p.id
               LEFT JOIN registrations r ON r.id = rp.registration_id
              GROUP BY p.id, p.name, p.icon, p.is_active, p.sort_order
             HAVING p.is_active = 1 OR COUNT(r.id) > 0
              ORDER BY p.sort_order, p.name"
        )->fetchAll();

        return array_map(static function (array $row): array {
            $total = (int) $row['total'];
            $graduating = (int) $row['graduating'];
            $boarding = (int) $row['boarding'];

            return [
                'id'                      => (int) $row['id'],
                'name'                    => (string) $row['name'],
                'icon'                    => $row['icon'] !== null ? (string) $row['icon'] : null,
                'is_active'               => (bool) $row['is_active'],
                'total'                   => $total,
                'graduating'              => $graduating,
                'non_graduating'          => $total - $graduating,
                'boarding'                => $boarding,
                'commuter'                => $total - $boarding,
                'boarding_graduating'     => (int) $row['boarding_graduating'],
                'boarding_non_graduating' => (int) $row['boarding_non_graduating'],
            ];
        }, $rows);
    }

    /** Number of distinct programs chosen by at least one applicant. */
    public static function programsRepresented(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(DISTINCT program_id) FROM registration_programs')->fetchColumn();
    }

    /** Total program ticks across all registrations (for the average per applicant). */
    public static function programSelections(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM registration_programs')->fetchColumn();
    }

    /**
     * Registrations per calendar day (app timezone), most recent $days days
     * that have data, returned in chronological order.
     *
     * @return list<array{day: string, total: int, graduating: int}>
     */
    public static function daily(int $days = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT DATE(created_at) AS day, COUNT(*) AS total, COALESCE(SUM(is_graduating = 1), 0) AS graduating
               FROM registrations
              GROUP BY DATE(created_at)
              ORDER BY day DESC
              LIMIT :days'
        );
        $stmt->bindValue('days', $days, PDO::PARAM_INT);
        $stmt->execute();

        $rows = array_map(static fn (array $row): array => [
            'day'        => (string) $row['day'],
            'total'      => (int) $row['total'],
            'graduating' => (int) $row['graduating'],
        ], $stmt->fetchAll());

        return array_reverse($rows);
    }
}
