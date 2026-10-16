<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Simpan hasil import master berdasarkan part_no:
 * - part_no sudah ada di tabel -> datanya ditimpa
 * - part_no belum ada          -> dibuat baris baru
 * Pencocokan dilakukan di aplikasi, jadi tidak bergantung pada unique index part_no di database.
 */
trait SavesByPartNo
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  baris import (sudah unik per part_no)
     * @return array{0: int, 1: int} [jumlah baru, jumlah ditimpa]
     */
    private function saveByPartNo(Model $model, array $rows): array
    {
        $table = fn () => $model->getConnection()->table($model->getTable());

        // part_no di MySQL dibandingkan tanpa beda huruf besar/kecil & spasi di belakang
        $key = fn ($partNo) => mb_strtolower(rtrim((string) $partNo));

        $existing = [];
        foreach (array_chunk(array_column($rows, 'part_no'), 1000) as $chunk) {
            foreach ($table()->whereIn('part_no', $chunk)->pluck('part_no') as $partNo) {
                $existing[$key($partNo)] = true;
            }
        }

        $new = [];
        $updated = 0;
        foreach ($rows as $row) {
            if (isset($existing[$key($row['part_no'])])) {
                $table()->where('part_no', $row['part_no'])->update(Arr::except($row, ['part_no']));
                $updated++;
            } else {
                $new[] = $row;
            }
        }

        foreach (array_chunk($new, 500) as $chunk) {
            $table()->insert($chunk);
        }

        return [count($new), $updated];
    }
}
