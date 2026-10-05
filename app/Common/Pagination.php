<?php

declare(strict_types=1);

namespace App\Common;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class Pagination
{
    /**
     * Resolve a capped pagination page size.
     */
    public static function perPage(Request $request, int $default = 10, int $max = 50): int
    {
        $value = $request->query('limit', $request->query('per_page', $request->input('per_page', $default)));

        return max(1, min((int) $value, $max));
    }

    /**
     * Format a paginator instance into a standard service response shape.
     *
     * @template TItem
     *
     * @param  LengthAwarePaginator<TItem>  $paginator
     * @param  iterable<TItem>|null  $items
     * @param  array<string, mixed>  $extra
     * @return array{items: array<int, mixed>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public static function format(
        LengthAwarePaginator $paginator,
        mixed $items = null,
        array $extra = []
    ): array {
        $resolvedItems = match (true) {
            is_array($items) => $items,
            $items instanceof Collection => $items->all(),
            default => $paginator->items(),
        };

        return array_merge([
            'items' => $resolvedItems,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], $extra);
    }

    /**
     * Return an empty paginated result.
     *
     * @param  array<string, mixed>  $extra
     * @return array{items: array<int, mixed>, pagination: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public static function empty(int $perPage = 10, array $extra = []): array
    {
        return array_merge([
            'items' => [],
            'pagination' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
            ],
        ], $extra);
    }
}
