<?php

declare(strict_types=1);

if (!function_exists('table_paginate')) {
    /**
     * Potong array untuk tabel; maksimal 50 item per halaman.
     *
     * @return array{items: array, total: int, page: int, perPage: int, totalPages: int}
     */
    function table_paginate(array $items, int $page, int $perPage = 50): array
    {
        $perPage = max(1, min(50, $perPage));
        $total   = count($items);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page    = max(1, min($page, $totalPages));
        $offset  = ($page - 1) * $perPage;

        return [
            'items'      => array_slice(array_values($items), $offset, $perPage),
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
        ];
    }
}

if (!function_exists('table_pagination_query_params')) {
    /** GET saat ini tanpa `page` (untuk link halaman berikutnya). */
    function table_pagination_query_params(\CodeIgniter\HTTP\IncomingRequest $request): array
    {
        $q = $request->getGet();
        unset($q['page']);

        return $q;
    }
}

if (!function_exists('table_pagination_uri_path')) {
    /** Path relatif, mis. /tasks atau /team/users */
    function table_pagination_uri_path(): string
    {
        helper('url');
        $s = trim((string) uri_string(), '/');

        return $s === '' ? '/' : '/' . $s;
    }
}
