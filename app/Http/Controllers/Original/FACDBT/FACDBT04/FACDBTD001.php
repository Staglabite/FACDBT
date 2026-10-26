<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Services\FACDBT\AreaName;
use App\Services\FACDBT\RunningTakingPeriod;
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

    // Jarak slot (jam) chart Counting Progress & Absolute Variance, dan chart Counting Progress Oil.
    // Slot dimulai dari 00:00 setiap hari taking.
    private const SLOT_HOURS     = 4;
    private const OIL_SLOT_HOURS = 5;

    /**
     * Jam slot chart atas & Absolute Variance: tiap SLOT_HOURS (non-oil) / OIL_SLOT_HOURS (oil) jam mulai 00:00
     * setiap hari, dari hari pertama taking s/d hari terakhir periode (CLOSE_DATE, atau hari ini bila belum ditutup).
     *
     * @return array{times: array<int, string>, non_oil: array<int, int>, oil: array<int, int>} times urut waktu;
     *         non_oil / oil berisi index slot di times
     */
    public static function slotPlan(Carbon $baseDay, string $closeDate): array
    {
        $lastDay = Carbon::parse($closeDate)->startOfDay();
        $groups  = ['non_oil' => self::SLOT_HOURS, 'oil' => self::OIL_SLOT_HOURS];

        $byGroup = ['non_oil' => [], 'oil' => []];
        for ($day = $baseDay->copy()->startOfDay(); $day->lte($lastDay); $day->addDay()) {
            foreach ($groups as $group => $hours) {
                for ($h = 0; $h < 24; $h += $hours) {
                    $byGroup[$group][] = $day->copy()->setTime($h, 0)->format('Y-m-d H:i:s');
                }
            }
        }

        $times = array_values(array_unique(array_merge($byGroup['non_oil'], $byGroup['oil'])));
        sort($times);
        $index = array_flip($times);

        return [
            'times'   => $times,
            'non_oil' => array_map(fn ($t) => $index[$t], $byGroup['non_oil']),
            'oil'     => array_map(fn ($t) => $index[$t], $byGroup['oil']),
        ];
    }

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
        return $this->dashboardCache('FACDBTD001:location-area-map:v4', function () use ($db) {
            $map = [];
            foreach ($db->table('SIM_INV_GROUP_LOCATION')->select('LOCATION_NO', 'location_group')->get() as $r) {
                $loc   = trim((string) $r->LOCATION_NO);
                $group = AreaName::of($r->location_group);
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
        $concluding = RunningTakingPeriod::find($db);

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

        $concluding = RunningTakingPeriod::find($db);

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

        $plan     = self::slotPlan($baseDay, $closeDate);
        $cacheKey = "FACDBTD001:v7:{$takingDate}:{$closeDate}:{$latestInvListNo}";

        $payload = $this->dashboardCache($cacheKey, function () use ($db, $plan, $takingDate, $closeDate, $latestInvListNo) {
            $slotTimes = $plan['times'];

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
                'non_oil' => $buildSeries(0, $plan['non_oil']),
                'oil'     => $buildSeries(1, $plan['oil']),
            ];
        });

        // Slot yang jamnya belum tiba dikosongkan (tidak meneruskan progress jam sebelumnya)
        foreach ($payload as $category => $series) {
            $payload[$category] = self::blankFutureSlots($series, ['item_taking', 'qty_taking', 'pct_qty', 'pct_item']);
        }

        return response()->json($payload);
    }

    /**
     * Kosongkan (null) nilai $fields pada slot yang jamnya belum tiba. Dilakukan setelah cache supaya
     * slot terisi tepat saat jamnya tiba walaupun hasil hitungan masih dari cache.
     */
    public static function blankFutureSlots(array $series, array $fields, ?string $now = null): array
    {
        $now ??= now()->format('Y-m-d H:i:s');

        foreach ($series as $i => $slot) {
            if ($slot['time'] > $now) {
                foreach ($fields as $field) {
                    $series[$i][$field] = null;
                }
            }
        }

        return $series;
    }

    /**
     * Pilihan hari untuk filter rentang waktu (Dari - Sampai) chart bawah & variance.
     */
    public function getTimeRange()
    {
        $concluding = RunningTakingPeriod::find(DB::connection(self::CONN));

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

        $cacheKey = "FACDBTD001:area:v12:{$takingDate}:{$closeDate}:{$latestInvListNo}:{$takingFrom}:{$takingTo}";

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
            $allAreaNames = AreaName::list($db->table('SIM_INV_GROUP_LOCATION')->distinct()->pluck('location_group'));
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
     * Label area (seperti chart progress by area) untuk satu (lokasi, part): area non-oil / 'OIL <area>' / 'PPC-OIL'.
     * Lokasi di beberapa group punya beberapa label.
     *
     * @return array<int, string>
     */
    private static function lineAreaLabels(string $location, bool $isOil, array $areaMap): array
    {
        $labels = [];
        foreach ($areaMap[$location] ?? [null] as $area) {
            $labels[self::areaLabel($location, $area, $isOil)] = true;
        }

        return array_keys($labels);
    }

    /**
     * Filter interaktif chart variance dari request: area (label area chart progress), seg (4W / 2W),
     * acc (RM / WIP / FG). Kosong = tanpa filter.
     *
     * @return array{area: ?string, seg: ?string, acc: ?string}
     */
    private static function varianceFilters(Request $request): array
    {
        $value = fn (string $key) => ($v = trim((string) $request->input($key, ''))) !== '' ? $v : null;

        return ['area' => $value('area'), 'seg' => $value('seg'), 'acc' => $value('acc')];
    }

    /**
     * Ringkasan variance dari baris dasar [[labels, seg, acc, nett, absolute], ...] dengan filter interaktif.
     * Chart yang menjadi sumber filter tetap menampilkan semua kelompoknya (yang lain dipudarkan di browser):
     * - by_part_group       : difilter area & acc (tidak difilter seg)
     * - by_accounting_group : difilter area & seg (tidak difilter acc)
     * - nett / absolute     : difilter area, seg & acc
     */
    public static function varianceSummary(array $rows, array $filters): array
    {
        $byPartGroup = [];
        $byAccGroup  = [];
        $nett = 0.0;
        $absolute = 0.0;

        foreach ($rows as [$labels, $seg, $acc, $rowNett, $rowAbs]) {
            if ($filters['area'] !== null && !in_array($filters['area'], $labels, true)) {
                continue;
            }
            $segMatch = $filters['seg'] === null || $filters['seg'] === $seg;
            $accMatch = $filters['acc'] === null || $filters['acc'] === $acc;

            if ($accMatch) {
                $byPartGroup[$seg]['nett'] = ($byPartGroup[$seg]['nett'] ?? 0.0) + $rowNett;
                $byPartGroup[$seg]['absolute'] = ($byPartGroup[$seg]['absolute'] ?? 0.0) + $rowAbs;
            }
            if ($segMatch) {
                $byAccGroup[$acc]['nett'] = ($byAccGroup[$acc]['nett'] ?? 0.0) + $rowNett;
                $byAccGroup[$acc]['absolute'] = ($byAccGroup[$acc]['absolute'] ?? 0.0) + $rowAbs;
            }
            if ($segMatch && $accMatch) {
                $nett     += $rowNett;
                $absolute += $rowAbs;
            }
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
    }

    /**
     * Endpoint untuk Chart Variance (Nett & Absolute Variance).
     * Variance dihitung per (lokasi, part): amount = (qty taking - qty book) x unit cost,
     * unit cost dari SIM_INV_UNIT_COST_TEST (mat + lab + foh); UNIT_COST di book tidak dipakai.
     * - Nett variance     : jumlah amount
     * - Absolute variance : jumlah ABS(amount)
     * Dikelompokkan per seg (4W / 2W) dan accounting_group (RM / WIP / FG) dari SIM_INV_PART_TEST.
     * Taking hanya yang di-scan di rentang filter waktu (sama seperti chart progress by area).
     * Filter interaktif: `area`, `seg`, `acc` (lihat varianceSummary()).
     */
    public function getVariance(Request $request)
    {
        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db, $request);
        if ($period === null) {
            return response()->json(self::varianceSummary([], self::varianceFilters($request)));
        }

        [$takingDate, $closeDate, $takingFrom, $takingTo, $latestInvListNo] = $period;

        $cacheKey = "FACDBTD001:variance:v8:{$takingDate}:{$closeDate}:{$latestInvListNo}:{$takingFrom}:{$takingTo}";

        // Baris dasar per (label area, seg, acc) supaya filter interaktif cukup dihitung di PHP
        $rows = $this->dashboardCache($cacheKey, function () use ($db, $takingDate, $closeDate, $takingFrom, $takingTo, $latestInvListNo) {
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

            $unitCosts  = $this->varianceUnitCosts($db);
            $partGroups = $this->variancePartGroups($db);
            $oilParts   = $this->oilPartsSub($db)->pluck('is_oil', 'part_no')->all();
            $areaMap    = $this->locationAreaMap($db);

            $rows = [];
            foreach ($lines as $key => [$qtyBook, $qtyTaking]) {
                [$location, $part] = explode('|', $key, 2);
                $variance = ($qtyTaking - $qtyBook) * ($unitCosts[$part] ?? 0.0);
                [$seg, $acc] = $partGroups[$part] ?? ['OTHERS', 'OTHERS'];
                $labels = self::lineAreaLabels($location, (int) ($oilParts[$part] ?? 0) === 1, $areaMap);

                $group = implode("\0", $labels) . "\1{$seg}\1{$acc}";
                $rows[$group] ??= [$labels, $seg, $acc, 0.0, 0.0];
                $rows[$group][3] += $variance;
                $rows[$group][4] += abs($variance);
            }

            return array_values($rows);
        });

        return response()->json(self::varianceSummary($rows, self::varianceFilters($request)));
    }

    /**
     * Endpoint untuk Chart Absolute Variance per slot waktu (Abs Var [+] dan Abs Var [-]).
     * Slot waktu sama dengan chart atas (Counting Progress keseluruhan), tidak terpengaruh filter tombol waktu.
     * - Abs Var [+] : jumlah variance amount per (lokasi, part) yang positif (taking > book)
     * - Abs Var [-] : jumlah ABS variance amount per (lokasi, part) yang negatif (taking < book)
     * Taking dihitung kumulatif s/d jam slot. Filter interaktif `area`, `seg`, `acc` berlaku.
     */
    public function getVarianceTrend(Request $request)
    {
        $db = DB::connection(self::CONN);

        $concluding = RunningTakingPeriod::find($db);

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

        $plan      = self::slotPlan($baseDay, $closeDate);
        $slotTimes = array_intersect_key($plan['times'], array_flip($plan['non_oil']));

        $cacheKey = "FACDBTD001:variance-trend:v6:{$takingDate}:{$closeDate}:{$latestInvListNo}";

        // Baris dasar per (label area, seg, acc): [labels, seg, acc, [slot => Var (+)], [slot => |Var (-)|]]
        $rows = $this->dashboardCache($cacheKey, function () use ($db, $takingDate, $closeDate, $latestInvListNo, $slotTimes) {
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

            $unitCosts  = $this->varianceUnitCosts($db);
            $partGroups = $this->variancePartGroups($db);
            $oilParts   = $this->oilPartsSub($db)->pluck('is_oil', 'part_no')->all();
            $areaMap    = $this->locationAreaMap($db);
            $zero       = array_fill_keys(array_keys($slotTimes), 0.0);

            $rows = [];
            foreach ($lines as $key => [$qtyBook, $qtyTaking]) {
                [$location, $part] = explode('|', $key, 2);
                $unitCost = $unitCosts[$part] ?? 0.0;
                if ($unitCost == 0.0) {
                    continue;
                }
                [$seg, $acc] = $partGroups[$part] ?? ['OTHERS', 'OTHERS'];
                $labels = self::lineAreaLabels($location, (int) ($oilParts[$part] ?? 0) === 1, $areaMap);

                $group = implode("\0", $labels) . "\1{$seg}\1{$acc}";
                $rows[$group] ??= [$labels, $seg, $acc, $zero, $zero];
                foreach (array_keys($slotTimes) as $i) {
                    $variance = (($qtyTaking[$i] ?? 0.0) - $qtyBook) * $unitCost;
                    if ($variance > 0) {
                        $rows[$group][3][$i] += $variance;
                    } else {
                        $rows[$group][4][$i] -= $variance;
                    }
                }
            }

            return array_values($rows);
        });

        $filters  = self::varianceFilters($request);
        $positive = array_fill_keys(array_keys($slotTimes), 0.0);
        $negative = $positive;
        foreach ($rows as [$labels, $seg, $acc, $pos, $neg]) {
            if (($filters['area'] !== null && !in_array($filters['area'], $labels, true))
                || ($filters['seg'] !== null && $filters['seg'] !== $seg)
                || ($filters['acc'] !== null && $filters['acc'] !== $acc)) {
                continue;
            }
            foreach (array_keys($slotTimes) as $i) {
                $positive[$i] += $pos[$i];
                $negative[$i] += $neg[$i];
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

        return response()->json(self::blankFutureSlots($series, ['abs_positive', 'abs_negative']));
    }
}
