<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\BuildsVarianceLines;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Nett Variance Dashboard (ringkasan + detail part positif / negatif)
class FACDBTD004 extends Controller
{
    use BuildsVarianceLines;

    private const CONN = 'INV_TAKING';
    private const CACHE_TTL = 60;

    // Jumlah baris maksimal per tabel (diambil dari variance amount terbesar)
    private const TABLE_LIMIT = 500;

    public function index()
    {
        return view('modules.FACDBT.FACDBT04.FACDBTD004.FACDBTD004');
    }

    /**
     * Pilihan filter: slot waktu dan area (location_group dari SIM_INV_GROUP_LOCATION).
     */
    public function getFilters()
    {
        $db = DB::connection(self::CONN);
        $period = $this->resolvePeriod($db);

        $areas = $db->table('SIM_INV_GROUP_LOCATION')
            ->selectRaw('TRIM(location_group) as location_group')
            ->whereRaw("TRIM(location_group) <> ''")
            ->distinct()
            ->orderBy('location_group')
            ->pluck('location_group');

        return response()->json([
            'times' => $period ? $this->timeSlotsPayload($period['baseDay']) : [],
            'areas' => $areas,
        ]);
    }

    /**
     * Variance per part di area yang dipilih (semua area bila tidak dipilih).
     * Variance qty = qty taking - qty book (dicocokkan per lokasi lalu dijumlah per part),
     * variance amount = variance qty x unit cost.
     * Taking kumulatif s/d waktu filter; tanpa filter waktu: semua taking di periode taking.
     */
    public function getData(Request $request)
    {
        $empty = [
            'nett_qty'    => 0,
            'nett_amount' => 0,
            'by_area'     => [],
            'positive'    => ['qty' => 0, 'amount' => 0, 'groups' => []],
            'negative'    => ['qty' => 0, 'amount' => 0, 'groups' => []],
        ];

        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db);
        if ($period === null) {
            return response()->json($empty);
        }

        $takingUntil = $this->parseTime($request->get('time')) ?? $period['closeDate'];
        $selected = collect((array) $request->input('areas', []))
            ->map(fn ($a) => trim((string) $a))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $cacheKey = 'FACDBTD004:data:v1:' . md5(implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo'], $takingUntil, implode(',', $selected)]));

        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($db, $period, $takingUntil, $selected) {
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

            $names = $this->partNames($db);
            $positive = array_filter($perPart, fn ($p) => $p['qty'] > 0);
            $negative = array_filter($perPart, fn ($p) => $p['qty'] < 0);

            return [
                'nett_qty'    => round(array_sum(array_column($perPart, 'qty')), 2),
                'nett_amount' => round(array_sum(array_column($perPart, 'amount')), 2),
                'by_area'     => $byArea,
                'positive'    => $this->detail($positive, $names, true),
                'negative'    => $this->detail($negative, $names, false),
            ];
        });

        return response()->json($result);
    }

    /**
     * Detail tabel: part dikelompokkan per nama part. Kelompok diurutkan dari total amount terbesar
     * (positif) / paling minus (negatif), baris di dalam kelompok juga.
     */
    private function detail(array $parts, array $names, bool $positive): array
    {
        $rows = collect($parts)
            ->map(fn ($p, $partNo) => [
                'part_no' => (string) $partNo,
                'name'    => $names[$partNo] ?? 'UNKNOWN',
                'qty'     => round($p['qty'], 2),
                'amount'  => round($p['amount'], 2),
            ])
            ->sortByDesc(fn ($r) => abs($r['amount']))
            ->take(self::TABLE_LIMIT);

        $direction = $positive ? -1 : 1;
        $groups = $rows->groupBy('name')
            ->map(fn ($items, $name) => [
                'name'   => (string) $name,
                'amount' => round($items->sum('amount'), 2),
                'rows'   => $items->sortBy(fn ($r) => $direction * $r['amount'])->values()->all(),
            ])
            ->sortBy(fn ($g) => $direction * $g['amount'])
            ->values()
            ->all();

        return [
            'qty'       => round(array_sum(array_column($parts, 'qty')), 2),
            'amount'    => round(array_sum(array_column($parts, 'amount')), 2),
            'total'     => count($parts),
            'truncated' => count($parts) > self::TABLE_LIMIT,
            'groups'    => $groups,
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
