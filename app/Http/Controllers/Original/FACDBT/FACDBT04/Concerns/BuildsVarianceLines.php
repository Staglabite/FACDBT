<?php

namespace App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bahan bersama dashboard variance (FACDBTD003 & FACDBTD004):
 * periode taking, filter rentang waktu, pemetaan lokasi -> area, unit cost, dan variance per (lokasi, part).
 *
 * Class pemakai wajib punya konstanta CONN dan CACHE_TTL.
 */
trait BuildsVarianceLines
{
    use CachesDashboard;
    use FiltersTakingRange;

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
     * Variance per (lokasi, part), cache dipakai bersama oleh dashboard variance.
     * Taking hanya yang di-scan di rentang $takingFrom s/d $takingTo; book tetap penuh periode.
     * Tabel besar diagregasi tanpa join; pemetaan unit cost di PHP karena join dengan TRIM()
     * membuat index tidak terpakai.
     * Unit cost dari SIM_INV_UNIT_COST_TEST (mat + lab + foh); UNIT_COST di book tidak dipakai.
     *
     * @return array<int, array{0: string, 1: string, 2: float, 3: float, 4: float}>
     *         [[location_no, part_no, qty_book, qty_taking, unit_cost], ...]
     */
    private function varianceLines($db, array $period, string $takingFrom, string $takingTo): array
    {
        $key = 'FACDBT04:variance-lines:v3:' . md5(implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo'], $takingFrom, $takingTo]));

        return $this->dashboardCache($key, function () use ($db, $period, $takingFrom, $takingTo) {
            $book = $db->table('SIM_INV_TAKING_BOOK_TAB')
                ->whereBetween('CREATED_DATETIME', [$period['takingDate'], $period['closeDate']])
                ->where('INV_LIST_NO', $period['invListNo'])
                ->groupBy('LOCATION_NO', 'PART_NO')
                ->select('LOCATION_NO', 'PART_NO', DB::raw('SUM(QUANTITY) as qty'))
                ->get();

            $taking = $db->table('SIM_INV_TAKING_DATA_TAB')
                ->whereBetween('CREATED_DATETIME', [$takingFrom, $takingTo])
                ->groupBy('LOCATION_NO', 'PART_NO')
                ->select('LOCATION_NO', 'PART_NO', DB::raw('SUM(QTY) as qty'))
                ->get();

            // [lokasi|part => [qty_book, qty_taking]]
            $merged = [];
            foreach ($book as $r) {
                $merged[trim((string) $r->LOCATION_NO) . '|' . $r->PART_NO] = [(float) $r->qty, 0.0];
            }
            foreach ($taking as $r) {
                $k = trim((string) $r->LOCATION_NO) . '|' . $r->PART_NO;
                $merged[$k] ??= [0.0, 0.0];
                $merged[$k][1] += (float) $r->qty;
            }

            $unitCosts = $this->masterUnitCosts($db);

            $lines = [];
            foreach ($merged as $k => [$qtyBook, $qtyTaking]) {
                [$loc, $part] = explode('|', $k, 2);
                $lines[] = [$loc, $part, $qtyBook, $qtyTaking, $unitCosts[$part] ?? 0.0];
            }

            return $lines;
        });
    }

    /**
     * @return array<string, string> [location_no => location_group]; lokasi di beberapa group memakai group pertama
     */
    private function locationAreas($db): array
    {
        return $this->dashboardCache('FACDBT04:location-areas:v1', function () use ($db) {
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
        return $this->dashboardCache('FACDBT04:unit-costs:v1', function () use ($db) {
            return $db->table('SIM_INV_UNIT_COST_TEST')
                ->groupBy('part_no')
                ->select('part_no', DB::raw('MAX(COALESCE(mat_cost, 0) + COALESCE(lab_cost, 0) + COALESCE(foh_cost, 0)) as unit_cost'))
                ->pluck('unit_cost', 'part_no')
                ->map(fn ($v) => (float) $v)
                ->all();
        });
    }
}
