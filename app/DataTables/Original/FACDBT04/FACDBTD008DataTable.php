<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\TakingRevisionTable;
use App\Traits\DataTableTrait;
use Illuminate\Database\Query\Builder;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\QueryDataTable;
use Yajra\DataTables\Services\DataTable;

// Taking Data Revision Datatable (seluruh periode taking, tanpa action button)
class FACDBTD008DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param  Builder  $query  Results from query() method.
     */
    public function dataTable(Builder $query): QueryDataTable
    {
        return (new QueryDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected[]" value="' . e($row->row_key) . '">';
            })
            ->rawColumns(['checkbox'])
            // Search sudah diterapkan di query() (sama dengan export)
            ->filter(fn () => null);
    }

    /**
     * Get the query source of dataTable: difilter periode Year -> Month -> Event dan Search.
     */
    public function query(): Builder
    {
        return app(TakingRevisionTable::class)->query(request())
            ->orderByDesc('r.CREATED_DATETIME')
            ->orderBy('r.ID_TAKING')
            ->orderBy('r.REV_NO');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtd008-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('taking-data-revision.table'))
            ->parameters([
                // Data diambil per halaman dari server; tanpa ini seluruh tabel dimuat sekaligus dan memori PHP habis
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

        foreach (TakingRevisionTable::COLUMNS as $column => $title) {
            $col = Column::make($column)
                ->name(TakingRevisionTable::columnName($column))
                ->title($title)
                ->addClass('text-nowrap');
            if (in_array($column, ['QTY', 'CONVERSION_FACTOR'], true)) {
                $col->addClass('text-end');
            }
            $columns[] = $col;
        }

        return $columns;
    }

    protected function filename(): string
    {
        return 'Taking_Data_Revision-' . date('YmdHis');
    }
}
