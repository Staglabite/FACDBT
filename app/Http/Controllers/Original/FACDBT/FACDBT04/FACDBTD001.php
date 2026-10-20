<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\CachesDashboard;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\FiltersTakingRange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FACDBTD001 extends Controller
{
    use CachesDashboard;
    use FiltersTakingRange;

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
     * Subquery: 1 baris per part_no dengan flag is_oil (inventory_uom = ml).
     * Di-group supaya join tidak menggandakan baris / SUM(QUANTITY).
     */
    private function oilPartsSub($db)
    {
        return $db->table('SIM_INV_UNIT_COST_TEST')
            ->select(
                'part_no',
                DB::raw("MAX(CASE WHEN LOWER(inventory_uom) = 'ml' THEN 1 ELSE 0 END) as is_oil")
            )
            ->groupBy('part_no');
    }

    /**
     * Subquery: 1 baris per PART_NO (is_oil + total qty book).
     */
    private function bookPartsSub($db, string $takingDate, string $closeDate, string $invListNo)
    {
        return $db->table('SIM_INV_TAKING_BOOK_TAB as b')
            ->leftJoinSub($this->oilPartsSub($db), 'cost', 'cost.part_no', '=', 'b.PART_NO')
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
     * Pemetaan lokasi -> daftar area (location_group dari SIM_INV_GROUP_LOCATION).
     * Lokasi di lebih dari satu group masuk ke semua group-nya. Dikerjakan di PHP karena
     * join dengan TRIM() di SQL membuat index tidak terpakai.
     *
     * @return array<string, array<int, string|null>> [location_no => [area, ...]]
     */
    private function locationAreaMap($db): array
    {
        return $this->dashboardCache('FACDBTD001:location-area-map:v3', function () use ($db) {
            $map = [];
            foreach ($db->table('SIM_INV_GROUP_LOCATION')->select('LOCATION_NO', 'location_group')->get() as $r) {
                $loc   = trim((string) $r->LOCATION_NO);
                $group = trim((string) $r->location_group);
                $map[$loc][$group === '' ? '' : $group] = $group === '' ? null : $group;
            }

            return array_map('array_values', $map);
        });
    }

    /**
     * Label area untuk chart progress by area.
     * - Part oil (inventory_uom = ml) di lokasi / group PPC  -> 'PPC-OIL'
     * - Part oil lainnya                                     -> 'OIL <location_group>'
     * - Part non-oil                                         -> '<location_group>'
     * Lokasi tanpa group -> 'UNKNOWN'.
     */
    public static function areaLabel(string $locationNo, ?string $area, bool $isOil): string
    {
        if ($isOil && (str_contains(strtoupper($locationNo), 'PPC') || str_contains(strtoupper((string) $area), 'PPC'))) {
            return 'PPC-OIL';
        }

        $base = $area ?? 'UNKNOWN';

        if ($isOil && !str_starts_with(strtoupper($base), 'OIL ')) {
            return 'OIL ' . $base;
        }

        return $base;
    }

    /**
     * Qty book per (area, part) untuk chart progress by area.
     *
     * @return \Illuminate\Support\Collection<int, object{area_label: string, PART_NO: string, qty_book: float}>
     */
    private function bookPerAreaPart($db, string $takingDate, string $closeDate, string $invListNo)
    {
        $book = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->where('INV_LIST_NO', $invListNo)
            ->groupBy('LOCATION_NO', 'PART_NO')
            ->select('LOCATION_NO', 'PART_NO', DB::raw('SUM(QUANTITY) as qty'))
            ->get();

        return collect(self::groupBookByArea(
            $book,
            $this->oilPartsSub($db)->pluck('is_oil', 'part_no')->all(),
            $this->locationAreaMap($db)
        ));
    }

    /**
     * Jumlahkan qty book per (area, part). Lokasi di beberapa group dihitung di tiap group-nya.
     *
     * Dipakai untuk book maupun taking (qty taking per lokasi dipetakan ke area yang sama).
     *
     * @param  iterable<object{LOCATION_NO: string, PART_NO: string, qty: float}>  $book
     * @param  array<string, int|string>  $oilParts  [part_no => is_oil]
     * @param  array<string, array<int, string|null>>  $areaMap  [location_no => [area, ...]]
     * @return array<int, object{area_label: string, PART_NO: string, qty_book: float}>
     */
    public static function groupBookByArea(iterable $book, array $oilParts, array $areaMap): array
    {
        $totals = [];
        foreach ($book as $r) {
            $isOil = (int) ($oilParts[$r->PART_NO] ?? 0) === 1;
            foreach ($areaMap[trim((string) $r->LOCATION_NO)] ?? [null] as $area) {
                $label = self::areaLabel((string) $r->LOCATION_NO, $area, $isOil);
                $totals[$label][$r->PART_NO] = ($totals[$label][$r->PART_NO] ?? 0.0) + (float) $r->qty;
            }
        }

        $rows = [];
        foreach ($totals as $label => $parts) {
            foreach ($parts as $part => $qty) {
                $rows[] = (object) ['area_label' => (string) $label, 'PART_NO' => (string) $part, 'qty_book' => $qty];
            }
        }

        return $rows;
    }

    /**
     * Periode taking aktif + rentang filter waktu (Dari - Sampai) + INV_LIST_NO terbaru.
     * Dipakai oleh chart bawah (progress by area) dan chart variance.
     *
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}|null [takingDate, closeDate, takingFrom, takingTo, invListNo]
     */
    private function resolvePeriod($db, Request $request): ?array
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

        // Rentang filter waktu; tanpa filter: seluruh periode taking
        [$takingFrom, $takingTo] = $this->takingRange($request, $takingDate, $closeDate);

        $latestInvListNo = $this->latestInvListNo($db, $takingDate, $closeDate);

        if ($latestInvListNo === null) {
            return null;
        }

        return [$takingDate, $closeDate, $takingFrom, $takingTo, $latestInvListNo];
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

        $payload = $this->dashboardCache($cacheKey, function () use ($db, $baseDay, $takingDate, $closeDate, $latestInvListNo) {
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
     * Pilihan hari untuk filter rentang waktu (Dari - Sampai) chart bawah & variance.
     */
    public function getTimeRange()
    {
        $concluding = DB::connection(self::CONN)->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('TAKING_DATE', 'CLOSE_DATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

        if (!$concluding) {
            return response()->json(['days' => []]);
        }

        return response()->json($this->timeRangePayload(
            Carbon::parse($concluding->TAKING_DATE)->startOfDay()->format('Y-m-d H:i:s'),
            Carbon::parse($concluding->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s')
        ));
    }

    /**
     * Endpoint untuk Chart Bawah (Counting Progress by Part & Oil Area)
     * Mengikuti batas parameter waktu yang dipilih
     */
    public function getProgressByArea(Request $request)
    {
        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db, $request);
        if ($period === null) {
            return response()->json(['parts' => [], 'oil' => []]);
        }

        [$takingDate, $closeDate, $takingFrom, $takingTo, $latestInvListNo] = $period;

        $cacheKey = "FACDBTD001:area:v11:{$takingDate}:{$closeDate}:{$latestInvListNo}:{$takingFrom}:{$takingTo}";

        $result = $this->dashboardCache($cacheKey, function () use ($db, $takingDate, $closeDate, $takingFrom, $takingTo, $latestInvListNo) {
            // 1 baris per (area, part). Part oil (inventory_uom = ml) dipisah per part ke area
            // 'OIL <location_group>' / 'PPC-OIL' (lihat areaLabel), karena satu lokasi bisa berisi
            // part oil dan non-oil sekaligus.
            $bookRows = $this->bookPerAreaPart($db, $takingDate, $closeDate, $latestInvListNo);

            if ($bookRows->isEmpty()) {
                return ['parts' => [], 'oil' => []];
            }

            // Taking yang di-scan di rentang filter, per (lokasi, part), lalu dipetakan ke area dengan
            // aturan yang sama seperti book. Taking hanya dihitung untuk area lokasi tempat part di-scan,
            // bukan untuk semua area yang punya part tersebut di book.
            // Memakai JOIN ke daftar part book, bukan whereIn dengan ribuan nilai.
            $partsSub = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('INV_LIST_NO', $latestInvListNo)
                ->select('PART_NO')
                ->distinct();

            $takingRows = $db->table('SIM_INV_TAKING_DATA_TAB as t')
                ->joinSub($partsSub, 'bp', 'bp.PART_NO', '=', 't.PART_NO')
                ->whereBetween('t.CREATED_DATETIME', [$takingFrom, $takingTo])
                ->groupBy('t.LOCATION_NO', 't.PART_NO')
                ->select('t.LOCATION_NO', 't.PART_NO', DB::raw('SUM(t.QTY) as qty'))
                ->get();

            // [area => [part => qty taking]]
            $takingData = [];
            foreach (self::groupBookByArea(
                $takingRows,
                $this->oilPartsSub($db)->pluck('is_oil', 'part_no')->all(),
                $this->locationAreaMap($db)
            ) as $row) {
                $takingData[$row->area_label][$row->PART_NO] = $row->qty_book;
            }

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
                    if (isset($takingData[$areaName][$row->PART_NO])) {
                        $itemTaking++;
                        $qtyTaking += (float) $takingData[$areaName][$row->PART_NO];
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
            $areaMap = $this->locationAreaMap($db);
            $result['unmapped'] = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
                ->where('INV_LIST_NO', $latestInvListNo)
                ->groupBy('LOCATION_NO', 'WAREHOUSE')
                ->select('LOCATION_NO', 'WAREHOUSE', DB::raw('COUNT(DISTINCT PART_NO) as parts'))
                ->get()
                ->filter(fn ($r) => empty(array_filter($areaMap[trim((string) $r->LOCATION_NO)] ?? [])))
                ->values();
        }

        return response()->json($result);
    }

    /**
     * Qty book per (lokasi, part) periode aktif: [lokasi|part => qty].
     * Tabel besar diagregasi tanpa join; pencocokan unit cost & master part dilakukan di PHP
     * (sama seperti dashboard variance lain).
     */
    private function varianceBookQty($db, string $takingDate, string $closeDate, string $invListNo): array
    {
        $book = [];
        $rows = $db->table('SIM_INV_TAKING_BOOK_TAB')
            ->whereBetween('CREATED_DATETIME', [$takingDate, $closeDate])
            ->where('INV_LIST_NO', $invListNo)
            ->groupBy('LOCATION_NO', 'PART_NO')
            ->select('LOCATION_NO', 'PART_NO', DB::raw('SUM(QUANTITY) as qty'))
            ->get();

        foreach ($rows as $r) {
            $key = trim((string) $r->LOCATION_NO) . '|' . $r->PART_NO;
            $book[$key] = ($book[$key] ?? 0.0) + (float) $r->qty;
        }

        return $book;
    }

    /**
     * @return array<string, float> [part_no => unit cost (mat + lab + foh)] dari SIM_INV_UNIT_COST_TEST.
     * UNIT_COST di book tidak dipakai.
     */
    private function varianceUnitCosts($db): array
    {
        return $this->dashboardCache('FACDBT04:unit-costs:v1', function () use ($db) {
            return $db->table('SIM_INV_UNIT_COST_TEST')
                ->groupBy('part_no')
                ->select('part_no', DB::raw('MAX(COALESCE(mat_cost, 0) + COALESCE(lab_cost, 0) + COALESCE(foh_cost, 0)) as unit_cost'))
                ->pluck('unit_cost', 'part_no')
                ->map(fn ($v) => (float) $v)
                ->all();
        });
    }

    /**
     * @return array<string, array{0: string, 1: string}> [part_no => [seg (4W / 2W), accounting_group]]
     */
    private function variancePartGroups($db): array
    {
        return $this->dashboardCache('FACDBTD001:part-groups:v3', function () use ($db) {
            $map = [];
            foreach ($db->table('SIM_INV_PART_TEST')->select('part_no', 'seg', 'accounting_group')->get() as $r) {
                $partGroup = strtoupper(trim((string) $r->seg));
                $accGroup  = strtoupper(trim((string) $r->accounting_group));
                // WIP-M digabung ke WIP (kotak di dashboard hanya RM / WIP / FG)
                if (str_starts_with($accGroup, 'WIP-')) {
                    $accGroup = 'WIP';
                }
                $map[$r->part_no] = [$partGroup !== '' ? $partGroup : 'OTHERS', $accGroup !== '' ? $accGroup : 'OTHERS'];
            }

            return $map;
        });
    }

    /**
     * Endpoint untuk Chart Variance (Nett & Absolute Variance).
     * Variance dihitung per (lokasi, part): amount = (qty taking - qty book) x unit cost,
     * unit cost dari SIM_INV_UNIT_COST_TEST (mat + lab + foh); UNIT_COST di book tidak dipakai.
     * - Nett variance     : jumlah amount
     * - Absolute variance : jumlah ABS(amount)
     * Dikelompokkan per seg (4W / 2W) dan accounting_group (RM / WIP / FG) dari SIM_INV_PART_TEST.
     * Taking hanya yang di-scan di rentang filter waktu (sama seperti chart progress by area).
     */
    public function getVariance(Request $request)
    {
        $empty = [
            'nett'              => 0,
            'absolute'          => 0,
            'by_part_group'     => [],
            'by_accounting_group' => [],
        ];

        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db, $request);
        if ($period === null) {
            return response()->json($empty);
        }

        [$takingDate, $closeDate, $takingFrom, $takingTo, $latestInvListNo] = $period;

        $cacheKey = "FACDBTD001:variance:v7:{$takingDate}:{$closeDate}:{$latestInvListNo}:{$takingFrom}:{$takingTo}";

        $result = $this->dashboardCache($cacheKey, function () use ($db, $takingDate, $closeDate, $takingFrom, $takingTo, $latestInvListNo, $empty) {
            // [lokasi|part => [qty book, qty taking]]
            $lines = [];
            foreach ($this->varianceBookQty($db, $takingDate, $closeDate, $latestInvListNo) as $key => $qty) {
                $lines[$key] = [$qty, 0.0];
            }

            $taking = $db->table('SIM_INV_TAKING_DATA_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingFrom, $takingTo])
                ->groupBy('LOCATION_NO', 'PART_NO')
                ->select('LOCATION_NO', 'PART_NO', DB::raw('SUM(QTY) as qty'))
                ->get();
            foreach ($taking as $r) {
                $key = trim((string) $r->LOCATION_NO) . '|' . $r->PART_NO;
                $lines[$key] ??= [0.0, 0.0];
                $lines[$key][1] += (float) $r->qty;
            }

            if (empty($lines)) {
                return $empty;
            }

            $unitCosts  = $this->varianceUnitCosts($db);
            $partGroups = $this->variancePartGroups($db);

            $byPartGroup = [];
            $byAccGroup  = [];
            $nett = 0.0;
            $absolute = 0.0;
            foreach ($lines as $key => [$qtyBook, $qtyTaking]) {
                $part     = explode('|', $key, 2)[1];
                $variance = ($qtyTaking - $qtyBook) * ($unitCosts[$part] ?? 0.0);
                [$partGroup, $accGroup] = $partGroups[$part] ?? ['OTHERS', 'OTHERS'];

                $nett     += $variance;
                $absolute += abs($variance);

                $byPartGroup[$partGroup]['nett'] = ($byPartGroup[$partGroup]['nett'] ?? 0.0) + $variance;
                $byPartGroup[$partGroup]['absolute'] = ($byPartGroup[$partGroup]['absolute'] ?? 0.0) + abs($variance);
                $byAccGroup[$accGroup]['nett'] = ($byAccGroup[$accGroup]['nett'] ?? 0.0) + $variance;
                $byAccGroup[$accGroup]['absolute'] = ($byAccGroup[$accGroup]['absolute'] ?? 0.0) + abs($variance);
            }

            $summarize = fn (array $groups) => array_map(
                fn ($group, $sum) => [
                    'group'    => (string) $group,
                    'nett'     => round($sum['nett'], 2),
                    'absolute' => round($sum['absolute'], 2),
                ],
                array_keys($groups),
                $groups
            );

            return [
                'nett'                => round($nett, 2),
                'absolute'            => round($absolute, 2),
                'by_part_group'       => $summarize($byPartGroup),
                'by_accounting_group' => $summarize($byAccGroup),
            ];
        });

        return response()->json($result);
    }

    /**
     * Endpoint untuk Chart Absolute Variance per slot waktu (Abs Var [+] dan Abs Var [-]).
     * Slot waktu sama dengan chart atas (Counting Progress keseluruhan), tidak terpengaruh filter tombol waktu.
     * - Abs Var [+] : jumlah variance amount per (lokasi, part) yang positif (taking > book)
     * - Abs Var [-] : jumlah ABS variance amount per (lokasi, part) yang negatif (taking < book)
     * Taking dihitung kumulatif s/d jam slot.
     */
    public function getVarianceTrend(Request $request)
    {
        $db = DB::connection(self::CONN);

        $concluding = $db->table('SIM_INV_TAKING_CONCLUDING_TAB')
            ->select('TAKING_DATE', 'CLOSE_DATE')
            ->orderByDesc('TAKING_DATE')
            ->first();

        if (!$concluding) {
            return response()->json([]);
        }

        $baseDay    = Carbon::parse($concluding->TAKING_DATE)->startOfDay();
        $takingDate = $baseDay->format('Y-m-d H:i:s');
        $closeDate  = Carbon::parse($concluding->CLOSE_DATE)->endOfDay()->format('Y-m-d H:i:s');

        $latestInvListNo = $this->latestInvListNo($db, $takingDate, $closeDate);

        if ($latestInvListNo === null) {
            return response()->json([]);
        }

        $cacheKey = "FACDBTD001:variance-trend:v4:{$takingDate}:{$closeDate}:{$latestInvListNo}";

        $series = $this->dashboardCache($cacheKey, function () use ($db, $baseDay, $takingDate, $closeDate, $latestInvListNo) {
            $slotTimes = [];
            foreach (self::NON_OIL_SLOTS as $i) {
                [$dayOffset, $hm] = self::SLOTS[$i];
                [$h, $m] = explode(':', $hm);
                $slotTimes[$i] = $baseDay->copy()->addDays($dayOffset)->setTime((int) $h, (int) $m, 0)->format('Y-m-d H:i:s');
            }
            $lastSlot = max($slotTimes);

            // Qty taking kumulatif per (lokasi, part) untuk setiap slot dalam satu query
            $takingQuery = $db->table('SIM_INV_TAKING_DATA_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingDate, $lastSlot])
                ->groupBy('LOCATION_NO', 'PART_NO')
                ->select('LOCATION_NO', 'PART_NO');
            foreach ($slotTimes as $i => $time) {
                $takingQuery->selectRaw("SUM(CASE WHEN CREATED_DATETIME <= ? THEN QTY ELSE 0 END) as qty_{$i}", [$time]);
            }

            // [lokasi|part => [qty book, [slot => qty taking kumulatif]]]
            $lines = [];
            foreach ($this->varianceBookQty($db, $takingDate, $closeDate, $latestInvListNo) as $key => $qty) {
                $lines[$key] = [$qty, []];
            }
            foreach ($takingQuery->get() as $r) {
                $key = trim((string) $r->LOCATION_NO) . '|' . $r->PART_NO;
                $lines[$key] ??= [0.0, []];
                foreach (array_keys($slotTimes) as $i) {
                    $lines[$key][1][$i] = ($lines[$key][1][$i] ?? 0.0) + (float) $r->{"qty_{$i}"};
                }
            }

            $unitCosts = $this->varianceUnitCosts($db);

            $positive = array_fill_keys(array_keys($slotTimes), 0.0);
            $negative = $positive;
            foreach ($lines as $key => [$qtyBook, $qtyTaking]) {
                $unitCost = $unitCosts[explode('|', $key, 2)[1]] ?? 0.0;
                if ($unitCost == 0.0) {
                    continue;
                }
                foreach (array_keys($slotTimes) as $i) {
                    $variance = (($qtyTaking[$i] ?? 0.0) - $qtyBook) * $unitCost;
                    if ($variance > 0) {
                        $positive[$i] += $variance;
                    } else {
                        $negative[$i] -= $variance;
                    }
                }
            }

            $series = [];
            foreach ($slotTimes as $i => $time) {
                $series[] = [
                    'time'         => $time,
                    'abs_positive' => round($positive[$i], 2),
                    'abs_negative' => round($negative[$i], 2),
                ];
            }

            return $series;
        });

        return response()->json($series);
    }
}
