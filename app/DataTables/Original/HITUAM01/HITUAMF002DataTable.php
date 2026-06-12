<?php

namespace App\DataTables\Original\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHMENU as Menu;
use App\Traits\DataTableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class HITUAMF002DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Menu> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected-service[]" value="' . $row->IID . '">';
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
                    $query->whereAny(['VAPPID', 'VDESC'], 'ILIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<HITUAMF002>
     */
    public function query(Menu $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['application'])
            ->select('HITUAM_MSHMENUS.*')
            ->selectSub(function ($query) {
                $query->from('HITUAM_MSHMENUS as child_menus')
                    ->selectRaw('count(*)')
                    ->whereNull('child_menus.DDELETE')
                    ->whereColumn('child_menus.VPARENT', DB::raw('CAST("HITUAM_MSHMENUS"."IID" AS VARCHAR)'));
            }, 'child_count')
            ->selectSub(function ($query) {
                $query->from('HITUAM_MSHSERVICES')
                    ->selectRaw('count(*)')
                    ->whereNull('HITUAM_MSHSERVICES.DDELETE')
                    ->whereColumn('HITUAM_MSHSERVICES.VMENUID', 'HITUAM_MSHMENUS.VAPPID');
            }, 'services_count')
            ->selectSub(function ($query) {
                $query->from('HITUAM_MSHROLEACCESS')
                    ->selectRaw('count(*)')
                    ->whereNull('HITUAM_MSHROLEACCESS.DDELETE')
                    ->where('HITUAM_MSHROLEACCESS.BSTATUS', true)
                    ->whereColumn('HITUAM_MSHROLEACCESS.VMENUID', 'HITUAM_MSHMENUS.VAPPID');
            }, 'accesses_count');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('hituamf002-table')
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
                        $("#select-all-service").off("click").on("click", function(){
                            var checked = this.checked;
                            $("input[name=\'selected-service[]\']").prop("checked", checked).trigger("change");
                        });

                        $("input[name=\'selected-service[]\']").off("change").on("change", function(){
                            var anyChecked = $("input[name=\'selected-service[]\']:checked").length > 0;
                            $("#btn-delete-selected").toggleClass("d-none", !anyChecked);

                            var allChecked = $("input[name=\'selected-service[]\']").length === $("input[name=\'selected-service[]\']:checked").length;
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
            Column::make('VAPPID')->title('App ID'),
            Column::make('VFLAG')->title('Flag'),
            Column::make('VAPPDESC')->title('Name'),
            Column::make('VURL')->title('Url'),
            Column::make('VDESC')->title('Description'),
            Column::make('application.VPROJECTDESC')->title('Application'),
            Column::make('NSORTAPP')->title('Order'),
            Column::make('VCREA')->title('Created By'),
            Column::make('DCREA')->title('Created Date'),
            Column::make('VMODI')->title('Modified By'),
            Column::make('DMODI')->title('Modified Date'),
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
        return 'Master_Menu-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'HITUAMF002-Update',
                'icon' => 'pencil',
                'class' => 'edit-menu',
            ],
            [
                'action' => 'delete',
                'service' => 'HITUAMF002-Delete',
                'icon' => 'trash',
                'class' => 'delete-menu',
                'title' => 'Delete menu',
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}
