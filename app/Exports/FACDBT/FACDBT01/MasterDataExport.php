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
     * @param  array<string, string>  $expressions  [nama kolom di $columns => ekspresi SQL] untuk kolom hitungan
     */
    public function __construct(
        private Builder $query,
        private array $columns,
        private array $expressions = [],
    ) {}

    public function download(string $filename): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');

            // BOM supaya Excel membaca CSV sebagai UTF-8
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($this->columns));

            $columns = array_keys($this->columns);
            $query = $this->query->select(array_values(array_diff($columns, array_keys($this->expressions))));
            foreach ($this->expressions as $alias => $expression) {
                $query->selectRaw("{$expression} as {$alias}");
            }

            foreach ($query->toBase()->lazy(self::CHUNK_SIZE) as $row) {
                fputcsv($out, array_map(fn ($column) => $row->{$column}, $columns));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
