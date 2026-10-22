<?php

namespace App\DataTables\Original\FACDBT04;

use App\Services\FACDBT\VarianceDashboardService;
use App\Traits\DataTableTrait;
use Illuminate\Support\Collection;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Tabel detail part dashboard variance (template seperti FACDBT01, tanpa tombol aksi).
// Turunannya menentukan dashboard (service, id tabel, route) dan sisi tabel (positive / negative).
abstract class VarianceDetailDataTable extends DataTable
{
    use DataTableTrait;

    // Format angka di browser: 1310275083 -> "1,310,275,083"
    protected const NUMBER_RENDER = "$.fn.dataTable.render.number(',', '.', 0)";

    /** 'positive' atau 'negative' */
    protected string $type;

    /**
     * Service perhitungan dashboard (hasilnya di-cache, dipakai juga oleh ringkasan).
     */
    abstract protected function service(): VarianceDashboardService;

    /**
     * Prefix id tabel, contoh "facdbtd003" -> "facdbtd003-positive-table".
     */
    abstract protected function tablePrefix(): string;

    /**
     * Prefix nama route ajax, contoh "taking-qty-amount" -> "taking-qty-amount.positive-table".
     */
    abstract protected function routePrefix(): string;

    /**
     * Arah urutan awal kolom Var Amount.
     */
    protected function orderDirection(): string
    {
        return 'desc';
    }

    /**
     * Build the DataTable class.
     *
     * @param  Collection<int, array>  $query  Results from query() method (nama parameter harus $query).
     */
    public function dataTable(Collection $query): CollectionDataTable
    {
        // Var Qty / Var Amount dikirim sebagai angka (diformat di browser, lihat getColumns()) supaya
        // pengurutan per kolom numerik; bila diformat di server "1,310,275,083" terurut di bawah "261,868,010"
        return (new CollectionDataTable($query))
            ->addIndexColumn()
            ->filter(function ($instance) {
                $keyword = strtolower(trim((string) request('keyword')));

                if ($keyword !== '') {
                    $instance->collection = $instance->collection->filter(
                        fn ($row) => str_contains(strtolower($row['name']), $keyword)
                            || str_contains(strtolower($row['part_no']), $keyword)
                            || str_contains(strtolower($row['location_no'] ?? ''), $keyword)
                    );
                }
            }, true);
    }

    /**
     * Get the query source of dataTable (hasil perhitungan, sudah di-cache di service).
     */
    public function query(): Collection
    {
        return collect($this->service()->compute(request())[$this->type]['rows'] ?? []);
    }

    /**
     * Optional method if you want to use the html builder.
     */
    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId($this->tableId())
            ->columns($this->getColumns())
            ->minifiedAjax(route($this->routePrefix() . ".{$this->type}-table"))
            ->orderBy(4, $this->orderDirection())
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
            Column::make('qty')->title('Var Qty')->addClass('text-end')->render(self::NUMBER_RENDER),
            Column::make('amount')->title('Var Amount')->addClass('text-end')->render(self::NUMBER_RENDER),
        ];
    }

    public function tableId(): string
    {
        return $this->tablePrefix() . "-{$this->type}-table";
    }

    /**
     * Get the filename for export.
     */
    protected function filename(): string
    {
        return strtoupper($this->tablePrefix()) . '_' . ucfirst($this->type) . '-' . date('YmdHis');
    }
}
