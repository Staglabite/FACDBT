<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\DataTables\Original\FACDBT04\FACDBTD005NegativeDataTable;
use App\DataTables\Original\FACDBT04\FACDBTD005PositiveDataTable;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\BuildsVarianceLines;
use App\Services\FACDBT\AbsoluteVarianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Absolute Variance Dashboard (ringkasan + detail part lebih / kurang)
class FACDBTD005 extends Controller
{
    use BuildsVarianceLines;

    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    public function index(FACDBTD005PositiveDataTable $positive, FACDBTD005NegativeDataTable $negative)
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD005.FACDBTD005', [
            'positiveTable' => $positive->html(),
            'negativeTable' => $negative->html(),
        ]);
    }

    /**
     * Pilihan filter: hari untuk rentang waktu dan area (location_group dari SIM_INV_GROUP_LOCATION).
     */
    public function getFilters()
    {
        $db = DB::connection(self::CONN);
        $period = $this->resolvePeriod($db);

        $areas = $db->table('SIM_INV_GROUP_LOCATION')
            ->selectRaw('TRIM(location_group) as location_group')
            ->whereRaw("TRIM(location_group) <> ''")
            ->distinct()
            ->orderBy('location_group')
            ->pluck('location_group');

        return response()->json([
            'time_range' => $period ? $this->timeRangePayload($period['takingDate'], $period['closeDate']) : ['days' => []],
            'areas' => $areas,
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
    public function positiveTable(FACDBTD005PositiveDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Tabel merah: variance kurang (taking < book), nilai absolut.
     */
    public function negativeTable(FACDBTD005NegativeDataTable $dataTable)
    {
        return $dataTable->ajax();
    }
}
