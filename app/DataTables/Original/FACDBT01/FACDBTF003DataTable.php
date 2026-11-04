<?php

namespace App\DataTables\Original\FACDBT01;

use App\DataTables\Original\FACDBT01\Concerns\AppliesDefaultOrder;
use App\DataTables\Original\FACDBT01\Concerns\MasterServiceName;
use App\Models\FACDBT01\FACDBT_MSHUNITCOST as UnitCost; // Mengambil model Unit Cost
use App\Traits\DataTableTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Unit Cost Datatable
class FACDBTF003DataTable extends DataTable
{
    use DataTableTrait;
    use AppliesDefaultOrder;
    use MasterServiceName;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<UnitCost>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                // IID sebagai value dari checkbox
                return '<input type="checkbox" class="form-check-input" name="selected[]" value="' . $row->IID . '">';
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->rawColumns(['checkbox', 'action'])
            ->setRowId('IID') // IID sebagai Row ID
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    // Menggunakan LIKE untuk pencarian teks pada kolom Unit Cost
                    $query->whereAny([
                        'VPARTNO', 
                        'VPARTDESC', 
                        'VUOM', 
                        'VACCGROUP', 
                        'NONHANDQTY', 
                        'MMATCOST', 
                        'MLABCOST',
                        'MFOHCOST'
                    ], 'LIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<UnitCost>
     */
    public function query(UnitCost $model): QueryBuilder
    {
        $query = $model->newQuery();

        // Menangkap filter selected_ids jika ada request dari JS (Fitur Export Selected)
        if (request()->has('selected_ids') && !empty(request('selected_ids'))) {
            $selectedIds = explode(',', request('selected_ids'));
            $query->whereIn('IID', $selectedIds);
        }

        // Urutan bawaan; klik judul kolom menggantikannya
        return $query->when(!$this->hasColumnOrder(), fn ($q) => $q->orderBy('VPARTNO', 'asc'));
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtf003-table') // ID Table disesuaikan ke FACDBT003
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
            Column::make('VPARTNO')->title('Part No'),
            Column::make('VPARTDESC')->title('Description'),
            Column::make('VUOM')->title('UOM'),
            Column::make('VACCGROUP')->title('Accounting Group'),
            Column::make('NONHANDQTY')->title('On Hand Qty'),
            Column::make('MMATCOST')->title('Material Cost'),
            Column::make('MLABCOST')->title('Labor Cost'),
            Column::make('MFOHCOST')->title('FOH Cost'),
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
        return 'Master_UnitCost-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => $this->masterService('003', 'Update'), // Disesuaikan untuk Master Unit Cost
                'icon' => 'pencil',
                'class' => 'edit-unitcost', // Class tombol edit
                'id' => $data->IID, 
            ],
            [
                'action' => 'delete',
                'service' => $this->masterService('003', 'Delete'),
                'icon' => 'trash',
                'class' => 'delete-unitcost', // Class tombol delete
                'title' => 'Delete unit cost',
                'id' => $data->IID,
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}