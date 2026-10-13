<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Validator;

/**
 * Parses and sanitises the admin list's query parameters. Anything invalid
 * falls back to a safe default, so manipulated URLs can never reach SQL as
 * raw strings: sort keys are whitelisted, numbers are clamped.
 */
final class RegistrationFilters
{
    public const SORTS = [
        'newest'    => 'Legújabb elöl',
        'oldest'    => 'Legrégebbi elöl',
        'name_asc'  => 'Név szerint (A–Z)',
        'name_desc' => 'Név szerint (Z–A)',
    ];

    public const SEARCH_MAX_LENGTH = 100;

    public string $search = '';
    public ?int $programId = null;
    public ?bool $graduating = null;
    public ?string $residency = null;
    public string $sort = 'newest';
    public int $perPage = 25;
    public int $page = 1;

    /** True when the URL asked for a program that does not exist. */
    public bool $invalidProgram = false;

    private int $defaultPerPage = 25;

    /**
     * @param array<string, mixed> $query     usually $_GET
     * @param list<int>            $programIds every existing program ID (active or not)
     * @param list<int>            $perPageOptions
     */
    public static function fromQuery(array $query, array $programIds, array $perPageOptions = [25, 50, 100], int $defaultPerPage = 25): self
    {
        $f = new self();

        $search = Validator::singleLine($query['q'] ?? '');
        if ($search !== null && $search !== '' && !Validator::hasInvisibleCharacters($search)) {
            $f->search = mb_substr($search, 0, self::SEARCH_MAX_LENGTH);
        }

        $program = $query['program'] ?? '';
        if ($program !== '' && $program !== null) {
            if (Validator::isIdString($program) && in_array((int) $program, $programIds, true)) {
                $f->programId = (int) $program;
            } else {
                $f->invalidProgram = true;
            }
        }

        $graduating = $query['graduating'] ?? '';
        if ($graduating === 'yes') {
            $f->graduating = true;
        } elseif ($graduating === 'no') {
            $f->graduating = false;
        }

        $residency = $query['residency'] ?? '';
        if ($residency === 'boarding' || $residency === 'commuter') {
            $f->residency = $residency;
        }

        $sort = $query['sort'] ?? '';
        if (is_string($sort) && array_key_exists($sort, self::SORTS)) {
            $f->sort = $sort;
        }

        $f->defaultPerPage = $defaultPerPage;
        $f->perPage = $defaultPerPage;
        $perPage = $query['per_page'] ?? '';
        if (Validator::isIdString($perPage) && in_array((int) $perPage, $perPageOptions, true)) {
            $f->perPage = (int) $perPage;
        }

        $page = $query['page'] ?? '';
        if (Validator::isIdString($page)) {
            $f->page = min((int) $page, 1_000_000);
        }

        return $f;
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->programId !== null || $this->graduating !== null || $this->residency !== null;
    }

    /** Clamps the page number once the total row count is known. */
    public function clampPage(int $totalRows): int
    {
        $pages = max(1, (int) ceil($totalRows / $this->perPage));
        $this->page = max(1, min($this->page, $pages));

        return $pages;
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * Query parameters for links (pagination, export). Defaults are omitted
     * to keep URLs short.
     *
     * @param array<string, scalar|null> $overrides
     * @return array<string, scalar>
     */
    public function toQuery(array $overrides = []): array
    {
        $params = [
            'q'          => $this->search,
            'program'    => $this->programId,
            'graduating' => $this->graduating === null ? null : ($this->graduating ? 'yes' : 'no'),
            'residency'  => $this->residency,
            'sort'       => $this->sort === 'newest' ? null : $this->sort,
            'per_page'   => $this->perPage === $this->defaultPerPage ? null : $this->perPage,
            'page'       => $this->page === 1 ? null : $this->page,
        ];
        $params = array_merge($params, $overrides);

        return array_filter($params, static fn ($v) => $v !== null && $v !== '');
    }
}
