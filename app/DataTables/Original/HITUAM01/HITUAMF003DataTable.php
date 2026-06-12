<?php

namespace App\DataTables\Original\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHSERVICE as Service;
use App\Traits\DataTableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;

use Yajra\DataTables\Services\DataTable;

class HITUAMF003DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Service> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected-service[]" value="' . $row->IID . '" style="cursor: pointer;">';
            })
            ->editColumn('DBEGINEFF', function ($data) {
                return Carbon::parse($data->DBEGINEFF)->format('d M Y');
            })
            ->editColumn('DENDEFF', function ($data) {
                return Carbon::parse($data->DENDEFF)->format('d M Y');
            })
            ->editColumn('DCREA', function ($data) {
                return Carbon::parse($data->DCREA)->format('d M Y H:i');
            })
            ->editColumn('DMODI', function ($data) {
                return $data->DMODI
                    ? Carbon::parse($data->DMODI)->format('d M Y H:i')
                    : null; // atau '-'
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->rawColumns(['checkbox', 'action'])
            ->setRowId('IID')
            ->filterColumn('DCREA', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DCREA', $keyword);
            })
            ->filterColumn('DMODI', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DMODI', $keyword);
            })
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    $query->whereAny(['VNAME', 'VDESC'], 'ILIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Service>
     */
    public function query(Service $model): QueryBuilder
    {
        return $model->newQuery()
            ->with('menu', 'application')
            ->select('HITUAM_MSHSERVICES.*')
            ->selectSub(function ($query) {
                $query->from('HITUAM_MSHROLESERVICES')
                    ->selectRaw('count(*)')
                    ->whereNull('HITUAM_MSHROLESERVICES.DDELETE')
                    ->whereColumn('HITUAM_MSHROLESERVICES.VSERVICE', 'HITUAM_MSHSERVICES.VNAME');
            }, 'role_service_count');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('hituamf003-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'processing' => false,
                'orderCellsTop' => true,
                'initComplete' => '
                    function () {
                        var table = this.api();
                        ' . $this->getScriptForSearchRow() . '
                    }
                ',
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
                            $("#btn-delete-selected").toggleClass("d-none", !anyChecked);

                            var checkboxes = $("input[name=\'selected-service[]\']");
                            var checkedBoxes = $("input[name=\'selected-service[]\']:checked");
                            var allChecked = checkboxes.length > 0 && checkboxes.length === checkedBoxes.length;
                            $("#select-all-service").prop("checked", allChecked);
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
                ->title('<input type="checkbox" class="form-check-input" id="select-all-service">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
            Column::make('VNAME')->title('Name')->addClass('text-nowrap'),
            Column::make('VDESC')->title('Description')->addClass('text-nowrap'),
            Column::make('VURL')->title('Url')->addClass('text-nowrap'),
            Column::make('VMETHOD')->title('Method')->addClass('text-nowrap'),
            Column::make('menu.VAPPDESC')->title('Menu')->addClass('text-nowrap'),
            Column::make('application.VPROJECTDESC')->title('Application')->addClass('text-nowrap'),
            Column::make('DBEGINEFF')->title('Begin Eff')->addClass('text-nowrap'),
            Column::make('DENDEFF')->title('End Eff')->addClass('text-nowrap'),
            Column::make('VCREA')->title('Created By')->addClass('text-nowrap'),
            Column::make('DCREA')->title('Created Date')->addClass('text-nowrap'),
            Column::make('VMODI')->title('Modified By')->addClass('text-nowrap'),
            Column::make('DMODI')->title('Modified Date')->addClass('text-nowrap'),
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
        return 'Master_Service-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'HITUAMF003-Update',
                'icon' => 'pencil',
                'class' => 'edit-service',
            ],
            [
                'action' => 'delete',
                'service' => 'HITUAMF003-Delete',
                'icon' => 'trash',
                'class' => 'delete-service',
                'title' => 'Delete service',
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}
