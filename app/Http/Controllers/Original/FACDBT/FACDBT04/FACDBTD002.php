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
     * Progress per warehouse (Master Location VWAREHOUSE) untuk lokasi di Master Group Location.
     * - % Item : item (part unik) taking terhadap item book
     * - % Qty  : qty taking terhadap qty book
     * Book & taking dicocokkan per lokasi.
     * - Filter waktu dipilih : hanya taking yang di-scan di rentang Dari - Sampai; tanpa filter: seluruh periode.
     * - Filter group dipilih : semua warehouse dari lokasi di group tersebut (`group`, atau beberapa area `groups[]`
     *                          saat warehouse diklik dan warehouse itu ada di beberapa area).
     * - Tanpa filter group   : hanya warehouse yang sudah punya progress (taking > 0) dari keseluruhan.
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

        $locations = $this->locationWarehouses($db, $groups);
        if (empty($locations)) {
            return response()->json($empty);
        }

        // Agregasi per (lokasi, part) tidak bergantung pada filter group, jadi cache-nya dipakai bersama
        $base = implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo']]);

        $book = $this->dashboardCache('FACDBTD002:book:v2:' . md5($base), fn () => $this->perLocationPart(
            $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$period['takingDate'], $period['closeDate']])
                ->where('INV_LIST_NO', $period['invListNo']),
            'QUANTITY'
        ));

        $taking = $this->dashboardCache('FACDBTD002:taking:v3:' . md5($base . '|' . $takingFrom . '|' . $takingTo), fn () => $this->perLocationPart(
            $db->table('SIM_INV_TAKING_DATA_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingFrom, $takingTo]),
            'QTY'
        ));

        $bookTotals   = $this->totalsPerWarehouse($book, $locations);
        $takingTotals = $this->totalsPerWarehouse($taking, $locations);

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

        $areasOf = $this->warehouseAreas($db);

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
     * Pemetaan lokasi -> warehouse untuk lokasi di Master Group Location (opsional difilter beberapa area).
     * Dikerjakan di PHP karena join dengan TRIM() di kedua sisi membuat index tidak terpakai.
     *
     * @param  array<int, string>  $groups  area terpilih; kosong = semua
     * @return array<string, string> [location_no => warehouse]
     */
    private function locationWarehouses($db, array $groups): array
    {
        $warehouses = $this->warehouseOfLocations($db);

        // Area gabungan (mis. QA) mencakup semua location group-nya
        $groupNames = collect($groups)->flatMap(fn ($g) => AreaName::groupsOf($g))->map(fn ($g) => strtoupper($g))->unique()->values()->all();

        $locations = FacdbtTable::active($db, FacdbtTable::GROUP_LOCATIONS)
            ->when(!empty($groupNames), fn ($q) => $q->whereIn(DB::raw('UPPER(TRIM(VLOCGROUP))'), $groupNames))
            ->distinct()
            ->pluck('VLOCATIONNO');

        $result = [];
        foreach ($locations as $loc) {
            $loc = trim((string) $loc);
            $result[$loc] = $warehouses[$loc] ?? 'UNKNOWN';
        }

        return $result;
    }

    /**
     * @return array<string, array<int, string>> [warehouse => [area, ...]] dari lokasi warehouse di Master Group Location
     */
    private function warehouseAreas($db): array
    {
        return $this->dashboardCache('FACDBTD002:warehouse-areas:v1', function () use ($db) {
            $warehouses = $this->warehouseOfLocations($db);
            $map = [];
            foreach (FacdbtTable::active($db, FacdbtTable::GROUP_LOCATIONS)->get(['VLOCATIONNO', 'VLOCGROUP']) as $r) {
                $area = AreaName::of($r->VLOCGROUP);
                if ($area !== '') {
                    $map[$warehouses[trim((string) $r->VLOCATIONNO)] ?? 'UNKNOWN'][$area] = true;
                }
            }

            return array_map(function ($areas) {
                $areas = array_keys($areas);
                sort($areas);

                return $areas;
            }, $map);
        });
    }

    /**
     * @return array<string, string> [location_no => warehouse] dari Master Location
     */
    private function warehouseOfLocations($db): array
    {
        return $this->dashboardCache('FACDBTD002:warehouses:v2', function () use ($db) {
            $map = [];
            foreach (FacdbtTable::active($db, FacdbtTable::LOCATIONS)->select('VLOCATIONNO', 'VWAREHOUSE')->get() as $row) {
                $warehouse = trim((string) $row->VWAREHOUSE);
                $map[trim((string) $row->VLOCATIONNO)] = $warehouse !== '' ? $warehouse : 'UNKNOWN';
            }

            return $map;
        });
    }

    /**
     * Agregasi tabel besar (book / taking) per (LOCATION_NO, PART_NO) tanpa join.
     *
     * @return array<int, array{0: string, 1: string, 2: float}> [[location_no, part_no, qty], ...]
     */
    private function perLocationPart($query, string $qtyColumn): array
    {
        return $this->eventScope($query, DB::connection(self::CONN))
            ->groupBy('LOCATION_NO', 'PART_NO')
            ->select('LOCATION_NO', 'PART_NO', DB::raw("SUM({$qtyColumn}) as qty"))
            ->get()
            ->map(fn ($r) => [trim((string) $r->LOCATION_NO), (string) $r->PART_NO, (float) $r->qty])
            ->all();
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: float}>  $rows
     * @param  array<string, string>  $locations  [location_no => warehouse]
     * @return array<string, array{items: int, qty: float}>
     */
    private function totalsPerWarehouse(array $rows, array $locations): array
    {
        $parts  = [];
        $totals = [];

        foreach ($rows as [$loc, $part, $qty]) {
            if (!isset($locations[$loc])) {
                continue;
            }

            $warehouse = $locations[$loc];
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
