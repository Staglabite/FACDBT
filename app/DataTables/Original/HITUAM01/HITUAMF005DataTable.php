<?php

namespace App\DataTables\Original\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHUSER as User;
use App\Traits\DataTableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Support\Facades\DB;

// User DataTable
class HITUAMF005DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<User>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected-user[]" value="' . $row->IID . '">';
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->addColumn('roles', function ($row) {
                $roles = $row->roles->pluck('VROLENAME')->toArray();

                $html = '<div class="demo-inline-spacing">';
                foreach ($roles as $role) {
                    $html .= "<span class='badge bg-label-primary'>{$role}</span>";
                }
                $html .= '</div>';

                return $html;
            })
            ->rawColumns(['checkbox', 'action', 'roles'])
            ->editColumn('DCREA', function ($data) {
                return Carbon::parse($data->DCREA)->format('d M Y H:i');
            })
            ->editColumn('DMODI', function ($data) {
                return $data->DMODI
                    ? Carbon::parse($data->DMODI)->format('d M Y H:i')
                    : null; // atau '-'
            })
            ->setRowId('IID')
            ->filterColumn('roles', function ($query, $keyword) {
                $query->whereHas('roles', function ($q) use ($keyword) {
                    $roleTable = $q->getModel()->getTable();
                    $q->where("{$roleTable}.VROLENAME", 'ILIKE', "%{$keyword}%");
                });
            })
            ->filterColumn('DCREA', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DCREA', $keyword);
            })
            ->filterColumn('DMODI', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DMODI', $keyword);
            })
            ->orderColumn('roles', function ($query, $order) {
                $query->orderByRaw(
                    '(
                        SELECT MIN("HITUAM_MSHROLES"."VROLENAME")
                        FROM "HITUAM_MSHUSERROLES"
                        INNER JOIN "HITUAM_MSHROLES"
                            ON "HITUAM_MSHROLES"."VROLENAME" = "HITUAM_MSHUSERROLES"."VROLE"
                        WHERE "HITUAM_MSHUSERROLES"."VUSERNAME" = "HITUAM_MSHUSER"."VUSERNAME"
                    ) ' . $order
                );
            })
            ->filter(function ($query) {
                $keyword = request('keyword');
                $columns = request()->get('columns', []);

                if (! empty($keyword)) {
                    $query->whereAny(['VEMPNO', 'VUSERNAME', 'VPHONE', 'VEMAIL'], 'ILIKE', "%{$keyword}%");
                }

            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<User>
     */
    public function query(User $model): QueryBuilder
    {
        return $model->newQuery()->with(['roles']);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('hituamf005-table')
            ->columns($this->getColumns())
            ->minifiedAjax(route('hituam.master-userrole.users'))
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
                        $("#select-all-user").off("click").on("click", function(){
                            var checked = this.checked;
                            $("input[name=\'selected-user[]\']").prop("checked", checked).trigger("change");
                        });

                        $("input[name=\'selected-user[]\']").off("change").on("change", function(){
                            var anyChecked = $("input[name=\'selected-user[]\']:checked").length > 0;
                            $("#btn-delete-user-selected").toggleClass("d-none", !anyChecked);

                            var allChecked = $("input[name=\'selected-user[]\']").length === $("input[name=\'selected-user[]\']:checked").length;
                            $("#select-all-user").prop("checked", allChecked);
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
                ->title('<input type="checkbox" class="form-check-input" id="select-all-user">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
            Column::make('VUSERNAME')->title('Username')->addClass('text-nowrap'),
            Column::make('VEMAIL')->title('Email')->addClass('text-nowrap'),
            Column::make('VEMPNO')->title('NPK')->addClass('text-nowrap'),
            Column::make('roles')->title('Roles')->addClass('text-nowrap'),
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
        return 'Master_User-' . date('YmdHis');
    }

    private function applyExternalOrdering(QueryBuilder $query, array $orderedUserIds, string $order): void
    {
        if (empty($orderedUserIds)) {
            $query->orderBy('IID', $order);

            return;
        }

        $caseStatements = collect($orderedUserIds)
            ->values()
            ->map(fn($id, $index) => "WHEN {$id} THEN {$index}")
            ->implode(' ');

        $defaultRank = count($orderedUserIds);

        $query->orderByRaw("CASE \"HITUAM_MSHUSER\".\"IID\" {$caseStatements} ELSE {$defaultRank} END {$order}");
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'HITUAMF005-Update',
                'icon' => 'pencil',
                'class' => 'edit-user',
            ],
            [
                'action' => 'delete',
                'service' => 'HITUAMF005-Delete',
                'icon' => 'trash',
                'class' => 'delete-user',
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}
