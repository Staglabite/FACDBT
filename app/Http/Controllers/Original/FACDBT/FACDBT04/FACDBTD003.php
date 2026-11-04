<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\DataTables\Original\FACDBT04\FACDBTD003NegativeDataTable;
use App\DataTables\Original\FACDBT04\FACDBTD003PositiveDataTable;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\BuildsVarianceLines;
use App\Services\FACDBT\NettVarianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Variance Taking by Qty & Amount / Nett Variance Dashboard (ringkasan + detail part positif / negatif)
class FACDBTD003 extends Controller
{
    use BuildsVarianceLines;

    private const CONN = 'FACDBT';
    private const CACHE_TTL = 60;

    public function index(FACDBTD003PositiveDataTable $positive, FACDBTD003NegativeDataTable $negative)
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD003.FACDBTD003', [
            'positiveTable' => $positive->html(),
            'negativeTable' => $negative->html(),
        ]);
    }

    /**
     * Pilihan filter: hari untuk rentang waktu (area dipilih dengan klik chart Nett Variance by Area).
     */
    public function getFilters()
    {
        $db = DB::connection(self::CONN);
        $period = $this->resolvePeriod($db);

        return response()->json([
            'time_range' => $period ? $this->timeRangePayload($period['takingDate'], $period['closeDate']) : ['days' => []],
        ]);
    }

    /**
     * Ringkasan & chart (tanpa baris detail; detail part dimuat lewat DataTable).
     */
    public function getData(Request $request, NettVarianceService $service)
    {
        $data = $service->compute($request);

        foreach (['positive', 'negative'] as $key) {
            unset($data[$key]['rows']);
        }

        return response()->json($data);
    }

    /**
     * Tabel biru: part dengan nett variance tidak minus.
     */
    public function positiveTable(FACDBTD003PositiveDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Tabel merah: part dengan nett variance minus.
     */
    public function negativeTable(FACDBTD003NegativeDataTable $dataTable)
    {
        return $dataTable->ajax();
    }
}
