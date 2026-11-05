<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Services\FACDBT\FacdbtTable;
use App\Services\FACDBT\AreaName;
use App\Services\FACDBT\RunningTakingPeriod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\CachesDashboard;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\FiltersTakingRange;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\UsesTakingEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Dashboard Progress by Group Location & Detail Location
class FACDBTD002 extends Controller
{
    use CachesDashboard;
    use FiltersTakingRange;
    use UsesTakingEvent;

    private const CONN = 'FACDBT';
    private const CACHE_TTL = 60;

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD002.FACDBTD002');
    }

    /**
     * Pilihan hari untuk filter rentang waktu (Dari - Sampai).
     */
    public function getTimeRange()
    {
        $period = $this->resolvePeriod(DB::connection(self::CONN));
        if ($period === null) {
            return response()->json(['days' => []]);
        }

        return response()->json($this->timeRangePayload($period['takingDate'], $period['closeDate']));
    }

    /**
     * Progress per warehouse (kolom WAREHOUSE di tabel book & taking).
     * - % Item : jumlah part unik taking di warehouse / jumlah part unik book di warehouse x 100
     * - % Qty  : total qty taking di warehouse / total qty book di warehouse x 100
     * - Filter waktu dipilih : hanya taking yang di-scan di rentang Dari - Sampai; tanpa filter: seluruh periode.
     * - Filter area dipilih  : hanya baris dari lokasi di area tersebut (`group`, atau beberapa area `groups[]`
     *                          saat warehouse diklik dan warehouse itu ada di beberapa area).
     * - Tanpa filter area    : hanya warehouse yang sudah punya progress (taking > 0).
     * `areas` = [warehouse => [area, ...]] untuk warehouse yang tampil, dipakai saat chart warehouse diklik.
     */
    public function getData(Request $request)
    {
        $empty = ['item' => [], 'qty' => [], 'areas' => (object) []];
        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db);
        if ($period === null) {
            return response()->json($empty);
        }

        [$takingFrom, $takingTo] = $this->takingRange($request, $period['takingDate'], $period['closeDate']);
        $groups = collect((array) $request->input('groups', []))
            ->push($request->input('group', ''))
            ->map(fn ($g) => trim((string) $g))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $locationAreas = $this->locationAreas($db);

        // Lokasi yang termasuk area terpilih; null = semua lokasi
        $allowed = null;
        if (!empty($groups)) {
            $allowed = [];
            foreach ($locationAreas as $loc => $areas) {
                if (array_intersect($areas, $groups)) {
                    $allowed[$loc] = true;
                }
            }
            if (empty($allowed)) {
                return response()->json($empty);
            }
        }

        // Agregasi per (warehouse, lokasi, part) tidak bergantung pada filter area, jadi cache-nya dipakai bersama
        $base = implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo']]);

        $book = $this->dashboardCache('FACDBTD002:book:v3:' . md5($base), fn () => $this->perWarehouseLocationPart(
            $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$period['takingDate'], $period['closeDate']])
                ->where('INV_LIST_NO', $period['invListNo']),
            'QUANTITY'
        ));

        $taking = $this->dashboardCache('FACDBTD002:taking:v4:' . md5($base . '|' . $takingFrom . '|' . $takingTo), fn () => $this->perWarehouseLocationPart(
            $db->table('SIM_INV_TAKING_DATA_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingFrom, $takingTo]),
            'QTY'
        ));

        $bookTotals   = $this->totalsPerWarehouse($book, $allowed);
        $takingTotals = $this->totalsPerWarehouse($taking, $allowed);

        $rows = collect($bookTotals)->map(function ($b, $warehouse) use ($takingTotals) {
            $t = $takingTotals[$warehouse] ?? ['items' => 0, 'qty' => 0.0];

            return [
                'warehouse' => (string) $warehouse,
                'pct_item'  => $b['items'] > 0 ? round(($t['items'] / $b['items']) * 100, 2) : 0,
                'pct_qty'   => $b['qty'] > 0 ? round(($t['qty'] / $b['qty']) * 100, 2) : 0,
            ];
        })->values();

        if (empty($groups)) {
            $rows = $rows->filter(fn ($r) => $r['pct_item'] > 0 || $r['pct_qty'] > 0)->values();
        }

        $areasOf = $this->warehouseAreas($book, $locationAreas);

        // Tiap chart diurutkan dari progress terbesar
        $sortBy = fn (string $key) => $rows->sortByDesc($key)
            ->map(fn ($r) => ['warehouse' => $r['warehouse'], 'pct' => $r[$key]])
            ->values()
            ->all();

        return response()->json([
            'item'  => $sortBy('pct_item'),
            'qty'   => $sortBy('pct_qty'),
            'areas' => (object) $rows->mapWithKeys(fn ($r) => [$r['warehouse'] => $areasOf[$r['warehouse']] ?? []])->all(),
        ]);
    }

    /**
     * @return array<string, array<int, string>> [location_no => [area, ...]] dari Master Group Location
     */
    private function locationAreas($db): array
    {
        return $this->dashboardCache('FACDBTD002:location-areas:v1', function () use ($db) {
            $map = [];
            foreach (FacdbtTable::active($db, FacdbtTable::GROUP_LOCATIONS)->get(['VLOCATIONNO', 'VLOCGROUP']) as $r) {
                $area = AreaName::of($r->VLOCGROUP);
                if ($area !== '') {
                    $map[trim((string) $r->VLOCATIONNO)][$area] = true;
                }
            }

            return array_map(fn ($areas) => array_keys($areas), $map);
        });
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: float}>  $book
     * @param  array<string, array<int, string>>  $locationAreas
     * @return array<string, array<int, string>> [warehouse => [area, ...]] dari lokasi book warehouse tersebut
     */
    private function warehouseAreas(array $book, array $locationAreas): array
    {
        $map = [];
        foreach ($book as [$warehouse, $loc]) {
            foreach ($locationAreas[$loc] ?? [] as $area) {
                $map[$warehouse][$area] = true;
            }
        }

        return array_map(function ($areas) {
            $areas = array_keys($areas);
            sort($areas);

            return $areas;
        }, $map);
    }

    /**
     * Agregasi tabel besar (book / taking) per (WAREHOUSE, LOCATION_NO, PART_NO) tanpa join.
     *
     * @return array<int, array{0: string, 1: string, 2: string, 3: float}> [[warehouse, location_no, part_no, qty], ...]
     */
    private function perWarehouseLocationPart($query, string $qtyColumn): array
    {
        return $this->eventScope($query, DB::connection(self::CONN))
            ->groupBy('WAREHOUSE', 'LOCATION_NO', 'PART_NO')
            ->select('WAREHOUSE', 'LOCATION_NO', 'PART_NO', DB::raw("SUM({$qtyColumn}) as qty"))
            ->get()
            ->map(function ($r) {
                $warehouse = trim((string) $r->WAREHOUSE);

                return [$warehouse !== '' ? $warehouse : 'UNKNOWN', trim((string) $r->LOCATION_NO), (string) $r->PART_NO, (float) $r->qty];
            })
            ->all();
    }

    /**
     * Total per warehouse: part unik & jumlah qty.
     *
     * @param  array<int, array{0: string, 1: string, 2: string, 3: float}>  $rows
     * @param  array<string, bool>|null  $allowed  lokasi area terpilih; null = semua lokasi
     * @return array<string, array{items: int, qty: float}>
     */
    private function totalsPerWarehouse(array $rows, ?array $allowed): array
    {
        $parts  = [];
        $totals = [];

        foreach ($rows as [$warehouse, $loc, $part, $qty]) {
            if ($allowed !== null && !isset($allowed[$loc])) {
                continue;
            }

            $parts[$warehouse][$part] = true;
            $totals[$warehouse]['qty'] = ($totals[$warehouse]['qty'] ?? 0.0) + $qty;
        }

        foreach ($totals as $warehouse => $total) {
            $totals[$warehouse]['items'] = count($parts[$warehouse]);
        }

        return $totals;
    }

    /**
     * Periode taking aktif (TAKING_DATE s/d CLOSE_DATE terbaru) + INV_LIST_NO terbaru.
     */
    private function resolvePeriod($db): ?array
    {
        $concluding = RunningTakingPeriod::find($db);

        if (!$concluding) {
            return null;
        }

        $baseDay    = Carbon::parse($concluding->TAKING_DATE)->startOfDay();
        $takingDate = $baseDay->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($concluding->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        // INV_LIST_NO dibandingkan sebagai string supaya index tetap terpakai
        $invListNo = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->orderByRaw('CAST(INV_LIST_NO AS UNSIGNED) DESC')
            ->value('INV_LIST_NO');

        if ($invListNo === null || $invListNo === '') {
            return null;
        }

        return [
            'baseDay'    => $baseDay,
            'takingDate' => $takingDate,
            'closeDate'  => $closeDate,
            'invListNo'  => (string) $invListNo,
        ];
    }
}
