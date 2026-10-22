<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Services\FACDBT\AreaName;
use App\DataTables\Original\FACDBT04\FACDBTD006DataTable;
use App\Exports\FACDBT\FACDBT04\FACDBTD006 as Export;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\CachesDashboard;
use App\Services\FACDBT\RunningTakingPeriod;
use App\Services\FACDBT\TakingRevisionTable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard Taking Data Revision: revisi data taking (SIM_INV_TAKING_DATA_REVISION_TAB) periode yang sedang berjalan.
 *
 * - Taking Data Revision : per area, Item_Book (jumlah baris book) dan % Area (jumlah revisi / Item_Book x 100)
 * - Reason Revision      : porsi revisi per REASON_REVISION
 * - Revision Performance : porsi revisi per MODIFIED_BY
 * Area = SIM_INV_GROUP_LOCATION.location_group dari LOCATION_NO. Filter Modified By & Area boleh lebih dari satu.
 * Di bawah chart: tabel semua revisi seluruh periode taking (filter Year -> Month -> Event) dengan export CSV.
 */
class FACDBTD006 extends Controller
{
    use CachesDashboard;

    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    private const UNKNOWN = 'UNKNOWN';

    public function index(FACDBTD006DataTable $dataTable)
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD006.FACDBTD006', ['dataTable' => $dataTable->html()]);
    }

    /**
     * Ajax tabel revisi. Dengan `ids_only`: key semua baris yang lolos filter (untuk "centang semua").
     */
    public function table(FACDBTD006DataTable $dataTable)
    {
        if (request()->boolean('ids_only')) {
            $ids = $dataTable->dataTable($dataTable->query())
                ->skipPaging()
                ->getFilteredQuery()
                ->reorder()
                ->select(DB::raw(TakingRevisionTable::ROW_KEY_SQL . ' as row_key'))
                ->pluck('row_key')
                ->all();

            return response()->json(['ids' => $ids]);
        }

        return $dataTable->ajax();
    }

    /**
     * Daftar periode untuk filter Year -> Month -> Event.
     */
    public function getPeriods(TakingRevisionTable $table)
    {
        return response()->json(['events' => $table->periods()]);
    }

    /**
     * Export CSV: data yang dicentang saja, atau semua data pada filter periode & pencarian saat ini.
     */
    public function export(Request $request, TakingRevisionTable $table)
    {
        $ids = array_values(array_filter((array) $request->input('ids', []), fn ($id) => $id !== null && $id !== ''));

        $query = $table->query($request)
            ->when(!empty($ids), fn ($q) => $q->whereIn(DB::raw(TakingRevisionTable::ROW_KEY_SQL), $ids))
            ->orderByDesc('r.CREATED_DATETIME')
            ->orderBy('r.ID_TAKING')
            ->orderBy('r.REV_NO');

        return Export::download($query);
    }

    /**
     * Data semua chart + daftar pilihan filter, untuk filter request `modifiers[]` dan `areas[]`
     * (tanpa pilihan: semua). Filter Area berlaku untuk semua chart; filter Modified By untuk jumlah revisi.
     */
    public function getData(Request $request)
    {
        $source = $this->source();

        $modifiers = $this->selected($request, 'modifiers');
        $areas     = $this->selected($request, 'areas');

        $inArea     = fn (string $area) => !$areas || isset($areas[$area]);
        $byModifier = fn (string $modifier) => !$modifiers || isset($modifiers[$modifier]);

        $itemBook = [];
        foreach ($source['book'] as $area => $count) {
            if ($inArea($area)) {
                $itemBook[$area] = $count;
            }
        }

        $revisionsPerArea = [];
        $perReason        = [];
        $perModifier      = [];
        $total            = 0;
        foreach ($source['revisions'] as [$area, $modifier, $reason, $count]) {
            if (!$inArea($area) || !$byModifier($modifier)) {
                continue;
            }
            $revisionsPerArea[$area] = ($revisionsPerArea[$area] ?? 0) + $count;
            $perReason[$reason]      = ($perReason[$reason] ?? 0) + $count;
            $perModifier[$modifier]  = ($perModifier[$modifier] ?? 0) + $count;
            $total += $count;
        }

        $byArea = [];
        foreach (array_keys($itemBook + $revisionsPerArea) as $area) {
            $book      = $itemBook[$area] ?? 0;
            $revisions = $revisionsPerArea[$area] ?? 0;
            $byArea[]  = [
                'area'      => (string) $area,
                'item_book' => $book,
                'revisions' => $revisions,
                // Tanpa book persentase tidak bisa dihitung
                'percent'   => $book > 0 ? round($revisions / $book * 100, 1) : null,
            ];
        }
        usort($byArea, fn ($a, $b) => [$b['percent'] ?? -1, $b['item_book']] <=> [$a['percent'] ?? -1, $a['item_book']]);

        return response()->json([
            'modifiers'            => $source['modifiers'],
            'areas'                => $source['areas'],
            'total'                => $total,
            'by_area'              => $byArea,
            'reason_revision'      => $this->shares($perReason, $total),
            'revision_performance' => $this->shares($perModifier, $total),
        ]);
    }

    /**
     * Data dasar periode berjalan (di-cache; dihitung ulang bila ada scan / revisi / book baru):
     * jumlah baris book per area, jumlah revisi per (area, modified by, reason), dan daftar pilihan filter.
     */
    private function source(): array
    {
        $empty = ['book' => [], 'revisions' => [], 'modifiers' => [], 'areas' => $this->areaList()];

        $db     = DB::connection(self::CONN);
        $period = RunningTakingPeriod::find($db);
        if (!$period) {
            return $empty;
        }

        $takingDate = Carbon::parse($period->TAKING_DATE)->startOfDay()->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($period->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        return $this->dashboardCache("FACDBTD006:source:v3:{$takingDate}:{$closeDate}", function () use ($db, $takingDate, $closeDate, $empty) {
            $areaOf = $this->locationAreas($db);
            $area   = fn ($loc) => $areaOf[trim((string) $loc)] ?? self::UNKNOWN;

            $book = [];
            $invListNo = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->orderByRaw('CAST(INV_LIST_NO AS UNSIGNED) DESC')
                ->value('INV_LIST_NO');
            if ($invListNo !== null && $invListNo !== '') {
                $rows = $db->table('SIM_INV_TAKING_BOOK_TAB')
                    ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                    ->where('INV_LIST_NO', $invListNo)
                    ->groupBy('LOCATION_NO')
                    ->select('LOCATION_NO', DB::raw('COUNT(*) as n'))
                    ->get();
                foreach ($rows as $r) {
                    $a = $area($r->LOCATION_NO);
                    $book[$a] = ($book[$a] ?? 0) + (int) $r->n;
                }
            }

            // [[area, modified by, reason, jumlah revisi], ...]
            $revisions = [];
            $modifiers = [];
            // Satu revisi = REV_NO bertambah 1, jadi baris REV_NO 0 (data awal) tidak dihitung
            $rows = $db->table('SIM_INV_TAKING_DATA_REVISION_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('REV_NO', '>', 0)
                ->groupBy('LOCATION_NO', 'MODIFIED_BY', 'REASON_REVISION')
                ->select('LOCATION_NO', 'MODIFIED_BY', 'REASON_REVISION', DB::raw('COUNT(*) as n'))
                ->get();
            foreach ($rows as $r) {
                $modifier = trim((string) $r->MODIFIED_BY) ?: self::UNKNOWN;
                $reason   = trim((string) $r->REASON_REVISION) ?: self::UNKNOWN;
                $key      = $area($r->LOCATION_NO) . "\0{$modifier}\0{$reason}";

                $revisions[$key] ??= [$area($r->LOCATION_NO), $modifier, $reason, 0];
                $revisions[$key][3] += (int) $r->n;
                $modifiers[$modifier] = true;
            }

            $modifiers = array_keys($modifiers);
            sort($modifiers, SORT_NATURAL | SORT_FLAG_CASE);

            return [
                'book'      => $book,
                'revisions' => array_values($revisions),
                'modifiers' => $modifiers,
                'areas'     => $empty['areas'],
            ];
        });
    }

    /**
     * Daftar area untuk filter (location_group dari SIM_INV_GROUP_LOCATION).
     */
    private function areaList(): array
    {
        return AreaName::list(DB::connection(self::CONN)->table('SIM_INV_GROUP_LOCATION')->distinct()->pluck('location_group'));
    }

    /**
     * @return array<string, string> [location_no => location_group]; lokasi di beberapa group memakai group pertama
     */
    private function locationAreas($db): array
    {
        $map = [];
        foreach ($db->table('SIM_INV_GROUP_LOCATION')->orderBy('IID')->get(['LOCATION_NO', 'location_group']) as $r) {
            $loc   = trim((string) $r->LOCATION_NO);
            $group = AreaName::of($r->location_group);
            if ($group !== '' && !isset($map[$loc])) {
                $map[$loc] = $group;
            }
        }

        return $map;
    }

    /**
     * @return array<string, true> nilai filter yang dipilih (kosong = semua)
     */
    private function selected(Request $request, string $key): array
    {
        $values = array_map(fn ($v) => trim((string) $v), (array) $request->input($key, []));

        return array_fill_keys(array_filter($values, fn ($v) => $v !== ''), true);
    }

    /**
     * Porsi tiap nilai terhadap total revisi, diurutkan dari terbesar.
     */
    private function shares(array $counts, int $total): array
    {
        arsort($counts);

        $result = [];
        foreach ($counts as $label => $count) {
            $result[] = [
                'label'   => (string) $label,
                'count'   => $count,
                'percent' => $total > 0 ? round($count / $total * 100, 1) : 0,
            ];
        }

        return $result;
    }
}
