<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\DataTables\Original\FACDBT04\FACDBTD004NegativeDataTable;
use App\DataTables\Original\FACDBT04\FACDBTD004PositiveDataTable;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\BuildsVarianceLines;
use App\Services\FACDBT\AbsoluteVarianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Variance Taking by Part No per Location No / Absolute Variance Dashboard (ringkasan + detail part per lokasi lebih / kurang)
class FACDBTD004 extends Controller
{
    use BuildsVarianceLines;

    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    public function index(FACDBTD004PositiveDataTable $positive, FACDBTD004NegativeDataTable $negative)
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD004.FACDBTD004', [
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
    public function getData(Request $request, AbsoluteVarianceService $service)
    {
        $data = $service->compute($request);

        foreach (['positive', 'negative'] as $key) {
            unset($data[$key]['rows']);
        }

        return response()->json($data);
    }

    /**
     * Tabel biru: variance lebih (taking > book), nilai absolut.
     */
    public function positiveTable(FACDBTD004PositiveDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Tabel merah: variance kurang (taking < book), nilai absolut.
     */
    public function negativeTable(FACDBTD004NegativeDataTable $dataTable)
    {
        return $dataTable->ajax();
    }
}
