<?php

namespace App\DataTables\Original\FACDBT04;

use App\Exports\FACDBT\FACDBT04\FACDBTD006 as Export;
use App\Models\FACDBT04\FACDBT_SIMINVTAKINGDATAHISTORY as TakingHistory;
use App\Traits\DataTableTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// History Taking Datatable (tanpa action button)
class FACDBTD006DataTable extends DataTable
{
    use DataTableTrait;

    // Kolom angka yang ditampilkan dengan pemisah ribuan
    private const QTY_COLUMNS = ['qty_book', 'qty_taking', 'qty_variance'];
    private const AMOUNT_COLUMNS = ['mat_cost', 'lab_cost', 'foh_cost', 'amount_book', 'amount_taking', 'amount_variance'];

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<TakingHistory>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        $dataTable = (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected[]" value="' . $row->IID . '">';
            })
            ->editColumn('time', fn ($row) => $row->time?->format('Y-m-d H:i:s'))
            ->rawColumns(['checkbox'])
            ->setRowId('IID')
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    $query->whereAny([
                        'location_no',
                        'location_group',
                        'area',
                        'warehouse',
                        'part_no',
                        'part_name',
                        'LOT_BATCH_NO',
                    ], 'LIKE', "%{$keyword}%");
                }
            });

        foreach (self::QTY_COLUMNS as $column) {
            $dataTable->editColumn($column, fn ($row) => self::number($row->{$column}, 4));
        }
        foreach (self::AMOUNT_COLUMNS as $column) {
            $dataTable->editColumn($column, fn ($row) => self::number($row->{$column}, 2));
        }

        return $dataTable;
    }

    /**
     * Get the query source of dataTable: difilter periode Year -> Month -> Event.
     *
     * @return QueryBuilder<TakingHistory>
     */
    public function query(TakingHistory $model): QueryBuilder
    {
        return $model->newQuery()
            ->forPeriod(request('year'), request('month'), request('taking_date'))
            ->orderByDesc('TAKING_DATE')
            ->orderBy('location_no')
            ->orderBy('part_no');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtd006-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
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

        foreach (Export::COLUMNS as $column => $title) {
            $col = Column::make($column)->title($title)->addClass('text-nowrap');
            if (in_array($column, [...self::QTY_COLUMNS, ...self::AMOUNT_COLUMNS, 'item_book', 'item_taking'], true)) {
                $col->addClass('text-end');
            }
            $columns[] = $col;
        }

        return $columns;
    }

    protected function filename(): string
    {
        return 'History_Taking-' . date('YmdHis');
    }

    private static function number($value, int $decimals): string
    {
        $formatted = number_format((float) $value, $decimals, '.', ',');

        // Buang nol di belakang koma: 12.5000 -> 12.5, 12.0000 -> 12
        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}
