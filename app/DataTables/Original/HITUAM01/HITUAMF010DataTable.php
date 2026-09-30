<?php

namespace App\DataTables\Original\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHCONFIGURATION as Config;
use App\Traits\DataTableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class HITUAMF010DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<Config> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', function ($row) {
                return '
                    <div class="d-flex gap-2 align-items-center">
                        <input type="checkbox" class="form-check-input" name="selected-configuration[]" value="' . $row->id . '">
                    </div>
                ';
            })
            ->editColumn('VVALUE', function ($data) {
                $confLength = Config::where('VVARIABLE', 'max_char_value')->first();
                $value = $data->VVALUE;
                if (strlen($value) > 50) {
                    $value = substr($value, 0, (int)$confLength?->VVALUE == null ? 50 : (int)$confLength->VVALUE) . '...';
                }
                return $value;
            })
            ->addColumn('action', fn($data) => $this->getActionButton($data))
            ->editColumn('DCREA', function ($data) {
                return $data->DCREA
                    ? Carbon::parse($data->DCREA)->format('d M Y H:i')
                    : null;
            })
            ->editColumn('DMODI', function ($data) {
                return $data->DMODI
                    ? Carbon::parse($data->DMODI)->format('d M Y H:i')
                    : null; // atau '-'
            })
            ->rawColumns(['checkbox', 'action'])
            ->setRowId('IID')
            ->filterColumn('DCREA', function ($query, $keyword) {
                $this->applyDateRangeFilter($query, 'DCREA', $keyword);
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<Config>
     */
    public function query(Config $model): QueryBuilder
    {
        return $model->newQuery();
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('hituamf010-table')
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
                        $("#select-all-configuration").off("click").on("click", function(){
                            var checked = this.checked;
                            $("input[name=\'selected-configuration[]\']").prop("checked", checked).trigger("change");
                        });

                        $("input[name=\'selected-configuration[]\']").off("change").on("change", function(){
                            var anyChecked = $("input[name=\'selected-configuration[]\']:checked").length > 0;
                            $("#btn-delete-selected").toggleClass("d-none", !anyChecked);

                            var allChecked = $("input[name=\'selected-configuration[]\']").length === $("input[name=\'selected-configuration[]\']:checked").length;
                            $("#select-all-configuration").prop("checked", allChecked);
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
                ->title('<input type="checkbox" class="form-check-input" id="select-all-configuration">')
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->width(30)
                ->addClass('text-center'),
            Column::make('VVARIABLE')->title('Variable')->addClass('text-nowrap'),
            Column::make('VVALUE')->title('Value'),
            Column::make('VCREA')->title('Created By')->addClass('text-nowrap'),
            Column::make('DCREA')->title('Created Date')->addClass('text-nowrap'),
            Column::make('VMODI')->title('Modified By')->addClass('text-nowrap'),
            Column::make('DMODI')->title('Modified Date')->addClass('text-nowrap'),
            Column::computed('action')
                ->title('Action')
                ->width('10%'),

        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'HITUAMF010_' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => 'HITUAMF010-Update',
                'icon' => 'pencil',
                'class' => 'edit-configuration',
            ],
            [
                'action' => 'delete',
                'service' => 'HITUAMF010-Delete',
                'icon' => 'trash',
                'class' => 'delete-configuration',
            ],
        ];

        return $this->actionButtons($data, $buttons);
    }
}
