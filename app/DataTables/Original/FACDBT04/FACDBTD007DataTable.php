<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\TakingByLocationService;
use App\Traits\DataTableTrait;
use Illuminate\Support\Collection;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Overview Taking Data by Location Datatable (tanpa action button)
class FACDBTD007DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param  Collection<int, array>  $query  Results from query() method (nama parameter harus $query).
     */
    public function dataTable(Collection $query): CollectionDataTable
    {
        return (new CollectionDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected[]" value="' . e($row['key']) . '">';
            })
            ->editColumn('qty_book', fn ($row) => self::number($row['qty_book']))
            ->editColumn('qty_taking', fn ($row) => self::number($row['qty_taking']))
            ->editColumn('progress', fn ($row) => $row['progress'] === null ? '-' : self::number($row['progress']) . '%')
            ->rawColumns(['checkbox'])
            // Search & pencarian per kolom sudah diterapkan di query() (sama dengan export)
            ->filter(fn () => null, true);
    }

    /**
     * Get the query source of dataTable: data taking periode aktif (di-cache di service).
     */
    public function query(): Collection
    {
        return app(TakingByLocationService::class)->filtered(request());
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtd007-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'serverSide' => true,
                'processing' => false,
                'orderCellsTop' => true,
                'order' => [],
                'initComplete' => '
                    function () {
                        var table = this.api();
                        ' . $this->getScriptForSearchRow(false) . '
                    }
                ',
                'dom' => 'r' .
                    "<'table-responsive border-top'tr>" .
                    "<'d-flex align-items-center justify-content-center justify-content-lg-between flex-wrap gap-2 text-center px-6 mt-6'ip>",
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        $columns = [
            Column::computed('checkbox')
                ->title('<input type="checkbox" class="form-check-input" id="select-all">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
        ];

        foreach (TakingByLocationService::COLUMNS as $column => $title) {
            $col = Column::make($column)->title($title)->addClass('text-nowrap');
            if (in_array($column, ['qty_book', 'qty_taking', 'progress'], true)) {
                $col->addClass('text-end');
            }
            $columns[] = $col;
        }

        return $columns;
    }

    protected function filename(): string
    {
        return 'Taking_By_Location-' . date('YmdHis');
    }

    private static function number($value): string
    {
        $formatted = number_format((float) $value, 4, '.', ',');

        // Buang nol di belakang koma: 12.5000 -> 12.5, 12.0000 -> 12
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
