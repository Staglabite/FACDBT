<?php

namespace App\DataTables\Original\FACDBT01;

use App\DataTables\Original\FACDBT01\Concerns\MasterServiceName;
use App\Models\FACDBT01\FACDBT_SIMINVTAKINGEVENT as TakingEventModel;
use App\Services\FACDBT\TakingEvent;
use App\Traits\DataTableTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Master Event Datatable
class FACDBTF006DataTable extends DataTable
{
    use DataTableTrait;
    use MasterServiceName;

    private const BADGES = [
        TakingEvent::PRE    => 'bg-label-info',
        TakingEvent::TWH    => 'bg-label-warning',
        TakingEvent::DIRECT => 'bg-label-primary',
    ];

    /**
     * @param  QueryBuilder<TakingEventModel>  $query
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('checkbox', fn ($row) => '<input type="checkbox" class="form-check-input" name="selected[]" value="' . $row->IID . '">')
            ->editColumn('TAKING_DATE', fn ($row) => $row->TAKING_DATE?->format('Y-m-d'))
            ->editColumn('EVENT_TYPE', fn ($row) => '<span class="badge ' . (self::BADGES[$row->EVENT_TYPE] ?? 'bg-label-secondary') . '">'
                . e(TakingEvent::TYPES[$row->EVENT_TYPE] ?? $row->EVENT_TYPE) . '</span>')
            // Jumlah part hanya berarti untuk Pre-Taking
            ->editColumn('parts_count', fn ($row) => $row->EVENT_TYPE === TakingEvent::PRE ? number_format((int) $row->parts_count) : '-')
            ->addColumn('action', fn ($data) => $this->getActionButton($data))
            ->rawColumns(['checkbox', 'EVENT_TYPE', 'action'])
            ->setRowId('IID')
            ->filter(function ($query) {
                $keyword = trim((string) request('keyword'));

                if ($keyword !== '') {
                    // Nama event (mis. "Pre-Taking") juga bisa dicari
                    $types = array_keys(array_filter(TakingEvent::TYPES, fn ($name) => stripos($name, $keyword) !== false));

                    $query->where(function ($q) use ($keyword, $types) {
                        $q->whereAny(['TAKING_DATE', 'EVENT_TYPE', 'DESCRIPTION', 'CREATED_BY', 'MODIFIED_BY'], 'LIKE', "%{$keyword}%")
                            ->orWhereIn('EVENT_TYPE', $types);
                    });
                }
            });
    }

    /**
     * Event + periode (Close Date, Month, Year dari SIM_INV_TAKING_CONCLUDING_TAB) + jumlah part Pre-Taking.
     *
     * @return QueryBuilder<TakingEventModel>
     */
    public function query(TakingEventModel $model): QueryBuilder
    {
        $period = fn (string $column) => fn ($q) => $q->from('SIM_INV_TAKING_CONCLUDING_TAB as c')
            ->select("c.{$column}")
            ->whereRaw('DATE(c.TAKING_DATE) = ' . TakingEvent::TABLE . '.TAKING_DATE')
            ->limit(1);

        return $model->newQuery()
            ->select(TakingEvent::TABLE . '.*')
            ->selectSub($period('CLOSE_DATE'), 'close_date')
            ->selectSub($period('MONTH'), 'period_month')
            ->selectSub($period('YEAR'), 'period_year')
            ->withCount('parts')
            ->orderBy('TAKING_DATE', 'desc');
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtf006-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                'serverSide' => true,
                'processing' => false,
                'order' => [],
                'dom' => 'r' .
                    "<'table-responsive border-top'tr>" .
                    "<'d-flex align-items-center justify-content-center justify-content-lg-between flex-wrap gap-2 text-center px-6 mt-6'ip>",
            ]);
    }

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
            Column::make('TAKING_DATE')->title('Taking Date'),
            Column::make('close_date')->title('Close Date')->searchable(false),
            Column::make('period_month')->title('Month')->searchable(false),
            Column::make('period_year')->title('Year')->searchable(false),
            Column::make('EVENT_TYPE')->title('Event'),
            Column::make('DESCRIPTION')->title('Description'),
            Column::make('parts_count')->title('Part Count')->searchable(false)->addClass('text-end'),
            Column::make('CREATED_BY')->title('Created By'),
            Column::make('CREATED_DATETIME')->title('Created Date'),
            Column::make('MODIFIED_BY')->title('Modified By'),
            Column::make('MODIFIED_DATETIME')->title('Modified Date'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(90)
                ->addClass('text-center text-nowrap'),
        ];
    }

    protected function filename(): string
    {
        return 'Master_Event-' . date('YmdHis');
    }

    private function getActionButton($data): string
    {
        $buttons = [
            [
                'action' => 'edit',
                'service' => $this->masterService('006', 'Update'),
                'icon' => 'pencil',
                'class' => 'edit-event',
                'title' => 'Edit event',
                'id' => $data->IID,
            ],
        ];

        if ($data->EVENT_TYPE === TakingEvent::PRE) {
            $buttons[] = [
                'action' => 'parts',
                'service' => $this->masterService('006', 'Update'),
                'icon' => 'list-details',
                'class' => 'event-parts',
                'title' => 'Pre-Taking part list',
                'id' => $data->IID,
                'data' => ['date' => $data->TAKING_DATE?->format('Y-m-d'), 'count' => (int) $data->parts_count],
            ];
        }

        $buttons[] = [
            'action' => 'delete',
            'service' => $this->masterService('006', 'Delete'),
            'icon' => 'trash',
            'class' => 'delete-event',
            'title' => 'Delete event',
            'id' => $data->IID,
        ];

        return $this->actionButtons($data, $buttons);
    }
}
