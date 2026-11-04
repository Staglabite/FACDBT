<?php

namespace App\DataTables\Original\FACDBT01;

use App\Models\FACDBT01\FACDBT_SIMINVTAKINGBOOKTAB as TakingBook;
use App\Services\FACDBT\FacdbtTable;
use App\Traits\DataTableTrait;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

// Taking Book Datatable (hanya lihat; data book milik sistem taking / scanner)
class FACDBTF004DataTable extends DataTable
{
    use DataTableTrait;

    /**
     * Kolom tabel & file export: [alias => [kolom SQL, judul]]. Kolom b dari book, p dari Master Part,
     * l dari Master Location.
     */
    public const COLUMNS = [
        'MONTH' => ['b.MONTH', 'Month'],
        'YEAR' => ['b.YEAR', 'Year'],
        'PART_NO' => ['b.PART_NO', 'Part No'],
        'part_description' => ['p.VPARTDESC', 'Part Description'],
        'LOCATION_NO' => ['b.LOCATION_NO', 'Location No'],
        'location_name' => ['l.VLOCDESC', 'Location Name'],
        'HANDLING_UNIT_TYPE_ID' => ['b.HANDLING_UNIT_TYPE_ID', 'Handling Unit Type Id'],
        'QUANTITY' => ['b.QUANTITY', 'On Hand Qty'],
        'UNIT_MEAS' => ['b.UNIT_MEAS', 'UoM'],
        'LOT_BATCH_NO' => ['b.LOT_BATCH_NO', 'Lot Batch No'],
        'WDR' => ['b.WDR', 'WDR'],
        'WAREHOUSE' => ['b.WAREHOUSE', 'Warehouse'],
        'BAY_NO' => ['b.BAY_NO', 'Bay'],
        'ROW_NO' => ['b.ROW_NO', 'Row'],
        'TIER_NO' => ['b.TIER_NO', 'Tier'],
        'BIN_NO' => ['b.BIN_NO', 'Bin'],
        'AVAILABILITY_CONTROL_ID' => ['b.AVAILABILITY_CONTROL_ID', 'Availability Control Id'],
        'AVAILABILITY_CONTROL_DESC' => ['b.AVAILABILITY_CONTROL_DESC', 'Availability Control Description'],
        'accounting_group' => ['p.VACCGROUP', 'Accounting Group'],
        'location_group' => ['l.VLOCGROUP', 'Location Group'],
        'location_group_desc' => ['l.VLOCGROUPDESC', 'Location Group Desc'],
        'product_family' => ['p.VPRODGROUP', 'Product Family'],
    ];

    /**
     * @param  QueryBuilder<TakingBook>  $query  Results from query() method.
     */
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addIndexColumn()
            ->filter(function ($query) {
                $keyword = request('keyword');

                if (! empty($keyword)) {
                    // Pencarian teks disesuaikan dengan kolom-kolom utama pada Taking Book
                    $query->whereAny([
                        'b.PART_NO',
                        'p.VPARTDESC',
                        'b.LOCATION_NO',
                        'l.VLOCDESC',
                        'b.LOT_BATCH_NO',
                        'b.WAREHOUSE',
                        'l.VLOCGROUP',
                    ], 'LIKE', "%{$keyword}%");
                }
            });
    }

    /**
     * @return QueryBuilder<TakingBook>
     */
    public function query(TakingBook $model): QueryBuilder
    {
        $select = ['b.*'];
        foreach (self::COLUMNS as $alias => [$column]) {
            if (! str_starts_with($column, 'b.')) {
                $select[] = "{$column} as {$alias}";
            }
        }

        return self::latestBook($model)->select($select);
    }

    /**
     * Book INV_LIST_NO terbaru, dengan deskripsi part dari Master Part serta nama & group lokasi dari
     * Master Location (yang belum dihapus). Dipakai tabel dan export.
     *
     * @return QueryBuilder<TakingBook>
     */
    public static function latestBook(TakingBook $model): QueryBuilder
    {
        $latestInvListNo = DB::connection('FACDBT')
            ->table('SIM_INV_TAKING_BOOK_TAB')
            ->max(DB::raw('CAST(INV_LIST_NO AS UNSIGNED)'));

        return $model->newQuery()
            ->from('SIM_INV_TAKING_BOOK_TAB as b')
            ->leftJoin(FacdbtTable::PARTS . ' as p', fn ($join) => $join->on('p.VPARTNO', '=', 'b.PART_NO')->whereNull('p.DDELETE'))
            ->leftJoin(FacdbtTable::LOCATIONS . ' as l', fn ($join) => $join->on('l.VLOCATIONNO', '=', 'b.LOCATION_NO')->whereNull('l.DDELETE'))
            ->where('b.INV_LIST_NO', $latestInvListNo);
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('facdbtf004-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->parameters([
                // Data diambil per halaman dari server; tanpa ini seluruh tabel dimuat sekaligus dan memori PHP habis
                'serverSide' => true,
                'processing' => false,
                'orderCellsTop' => true,
                'order' => [],
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

    public function getColumns(): array
    {
        $columns = [
            Column::make('DT_RowIndex')->title('No')->orderable(false)->searchable(false)->width(30)->addClass('text-center'),
        ];
        foreach (self::COLUMNS as $alias => [$column, $title]) {
            $columns[] = Column::make($alias)->name($column)->title($title);
        }

        return $columns;
    }

    protected function filename(): string
    {
        return 'Taking_Book-' . date('YmdHis');
    }
}
