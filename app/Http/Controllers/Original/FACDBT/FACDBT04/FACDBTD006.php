<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\DataTables\Original\FACDBT04\FACDBTD006DataTable;
use App\Exports\FACDBT\FACDBT04\FACDBTD006 as Export;
use App\Http\Controllers\Controller;
use App\Models\FACDBT04\FACDBT_SIMINVTAKINGDATAHISTORY as TakingHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;

// History Taking: hasil taking periode yang sudah ditutup (SIM_INV_TAKING_DATA_HISTORY)
class FACDBTD006 extends Controller
{
    public function index(FACDBTD006DataTable $dataTable)
    {
        // "Centang semua": cukup kirim ID baris yang lolos filter, bukan seluruh isi tabel
        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $dataTable->filteredKeys('IID')]);
        }

        return $dataTable->render('modules.FACDBT.FACDBT04.FACDBTD006.FACDBTD006');
    }

    /**
     * Daftar periode di history untuk filter Year -> Month -> Event.
     * Event = satu periode taking (TAKING_DATE); satu bulan bisa punya lebih dari satu event.
     */
    public function getFilters()
    {
        $periods = TakingHistory::query()
            ->select('YEAR', 'MONTH', 'TAKING_DATE', 'CLOSE_DATE')
            ->distinct()
            ->orderByDesc('YEAR')
            ->orderByDesc('MONTH')
            ->orderBy('TAKING_DATE')
            ->toBase()
            ->get();

        $events = [];
        foreach ($periods->groupBy(fn ($p) => $p->YEAR . '-' . $p->MONTH) as $items) {
            foreach ($items->values() as $i => $p) {
                $taking = Carbon::parse($p->TAKING_DATE);
                $close  = Carbon::parse($p->CLOSE_DATE);
                $range  = $taking->isSameDay($close)
                    ? $taking->format('j M')
                    : $taking->format('j') . '–' . $close->format('j M');

                $events[] = [
                    'year'        => (int) $p->YEAR,
                    'month'       => (int) $p->MONTH,
                    'taking_date' => $taking->toDateString(),
                    'label'       => 'Taking ' . ($i + 1) . ' · ' . $range,
                ];
            }
        }

        return response()->json(['events' => $events]);
    }

    /**
     * Export CSV: data yang dicentang saja, atau semua data pada filter periode & pencarian saat ini.
     */
    public function export(Request $request)
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), fn ($id) => $id !== null && $id !== ''));
        $keyword = trim((string) $request->input('keyword', ''));

        $query = TakingHistory::query()
            ->when(!empty($ids), fn ($q) => $q->whereIn('IID', $ids))
            ->when(empty($ids), fn ($q) => $q
                ->forPeriod($request->input('year'), $request->input('month'), $request->input('taking_date'))
                ->when($keyword !== '', fn ($q) => $q->whereAny([
                    'location_no', 'location_group', 'area', 'warehouse', 'part_no', 'part_name', 'LOT_BATCH_NO',
                ], 'LIKE', "%{$keyword}%")))
            ->orderByDesc('TAKING_DATE')
            ->orderBy('location_no')
            ->orderBy('part_no')
            ->orderBy('IID');

        return Export::download($query);
    }
}
