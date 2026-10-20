<?php

namespace App\Exports\FACDBT\FACDBT04;

use App\Services\FACDBT\TakingByLocationService;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV Overview Taking Data by Location; kolom sama dengan DataTable FACDBTD007.
 */
class FACDBTD007
{
    public static function download(Collection $rows): StreamedResponse
    {
        $columns = TakingByLocationService::COLUMNS;

        return response()->streamDownload(function () use ($rows, $columns) {
            $out = fopen('php://output', 'w');

            // BOM supaya Excel membaca CSV sebagai UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns));

            foreach ($rows as $row) {
                // Baris service: [key, ...kolom COLUMNS]
                fputcsv($out, array_slice($row, 1));
            }

            fclose($out);
        }, 'Taking_By_Location-' . date('YmdHis') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
