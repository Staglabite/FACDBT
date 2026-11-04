<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF004DataTable;
use App\Exports\FACDBT\FACDBT01\MasterDataExport;
use App\Http\Controllers\Controller;
use App\Models\FACDBT01\FACDBT_SIMINVTAKINGBOOKTAB as TakingBook;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Taking Book (FACDBTF004): hanya lihat & export data bookkeeping INV_LIST_NO terbaru.
 * Data book milik sistem taking / scanner, jadi tidak ada tambah, ubah, hapus, maupun import di menu ini.
 */
class FACDBTF004 extends Controller
{
    protected string $connection = 'FACDBT';
    protected string $table = 'SIM_INV_TAKING_BOOK_TAB';

    /**
     * Render DataTable view beserta ringkasan statistik
     */
    public function index(FACDBTF004DataTable $dataTable)
    {
        $invListNo = $this->getInvListNo();

        // 1. Total Data Count Per Count Report
        $dataCountReport = $this->getMaxSeqCountingReportLine($invListNo);

        // 2. Total Data Bookkeeping
        $countBookeeping = $this->getCountBookeeping($invListNo);

        // 3. Variance Data Bookkeeping (DataCountReport - CountBookeeping)
        $varianceBookeeping = (int) $dataCountReport - (int) $countBookeeping;

        $qtyBookeeping = $this->getQtyBookeeping($invListNo);

        $summary = [
            'invListNo'          => $invListNo,
            'dateTransferred'    => $this->getDateTransferred($invListNo),
            'dataCountReport'    => number_format((float) $dataCountReport),
            'countBookeeping'    => number_format((float) $countBookeeping),
            'varianceBookeeping' => number_format((float) $varianceBookeeping),
            'qtyBookeeping'      => number_format((float) $qtyBookeeping, 2),
        ];

        return $dataTable->render('modules.FACDBT.FACDBT01.FACDBTF004.FACDBTF004', compact('summary'));
    }

    /**
     * Export CSV seluruh data book INV_LIST_NO terbaru, kolom sama dengan tabel.
     */
    public function export()
    {
        set_time_limit(300);

        $columns = [];
        $expressions = [];
        foreach (FACDBTF004DataTable::COLUMNS as $alias => [$column, $title]) {
            $columns[$alias] = $title;
            $expressions[$alias] = $column;
        }

        $query = FACDBTF004DataTable::latestBook(new TakingBook)
            ->orderBy('b.LOCATION_NO')
            ->orderBy('b.PART_NO')
            ->orderBy('b.SEQ');

        return (new MasterDataExport($query, $columns, $expressions))
            ->download('Taking_Book_' . $this->getInvListNo() . '-' . date('YmdHis') . '.csv');
    }

    /**
     * Mengambil MAX(SEQ) untuk Total Data Count per Count Report
     */
    public function getMaxSeqCountingReportLine($invListNo): int
    {
        if (!$invListNo) {
            return 0;
        }

        $maxSeq = DB::connection($this->connection)
            ->table($this->table)
            ->where('INV_LIST_NO', $invListNo)
            ->max(DB::raw('CAST(SEQ AS UNSIGNED)'));

        return (int) ($maxSeq ?? 0);
    }

    public function getInvListNo(): int
    {
        return (int) DB::connection($this->connection)->table($this->table)
            ->max(DB::raw('CAST(INV_LIST_NO AS UNSIGNED)')) ?? 0;
    }

    public function getCountBookeeping($invListNo): int
    {
        return DB::connection($this->connection)->table($this->table)
            ->where('INV_LIST_NO', $invListNo)
            ->count();
    }

    public function getQtyBookeeping($invListNo): float
    {
        return (float) DB::connection($this->connection)->table($this->table)
            ->where('INV_LIST_NO', $invListNo)
            ->sum('QUANTITY');
    }

    public function getDateTransferred($invListNo): string
    {
        $date = DB::connection($this->connection)->table($this->table)
            ->where('INV_LIST_NO', $invListNo)
            ->max('CREATED_DATETIME');

        return $date ? Carbon::parse($date)->translatedFormat('d F Y H:i:s') : '-';
    }
}
