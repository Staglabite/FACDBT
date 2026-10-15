<?php

namespace App\Exports\FACDBT\FACDBT01;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Export master data FACDBT01 langsung dari database.
 * FromQuery membaca data per chunk (config excel.exports.chunk_size), jadi data besar
 * tidak perlu dimuat sekaligus ke DataTable/browser.
 */
class MasterDataExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  array<string, string>  $columns  [kolom database => judul kolom di Excel]
     */
    public function __construct(
        private Builder $query,
        private array $columns,
    ) {}

    public function query(): Builder
    {
        return $this->query->select(array_keys($this->columns));
    }

    public function headings(): array
    {
        return array_values($this->columns);
    }

    public function map($row): array
    {
        return array_map(fn ($column) => $row->{$column}, array_keys($this->columns));
    }
}
