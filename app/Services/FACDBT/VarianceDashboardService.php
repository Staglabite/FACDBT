<?php

namespace App\Services\FACDBT;

use App\Http\Controllers\Original\FACDBT\FACDBT04\Concerns\BuildsVarianceLines;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dasar perhitungan dashboard variance dengan filter TIME & AREA serta tabel detail part
 * (Nett Variance FACDBTD004, Absolute Variance FACDBTD005).
 */
abstract class VarianceDashboardService
{
    use BuildsVarianceLines;

    protected const CONN = 'INV_TAKING';
    protected const CACHE_TTL = 60;

    /**
     * Prefix cache key, berbeda per dashboard.
     */
    abstract protected function cachePrefix(): string;

    /**
     * Payload kosong saat periode taking / book belum ada.
     */
    abstract protected function emptyResult(): array;

    /**
     * Hitung payload dari variance per (lokasi, part) yang sudah difilter area.
     *
     * @param  iterable<array{0: string, 1: string, 2: float, 3: float, 4: float, 5: string}>  $lines
     *         [[location_no, part_no, qty_book, qty_taking, unit_cost, area], ...]
     */
    abstract protected function build(iterable $lines, array $names): array;

    /**
     * Payload dashboard untuk filter request `time` (tanpa filter: semua taking di periode taking)
     * dan `areas[]` (tanpa filter: semua area). Hasil di-cache supaya ringkasan & DataTable memakai hasil yang sama.
     */
    public function compute(Request $request): array
    {
        $db = DB::connection(self::CONN);

        $period = $this->resolvePeriod($db);
        if ($period === null) {
            return $this->emptyResult();
        }

        $takingUntil = $this->parseTime($request->get('time')) ?? $period['closeDate'];
        $selected = collect((array) $request->input('areas', []))
            ->map(fn ($a) => trim((string) $a))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $cacheKey = $this->cachePrefix() . ':' . md5(implode('|', [$period['takingDate'], $period['closeDate'], $period['invListNo'], $takingUntil, implode(',', $selected)]));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($db, $period, $takingUntil, $selected) {
            $areas      = $this->locationAreas($db);
            $areaFilter = array_flip($selected);

            $lines = [];
            foreach ($this->varianceLines($db, $period, $takingUntil) as $line) {
                $area = $areas[$line[0]] ?? 'UNKNOWN';
                if ($areaFilter && !isset($areaFilter[$area])) {
                    continue;
                }

                $line[] = $area;
                $lines[] = $line;
            }

            return $this->build($lines, $this->partNames($db));
        });
    }

    /**
     * Minus: amount < 0, atau amount 0 (unit cost kosong) tetapi qty minus.
     */
    protected function isNegative(float $qty, float $amount): bool
    {
        return $amount < 0 || ($amount == 0 && $qty < 0);
    }

    /**
     * @param  array<string, float>  $perArea
     */
    protected function areaSeries(array $perArea): array
    {
        return collect($perArea)
            ->map(fn ($v, $area) => ['area' => (string) $area, 'value' => round($v, 2)])
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    /**
     * Baris tabel detail + total qty & amount.
     *
     * @param  array<string, array{qty: float, amount: float}>  $parts
     */
    protected function detail(array $parts, array $names): array
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
    protected function partNames($db): array
    {
        return Cache::remember('FACDBT04:part-names:v1', self::CACHE_TTL, function () use ($db) {
            return $db->table('SIM_INV_PART_TEST')
                ->select('part_no', 'inventory_part_description')
                ->get()
                ->mapWithKeys(fn ($r) => [(string) $r->part_no => trim((string) $r->inventory_part_description) ?: 'UNKNOWN'])
                ->all();
        });
    }
}
