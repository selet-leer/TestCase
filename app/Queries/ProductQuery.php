<?php

namespace App\Queries;

use Illuminate\Http\Request;

readonly class ProductQuery
{
    public const FILTERABLE = ['name', 'price', 'stock', 'version'];

    public const SORTABLE = ['name', 'price', 'stock', 'version'];

    public const OPERATORS = ['eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'like'];

    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    /**
     * @param  array<array<string, string>>  $filters
     * @param  array<array<string, string>>  $sorts
     */
    public function __construct(
        public array $filters,
        public array $sorts,
        public int $page,
        public int $perPage,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            self::parseFilters($request->query('filter')),
            self::parseSorts((string) $request->query('sort', '')),
            max(1, (int) $request->query('page', 1)),
            self::clampPerPage((int) $request->query('per_page', self::DEFAULT_PER_PAGE)),
        );
    }

    /**
     * @return array<array<string, string>>
     */
    private static function parseFilters(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $filters = [];

        foreach ($raw as $field => $conditions) {
            if (! in_array($field, self::FILTERABLE, true) || ! is_array($conditions)) {
                continue;
            }

            foreach ($conditions as $op => $value) {
                if (in_array($op, self::OPERATORS, true) && is_string($value)) {
                    $filters[] = ['field' => $field, 'op' => $op, 'value' => $value];
                }
            }
        }

        return $filters;
    }

    /**
     * @return array<array<string, string>>
     */
    private static function parseSorts(string $raw): array
    {
        $sorts = [];

        foreach (array_filter(explode(',', $raw)) as $token) {
            $direction = str_starts_with($token, '-') ? 'desc' : 'asc';
            $field = ltrim($token, '-');

            if (in_array($field, self::SORTABLE, true)) {
                $sorts[] = ['field' => $field, 'direction' => $direction];
            }
        }

        return $sorts;
    }

    private static function clampPerPage(int $value): int
    {
        if ($value < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($value, self::MAX_PER_PAGE);
    }
}
