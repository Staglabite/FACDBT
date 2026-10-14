<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\NettVarianceService;
use App\Traits\DataTableTrait;
use Illuminate\Support\Collection;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Detail part Nett Variance Dashboard; turunannya menentukan tabel positif / negatif
abstract class FACDBTD004DataTable extends DataTable
{
    use DataTableTrait;

    /** 'positive' atau 'negative' */
    protected string $type;

    /**
     * Build the DataTable class.
     *
     * @param  Collection<int, array>  $query  Results from query() method (nama parameter harus $query).
     */
    public function dataTable(Collection $query): CollectionDataTable
    {
        return (new CollectionDataTable($query))
            ->addIndexColumn()
            ->editColumn('qty', fn ($row) => number_format($row['qty'], 0))
            ->editColumn('amount', fn ($row) => number_format($row['amount'], 0))
            ->filter(function ($instance) {
                $keyword = strtolower(trim((string) request('keyword')));

                if ($keyword !== '') {
                    $instance->collection = $instance->collection->filter(
                        fn ($row) => str_contains(strtolower($row['name']), $keyword)
                            || str_contains(strtolower($row['part_no']), $keyword)
                    );
                }
            }, true);
    }

    /**
     * Get the query source of dataTable (hasil perhitungan, sudah di-cache di service).
     */
    public function query(NettVarianceService $service): Collection
    {
        return collect($service->compute(request())[$this->type]['rows'] ?? []);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId($this->tableId())
            ->columns($this->getColumns())
            ->minifiedAjax(route("nett-variance-dashboard.{$this->type}-table"))
            // Tabel positif dari amount terbesar, tabel negatif dari yang paling minus
            ->orderBy(4, $this->type === 'positive' ? 'desc' : 'asc')
            ->parameters([
                'processing' => false,
                'orderCellsTop' => true,
                // Data dimuat JS setelah ringkasan selesai dihitung (supaya hasil cache dipakai)
                'deferLoading' => 0,
                'pageLength' => 10,
                'initComplete' => '
                    function () {
                        var table = this.api();
                        ' . $this->getScriptForSearchRow(false) . '
                    }
                ',
                'dom' => 'r' .
                    "<'table-responsive border-top'tr>" .
                    "<'d-flex align-items-center justify-content-center justify-content-lg-between flex-wrap gap-2 text-center px-3 mt-3'ip>",
            ]);
    }

    /**
     * Get the dataTable columns definition.
     */
    public function getColumns(): array
    {
        return [
            Column::make('DT_RowIndex')->title('No')->orderable(false)->searchable(false)->width(30)->addClass('text-center'),
            Column::make('name')->title('Part Name'),
            Column::make('part_no')->title('Part No'),
            Column::make('qty')->title('Var Qty')->addClass('text-end'),
            Column::make('amount')->title('Var Amount')->addClass('text-end'),
        ];
    }

    public function tableId(): string
    {
        return "facdbtd004-{$this->type}-table";
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return 'Nett_Variance_' . ucfirst($this->type) . '-' . date('YmdHis');
    }
}
