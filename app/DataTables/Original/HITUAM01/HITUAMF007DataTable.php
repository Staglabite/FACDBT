<?php

namespace App\DataTables\Original\HITUAM01;

use App\Models\HITUAM01\HITUAM_MSHROLE_ACCESS;
use App\Traits\DataTableTrait;
use Yajra\DataTables\Services\DataTable;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;

class HITUAMF007DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Build the DataTable class.
     *
     * @param QueryBuilder<HITUAM_MSHROLE_ACCESS> $query Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->editColumn('BSTATUS', function ($row) {
                return $row->BSTATUS ? 'Active' : 'Inactive';
            })
            ->addColumn('created_by', function ($row) {
                return $row->VCREA ?? '-';
            })
            ->addColumn('created_at', function ($row) {
                return $row->DCREA ?? '-';
            })
            ->addColumn('modified_by', function ($row) {
                return $row->VMODI ?? '-';
            })
            ->addColumn('modified_at', function ($row) {
                return $row->DMODI ?? '-';
            })
            ->setRowId('IID')
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    $query->whereHas('role', function ($q) use ($keyword) {
                        $q->where('VROLENAME', 'ILIKE', "%{$keyword}%");
                    })->orWhereHas('menu', function ($q) use ($keyword) {
                        $q->where('VAPPDESC', 'ILIKE', "%{$keyword}%");
                    });
                }
            });
    }

    /**
     * Get the query source of dataTable.
     *
     * @return QueryBuilder<HITUAM_MSHROLE_ACCESS>
     */
    public function query(HITUAM_MSHROLE_ACCESS $model): QueryBuilder
    {
        return $model->newQuery()
            ->with(['role', 'menu'])
            ->where('HITUAM_MSHROLEACCESS.BSTATUS', true)
            ->whereHas('role', function ($query) {
                $query->whereNull('HITUAM_MSHROLES.DDELETE');
            })
            ->whereHas('menu', function ($query) {
                $query->whereNull('HITUAM_MSHMENUS.DDELETE');
            });
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('hituamf007-table')
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
                        ' . $this->getScriptForSearchRow(false) . '
                    }
                ',
                'dom' => 'r' .
                    "<'table-responsive border-top'tr>" .
                    "<'d-flex align-items-center justify-content-center justify-content-lg-between flex-wrap gap-2 text-center px-6 mt-6'ip>",
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('DT_RowIndex')->title('No')->orderable(false)->addClass('text-center'),
            Column::make('role.VROLENAME')->title('Role Name')->addClass('text-nowrap'),
            Column::make('menu.VAPPDESC')->title('Menu Name')->addClass('text-nowrap'),
            Column::make('BSTATUS')->title('Status')->addClass('text-nowrap'),
            Column::make('created_by')->title('Created By')->addClass('text-nowrap'),
            Column::make('created_at')->title('Created At')->addClass('text-nowrap'),
            Column::make('modified_by')->title('Modified By')->addClass('text-nowrap'),
            Column::make('modified_at')->title('Modified At')->addClass('text-nowrap'),
        ];
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'HITUAM_MSHROLE_ACCESS_' . date('YmdHis');
    }
}
