<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\RegistrationFilters;
use PDO;
use PDOException;
use PDOStatement;

final class Registration
{
    public const BOARDING = 'boarding';
    public const COMMUTER = 'commuter';
    public const RESIDENCIES = [self::BOARDING, self::COMMUTER];

    public const DELETED = 'deleted';
    public const NOT_FOUND = 'not_found';
    public const MISMATCH = 'mismatch';

    /** Unambiguous alphabet for public reference codes (no 0/O, 1/I/L). */
    private const REFERENCE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Whitelisted ORDER BY clauses — user input only ever selects a key. */
    private const ORDER_BY = [
        'newest'    => 'r.created_at DESC, r.id DESC',
        'oldest'    => 'r.created_at ASC, r.id ASC',
        'name_asc'  => 'r.full_name COLLATE utf8mb4_hungarian_ci ASC, r.id ASC',
        'name_desc' => 'r.full_name COLLATE utf8mb4_hungarian_ci DESC, r.id DESC',
    ];

    /**
     * Stores a registration and all of its program links atomically: if any
     * link fails (e.g. a foreign key error), nothing is saved.
     *
     * @param list<int>   $programIds already validated against active programs
     * @param string|null $createdAt  only for the demo seeder / tests; defaults to now
     * @return array{id: int, reference: string, created_at: string}
     */
    public static function create(string $fullName, string $currentSchool, string $residency, bool $isGraduating, array $programIds, ?string $createdAt = null): array
    {
        return Database::transaction(static function (PDO $pdo) use ($fullName, $currentSchool, $residency, $isGraduating, $programIds, $createdAt): array {
            $createdAt ??= Database::now();
            $insert = $pdo->prepare(
                'INSERT INTO registrations (reference_code, full_name, current_school, residency_preference, is_graduating, created_at)
                 VALUES (:reference, :full_name, :current_school, :residency, :graduating, :created_at)'
            );

            // Reference codes are random; on the (very unlikely) collision with
            // the unique key we simply draw a new one.
            for ($attempt = 1; ; $attempt++) {
                $reference = self::generateReference();
                try {
                    $insert->execute([
                        'reference'      => $reference,
                        'full_name'      => $fullName,
                        'current_school' => $currentSchool,
                        'residency'      => $residency,
                        'graduating'     => $isGraduating ? 1 : 0,
                        'created_at'     => $createdAt,
                    ]);
                    break;
                } catch (PDOException $e) {
                    if ($attempt < 5 && (int) ($e->errorInfo[1] ?? 0) === 1062) {
                        continue;
                    }
                    throw $e;
                }
            }

            $id = (int) $pdo->lastInsertId();
            $link = $pdo->prepare('INSERT INTO registration_programs (registration_id, program_id) VALUES (:registration_id, :program_id)');
            foreach (array_values(array_unique($programIds)) as $programId) {
                $link->execute(['registration_id' => $id, 'program_id' => $programId]);
            }

            return ['id' => $id, 'reference' => $reference, 'created_at' => $createdAt];
        });
    }

    /** @return array<string, mixed>|null */
    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.reference_code, r.full_name, r.current_school, r.residency_preference, r.is_graduating, r.created_at
               FROM registrations r
              WHERE r.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return self::withPrograms([$row])[0];
    }

    /**
     * Deletes one registration. The reference code from the confirmation
     * form must match the ID, so a tampered or stale form can never delete
     * a different applicant. Program links go via ON DELETE CASCADE.
     *
     * @return self::DELETED|self::NOT_FOUND|self::MISMATCH
     */
    public static function delete(int $id, string $expectedReference): string
    {
        return Database::transaction(static function (PDO $pdo) use ($id, $expectedReference): string {
            $stmt = $pdo->prepare('SELECT reference_code FROM registrations WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $reference = $stmt->fetchColumn();
            if ($reference === false) {
                return self::NOT_FOUND;
            }
            if (!hash_equals((string) $reference, $expectedReference)) {
                return self::MISMATCH;
            }

            $delete = $pdo->prepare('DELETE FROM registrations WHERE id = :id');
            $delete->execute(['id' => $id]);

            return $delete->rowCount() === 1 ? self::DELETED : self::NOT_FOUND;
        });
    }

    public static function count(RegistrationFilters $filters): int
    {
        [$where, $params] = self::whereClause($filters);
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM registrations r ' . $where);
        self::bind($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Filtered, sorted page of registrations with their programs attached.
     * Pass $limit = null to fetch every match (CSV export).
     *
     * @return list<array<string, mixed>>
     */
    public static function search(RegistrationFilters $filters, ?int $limit, int $offset = 0): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT r.id, r.reference_code, r.full_name, r.current_school, r.residency_preference, r.is_graduating, r.created_at
                  FROM registrations r '
            . $where
            . ' ORDER BY ' . (self::ORDER_BY[$filters->sort] ?? self::ORDER_BY['newest']);

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params['limit'] = $limit;
            $params['offset'] = $offset;
        }

        $stmt = Database::connection()->prepare($sql);
        self::bind($stmt, $params);
        $stmt->execute();

        return self::withPrograms($stmt->fetchAll());
    }

    /** @return list<array<string, mixed>> */
    public static function recent(int $limit): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT r.id, r.reference_code, r.full_name, r.current_school, r.residency_preference, r.is_graduating, r.created_at
               FROM registrations r
              ORDER BY r.created_at DESC, r.id DESC
              LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return self::withPrograms($stmt->fetchAll());
    }

    /**
     * Builds the WHERE clause for the admin filters. Every value is a bound
     * parameter; search wildcards typed by the user are escaped so "%" and
     * "_" are matched literally.
     *
     * @return array{0: string, 1: array<string, int|string>}
     */
    private static function whereClause(RegistrationFilters $filters): array
    {
        $clauses = [];
        $params = [];

        if ($filters->search !== '') {
            $like = '%' . strtr($filters->search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $clauses[] = "(r.full_name LIKE :q_name ESCAPE '!' OR r.current_school LIKE :q_school ESCAPE '!')";
            $params['q_name'] = $like;
            $params['q_school'] = $like;
        }
        if ($filters->programId !== null) {
            // EXISTS keeps one row per registration even with many programs.
            $clauses[] = 'EXISTS (SELECT 1 FROM registration_programs rpf WHERE rpf.registration_id = r.id AND rpf.program_id = :program_id)';
            $params['program_id'] = $filters->programId;
        }
        if ($filters->graduating !== null) {
            $clauses[] = 'r.is_graduating = :graduating';
            $params['graduating'] = $filters->graduating ? 1 : 0;
        }
        if ($filters->residency !== null) {
            $clauses[] = 'r.residency_preference = :residency';
            $params['residency'] = $filters->residency;
        }

        return [$clauses === [] ? '' : 'WHERE ' . implode(' AND ', $clauses), $params];
    }

    /** @param array<string, int|string> $params */
    private static function bind(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    /**
     * Attaches each row's programs with one extra query (no N+1).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function withPrograms(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT rp.registration_id, p.id, p.name, p.icon
               FROM registration_programs rp
               JOIN programs p ON p.id = rp.program_id
              WHERE rp.registration_id IN ($placeholders)
              ORDER BY p.sort_order, p.name"
        );
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $programs = [];
        foreach ($stmt->fetchAll() as $link) {
            $programs[(int) $link['registration_id']][] = [
                'id'   => (int) $link['id'],
                'name' => (string) $link['name'],
                'icon' => $link['icon'],
            ];
        }

        return array_map(static function (array $row) use ($programs): array {
            $row['id'] = (int) $row['id'];
            $row['is_graduating'] = (bool) $row['is_graduating'];
            $row['programs'] = $programs[$row['id']] ?? [];

            return $row;
        }, $rows);
    }

    private static function generateReference(): string
    {
        $alphabet = self::REFERENCE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
