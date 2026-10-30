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
     * minifiedAjax() tidak mengirim orderable=true (nilai bawaan), jadi orderable yang tidak dikirim = bisa
     * diurutkan, sama seperti cara Yajra membacanya; kolom seperti checkbox mengirim orderable=false.
     */
    private function hasColumnOrder(): bool
    {
        return self::requestHasColumnOrder((array) request('columns', []), (array) request('order', []));
    }

    /**
     * @param  array  $columns  parameter columns dari DataTables
     * @param  array  $order    parameter order dari DataTables
     */
    public static function requestHasColumnOrder(array $columns, array $order): bool
    {
        foreach ($order as $item) {
            $index = $item['column'] ?? null;
            if ($index === null || !isset($columns[$index])) {
                continue;
            }
            if (filter_var($columns[$index]['orderable'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                return true;
            }
        }

        return false;
    }
}
