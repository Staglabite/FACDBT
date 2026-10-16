<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\BuildsVarianceLines;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Dashboard Variance by Area (amount & qty)
class FACDBTD003 extends Controller
{
    use BuildsVarianceLines;

    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD003.FACDBTD003');
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
     * Variance per area (location_group dari SIM_INV_GROUP_LOCATION).
     * Variance dihitung per (lokasi, part): qty taking - qty book, amount = variance qty x unit cost.
     * Unit cost dari book (UNIT_COST), fallback ke SIM_INV_UNIT_COST_TEST (mat + lab + foh).
     * - Nett     : jumlah variance per area
     * - Absolute : jumlah ABS(variance) per area
     * - Var (+) / Var (-) : jumlah variance positif / negatif
     * Taking hanya yang di-scan di rentang waktu filter; tanpa filter: seluruh periode taking.
     */
    public function getData(Request $request)
    {
        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db);
        if ($period === null) {
            return response()->json($this->summarize([]));
        }

        [$takingFrom, $takingTo] = $this->takingRange($request, $period['takingDate'], $period['closeDate']);
        $result = $this->dashboardCache('FACDBTD003:data:v3:' . md5(implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo'], $takingFrom, $takingTo])), function () use ($db, $period, $takingFrom, $takingTo) {
            $areas   = $this->locationAreas($db);
            $perArea = [];

            foreach ($this->varianceLines($db, $period, $takingFrom, $takingTo) as [$loc, , $qtyBook, $qtyTaking, $unitCost]) {
                $area   = $areas[$loc] ?? 'UNKNOWN';
                $varQty = $qtyTaking - $qtyBook;
                $varAmt = $varQty * $unitCost;

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
}
