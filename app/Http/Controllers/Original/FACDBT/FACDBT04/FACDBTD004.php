<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\DataTables\Original\FACDBT04\FACDBTD004NegativeDataTable;
use App\DataTables\Original\FACDBT04\FACDBTD004PositiveDataTable;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\BuildsVarianceLines;
use App\Services\FACDBT\NettVarianceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Nett Variance Dashboard (ringkasan + detail part positif / negatif)
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
     * Pilihan filter: slot waktu dan area (location_group dari SIM_INV_GROUP_LOCATION).
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
            'times' => $period ? $this->timeSlotsPayload($period['baseDay']) : [],
            'areas' => $areas,
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
    public function positiveTable(FACDBTD004PositiveDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Tabel merah: part dengan nett variance minus.
     */
    public function negativeTable(FACDBTD004NegativeDataTable $dataTable)
    {
        return $dataTable->ajax();
    }
}
