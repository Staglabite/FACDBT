<?php

// Sesuaikan namespace dengan struktur folder Anda (contoh menggunakan FACDBT02)
namespace App\DataTables\Original\FACDBT01;

use App\Models\FACDBT01\FACDBT_MSHPART as Part; // Mengambil model Part dari folder FACDBT01
use App\Traits\DataTableTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Part Datatable
class FACDBTF002DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<Part>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                // Menggunakan part_no sebagai value dari checkbox
                return '<input type="checkbox" class="form-check-input" name="selected[]" value="' . $row->part_no . '">';
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->rawColumns(['checkbox', 'action'])
            ->setRowId('part_no') // Menggunakan part_no sebagai Row ID
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    // Menggunakan LIKE (atau ILIKE jika database mendukungnya) untuk pencarian teks
                    $query->whereAny([
                        'part_no', 
                        'inventory_part_description', 
                        'accounting_group', 
                        'seg', 
                        'prod_group', 
                        'part_group'
                    ], 'LIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Part>
     */
    public function query(Part $model): QueryBuilder
    {
        $query = $model->newQuery();

        // Menangkap filter selected_ids jika ada request dari JS (Fitur Export Selected)
        if (request()->has('selected_ids') && !empty(request('selected_ids'))) {
            // Memecah string ID menjadi array dan melakukan filter
            $selectedIds = explode(',', request('selected_ids'));
            $query->whereIn('part_no', $selectedIds);
        }

        return $query->orderBy('part_no', 'asc');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtf002-table') // ID Table di Blade
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                // Data diambil per halaman dari server; tanpa ini seluruh tabel dimuat sekaligus dan memori PHP habis
                'serverSide' => true,
                'processing' => false,
                'orderCellsTop' => true,
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
        return [
            Column::computed('checkbox')
                ->title('<input type="checkbox" class="form-check-input" id="select-all">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
            Column::make('part_no')->title('Part No'),
            Column::make('inventory_part_description')->title('Description'),
            Column::make('accounting_group')->title('Accounting Group'),
            Column::make('seg')->title('Seg'),
            Column::make('prod_group')->title('Prod Group'),
            Column::make('part_group')->title('Part Group'),
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
        return 'Master_Part-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'FACDBT002-Update', // Disesuaikan dengan penamaan service Part
                'icon' => 'pencil',
                'class' => 'edit-part',
                'id' => $data->part_no, // Menggunakan part_no sebagai ID
            ],
            [
                'action' => 'delete',
                'service' => 'FACDBT002-Delete',
                'icon' => 'trash',
                'class' => 'delete-part',
                'title' => 'Delete part',
                'id' => $data->part_no,
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}