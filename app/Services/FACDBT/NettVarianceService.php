<?php

namespace App\Services\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\BuildsVarianceLines;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan Nett Variance Dashboard (FACDBTD004), dipakai controller (ringkasan & chart)
 * dan DataTable (detail part positif / negatif).
 */
class NettVarianceService
{
    use BuildsVarianceLines;

    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    /**
     * Variance per part di area yang dipilih (request `areas[]`, semua area bila kosong),
     * taking kumulatif s/d request `time` (tanpa filter waktu: semua taking di periode taking).
     * Variance qty = qty taking - qty book (dicocokkan per lokasi lalu dijumlah per part),
     * variance amount = variance qty x unit cost.
     * Part dengan nett variance amount tidak minus masuk "positive", yang minus masuk "negative".
     */
    public function compute(Request $request): array
    {
        $empty = [
            'nett_qty'    => 0,
            'nett_amount' => 0,
            'by_area'     => [],
            'positive'    => ['qty' => 0, 'amount' => 0, 'rows' => []],
            'negative'    => ['qty' => 0, 'amount' => 0, 'rows' => []],
        ];

        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db);
        if ($period === null) {
            return $empty;
        }

        $takingUntil = $this->parseTime($request->get('time')) ?? $period['closeDate'];
        $selected = collect((array) $request->input('areas', []))
            ->map(fn ($a) => trim((string) $a))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $cacheKey = 'FACDBTD004:data:v2:' . md5(implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo'], $takingUntil, implode(',', $selected)]));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($db, $period, $takingUntil, $selected) {
            $areas      = $this->locationAreas($db);
            $areaFilter = array_flip($selected);

            $perArea = [];
            $perPart = [];
            foreach ($this->varianceLines($db, $period, $takingUntil) as [$loc, $part, $qtyBook, $qtyTaking, $unitCost]) {
                $area = $areas[$loc] ?? 'UNKNOWN';
                if ($areaFilter && !isset($areaFilter[$area])) {
                    continue;
                }

                $varQty = $qtyTaking - $qtyBook;
                $varAmt = $varQty * $unitCost;

                $perArea[$area] = ($perArea[$area] ?? 0.0) + $varAmt;
                $perPart[$part] ??= ['qty' => 0.0, 'amount' => 0.0];
                $perPart[$part]['qty']    += $varQty;
                $perPart[$part]['amount'] += $varAmt;
            }

            $byArea = collect($perArea)
                ->map(fn ($v, $area) => ['area' => (string) $area, 'value' => round($v, 2)])
                ->sortByDesc('value')
                ->values()
                ->all();

            // Minus: amount < 0, atau amount 0 (unit cost kosong) tetapi qty minus
            $isNegative = fn ($p) => $p['amount'] < 0 || ($p['amount'] == 0 && $p['qty'] < 0);
            $names = $this->partNames($db);

            return [
                'nett_qty'    => round(array_sum(array_column($perPart, 'qty')), 2),
                'nett_amount' => round(array_sum(array_column($perPart, 'amount')), 2),
                'by_area'     => $byArea,
                'positive'    => $this->detail(array_filter($perPart, fn ($p) => !$isNegative($p)), $names),
                'negative'    => $this->detail(array_filter($perPart, $isNegative), $names),
            ];
        });
    }

    /**
     * @param  array<string, array{qty: float, amount: float}>  $parts
     */
    private function detail(array $parts, array $names): array
    {
        $rows = [];
        foreach ($parts as $partNo => $p) {
            $rows[] = [
                'name'    => $names[$partNo] ?? 'UNKNOWN',
                'part_no' => (string) $partNo,
                'qty'     => round($p['qty'], 2),
                'amount'  => round($p['amount'], 2),
            ];
        }

        return [
            'qty'    => round(array_sum(array_column($rows, 'qty')), 2),
            'amount' => round(array_sum(array_column($rows, 'amount')), 2),
            'rows'   => $rows,
        ];
    }

    /**
     * @return array<string, string> [part_no => inventory_part_description]
     */
    private function partNames($db): array
    {
        return Cache::remember('FACDBTD004:part-names:v1', self::CACHE_TTL, function () use ($db) {
            return $db->table('SIM_INV_PART_TEST')
                ->select('part_no', 'inventory_part_description')
                ->get()
                ->mapWithKeys(fn ($r) => [(string) $r->part_no => trim((string) $r->inventory_part_description) ?: 'UNKNOWN'])
                ->all();
        });
    }
}
