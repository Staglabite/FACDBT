<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Dashboard Variance by Area (amount & qty)
class FACDBTD003 extends Controller
{
    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    // Slot filter waktu (sama dengan tombol slot di dashboard utama): [dayOffset, 'jam:menit']
    private const SLOTS = [
        [0, '00:00'],
        [0, '07:30'],
        [0, '13:00'],
        [0, '16:15'],
        [0, '19:30'],
        [1, '09:00'],
        [1, '10:00'],
        [1, '11:00'],
    ];

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD003.FACDBTD003');
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
        foreach (self::SLOTS as [$dayOffset, $hm]) {
            [$h, $m] = explode(':', $hm);
            $slotDate = $period['baseDay']->copy()->addDays($dayOffset)->setTime((int) $h, (int) $m, 0);

            $slots[] = [
                'label'     => $slotDate->format('j') . '-' . $slotDate->format('H.i'),
                'full_time' => $slotDate->format('Y-m-d H:i:s'),
            ];
        }

        return response()->json($slots);
    }

    /**
     * Variance per area (location_group dari SIM_INV_GROUP_LOCATION).
     * Variance dihitung per (lokasi, part): qty taking - qty book, amount = variance qty x unit cost.
     * Unit cost dari book (UNIT_COST), fallback ke SIM_INV_UNIT_COST_TEST (mat + lab + foh).
     * - Nett     : jumlah variance per area
     * - Absolute : jumlah ABS(variance) per area
     * - Var (+) / Var (-) : jumlah variance positif / negatif
     * Taking kumulatif s/d waktu filter; tanpa filter waktu: semua taking di periode taking.
     */
    public function getData(Request $request)
    {
        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db);
        if ($period === null) {
            return response()->json($this->summarize([]));
        }

        $takingUntil = $this->parseTime($request->get('time')) ?? $period['closeDate'];
        $base = implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo']]);

        $result = Cache::remember('FACDBTD003:data:v1:' . md5($base . '|' . $takingUntil), self::CACHE_TTL, function () use ($db, $period, $takingUntil) {
            // Tabel besar diagregasi per (lokasi, part) tanpa join; pemetaan ke area & unit cost di PHP
            // karena join dengan TRIM() membuat index tidak terpakai.
            $book = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$period['takingDate'], $period['closeDate']])
                ->where('INV_LIST_NO', $period['invListNo'])
                ->groupBy('LOCATION_NO', 'PART_NO')
                ->select('LOCATION_NO', 'PART_NO', DB::raw('SUM(QUANTITY) as qty'), DB::raw('MAX(UNIT_COST) as unit_cost'))
                ->get();

            $taking = $db->table('SIM_INV_TAKING_DATA_TAB')
                ->whereBetween('CREATED_DATETIME', [$period['takingDate'], $takingUntil])
                ->groupBy('LOCATION_NO', 'PART_NO')
                ->select('LOCATION_NO', 'PART_NO', DB::raw('SUM(QTY) as qty'))
                ->get();

            // [lokasi|part => [qty_book, qty_taking, unit_cost_book]]
            $lines = [];
            foreach ($book as $r) {
                $key = trim((string) $r->LOCATION_NO) . '|' . $r->PART_NO;
                $lines[$key] = [(float) $r->qty, 0.0, (float) $r->unit_cost];
            }
            foreach ($taking as $r) {
                $key = trim((string) $r->LOCATION_NO) . '|' . $r->PART_NO;
                $lines[$key] ??= [0.0, 0.0, 0.0];
                $lines[$key][1] += (float) $r->qty;
            }

            $areas     = $this->locationAreas($db);
            $unitCosts = $this->masterUnitCosts($db);

            $perArea = [];
            foreach ($lines as $key => [$qtyBook, $qtyTaking, $bookCost]) {
                [$loc, $part] = explode('|', $key, 2);
                $area     = $areas[$loc] ?? 'UNKNOWN';
                $unitCost = $bookCost != 0.0 ? $bookCost : ($unitCosts[$part] ?? 0.0);
                $varQty   = $qtyTaking - $qtyBook;
                $varAmt   = $varQty * $unitCost;

                $perArea[$area] ??= ['qty' => 0.0, 'abs_qty' => 0.0, 'amount' => 0.0, 'abs_amount' => 0.0, 'pos_qty' => 0.0, 'neg_qty' => 0.0, 'pos_amount' => 0.0, 'neg_amount' => 0.0];
                $a = &$perArea[$area];
                $a['qty']        += $varQty;
                $a['abs_qty']    += abs($varQty);
                $a['amount']     += $varAmt;
                $a['abs_amount'] += abs($varAmt);
                $a[$varQty >= 0 ? 'pos_qty' : 'neg_qty']       += $varQty;
                $a[$varAmt >= 0 ? 'pos_amount' : 'neg_amount'] += $varAmt;
                unset($a);
            }

            return $this->summarize($perArea);
        });

        return response()->json($result);
    }

    /**
     * Susun payload chart: per area (diurutkan dari terbesar) + total.
     */
    private function summarize(array $perArea): array
    {
        $series = function (string $key) use ($perArea) {
            return collect($perArea)
                ->map(fn ($v, $area) => ['area' => (string) $area, 'value' => round($v[$key], 2)])
                ->sortByDesc('value')
                ->values()
                ->all();
        };
        $total = fn (string $key) => round(array_sum(array_column($perArea, $key)), 2);

        return [
            'amount' => [
                'nett_by_area'     => $series('amount'),
                'absolute_by_area' => $series('abs_amount'),
                'nett'             => $total('amount'),
                'absolute'         => $total('abs_amount'),
                'positive'         => $total('pos_amount'),
                'negative'         => $total('neg_amount'),
            ],
            'qty' => [
                'nett_by_area'     => $series('qty'),
                'absolute_by_area' => $series('abs_qty'),
                'nett'             => $total('qty'),
                'absolute'         => $total('abs_qty'),
                'positive'         => $total('pos_qty'),
                'negative'         => $total('neg_qty'),
            ],
        ];
    }

    /**
     * @return array<string, string> [location_no => location_group]
     */
    private function locationAreas($db): array
    {
        return Cache::remember('FACDBTD003:areas:v1', self::CACHE_TTL, function () use ($db) {
            $map = [];
            foreach ($db->table('SIM_INV_GROUP_LOCATION')->select('LOCATION_NO', 'location_group')->orderBy('IID')->get() as $r) {
                $loc   = trim((string) $r->LOCATION_NO);
                $group = trim((string) $r->location_group);
                if ($group !== '' && !isset($map[$loc])) {
                    $map[$loc] = $group;
                }
            }

            return $map;
        });
    }

    /**
     * @return array<string, float> [part_no => unit cost (mat + lab + foh)]
     */
    private function masterUnitCosts($db): array
    {
        return Cache::remember('FACDBTD003:unit-costs:v1', self::CACHE_TTL, function () use ($db) {
            return $db->table('SIM_INV_UNIT_COST_TEST')
                ->groupBy('part_no')
                ->select('part_no', DB::raw('MAX(COALESCE(mat_cost, 0) + COALESCE(lab_cost, 0) + COALESCE(foh_cost, 0)) as unit_cost'))
                ->pluck('unit_cost', 'part_no')
                ->map(fn ($v) => (float) $v)
                ->all();
        });
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
