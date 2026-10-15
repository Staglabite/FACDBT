<?php

// Namespace disesuaikan persis dengan struktur folder (menggunakan Original)
namespace App\DataTables\Original\FACDBT01;

use App\Models\FACDBT01\FACDBT_MSHLOCATION as Location;
use App\Traits\DataTableTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Location Datatable
class FACDBTF001DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<Location>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                // Menggunakan location_no sebagai value dari checkbox
                return '<input type="checkbox" class="form-check-input" name="selected[]" value="' . $row->location_no . '">';
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->rawColumns(['checkbox', 'action'])
            ->setRowId('location_no') // Menggunakan location_no sebagai Row ID
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    // Menggunakan LIKE (atau ILIKE jika database mendukungnya) untuk pencarian teks
                    $query->whereAny([
                        'location_no', 
                        'location_group', 
                        'site', 
                        'warehouse', 
                        'location_description', 
                        'location_group_description'
                    ], 'LIKE', "%{$keyword}%");
                }
            });
    }

/**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Location>
     */
    public function query(Location $model): QueryBuilder
    {
        $query = $model->newQuery();

        // Menangkap filter selected_ids jika ada request dari JS
        if (request()->has('selected_ids') && !empty(request('selected_ids'))) {
            // Memecah string ID menjadi array dan melakukan filter
            $selectedIds = explode(',', request('selected_ids'));
            $query->whereIn('location_no', $selectedIds);
        }

        return $query->orderBy('location_no', 'asc');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtf001-table')
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
            Column::make('location_no')->title('Location No'),
            Column::make('location_group')->title('Location Group'),
            Column::make('site')->title('Site'),
            Column::make('warehouse')->title('Warehouse'),
            Column::make('location_description')->title('Description'),
            Column::make('location_group_description')->title('Group Description'),
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
        return 'Master_Location-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'FACDBT001-Update',
                'icon' => 'pencil',
                'class' => 'edit-location',
                'id' => $data->location_no, // <-- Tambahkan baris ini
            ],
            [
                'action' => 'delete',
                'service' => 'FACDBT001-Delete',
                'icon' => 'trash',
                'class' => 'delete-location',
                'title' => 'Delete location',
                'id' => $data->location_no, // <-- Tambahkan baris ini juga
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}