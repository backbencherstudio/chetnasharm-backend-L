<?php

namespace App\Support;

use Illuminate\Http\Request;

class Pagination
{
    /**
     * Resolve a capped pagination page size.
     *
     * @return int
     */
    public static function perPage(Request $request, int $default = 10, int $max = 50): int
    {
        $value = $request->query('limit', $request->query('per_page', $request->input('per_page', $default)));

        return max(1, min((int) $value, $max));
    }
}
