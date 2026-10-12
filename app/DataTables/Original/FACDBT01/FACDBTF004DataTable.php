<?php

namespace App\DataTables\Original\FACDBT01;

use App\Models\FACDBT01\FACDBT_SIMINVTAKINGBOOKTAB as TakingBook; // Mengambil model Taking Book
use App\Traits\DataTableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Taking Book Datatable
class FACDBTF004DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<TakingBook>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                // Menggunakan INV_LIST_NO sebagai value dari checkbox (Sesuaikan jika butuh composite key)
                return '<input type="checkbox" class="form-check-input" name="selected[]" value="' . $row->INV_LIST_NO . '">';
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->rawColumns(['checkbox', 'action'])
            ->setRowId('INV_LIST_NO') // Menggunakan INV_LIST_NO sebagai Row ID
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    // Pencarian teks disesuaikan dengan kolom-kolom utama pada Taking Book
                    $query->whereAny([
                        'INV_LIST_NO', 
                        'PART_NO', 
                        'LOCATION_NO', 
                        'LOT_BATCH_NO', 
                        'CONTRACT', 
                        'WAREHOUSE'
                    ], 'LIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<TakingBook>
     */
public function query(TakingBook $model)
{
    // 1. Ambil nilai INV_LIST_NO terbesar (paling baru)
    $latestInvListNo = DB::connection('INV_TAKING')
        ->table('SIM_INV_TAKING_BOOK_TAB')
        ->max(DB::raw('CAST(INV_LIST_NO AS UNSIGNED)'));

    // 2. Filter query DataTable hanya untuk INV_LIST_NO terbaru tersebut
    return $model->newQuery()
        ->where('INV_LIST_NO', $latestInvListNo);
}

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtf004-table') // ID Table disesuaikan ke FACDBT004
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'processing' => false,
                'orderCellsTop' => true,
                'buttons' => [
                    [
                        'extend' => 'excel',
                        'className' => 'd-none',
                        'filename' => $this->filename(),
                        'exportOptions' => [
                            'columns' => ':visible:not(:first-child):not(:last-child)',
                        ],
                    ],
                ],
                'initComplete' => '
                    function () {
                        var table = this.api();
                        ' . $this->getScriptForSearchRow() . '
                    }
                ',
                'dom' => 'r' .
                    "<'table-responsive border-top'tr>" .
                    "<'d-flex align-items-center justify-content-center justify-content-lg-between flex-wrap gap-2 text-center px-6 mt-6'ip>",
                'drawCallback' => '
                    function() {
                        $("#select-all").off("click").on("click", function(){
                            var checked = this.checked;
                            $("input[name=\'selected[]\']").prop("checked", checked).trigger("change");
                        });

                        $("input[name=\'selected[]\']").off("change").on("change", function(){
                            var anyChecked = $("input[name=\'selected[]\']:checked").length > 0;
                            $("#btn-delete-selected").toggleClass("d-none", !anyChecked);

                            var allChecked = $("input[name=\'selected[]\']").length === $("input[name=\'selected[]\']:checked").length;
                            $("#select-all").prop("checked", allChecked);
                        });
                    }
                ',
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        // Sesuaikan kolom yang ingin ditampilkan ke tabel HTML
        return [
            Column::computed('checkbox')
                ->title('<input type="checkbox" class="form-check-input" id="select-all">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
            Column::make('INV_LIST_NO')->title('Inv List No'),
            Column::make('SEQ')->title('Seq'),
            Column::make('PART_NO')->title('Part No'),
            Column::make('LOCATION_NO')->title('Location No'),
            Column::make('LOT_BATCH_NO')->title('Lot/Batch No'),
            Column::make('QUANTITY')->title('Quantity'),
            Column::make('UNIT_COST')->title('Unit Cost'),
            Column::make('WAREHOUSE')->title('Warehouse'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Taking_Book-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'FACDBT004-Update', // Disesuaikan untuk Taking Book
                'icon' => 'pencil',
                'class' => 'edit-takingbook', // Class tombol edit
                'id' => $data->INV_LIST_NO, 
            ],
            [
                'action' => 'delete',
                'service' => 'FACDBT004-Delete',
                'icon' => 'trash',
                'class' => 'delete-takingbook', // Class tombol delete
                'title' => 'Delete taking book',
                'id' => $data->INV_LIST_NO,
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}