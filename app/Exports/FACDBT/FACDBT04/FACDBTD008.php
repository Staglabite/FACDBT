<?php

namespace App\Exports\FACDBT\FACDBT04;

use App\Services\FACDBT\TakingRevisionTable;
use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV Taking Data Revision; kolom sama dengan DataTable FACDBTD008.
 * Data dibaca per chunk dan langsung ditulis ke response supaya data besar tidak dimuat sekaligus.
 */
class FACDBTD008
{
    private const CHUNK_SIZE = 2000;

    public static function download(Builder $query): StreamedResponse
    {
        $columns = TakingRevisionTable::COLUMNS;

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');

            // BOM supaya Excel membaca CSV sebagai UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns));

            foreach ($query->lazy(self::CHUNK_SIZE) as $row) {
                fputcsv($out, array_map(fn ($column) => $row->{$column}, array_keys($columns)));
            }

            fclose($out);
        }, 'Taking_Data_Revision-' . date('YmdHis') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
