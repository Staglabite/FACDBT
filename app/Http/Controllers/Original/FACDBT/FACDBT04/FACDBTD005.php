<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\DataTables\Original\FACDBT04\FACDBTD005DataTable;
use App\Exports\FACDBT\FACDBT04\FACDBTD005 as Export;
use App\Http\Controllers\Controller;
use App\Models\FACDBT04\FACDBT_SIMINVTAKINGDATAHISTORY as TakingHistory;
use App\Services\FACDBT\TakingEvent;
use App\Services\FACDBT\TakingHistoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

// History Taking: hasil taking periode yang sudah ditutup (FACDBT_HISTAKINGDATAS)
class FACDBTD005 extends Controller
{
    public function index(FACDBTD005DataTable $dataTable, TakingHistoryService $history)
    {
        // Periode yang baru ditutup langsung disimpan ke history saat halaman dibuka (tanpa menunggu scheduler)
        if (!request()->ajax()) {
            try {
                $history->snapshotPending(auth()->user()->VUSERNAME ?? 'SYSTEM');
            } catch (\Throwable $e) {
                // Halaman tetap dibuka; snapshot dicoba lagi saat halaman dibuka berikutnya
                report($e);
            }
        }

        // "Centang semua": cukup kirim ID baris yang lolos filter, bukan seluruh isi tabel
        if (request()->boolean('ids_only')) {
            return response()->json(['ids' => $dataTable->filteredKeys('IID')]);
        }

        return $dataTable->render('modules.FACDBT.FACDBT04.FACDBTD005.FACDBTD005');
    }

    /**
     * Daftar periode di history untuk filter Year -> Month -> Taking, dan pilihan filter Event (Master Event).
     * Taking = satu periode taking (DTAKINGDATE); satu bulan bisa punya lebih dari satu taking.
     */
    public function getFilters()
    {
        $periods = TakingHistory::query()
            ->select('IYEAR', 'IMONTH', 'DTAKINGDATE', 'DCLOSEDATE', 'VEVENTTYPE')
            ->distinct()
            ->orderByDesc('IYEAR')
            ->orderByDesc('IMONTH')
            ->orderBy('DTAKINGDATE')
            ->toBase()
            ->get();

        $events = [];
        foreach ($periods->groupBy(fn ($p) => $p->IYEAR . '-' . $p->IMONTH) as $items) {
            foreach ($items->values() as $i => $p) {
                $taking = Carbon::parse($p->DTAKINGDATE);
                $close  = Carbon::parse($p->DCLOSEDATE);
                $range  = $taking->isSameDay($close)
                    ? $taking->format('j M')
                    : $taking->format('j') . '–' . $close->format('j M');

                $event = TakingEvent::TYPES[$p->VEVENTTYPE ?? ''] ?? null;

                $events[] = [
                    'year'        => (int) $p->IYEAR,
                    'month'       => (int) $p->IMONTH,
                    'taking_date' => $taking->toDateString(),
                    'event_type'  => $p->VEVENTTYPE ?? null,
                    'label'       => 'Taking ' . ($i + 1) . ' · ' . $range . ($event ? " · {$event}" : ''),
                ];
            }
        }

        return response()->json([
            'events'      => $events,
            'event_types' => TakingEvent::TYPES,
        ]);
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
                ->forEvent($request->input('event_type'))
                ->when($keyword !== '', fn ($q) => $q->whereAny([
                    'VLOCATIONNO', 'VLOCGROUP', 'VAREA', 'VWAREHOUSE', 'VPARTNO', 'VPARTNAME', 'VLOTBATCHNO',
                ], 'LIKE', "%{$keyword}%")))
            ->orderByDesc('DTAKINGDATE')
            ->orderBy('VLOCATIONNO')
            ->orderBy('VPARTNO')
            ->orderBy('IID');

        return Export::download($query);
    }
}
