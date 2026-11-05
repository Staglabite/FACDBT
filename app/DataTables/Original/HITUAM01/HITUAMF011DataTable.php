<?php

namespace App\DataTables\Original\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHNEWS as News;
use App\Traits\DataTableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use App\Models\HITUAM01\HITUAM_MSHCONFIGURATION as Config;
// use App\Models\FACTWM01\FACTWM_MSHSUPPLIER as Supplier;
// use App\Models\FACTWM01\FACTWM_MSHCONFIGURATION as Config;

class HITUAMF011DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Supplier> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected-service[]" value="' . ($row->IID ?? '') . '">';
            })
            ->editColumn('BSTATUS', function ($data) {
                return $data->BSTATUS ? '<span class="badge bg-success">Published</span>' : '<span class="badge bg-secondary">Draft</span>';
            })
            ->editColumn('ITOTALVIEW', function ($data) {
                return number_format($data->ITOTALVIEW) ?? '0';
            })
            ->editColumn('AVIEWERS', function ($data) {
                $viewers = is_array($data->AVIEWERS)
                    ? $data->AVIEWERS
                    : array_map('trim', explode(',', $data->AVIEWERS));

                // $supplierService = app('App\Services\FACTWM\SupplierService');

                // // supplier dari viewers
                // $getSuppliers = $supplierService->getSuppliersByIds($viewers);

                // // semua supplier
                // $allSuppliers = Supplier::all();

                // // bandingkan ID
                // $viewerIds = $getSuppliers->pluck('id')->sort()->values();
                // $allIds    = $allSuppliers->pluck('id')->sort()->values();

                // if ($viewerIds->count() === $allIds->count() && $viewerIds->diff($allIds)->isEmpty()) {
                //     return 'All Vendor';
                // }
                // // kalau tidak semua
                // $confLength = Config::where('VVARIABLE', 'max_len_view_news')->first();
                // $maxLength = $confLength ? (int) $confLength->VVALUE : 150;
                // return strlen($getSuppliers->pluck('VNAME')->implode(', ')) > $maxLength
                //     ? substr($getSuppliers->pluck('VNAME')->implode(', '), 0, $maxLength) . '...'
                //     : $getSuppliers->pluck('VNAME')->implode(', ');
            })
            ->editColumn('VCONTENT', function ($data) {
                return strlen($data->VCONTENT) > 50 ? substr($data->VCONTENT, 0, 150) . '...' : $data->VCONTENT;
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->editColumn('DPUBLISHED_AT', function ($data) {
                return $data->DPUBLISHED_AT != null ? Carbon::parse($data->DPUBLISHED_AT)->timezone('Asia/Jakarta')->format('d M Y H:i') : '-';
            })
            ->editColumn('DCREA', function ($data) {
                return Carbon::parse($data->DCREA)->timezone('Asia/Jakarta')->format('d M Y H:i');
            })
            ->editColumn('DMODI', function ($data) {
                return $data->DMODI
                    ? Carbon::parse($data->DMODI)->timezone('Asia/Jakarta')->format('d M Y H:i')
                    : null; // atau '-'
            })
            ->rawColumns(['checkbox', 'action', 'BSTATUS', 'VCONTENT'])
            ->setRowId('IID')
            ->filterColumn('DPUBLISHED_AT', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DPUBLISHED_AT', $keyword);
            })
            ->filterColumn('DCREA', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DCREA', $keyword);
            })
            ->filterColumn('DMODI', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DMODI', $keyword);
            })
            ->filter(function ($query) {
                $keyword = request('keyword');
                $status = request('status');

                if (! empty($keyword)) {
                    $query->whereAny(['VTITLE', 'VSUBJECT', 'VCONTENT', 'AVIEWERS'], 'ILIKE', "%{$keyword}%");
                }

                if ($status !== null && $status !== '') {
                    if ($status == 'true') {
                        $query->where('BSTATUS', true);
                    } else {
                        $query->where('BSTATUS', false);
                    }
                } else {
                    $query->whereIn('BSTATUS', [true, false]);
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Supplier>
     */
    public function query(News $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('hituamf011-table')
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

                        let searchTimeout;
                        $(document).on(\'keyup\', \'#search-input\', e => {
                            clearTimeout(searchTimeout);
                            searchTimeout = setTimeout(() => {
                                const keyword = $(e.target).val();
                                table.ajax.reload();
                            }, 500);
                        });

                        $(document).on(\'change\', \'#filter-status\', e => {
                            const range = $(e.target).val();
                            table.ajax.reload();
                        });
                    }
                ',
                'dom' => 'r' .
                    "<'table-responsive border-top'tr>" .
                    "<'d-flex align-items-center justify-content-center justify-content-lg-between flex-wrap gap-2 text-center px-6 mt-6'ip>",
                'drawCallback' => '
                    function() {
                        $("#select-all-service").off("click").on("click", function(){
                            var checked = this.checked;
                            $("input[name=\'selected-service[]\']").prop("checked", checked).trigger("change");
                        });

                        $("input[name=\'selected-service[]\']").off("change").on("change", function(){
                            var anyChecked = $("input[name=\'selected-service[]\']:checked").length > 0;
                            if (anyChecked) {
                                $("#btn-delete-selected-service").removeClass("disabled");
                            } else {
                                $("#btn-delete-selected-service").addClass("disabled");
                            }

                            var allChecked = $("input[name=\'selected-service[]\']").length === $("input[name=\'selected-service[]\']:checked").length;
                            $("#select-all-service").prop("checked", allChecked);
                        });
                    }',
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::computed('checkbox')
                ->title('<input type="checkbox" class="form-check-input" id="select-all-service">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
            Column::make('VTITLE')->title('Title')->addClass('text-nowrap'),
            Column::make('BSTATUS')->title('News Status')->addClass('text-nowrap'),
            Column::make('ITOTALVIEW')->title('Total View')->addClass('text-nowrap'),
            Column::make('AVIEWERS')->title('Views')->addClass('text-nowrap'),
            Column::make('VCONTENT')->title('Content')->addClass('text-nowrap'),
            Column::make('DPUBLISHED_AT')->title('Published Date')->addClass('text-nowrap'),
            Column::make('VCREA')->title('Created By')->addClass('text-nowrap'),
            Column::make('DCREA')->title('Created Date')->addClass('text-nowrap'),
            Column::make('VMODI')->title('Updated By')->addClass('text-nowrap'),
            Column::make('DMODI')->title('Updated Date')->addClass('text-nowrap'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-nowrap'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'HITUAMF011_' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'HITUAMF011-Update',
                'icon' => 'pencil',
                'class' => 'edit-news',
            ],
            [
                'action' => 'delete',
                'service' => 'HITUAMF011-Delete',
                'icon' => 'trash',
                'class' => 'delete-news',
            ],
            [
                'action' => 'view',
                'service' => 'HITUAMF011-View',
                'icon' => 'eye',
                'class' => 'view-news',
            ]
        ];

        return $this->actionButtons($data, $buttons);
    }
}
