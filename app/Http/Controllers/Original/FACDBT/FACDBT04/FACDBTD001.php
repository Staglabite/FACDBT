<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FACDBTD001 extends Controller
{
    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    // Slot waktu untuk chart atas
    private const SLOTS = [
        0 => [0, '00:00', '6-00.00'],
        1 => [0, '07:30', '6-07.30'],
        2 => [0, '10:00', '6-10.00'],
        3 => [0, '13:00', '6-13.00'],
        4 => [0, '16:15', '6-16.15'],
        5 => [0, '19:30', '6-19.30'],
        6 => [1, '09:00', '7-09.00'],
        7 => [1, '10:00', '7-10.00'],
        8 => [1, '11:00', '7-11.00'],
    ];

    private const NON_OIL_SLOTS = [0, 1, 2, 3, 4, 5, 6, 7, 8];
    private const OIL_SLOTS     = [0, 2, 3, 4, 8];

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD001.FACDBTD001');
    }

    /**
     * INV_LIST_NO terbaru, dikembalikan dalam bentuk ASLI (string) dari tabel.
     * Penting: jangan dibandingkan sebagai angka di WHERE, karena MySQL akan
     * meng-cast setiap baris dan index tidak terpakai (full scan).
     */
    private function latestInvListNo($db, string $from, string $to): ?string
    {
        $value = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', [$from, $to])
            ->orderByRaw('CAST(INV_LIST_NO AS UNSIGNED) DESC')
            ->value('INV_LIST_NO');

        return ($value === null || $value === '') ? null : (string) $value;
    }

    /**
     * Subquery: 1 baris per PART_NO (is_oil + total qty book).
     * Cost di-group dulu supaya join tidak menggandakan baris / SUM(QUANTITY).
     */
    private function bookPartsSub($db, string $takingDate, string $closeDate, string $invListNo)
    {
        $costSub = $db->table('SIM_INV_UNIT_COST_TEST')
            ->select(
                'part_no',
                DB::raw("MAX(CASE WHEN LOWER(inventory_uom) = 'ml' THEN 1 ELSE 0 END) as is_oil")
            )
            ->groupBy('part_no');

        return $db->table('SIM_INV_TAKING_BOOK_TAB as b')
            ->leftJoinSub($costSub, 'cost', 'cost.part_no', '=', 'b.PART_NO')
            ->whereBetween('b.CREATED_DATETIME', [$takingDate, $closeDate])
            ->where('b.INV_LIST_NO', $invListNo)
            ->groupBy('b.PART_NO')
            ->select(
                'b.PART_NO',
                DB::raw('COALESCE(MAX(cost.is_oil), 0) as is_oil'),
                DB::raw('SUM(b.QUANTITY) as qty_book')
            );
    }

    /**
     * Subquery pemetaan lokasi -> area (location_group dari SIM_INV_GROUP_LOCATION).
     * DISTINCT per (lokasi, area) supaya lokasi di lebih dari satu group tidak hilang.
     */
    private function areaMapSub($db)
    {
        return $db->table('SIM_INV_GROUP_LOCATION as gl')
            ->select(
                DB::raw('TRIM(gl.LOCATION_NO) as loc_no'),
                DB::raw("NULLIF(TRIM(gl.location_group), '') as area_name")
            )
            ->distinct();
    }

    /**
     * Endpoint untuk Chart Atas (Non-Oil & Oil Progress Keseluruhan)
     * Tidak terpengaruh filter tombol waktu
     */
    public function getData(Request $request)
    {
        $empty = ['non_oil' => [], 'oil' => []];
        $db = DB::connection(self::CONN);

        $concluding = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('TAKING_DATE', 'CLOSE_DATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

        if (!$concluding) {
            return response()->json($empty);
        }

        $baseDay    = Carbon::parse($concluding->TAKING_DATE)->startOfDay();
        $takingDate = $baseDay->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($concluding->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        $latestInvListNo = $this->latestInvListNo($db, $takingDate, $closeDate);

        if ($latestInvListNo === null) {
            return response()->json($empty);
        }

        $cacheKey = "FACDBTD001:v6:{$takingDate}:{$closeDate}:{$latestInvListNo}";

        $payload = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($db, $baseDay, $takingDate, $closeDate, $latestInvListNo) {
            $slotTimes = [];
            foreach (self::SLOTS as $i => [$dayOffset, $hm]) {
                [$h, $m] = explode(':', $hm);
                $slotTimes[$i] = $baseDay->copy()->addDays($dayOffset)->setTime((int) $h, (int) $m, 0)->format('Y-m-d H:i:s');
            }

            $bookSub = $this->bookPartsSub($db, $takingDate, $closeDate, $latestInvListNo);

            // Total book per kategori (0 = non-oil, 1 = oil) -> dihitung di database
            $bookTotals = $db->query()
                ->fromSub($bookSub, 'bk')
                ->select(
                    'is_oil',
                    DB::raw('COUNT(*) as item_book'),
                    DB::raw('SUM(qty_book) as qty_book')
                )
                ->groupBy('is_oil')
                ->get()
                ->keyBy('is_oil');

            if ($bookTotals->isEmpty()) {
                return ['non_oil' => [], 'oil' => []];
            }

            // Progres taking untuk SEMUA slot dalam 1 query (conditional aggregation),
            // menggantikan load ratusan ribu baris ke PHP + filter berulang per slot.
            $agg = $db->table('SIM_INV_TAKING_DATA_TAB as t')
                ->joinSub($bookSub, 'bk', 'bk.PART_NO', '=', 't.PART_NO')
                ->whereBetween('t.CREATED_DATETIME', [$takingDate, $closeDate])
                ->groupBy('bk.is_oil')
                ->select('bk.is_oil');

            foreach ($slotTimes as $i => $time) {
                $agg->selectRaw(
                    "COUNT(DISTINCT CASE WHEN t.CREATED_DATETIME <= ? THEN t.PART_NO END) as item_{$i}",
                    [$time]
                );
                $agg->selectRaw(
                    "COALESCE(SUM(CASE WHEN t.CREATED_DATETIME <= ? THEN t.QTY ELSE 0 END), 0) as qty_{$i}",
                    [$time]
                );
            }

            $takingRows = $agg->get()->keyBy('is_oil');

            $buildSeries = function (int $isOilFlag, array $slotIndexes) use ($bookTotals, $takingRows, $slotTimes) {
                $book      = $bookTotals->get($isOilFlag);
                $taking    = $takingRows->get($isOilFlag);
                $itemBook  = (int) ($book->item_book ?? 0);
                $qtyBook   = (float) ($book->qty_book ?? 0);
                $series    = [];

                foreach ($slotIndexes as $slotIdx) {
                    $itemTaking = (int) ($taking->{"item_{$slotIdx}"} ?? 0);
                    $qtyTaking  = (float) ($taking->{"qty_{$slotIdx}"} ?? 0);

                    $series[] = [
                        'time'        => $slotTimes[$slotIdx],
                        'item_book'   => $itemBook,
                        'qty_book'    => $qtyBook,
                        'item_taking' => $itemTaking,
                        'qty_taking'  => $qtyTaking,
                        'pct_qty'     => $qtyBook > 0 ? round(($qtyTaking / $qtyBook) * 100, 2) : 0,
                        'pct_item'    => $itemBook > 0 ? round(($itemTaking / $itemBook) * 100, 2) : 0,
                    ];
                }

                return $series;
            };

            return [
                'non_oil' => $buildSeries(0, self::NON_OIL_SLOTS),
                'oil'     => $buildSeries(1, self::OIL_SLOTS),
            ];
        });

        return response()->json($payload);
    }

    /**
     * Mengambil daftar tombol filter waktu untuk bagian atas
     */
    public function getTimeSlots()
    {
        $db = DB::connection(self::CONN);
        $concluding = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('TAKING_DATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

        if (!$concluding) {
            return response()->json([]);
        }

        $baseDay = Carbon::parse($concluding->TAKING_DATE)->startOfDay();
        $slots = [];

        // Definisi: [dayOffset, 'jam:menit', 'format jam titik']
        // dayOffset 0 = hari pertama Taking, dayOffset 1 = hari kedua Taking
        $filterSlots = [
            [0, '00:00', '00.00'],
            [0, '07:30', '07.30'],
            [0, '13:00', '13.00'],
            [0, '16:15', '16.15'],
            [0, '19:30', '19.30'],
            [1, '09:00', '09.00'],
            [1, '10:00', '10.00'],
            [1, '11:00', '11.00'],
        ];

        foreach ($filterSlots as $key => [$dayOffset, $hm, $timeDot]) {
            [$h, $m] = explode(':', $hm);
            $slotDate = $baseDay->copy()->addDays($dayOffset)->setTime((int) $h, (int) $m, 0);

            // $slotDate->format('j') menghasilkan angka tanggal tanpa leading zero (misal: 6, 12, 13)
            // Hasil label: "12-07.30", "13-09.00", dsb.
            $label = $slotDate->format('j') . '-' . $timeDot;

            $slots[] = [
                'key'       => $key,
                'label'     => $label,
                'full_time' => $slotDate->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json($slots);
    }

    /**
     * Endpoint untuk Chart Bawah (Counting Progress by Part & Oil Area)
     * Mengikuti batas parameter waktu yang dipilih
     */
    public function getProgressByArea(Request $request)
    {
        $db = DB::connection(self::CONN);

        $concluding = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('TAKING_DATE', 'CLOSE_DATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

        if (!$concluding) {
            return response()->json(['parts' => [], 'oil' => []]);
        }

        $baseDay    = Carbon::parse($concluding->TAKING_DATE)->startOfDay();
        $takingDate = $baseDay->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($concluding->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        // Parameter waktu filter (default ke slot 6-07.30 jika belum ada yang diklik)
        $selectedTime = $request->get('time');
        if ($selectedTime) {
            try {
                $selectedTime = Carbon::parse($selectedTime)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $selectedTime = null;
            }
        }
        if (!$selectedTime) {
            $selectedTime = $baseDay->copy()->setTime(7, 30, 0)->format('Y-m-d H:i:s');
        }

        $latestInvListNo = $this->latestInvListNo($db, $takingDate, $closeDate);

        if ($latestInvListNo === null) {
            return response()->json(['parts' => [], 'oil' => []]);
        }

        $cacheKey = "FACDBTD001:area:v6:{$takingDate}:{$closeDate}:{$latestInvListNo}:{$selectedTime}";

        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($db, $takingDate, $closeDate, $selectedTime, $latestInvListNo) {
            $areaSub = $this->areaMapSub($db);

            // Book dikelompokkan di database: 1 baris per (area, part)
            $bookRows = $db->table('SIM_INV_TAKING_BOOK_TAB as b')
                ->leftJoinSub($areaSub, 'ar', DB::raw('TRIM(b.LOCATION_NO)'), '=', 'ar.loc_no')
                ->whereBetween('b.CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('b.INV_LIST_NO', $latestInvListNo)
                ->groupBy('area_label', 'b.PART_NO')
                ->select([
                    DB::raw("COALESCE(ar.area_name, 'UNKNOWN') as area_label"),
                    'b.PART_NO',
                    DB::raw('SUM(b.QUANTITY) as qty_book'),
                ])
                ->get();

            if ($bookRows->isEmpty()) {
                return ['parts' => [], 'oil' => []];
            }

            // Taking kumulatif s/d waktu filter. Memakai JOIN ke daftar part book,
            // bukan whereIn dengan ribuan nilai.
            $partsSub = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('INV_LIST_NO', $latestInvListNo)
                ->select('PART_NO')
                ->distinct();

            $takingData = $db->table('SIM_INV_TAKING_DATA_TAB as t')
                ->joinSub($partsSub, 'bp', 'bp.PART_NO', '=', 't.PART_NO')
                ->whereBetween('t.CREATED_DATETIME', [$takingDate, $selectedTime])
                ->groupBy('t.PART_NO')
                ->select('t.PART_NO', DB::raw('SUM(t.QTY) as total_qty_taking'))
                ->pluck('total_qty_taking', 'PART_NO')
                ->toArray();

            $partsData = [];
            $oilData   = [];

            // Daftar area diambil dari MASTER (SIM_INV_GROUP_LOCATION.location_group), bukan hanya dari
            // data book, supaya semua area tetap tampil (0% bila belum ada data book).
            $grouped      = $bookRows->groupBy('area_label');
            $allAreaNames = $db->table('SIM_INV_GROUP_LOCATION')
                ->selectRaw('TRIM(location_group) as area_name')
                ->whereRaw("TRIM(location_group) <> ''")
                ->distinct()
                ->pluck('area_name')
                ->all();
            $areaNames = array_values(array_unique(array_merge($allAreaNames, $grouped->keys()->all())));

            foreach ($areaNames as $areaName) {
                $items = $grouped->get($areaName, collect());
                $itemBook = $items->count(); // sudah unik per part
                $qtyBook  = (float) $items->sum('qty_book');

                $itemTaking = 0;
                $qtyTaking  = 0.0;

                foreach ($items as $row) {
                    if (isset($takingData[$row->PART_NO])) {
                        $itemTaking++;
                        $qtyTaking += (float) $takingData[$row->PART_NO];
                    }
                }

                $pctQty  = $qtyBook > 0 ? round(($qtyTaking / $qtyBook) * 100, 2) : 0;
                $pctItem = $itemBook > 0 ? round(($itemTaking / $itemBook) * 100, 2) : 0;

                $progress  = min(100, $pctQty);
                $remaining = round(max(0, 100 - $progress), 2);

                $payload = [
                    'area'          => $areaName,
                    'item_book'     => $itemBook,
                    'qty_book'      => $qtyBook,
                    'item_taking'   => $itemTaking,
                    'qty_taking'    => $qtyTaking,
                    'pct_qty'       => $pctQty,
                    'pct_item'      => $pctItem,
                    'pct_progress'  => $progress,
                    'pct_remaining' => $remaining,
                ];

                if (stripos($areaName, 'OIL') !== false) {
                    $oilData[] = $payload;
                } else {
                    $partsData[] = $payload;
                }
            }

            // Sorting: Parts dari progress terendah ke tertinggi, Oil dari tertinggi ke terendah
            usort($partsData, fn($a, $b) => $a['pct_progress'] <=> $b['pct_progress']);
            usort($oilData, fn($a, $b) => $b['pct_progress'] <=> $a['pct_progress']);

            return [
                'parts' => $partsData,
                'oil'   => $oilData,
            ];
        });

        // /...?debug=1 -> tampilkan lokasi book yang belum terpetakan ke area (penyebab UNKNOWN)
        if ($request->boolean('debug')) {
            $result['unmapped'] = $db->table('SIM_INV_TAKING_BOOK_TAB as b')
                ->leftJoinSub($this->areaMapSub($db), 'ar', DB::raw('TRIM(b.LOCATION_NO)'), '=', 'ar.loc_no')
                ->whereBetween('b.CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('b.INV_LIST_NO', $latestInvListNo)
                ->whereNull('ar.area_name')
                ->groupBy('b.LOCATION_NO', 'b.WAREHOUSE')
                ->select('b.LOCATION_NO', 'b.WAREHOUSE', DB::raw('COUNT(DISTINCT b.PART_NO) as parts'))
                ->get();
        }

        return response()->json($result);
    }
}