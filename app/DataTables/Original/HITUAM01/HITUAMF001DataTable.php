<?php

namespace App\DataTables\Original\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHAPPLICATION as Application;
use App\Models\HITUAMF001;
use App\Traits\DataTableTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Application Datatable
class HITUAMF001DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param  QueryBuilder<HITUAMF001>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="form-check-input" name="selected[]" value="' . $row->IID . '">';
            })
            ->editColumn('NORDERPROJECT', function ($row) {
                return $row->NORDERPROJECT;
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->rawColumns(['checkbox', 'action'])
            ->setRowId('IID')
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    $query->whereAny(['VPROJECTDESC', 'VDEPT', 'VPIC', 'VPORTALACCESS', 'VPUBLISH', 'VPORTALNAME', 'VOPERATIONAL', 'VSTRDZATION', 'VPREFIXPROJECT', 'VDATABASE'], 'ILIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<HITUAMF001>
     */
    public function query(Application $model): QueryBuilder
    {
        return $model->newQuery()
            ->withCount('menus')
            ->orderBy('DCREA', 'desc');
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('hituamf001-table')
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
        return [
            Column::computed('checkbox')
                ->title('<input type="checkbox" class="form-check-input" id="select-all">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
            Column::make('VDEPT')->title('Code'),
            Column::make('VPROJECTDESC')->title('Description'),
            Column::make('VPIC')->title('PIC'),
            Column::make('VPORTALACCESS')->title('Portal Access'),
            Column::make('VPUBLISH')->title('Publish'),
            Column::make('VPORTALNAME')->title('Portal Name'),
            Column::make('VOPERATIONAL')->title('Operational'),
            Column::make('VSTRDZATION')->title('Standardization'),
            Column::make('VPREFIXPROJECT')->title('Project Prefix'),
            Column::make('VDATABASE')->title('Database'),
            Column::make('NORDERPROJECT')->title('Order'),
            Column::make('VICON')->title('Icon'),
            Column::make('VHOST')->title('HOST'),
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
        return 'Master_Application-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'HITUAMF001-Update',
                'icon' => 'pencil',
                'class' => 'edit-application',
            ],
            [
                'action' => 'delete',
                'service' => 'HITUAMF001-Delete',
                'icon' => 'trash',
                'class' => 'delete-application',
                'title' => 'Delete application',
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}
