<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT01;

use App\DataTables\Original\FACDBT01\FACDBTF004DataTable;
use App\Exports\Templates\FACDBT\FACDBT01\FACDBTF004 as Template;
use App\Helpers\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\FACDBT\FACDBT01\TakingBookImportRequest;
use App\Http\Requests\FACDBT\FACDBT01\TakingBookRequest;
use App\Imports\FACDBT\FACDBT01\FACDBTF004 as Import;
use App\Models\FACDBT01\FACDBT_SIMINVTAKINGBOOKTAB as TakingBook;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel as FacadesExcel;

class FACDBTF004 extends Controller
{
    protected string $connection = 'INV_TAKING'; // Sesuaikan dengan konfigurasi koneksi database Anda
    protected string $table = 'SIM_INV_TAKING_BOOK_TAB';

    public function __construct()
    {
        ini_set('memory_limit', '1024M');
    }

    /**
     * Render DataTable view beserta ringkasan statistik
     */
    public function index(FACDBTF004DataTable $dataTable)
    {
        // "Centang semua": cukup kirim ID baris yang lolos filter, bukan seluruh isi tabel
        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $dataTable->filteredKeys('INV_LIST_NO')]);
        }

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

    /**
     * API Load data (menggantikan query F3 dengan tabel TEST)
     */
    public function loadData(Request $request)
    {
        $invListNo = $request->get('inv_list_no') ?: $this->getInvListNo();

        if (!$invListNo) {
            return response()->json([]);
        }

        $result = DB::connection($this->connection)->table('SIM_INV_TAKING_BOOK_TAB as ITB')
            ->leftJoin('SIM_INV_LOCATION_TEST as IL', 'ITB.LOCATION_NO', '=', 'IL.location_no')
            ->leftJoin('SIM_INV_PART_TEST as IP', 'ITB.PART_NO', '=', 'IP.part_no')
            ->where('ITB.QUANTITY', '>', 0)
            ->where('ITB.INV_LIST_NO', $invListNo)
            ->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('SIM_INV_TAKING_CONCLUDING_TAB as ITC')
                    ->where('ITC.ROWSTATE', 'Started')
                    ->whereColumn(DB::raw('ITC.MONTH'), DB::raw('CAST(ITB.MONTH AS UNSIGNED)'))
                    ->whereColumn(DB::raw('ITC.YEAR'), DB::raw('CAST(ITB.YEAR AS UNSIGNED)'));
            })
            ->select([
                'ITB.MONTH',
                'ITB.YEAR',
                'ITB.PART_NO',
                'IP.description',
                'ITB.LOCATION_NO',
                'ITB.LOT_BATCH_NO',
                'ITB.QUANTITY',
                'ITB.UNIT_COST',
                'ITB.UNIT_MEAS',
                'IP.dim_quality',
                'ITB.WDR',
                'ITB.HANDLING_UNIT_TYPE_ID',
                'IL.warehouse_id',
                'IL.location_group',
                'IL.location_group_description',
                'IP.accounting_group',
                'IP.accounting_group_desc',
                'IP.part_product_family',
                'IP.part_product_family_desc',
                'ITB.CREATED_BY',
                'ITB.CREATED_DATETIME',
                'ITB.MODIFIED_BY',
                'ITB.MODIFIED_DATETIME'
            ])
            ->get();

        return response()->json($result);
    }

    /* =========================================================================
     *  DATABASE REPOSITORY / HELPER METHODS
     * ========================================================================= */

    public function getInvListNo(): int
    {
        return (int) DB::connection($this->connection)->table($this->table)
            ->max(DB::raw('CAST(INV_LIST_NO AS UNSIGNED)')) ?? 0;
    }

    public function getMaxInvListNo($month, $year): int
    {
        return (int) DB::connection($this->connection)->table($this->table)
            ->where('MONTH', $month)
            ->where('YEAR', $year)
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

    public function checkDataTemp($invListNo): int
    {
        return DB::connection($this->connection)->table('SIM_INV_TAKING_BOOK_TEMP_TAB')
            ->where('INV_LIST_NO', $invListNo)
            ->count();
    }

    public function insertBookepingTemp($invListNo, $maxSeqCountingReportLine): void
    {
        DB::connection($this->connection)->statement("CALL InsertTempData(?, ?)", [
            $invListNo,
            $maxSeqCountingReportLine
        ]);
    }

    public function getSeqTemp($invListNo): array
    {
        return DB::connection($this->connection)->table('SIM_INV_TAKING_BOOK_TEMP_TAB')
            ->select('SEQ')
            ->where('INV_LIST_NO', $invListNo)
            ->get()
            ->map(fn($item) => (array) $item)
            ->toArray();
    }

    public function deleteBookepingTemp(): void
    {
        DB::connection($this->connection)->statement("
            DELETE FROM SIM_INV_TAKING_BOOK_TEMP_TAB
            WHERE (INV_LIST_NO, SEQ) IN (
                SELECT INV_LIST_NO, SEQ FROM SIM_INV_TAKING_BOOK_TAB
            )
        ");
    }

    /* =========================================================================
     *  CRUD & EXCEL IMPORT / EXPORT METHODS
     * ========================================================================= */

    public function store(TakingBookRequest $request)
    {
        TakingBook::create(array_merge($request->validated(), [
            'CREATED_DATETIME' => now(),
            'CREATED_BY'       => auth()->user()->VUSERNAME ?? 'SYSTEM',
        ]));

        return Response::success(message: 'Taking Book created successfully');
    }

    public function show($id)
    {
        if ($id === 'undefined' || empty($id)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid ID provided'], 400);
        }

        $book = TakingBook::where('INV_LIST_NO', $id)->first();
        if (!$book) {
            return response()->json(['status' => 'error', 'message' => 'Taking Book not found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $book]);
    }

    public function update(TakingBookRequest $request, $id)
    {
        $book = TakingBook::where('INV_LIST_NO', $id)->first();
        if (!$book) {
            return Response::error(message: 'Data not found', status: 404);
        }

        $book->update(array_merge($request->validated(), [
            'MODIFIED_DATETIME' => now(),
            'MODIFIED_BY'       => auth()->user()->VUSERNAME ?? 'SYSTEM',
        ]));

        return Response::success(message: 'Taking Book updated successfully');
    }

    public function destroy($id)
    {
        $book = TakingBook::where('INV_LIST_NO', $id)->first();
        if (!$book) {
            return Response::error(message: 'Data not found', status: 404);
        }

        try {
            $book->delete();
            return Response::success(message: 'Taking Book data deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Taking Book data cannot be deleted, it may be related to other data');
        }
    }

    public function destroyMultiple(Request $request)
    {
        $ids = $request->ids;
        try {
            TakingBook::whereIn('INV_LIST_NO', $ids)->delete();
            return Response::success(message: 'Selected Taking Books deleted successfully');
        } catch (\Throwable $th) {
            return Response::error(message: 'Taking Book data cannot be deleted, it may be related to other data');
        }
    }

    public function downloadTemplate()
    {
        return FacadesExcel::download(new Template, 'Taking_Book_Template.xlsx');
    }

    public function import(TakingBookImportRequest $request)
    {
        try {
            $import = new Import;
            // Jenis file (xlsx / xls / csv) dideteksi dari ekstensi file yang diupload
            FacadesExcel::import($import, $request->file('file'));

            $result = $import->getResult();
            if ($result->totalError > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Validation error in {$result->totalErrorLogs} out of {$result->totalLogs} records",
                    'data'    => [
                        'total_data'       => $result->totalLogs,
                        'total_error_data' => $result->totalErrorLogs,
                        'total_error'      => $result->totalError,
                        'error_data'       => $result->errorLogs->values(),
                    ],
                ], 400);
            }

            $cleaned = $import->store();
            if (empty($cleaned)) {
                return response()->json(['success' => false, 'message' => 'No valid data to import.'], 400);
            }

            // Transaksi di koneksi INV_TAKING (tempat data book disimpan), bukan koneksi default
            $db = DB::connection($this->connection);
            $db->beginTransaction();
            try {
                // 1. Hitung INV_LIST_NO baru = max(INV_LIST_NO) + 1
                $latestInvListNo = $this->getInvListNo();
                $nextInvListNo   = $latestInvListNo + 1;
                $now             = now();
                $user            = auth()->user()->VUSERNAME ?? 'SYSTEM';

                // 2. Set INV_LIST_NO pada seluruh data baru ke $nextInvListNo
                //    dan pastikan timestamp audit terisi
                foreach ($cleaned as &$item) {
                    $item['INV_LIST_NO']      = $nextInvListNo;
                    $item['CREATED_DATETIME'] = $item['CREATED_DATETIME'] ?? $now;
                    $item['CREATED_BY']       = $item['CREATED_BY'] ?? $user;
                }
                unset($item); // Lepas reference foreach

                // 3. Gunakan insert biasa (bukan upsert) per batch/chunk 500 baris
                //    sehingga data lama sama sekali tidak ter-overwrite
                foreach (array_chunk($cleaned, 1000) as $chunk) {
                    TakingBook::insert($chunk);
                }

                $insertedCount = count($cleaned);
                $db->commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully imported {$insertedCount} record(s) into INV_LIST_NO: {$nextInvListNo}.",
                    'data'    => [
                        'inv_list_no'     => $nextInvListNo,
                        'total_imported'  => $insertedCount,
                        'total_processed' => $insertedCount,
                    ],
                ], 200);
            } catch (Exception $e) {
                $db->rollBack();
                return response()->json(['success' => false, 'message' => 'Failed to import data: ' . $e->getMessage()], 500);
            }
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            return Response::error(message: 'Error reading the Excel file: ' . $e->getMessage());
        }
    }
}