<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Programs (szakmák) — the single source of choices for the form, filters and statistics. */
final class Program
{
    /** @return list<array{id: int, slug: string, name: string, description: ?string, icon: ?string}> */
    public static function active(): array
    {
        $rows = Database::connection()->query(
            'SELECT id, slug, name, description, icon
               FROM programs
              WHERE is_active = 1
              ORDER BY sort_order, name'
        )->fetchAll();

        return array_map([self::class, 'cast'], $rows);
    }

    /**
     * Every program, including inactive ones (old registrations may still
     * reference them, so admin filters must know about them).
     *
     * @return list<array{id: int, slug: string, name: string, description: ?string, icon: ?string, is_active: bool}>
     */
    public static function all(): array
    {
        $rows = Database::connection()->query(
            'SELECT id, slug, name, description, icon, is_active
               FROM programs
              ORDER BY is_active DESC, sort_order, name'
        )->fetchAll();

        return array_map(static function (array $row): array {
            $program = self::cast($row);
            $program['is_active'] = (bool) $row['is_active'];

            return $program;
        }, $rows);
    }

    /**
     * Active programs keyed by ID — the whitelist used to validate submitted program IDs.
     *
     * @return array<int, array{id: int, slug: string, name: string, description: ?string, icon: ?string}>
     */
    public static function activeById(): array
    {
        $byId = [];
        foreach (self::active() as $program) {
            $byId[$program['id']] = $program;
        }

        return $byId;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{id: int, slug: string, name: string, description: ?string, icon: ?string}
     */
    private static function cast(array $row): array
    {
        return [
            'id'          => (int) $row['id'],
            'slug'        => (string) $row['slug'],
            'name'        => (string) $row['name'],
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'icon'        => $row['icon'] !== null ? (string) $row['icon'] : null,
        ];
    }
}
