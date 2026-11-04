<?php

namespace App\DataTables\Original\FACDBT01;

use App\DataTables\Original\FACDBT01\Concerns\AppliesDefaultOrder;
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
    use AppliesDefaultOrder;
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
            ->editColumn('DTAKINGDATE', fn ($row) => $row->DTAKINGDATE?->format('Y-m-d'))
            ->editColumn('VEVENTTYPE', fn ($row) => '<span class="badge ' . (self::BADGES[$row->VEVENTTYPE] ?? 'bg-label-secondary') . '">'
                . e(TakingEvent::TYPES[$row->VEVENTTYPE] ?? $row->VEVENTTYPE) . '</span>')
            // Jumlah part hanya berarti untuk Pre-Taking; Pre-Taking tanpa part diberi tanda peringatan
            ->editColumn('parts_count', fn ($row) => match (true) {
                $row->VEVENTTYPE !== TakingEvent::PRE => '-',
                (int) $row->parts_count === 0 => '<span class="badge bg-label-warning">Belum ada part</span>',
                default => number_format((int) $row->parts_count),
            })
            ->addColumn('action', fn ($data) => $this->getActionButton($data))
            // Daftar Taking Date Pre-Taking (periode belum ditutup) yang belum punya daftar part, untuk peringatan di atas tabel
            ->with('missing_parts', fn () => self::missingParts())
            ->rawColumns(['checkbox', 'VEVENTTYPE', 'parts_count', 'action'])
            ->setRowId('IID')
            ->filter(function ($query) {
                $keyword = trim((string) request('keyword'));

                if ($keyword !== '') {
                    // Nama event (mis. "Pre-Taking") juga bisa dicari
                    $types = array_keys(array_filter(TakingEvent::TYPES, fn ($name) => stripos($name, $keyword) !== false));

                    $query->where(function ($q) use ($keyword, $types) {
                        $q->whereAny(['DTAKINGDATE', 'VEVENTTYPE', 'VDESC', 'VCREA', 'VMODI'], 'LIKE', "%{$keyword}%")
                            ->orWhereIn('VEVENTTYPE', $types);
                    });
                }
            });
    }

    /**
     * Taking Date event Pre-Taking yang belum punya daftar part dan periodenya belum ditutup
     * (daftar part periode yang sudah ditutup memang dikosongkan setelah masuk history).
     *
     * @return array<int, string>
     */
    public static function missingParts(): array
    {
        return TakingEventModel::query()
            ->where('VEVENTTYPE', TakingEvent::PRE)
            ->whereDoesntHave('parts')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('SIM_INV_TAKING_CONCLUDING_TAB as c')
                ->whereRaw('DATE(c.TAKING_DATE) = ' . TakingEvent::TABLE . '.DTAKINGDATE')
                ->where('c.ROWSTATE', 'Closed'))
            ->orderBy('DTAKINGDATE')
            ->pluck('DTAKINGDATE')
            ->map(fn ($date) => substr((string) $date, 0, 10))
            ->all();
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
            ->whereRaw('DATE(c.TAKING_DATE) = ' . TakingEvent::TABLE . '.DTAKINGDATE')
            ->limit(1);

        return $model->newQuery()
            ->select(TakingEvent::TABLE . '.*')
            ->selectSub($period('CLOSE_DATE'), 'close_date')
            ->selectSub($period('MONTH'), 'period_month')
            ->selectSub($period('YEAR'), 'period_year')
            ->withCount('parts')
            // Urutan bawaan (terbaru dulu); klik judul kolom menggantikannya
            ->when(!$this->hasColumnOrder(), fn ($q) => $q->orderBy('DTAKINGDATE', 'desc'));
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
            Column::make('DTAKINGDATE')->title('Taking Date'),
            Column::make('close_date')->title('Close Date')->searchable(false),
            Column::make('period_month')->title('Month')->searchable(false),
            Column::make('period_year')->title('Year')->searchable(false),
            Column::make('VEVENTTYPE')->title('Event'),
            Column::make('VDESC')->title('Description'),
            Column::make('parts_count')->title('Part Count')->searchable(false)->addClass('text-end'),
            Column::make('VCREA')->title('Created By'),
            Column::make('DCREA')->title('Created Date'),
            Column::make('VMODI')->title('Modified By'),
            Column::make('DMODI')->title('Modified Date'),
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

        if ($data->VEVENTTYPE === TakingEvent::PRE) {
            $buttons[] = [
                'action' => 'parts',
                'service' => $this->masterService('006', 'Update'),
                'icon' => 'list-details',
                'class' => 'event-parts',
                'title' => 'Pre-Taking part list',
                'id' => $data->IID,
                'data' => ['date' => $data->DTAKINGDATE?->format('Y-m-d'), 'count' => (int) $data->parts_count],
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
