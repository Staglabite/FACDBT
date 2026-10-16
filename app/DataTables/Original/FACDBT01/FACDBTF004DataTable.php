<?php

namespace App\DataTables\Original\FACDBT01;

use App\Models\FACDBT01\FACDBT_SIMINVTAKINGBOOKTAB as TakingBook; // Mengambil model Taking Book
use App\Traits\DataTableTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
                        'b.PART_NO',
                        'p.inventory_part_description',
                        'b.LOCATION_NO',
                        'l.location_description',
                        'b.LOT_BATCH_NO',
                        'b.WAREHOUSE',
                        'l.location_group',
                    ], 'LIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<TakingBook>
     */
    public function query(TakingBook $model): QueryBuilder
    {
        // 1. Ambil nilai INV_LIST_NO terbesar (paling baru)
        $latestInvListNo = DB::connection('INV_TAKING')
            ->table('SIM_INV_TAKING_BOOK_TAB')
            ->max(DB::raw('CAST(INV_LIST_NO AS UNSIGNED)'));

        $select = ['b.*'];
        foreach (self::JOINED_COLUMNS as $alias => $column) {
            $select[] = "{$column} as {$alias}";
        }
        foreach ($this->optionalColumns() as $alias => $column) {
            $select[] = $column ? DB::raw("{$column} as {$alias}") : DB::raw("NULL as {$alias}");
        }

        // 2. Filter query DataTable hanya untuk INV_LIST_NO terbaru tersebut.
        //    Deskripsi part dari SIM_INV_PART_TEST, nama & group lokasi dari SIM_INV_LOCATION_TEST.
        return $model->newQuery()
            ->from('SIM_INV_TAKING_BOOK_TAB as b')
            ->leftJoin('SIM_INV_PART_TEST as p', 'p.part_no', '=', 'b.PART_NO')
            ->leftJoin('SIM_INV_LOCATION_TEST as l', 'l.location_no', '=', 'b.LOCATION_NO')
            ->where('b.INV_LIST_NO', $latestInvListNo)
            ->select($select);
    }

    // Kolom dari tabel Part (p) dan Location (l): alias => kolom
    private const JOINED_COLUMNS = [
        'part_description' => 'p.inventory_part_description',
        'location_name' => 'l.location_description',
        'accounting_group' => 'p.accounting_group',
        'location_group' => 'l.location_group',
        'location_group_desc' => 'l.location_group_description',
    ];

    // Kolom yang belum pasti ada di database: alias => kandidat nama kolom,
    // dicari berurutan di tabel book (b), part (p) lalu location (l)
    private const OPTIONAL_COLUMNS = [
        'accounting_group_desc' => ['accounting_group_desc', 'accounting_group_description'],
        'dim_quality' => ['dim_quality', 'dimquality'],
        'product_family' => ['product_family', 'prod_family'],
        'product_family_desc' => ['product_family_desc', 'product_family_description', 'prod_family_desc', 'prod_family_description'],
    ];

    /**
     * Kolom opsional yang ditemukan di database, contoh ['dim_quality' => 'b.DIM_QUALITY'].
     * Bernilai null bila kolomnya tidak ada; selnya tampil "-" dan tidak bisa dicari/diurutkan.
     *
     * @return array<string, string|null>
     */
    private function optionalColumns(): array
    {
        return once(function () {
            $schema = Schema::connection('INV_TAKING');
            $tables = [
                'b' => $schema->getColumnListing('SIM_INV_TAKING_BOOK_TAB'),
                'p' => $schema->getColumnListing('SIM_INV_PART_TEST'),
                'l' => $schema->getColumnListing('SIM_INV_LOCATION_TEST'),
            ];

            $found = [];
            foreach (self::OPTIONAL_COLUMNS as $alias => $candidates) {
                $found[$alias] = null;
                foreach ($tables as $tableAlias => $columns) {
                    foreach ($columns as $column) {
                        if (in_array(strtolower($column), $candidates, true)) {
                            $found[$alias] = "{$tableAlias}.{$column}";
                            break 3;
                        }
                    }
                }
            }

            return $found;
        });
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
                // Data diambil per halaman dari server; tanpa ini seluruh tabel dimuat sekaligus dan memori PHP habis
                'serverSide' => true,
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
            Column::make('MONTH')->name('b.MONTH')->title('Month'),
            Column::make('YEAR')->name('b.YEAR')->title('Year'),
            Column::make('PART_NO')->name('b.PART_NO')->title('Part No'),
            Column::make('part_description')->name(self::JOINED_COLUMNS['part_description'])->title('Part Description'),
            Column::make('LOCATION_NO')->name('b.LOCATION_NO')->title('Location No'),
            Column::make('location_name')->name(self::JOINED_COLUMNS['location_name'])->title('Location Name'),
            Column::make('HANDLING_UNIT_TYPE_ID')->name('b.HANDLING_UNIT_TYPE_ID')->title('Handling Unit Type Id'),
            Column::make('QUANTITY')->name('b.QUANTITY')->title('On Hand Qty'),
            Column::make('UNIT_MEAS')->name('b.UNIT_MEAS')->title('UoM'),
            Column::make('LOT_BATCH_NO')->name('b.LOT_BATCH_NO')->title('Lot Batch No'),
            Column::make('WDR')->name('b.WDR')->title('WDR'),
            Column::make('WAREHOUSE')->name('b.WAREHOUSE')->title('Warehouse'),
            Column::make('BAY_NO')->name('b.BAY_NO')->title('Bay'),
            Column::make('ROW_NO')->name('b.ROW_NO')->title('Row'),
            Column::make('TIER_NO')->name('b.TIER_NO')->title('Tier'),
            Column::make('BIN_NO')->name('b.BIN_NO')->title('Bin'),
            Column::make('AVAILABILITY_CONTROL_ID')->name('b.AVAILABILITY_CONTROL_ID')->title('Availability Control Id'),
            Column::make('AVAILABILITY_CONTROL_DESC')->name('b.AVAILABILITY_CONTROL_DESC')->title('Availability Control Description'),
            Column::make('accounting_group')->name(self::JOINED_COLUMNS['accounting_group'])->title('Accounting Group'),
            $this->optionalColumn('accounting_group_desc', 'Accounting Group Desc'),
            $this->optionalColumn('dim_quality', 'Dim/Quality'),
            Column::make('location_group')->name(self::JOINED_COLUMNS['location_group'])->title('Location Group'),
            Column::make('location_group_desc')->name(self::JOINED_COLUMNS['location_group_desc'])->title('Location Group Desc'),
            $this->optionalColumn('product_family', 'Product Family'),
            $this->optionalColumn('product_family_desc', 'Product Family Description'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center'),
        ];
    }

    private function optionalColumn(string $alias, string $title): Column
    {
        $column = $this->optionalColumns()[$alias];

        return Column::make($alias)
            ->name($column ?? $alias)
            ->title($title)
            ->defaultContent('-')
            ->searchable($column !== null)
            ->orderable($column !== null);
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