<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Dashboard Progress by Group Location & Detail Location
class FACDBTD002 extends Controller
{
    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    // Slot filter waktu: [dayOffset, 'jam:menit']. dayOffset 0 = hari pertama taking, 1 = hari kedua.
    private const SLOTS = [
        [0, '07:30'],
        [0, '09:00'],
        [0, '10:00'],
        [0, '11:00'],
        [0, '12:00'],
        [0, '13:00'],
        [0, '14:00'],
        [0, '15:00'],
        [0, '16:15'],
        [0, '19:30'],
        [1, '09:00'],
        [1, '10:00'],
        [1, '11:00'],
    ];

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD002.FACDBTD002');
    }

    /**
     * Daftar tombol filter lokasi (location_group dari SIM_INV_GROUP_LOCATION).
     */
    public function getGroups()
    {
        $groups = DB::connection(self::CONN)
            ->table('SIM_INV_GROUP_LOCATION')
            ->selectRaw('TRIM(location_group) as location_group')
            ->whereRaw("TRIM(location_group) <> ''")
            ->distinct()
            ->orderBy('location_group')
            ->pluck('location_group');

        return response()->json($groups);
    }

    /**
     * Daftar tombol filter waktu. Label memakai tanggal taking sebenarnya, contoh "6-07.30".
     */
    public function getTimeSlots()
    {
        $period = $this->resolvePeriod(DB::connection(self::CONN));
        if ($period === null) {
            return response()->json([]);
        }

        $slots = [];
        foreach ($this->slotTimes($period['baseDay']) as $slotDate) {
            $slots[] = [
                'label'     => $slotDate->format('j') . '-' . $slotDate->format('H.i'),
                'full_time' => $slotDate->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json($slots);
    }

    /**
     * Progress per warehouse (SIM_INV_LOCATION_TEST.warehouse) untuk lokasi di SIM_INV_GROUP_LOCATION.
     * - % Item : item (part unik) taking terhadap item book
     * - % Qty  : qty taking terhadap qty book
     * Book & taking dicocokkan per lokasi.
     * - Filter waktu dipilih : taking kumulatif s/d jam slot; tanpa filter waktu: semua taking di periode.
     * - Filter group dipilih : semua warehouse dari lokasi di group tersebut.
     * - Tanpa filter group   : hanya warehouse yang sudah punya progress (taking > 0) dari keseluruhan.
     */
    public function getData(Request $request)
    {
        $empty = ['item' => [], 'qty' => []];
        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db);
        if ($period === null) {
            return response()->json($empty);
        }

        $takingUntil = $this->parseTime($request->get('time')) ?? $period['closeDate'];
        $group       = trim((string) $request->get('group', ''));

        $locations = $this->locationWarehouses($db, $group);
        if (empty($locations)) {
            return response()->json($empty);
        }

        // Agregasi per (lokasi, part) tidak bergantung pada filter group, jadi cache-nya dipakai bersama
        $base = implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo']]);

        $book = Cache::remember('FACDBTD002:book:v2:' . md5($base), self::CACHE_TTL, fn () => $this->perLocationPart(
            $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$period['takingDate'], $period['closeDate']])
                ->where('INV_LIST_NO', $period['invListNo']),
            'QUANTITY'
        ));

        $taking = Cache::remember('FACDBTD002:taking:v2:' . md5($base . '|' . $takingUntil), self::CACHE_TTL, fn () => $this->perLocationPart(
            $db->table('SIM_INV_TAKING_DATA_TAB')
                ->whereBetween('CREATED_DATETIME', [$period['takingDate'], $takingUntil]),
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

        if ($group === '') {
            $rows = $rows->filter(fn ($r) => $r['pct_item'] > 0 || $r['pct_qty'] > 0)->values();
        }

        // Tiap chart diurutkan dari progress terbesar
        $sortBy = fn (string $key) => $rows->sortByDesc($key)
            ->map(fn ($r) => ['warehouse' => $r['warehouse'], 'pct' => $r[$key]])
            ->values()
            ->all();

        return response()->json([
            'item' => $sortBy('pct_item'),
            'qty'  => $sortBy('pct_qty'),
        ]);
    }

    /**
     * Pemetaan lokasi -> warehouse untuk lokasi di SIM_INV_GROUP_LOCATION (opsional difilter per group).
     * Dikerjakan di PHP karena join dengan TRIM() di kedua sisi membuat index tidak terpakai.
     *
     * @return array<string, string> [location_no => warehouse]
     */
    private function locationWarehouses($db, string $group): array
    {
        $warehouses = Cache::remember('FACDBTD002:warehouses:v1', self::CACHE_TTL, function () use ($db) {
            $map = [];
            foreach ($db->table('SIM_INV_LOCATION_TEST')->select('location_no', 'warehouse')->get() as $row) {
                $warehouse = trim((string) $row->warehouse);
                $map[trim((string) $row->location_no)] = $warehouse !== '' ? $warehouse : 'UNKNOWN';
            }

            return $map;
        });

        $locations = $db->table('SIM_INV_GROUP_LOCATION')
            ->when($group !== '', fn ($q) => $q->whereRaw('TRIM(location_group) = ?', [$group]))
            ->distinct()
            ->pluck('LOCATION_NO');

        $result = [];
        foreach ($locations as $loc) {
            $loc = trim((string) $loc);
            $result[$loc] = $warehouses[$loc] ?? 'UNKNOWN';
        }

        return $result;
    }

    /**
     * Agregasi tabel besar (book / taking) per (LOCATION_NO, PART_NO) tanpa join.
     *
     * @return array<int, array{0: string, 1: string, 2: float}> [[location_no, part_no, qty], ...]
     */
    private function perLocationPart($query, string $qtyColumn): array
    {
        return $query
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
        $concluding = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('TAKING_DATE', 'CLOSE_DATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

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

    /**
     * @return Carbon[]
     */
    private function slotTimes(Carbon $baseDay): array
    {
        return array_map(function ($slot) use ($baseDay) {
            [$dayOffset, $hm] = $slot;
            [$h, $m] = explode(':', $hm);

            return $baseDay->copy()->addDays($dayOffset)->setTime((int) $h, (int) $m, 0);
        }, self::SLOTS);
    }

    private function parseTime($value): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
