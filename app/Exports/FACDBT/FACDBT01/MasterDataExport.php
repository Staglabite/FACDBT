<?php

namespace App\Exports\FACDBT\FACDBT01;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export master data FACDBT01 ke CSV.
 * Data dibaca per chunk tanpa membuat model Eloquent dan langsung ditulis ke response,
 * jadi data besar tidak perlu dimuat sekaligus ke memori.
 */
class MasterDataExport
{
    private const CHUNK_SIZE = 2000;

    /**
     * @param  array<string, string>  $columns  [kolom database => judul kolom di CSV]
     */
    public function __construct(
        private Builder $query,
        private array $columns,
    ) {}

    public function download(string $filename): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // BOM supaya Excel membaca CSV sebagai UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($this->columns));

            $columns = array_keys($this->columns);
            foreach ($this->query->select($columns)->toBase()->lazy(self::CHUNK_SIZE) as $row) {
                fputcsv($out, array_map(fn ($column) => $row->{$column}, $columns));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
