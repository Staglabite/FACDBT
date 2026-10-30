<?php

namespace App\DataTables\Original\FACDBT01\Concerns;

/**
 * Urutan bawaan tabel server-side dipakai hanya bila user belum mengurutkan lewat judul kolom.
 * orderBy() di query() selalu didahulukan oleh database, sehingga urutan dari klik judul kolom
 * (yang ditambahkan DataTables di belakangnya) tidak berpengaruh bila urutan bawaan selalu dipasang.
 */
trait AppliesDefaultOrder
{
    /**
     * Request DataTables berisi urutan dari kolom yang bisa diurutkan?
     */
    private function hasColumnOrder(): bool
    {
        $columns = (array) request('columns', []);

        foreach ((array) request('order', []) as $order) {
            $column = $columns[$order['column'] ?? -1] ?? null;
            if ($column && filter_var($column['orderable'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }
}
