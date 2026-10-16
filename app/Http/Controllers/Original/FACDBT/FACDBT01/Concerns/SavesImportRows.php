<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Simpan hasil import master secara massal berdasarkan kolom pencocok:
 * - cocok dengan data di tabel -> datanya ditimpa
 * - tidak ada yang cocok       -> dibuat baris baru
 *
 * Data lama dibaca sekali per 1000 nilai, lalu ditimpa / ditambah per 500 baris, sehingga ribuan baris
 * tidak lagi butuh query per baris. Pencocokan dilakukan di aplikasi, jadi tidak bergantung pada
 * unique index; baris yang ditimpa diidentifikasi lewat primary key tabel.
 */
trait SavesImportRows
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  baris import (sudah unik per kolom pencocok)
     * @param  string[]  $matchColumns  kolom pencocok, contoh ['part_no']
     * @return array{0: int, 1: int} [jumlah baru, jumlah ditimpa]
     */
    private function saveImportRows(Model $model, array $rows, array $matchColumns): array
    {
        $table = fn () => $model->getConnection()->table($model->getTable());
        $primaryKey = $model->getKeyName();

        // Di MySQL nilai dibandingkan tanpa beda huruf besar/kecil & spasi di belakang
        $key = fn (array $row) => implode('|', array_map(
            fn ($column) => mb_strtolower(rtrim((string) ($row[$column] ?? ''))),
            $matchColumns
        ));

        $existing = [];
        $firstColumn = $matchColumns[0];
        $selectColumns = array_values(array_unique([$primaryKey, ...$matchColumns]));
        foreach (array_chunk(array_values(array_unique(array_column($rows, $firstColumn))), 1000) as $chunk) {
            foreach ($table()->whereIn($firstColumn, $chunk)->get($selectColumns) as $record) {
                $existing[$key((array) $record)] = $record->{$primaryKey};
            }
        }

        $toInsert = [];
        $toUpdate = [];
        foreach ($rows as $row) {
            $id = $existing[$key($row)] ?? null;
            if ($id === null) {
                $toInsert[] = $row;
            } else {
                $toUpdate[] = [$primaryKey => $id] + $row;
            }
        }

        if ($toUpdate) {
            $updateColumns = array_values(array_diff(array_keys($toUpdate[0]), [$primaryKey]));
            foreach (array_chunk($toUpdate, 500) as $chunk) {
                // Menimpa berdasarkan primary key (selalu unik)
                $table()->upsert($chunk, [$primaryKey], $updateColumns);
            }
        }

        foreach (array_chunk($toInsert, 500) as $chunk) {
            $table()->insert($chunk);
        }

        return [count($toInsert), count($toUpdate)];
    }
}
