<?php

namespace App\DataTables\Original\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHROLE as Role;
use App\Traits\DataTableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Role DataTable
class HITUAMF004DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<Role>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected-role[]" value="' . $row->NID . '" style="cursor: pointer;">';
            })
            ->editColumn('DCREA', function ($data) {
                return Carbon::parse($data->DCREA)->format('d M Y H:i');
            })
            ->editColumn('DMODI', function ($data) {
                return $data->DMODI
                    ? Carbon::parse($data->DMODI)->format('d M Y H:i')
                    : null; // atau '-'
            })
            ->editColumn('BSTATUS', function ($row) {
                return $row->BSTATUS ? 'Active' : 'Inactive';
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->rawColumns(['checkbox', 'action'])
            ->setRowId('NID')
            ->filterColumn('DCREA', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DCREA', $keyword);
            })
            ->filterColumn('DMODI', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DMODI', $keyword);
            })
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    $query->whereAny(['VROLENAME', 'VROLEDESC'], 'ILIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Role>
     */
    public function query(Role $model): QueryBuilder
    {
        return $model->newQuery()
            ->select('HITUAM_MSHROLES.*')
            ->selectSub(function ($query) {
                $query->from('HITUAM_MSHUSERROLES')
                    ->selectRaw('count(*)')
                    ->whereNull('HITUAM_MSHUSERROLES.DDELETE')
                    ->whereColumn('HITUAM_MSHUSERROLES.VROLE', 'HITUAM_MSHROLES.VROLENAME');
            }, 'user_count')
            ->selectSub(function ($query) {
                $query->from('HITUAM_MSHROLEACCESS')
                    ->selectRaw('count(*)')
                    ->whereNull('HITUAM_MSHROLEACCESS.DDELETE')
                    ->where('HITUAM_MSHROLEACCESS.BSTATUS', true)
                    ->whereColumn('HITUAM_MSHROLEACCESS.VROLE', 'HITUAM_MSHROLES.VROLENAME');
            }, 'access_count')
            ->selectSub(function ($query) {
                $query->from('HITUAM_MSHROLESERVICES')
                    ->selectRaw('count(*)')
                    ->whereNull('HITUAM_MSHROLESERVICES.DDELETE')
                    ->whereColumn('HITUAM_MSHROLESERVICES.VROLE', 'HITUAM_MSHROLES.VROLENAME');
            }, 'service_count');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('hituamf004-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('hituam.master-userrole.roles'))
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
                        $("#select-all-role").off("click").on("click", function(){
                            var checked = this.checked;
                            $("input[name=\'selected-role[]\']").prop("checked", checked).trigger("change");
                        });

                        $("input[name=\'selected-role[]\']").off("change").on("change", function(){
                            var anyChecked = $("input[name=\'selected-role[]\']:checked").length > 0;
                            $("#btn-delete-selected-role").toggleClass("d-none", !anyChecked);

                            var checkboxes = $("input[name=\'selected-role[]\']");
                            var checkedBoxes = $("input[name=\'selected-role[]\']:checked");
                            var allChecked = checkboxes.length > 0 && checkboxes.length === checkedBoxes.length;
                            $("#select-all-role").prop("checked", allChecked);
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
                ->title('<input type="checkbox" class="form-check-input" id="select-all-role">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
            Column::make('VROLENAME')->title('Name'),
            Column::make('VROLEDESC')->title('Role Description'),
            Column::make('BSTATUS')->title('Status'),
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
        return 'Master_Role-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'HITUAMF004-Update',
                'icon' => 'pencil',
                'class' => 'edit-role',
                'id' => $data->NID,
            ],
            [
                'action' => 'delete',
                'service' => 'HITUAMF004-Delete',
                'icon' => 'trash',
                'class' => 'delete-role',
                'id' => $data->NID,
                'title' => 'Delete role',
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}
